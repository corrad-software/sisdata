<?php

namespace App\Modules\Aud\Services;

use App\Modules\Aud\Events\AuditWriteFailed;
use App\Modules\Aud\Services\Siem\SiemSink;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * The single write path into audit_rekod (SRS-AUD-06, 26).
 *
 * Transactions — the trade-off, decided deliberately:
 *
 *  - Context (user, IP, time…) is captured when the action happens, but the
 *    row is written AFTER the surrounding DB transaction commits
 *    (`afterCommit`). A change that rolls back is then recorded once, with
 *    status "gagal", via the rollback hook — never as if it happened.
 *  - The audit write runs in its own short transaction holding a Postgres
 *    advisory lock, so the chain (no_urutan gapless, cincangan_sebelum = the
 *    previous hash) is serialised across processes without holding the lock
 *    for the whole business transaction.
 *  - Because it runs after commit, an audit failure can never roll back or
 *    fail the business transaction (SRS-AUD-26). The cost is a small window
 *    where the business commit succeeds and the process dies before the audit
 *    row is written; failures that DO raise are spooled to disk, logged as an
 *    incident and announced (AuditWriteFailed), and `aud:ulang-spool` replays
 *    them. Writing inside the business transaction would close that window
 *    but couple both failures and lock the chain for the full transaction.
 */
class AuditWriter
{
    public const LOCK_KEY = 7319019;

    /** Set by tests to simulate a broken audit store. */
    public static bool $simulateFailure = false;

    public function __construct(
        private AuditContext $context,
        private DatabaseManager $db,
        private SiemSink $siem,
    ) {}

    public function write(AuditEntry $entry): void
    {
        try {
            $row = $this->context->capture($entry);
        } catch (Throwable $e) {
            $this->incident($entry->action, $entry->module, $e, null);

            return;
        }

        $changes = $entry->changes;
        $connection = $this->db->connection();

        try {
            $connection->afterCommit(fn () => $this->persist($row, $changes));

            if ($connection->transactionLevel() > 0) {
                $connection->afterRollBack(function () use ($row, $changes) {
                    $row['status_transaksi'] = 'gagal';
                    $meta = $row['meta'] ? (array) json_decode($row['meta'], true) : [];
                    $meta['transaksi_dibatalkan'] = true;
                    $row['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
                    $this->persist($row, $changes);
                });
            }
        } catch (Throwable $e) {
            $this->incident($entry->action, $entry->module, $e, ['rekod' => $row, 'perubahan' => $changes]);
        }
    }

    /**
     * Chains and inserts one row. Never throws.
     *
     * @param  array<string, mixed>  $row
     * @param  list<array{0: string, 1: ?string, 2: ?string, 3: bool}>  $changes
     */
    public function persist(array $row, array $changes): ?int
    {
        try {
            if (self::$simulateFailure) {
                throw new \RuntimeException('Simulasi kegagalan storan audit');
            }

            $connection = $this->db->connection();
            [$id, $stored, $storedChanges] = $connection->transaction(fn () => $this->insertChained($connection, $row, $changes));
        } catch (Throwable $e) {
            $this->incident((string) $row['jenis_tindakan'], (string) $row['modul'], $e, ['rekod' => $row, 'perubahan' => $changes]);

            return null;
        }

        try {
            $this->siem->send(['id' => $id, ...$stored, 'perubahan' => $storedChanges]);
        } catch (Throwable $e) {
            $this->log('SIEM: penghantaran rekod audit gagal', ['id' => $id, 'ralat' => $e->getMessage()]);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{0: string, 1: ?string, 2: ?string, 3: bool}>  $changes
     * @return array{0: int, 1: array<string, mixed>, 2: list<array<string, mixed>>}
     */
    private function insertChained(Connection $connection, array $row, array $changes): array
    {
        if ($connection->getDriverName() === 'pgsql') {
            $connection->select('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
        }

        $head = $connection->table('audit_rekod')->orderByDesc('no_urutan')->first(['no_urutan', 'cincangan_rekod'])
            ?? $connection->table('audit_arkib')->orderByDesc('no_urutan')->first(['no_urutan', 'cincangan_rekod']);

        $changeRows = array_map(fn (array $c) => [
            'nama_medan' => mb_substr($c[0], 0, 150),
            'nilai_asal' => $c[1],
            'nilai_baharu' => $c[2],
            'sensitif_ditapis' => $c[3],
        ], $changes);

        $row['no_urutan'] = $head ? ((int) $head->no_urutan) + 1 : 1;
        $row['cincangan_sebelum'] = $head ? $head->cincangan_rekod : ChainHasher::GENESIS;
        $row['cincangan_rekod'] = ChainHasher::hash($row, $changeRows);

        $id = (int) $connection->table('audit_rekod')->insertGetId($row);

        if ($changeRows !== []) {
            $connection->table('audit_perubahan_medan')->insert(
                array_map(fn (array $c) => ['id_audit' => $id, ...$c], $changeRows)
            );
        }

        return [$id, $row, $changeRows];
    }

    /**
     * SRS-AUD-26: record the failure as an incident, keep the entry, tell admins.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function incident(string $action, string $module, Throwable $e, ?array $payload): void
    {
        try {
            $this->log('Kegagalan perekodan audit', [
                'tindakan' => $action,
                'modul' => $module,
                'ralat' => $e->getMessage(),
            ]);

            if ($payload !== null) {
                $path = (string) config('aud.laluan_spool');
                File::ensureDirectoryExists(dirname($path));
                file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
            }

            AuditWriteFailed::dispatch($action, $module, $e->getMessage());
        } catch (Throwable) {
            // Last resort: never let auditing break the caller.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, array $context): void
    {
        logger()->build(['driver' => 'single', 'path' => (string) config('aud.laluan_insiden')])->critical($message, $context);
        logger()->critical('[AUD] '.$message, $context);
    }
}
