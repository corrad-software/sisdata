<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Tkt\Contracts\CentralTicketSystem;
use App\Modules\Tkt\Models\LogIntegrasi;
use App\Modules\Tkt\Models\PautanAduan;
use App\Modules\Tkt\Models\PemetaanAduan;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-TKT-14/15/16: two-way integration with the Sistem Aduan berpusat
 * MAIS. Inbound tickets (through the API Gateway, machine account) enter
 * the same flow, idempotent on the complaint number, with configurable
 * category / severity mapping and a ticket ↔ complaint cross-reference.
 * Every status change of such a ticket goes into an outbox
 * (tkt_log_integrasi, keyed by an idempotency key), is pushed at once and
 * retried with back-off by `tkt:segerak-pusat`; failures and status
 * mismatches show in the reconciliation report. Every exchange is also
 * logged in MOD-ADM's integration log.
 */
class SegerakPusat
{
    public const KOD_INTEGRASI = 'tkt.sistem_aduan_pusat';

    public const MAKS_CUBAAN = 8;

    public function __construct(private Application $app, private CentralTicketSystem $remote, private Jejak $jejak) {}

    /** central value → local value for one mapping type */
    public function petaMasuk(string $jenis, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $row = PemetaanAduan::query()->where('jenis', $jenis)->where('aktif', true)->whereIn('arah', ['masuk', 'dua_hala'])
            ->whereRaw('upper(nilai_berpusat) = upper(?)', [$value])->first();

        return $row?->nilai_tempatan;
    }

    /** local value → central value */
    public function petaKeluar(string $jenis, string $value): string
    {
        $row = PemetaanAduan::query()->where('jenis', $jenis)->where('aktif', true)->whereIn('arah', ['keluar', 'dua_hala'])
            ->where('nilai_tempatan', $value)->orderBy('id')->first();

        return $row->nilai_berpusat ?? $value;
    }

    /**
     * Inbound ticket. Returns [ticket, created?]. Re-sending the same
     * complaint number never creates a second ticket.
     *
     * @param  array<string, mixed>  $data  nombor_aduan, tajuk, keterangan, kategori, keterukan, modul?, emel_pelapor?
     * @return array{0: Tiket, 1: bool}
     */
    public function terima(array $data, User $akaun): array
    {
        $nombor = (string) $data['nombor_aduan'];
        $started = microtime(true);
        $existing = PautanAduan::query()->where('nombor_aduan_berpusat', $nombor)->first();
        if ($existing !== null) {
            $this->logMasuk('tiket', $nombor, null, $existing->id_tiket, 'diabaikan', 200, 'pendua — tiket sedia ada');
            $this->adm(true, 'terima', null, $started, 'masuk');

            return [Tiket::query()->findOrFail($existing->id_tiket), false];
        }
        $kategori = $this->petaMasuk('kategori', isset($data['kategori']) ? (string) $data['kategori'] : null) ?? 'ralat';
        $keterukan = $this->petaMasuk('keterukan', isset($data['keterukan']) ? (string) $data['keterukan'] : null);

        $tiket = DB::transaction(function () use ($data, $nombor, $kategori, $keterukan) {
            $tiket = $this->app->make(TiketService::class)->buka([
                'tajuk' => $data['tajuk'],
                'penerangan' => $data['keterangan'],
                'kategori' => $kategori,
                'keterukan' => $keterukan !== null ? (int) $keterukan : null,
                'modul' => $data['modul'] ?? null,
                'emel_pelapor' => $data['emel_pelapor'] ?? null,
            ], 'sistem_aduan_pusat', null, $nombor);
            PautanAduan::query()->firstOrCreate(['id_tiket' => $tiket->id], [
                'nombor_aduan_berpusat' => $nombor, 'arah_asal' => 'berpusat', 'status_penyegerakan' => 'berjaya',
                'status_terakhir_disegerak' => $tiket->status, 'cap_masa_penyegerakan_akhir' => now(),
            ]);

            return $tiket;
        });
        $this->logMasuk('tiket', $nombor, null, $tiket->id, 'berjaya', 201, 'tiket '.$tiket->nombor_rujukan);
        $this->adm(true, 'terima', null, $started, 'masuk');
        // Acknowledge with our ticket number so both systems hold the cross-reference.
        $this->catat($tiket);

        return [$tiket, true];
    }

    /**
     * Inbound update for a complaint (reporter's comment / confirmation /
     * reopen request), idempotent on the caller's key.
     *
     * @param  array<string, mixed>  $data  kunci_idempotensi, tindakan (catatan|sahkan|buka_semula), catatan?
     */
    public function kemasKiniMasuk(string $nombor, array $data): Tiket
    {
        $link = PautanAduan::query()->where('nombor_aduan_berpusat', $nombor)->first();
        if ($link === null) {
            throw ValidationException::withMessages(['nombor_aduan' => __('tkt::tkt.ralat.aduan_tiada')]);
        }
        $tiket = Tiket::query()->findOrFail($link->id_tiket);
        $key = 'masuk:'.mb_substr((string) $data['kunci_idempotensi'], 0, 100);
        if (LogIntegrasi::query()->where('kunci_idempotensi', $key)->exists()) {
            return $tiket;
        }
        $note = isset($data['catatan']) ? (string) $data['catatan'] : null;
        $service = $this->app->make(TiketService::class);
        match ((string) $data['tindakan']) {
            'sahkan' => $service->sahkan($tiket, null, $note),
            'buka_semula' => $service->bukaSemula($tiket, null, $note ?? (string) __('tkt::tkt.kronologi.buka_semula_pusat')),
            default => $service->balasPelapor($tiket, (string) $note, null),
        };
        $this->logMasuk('tiket/'.$nombor, $nombor, $key, $tiket->id, 'berjaya', 200, (string) $data['tindakan']);

        return $tiket->refresh();
    }

    /** Queue (and try at once) a status push for a ticket that came from the central system. */
    public function catat(Tiket $tiket): ?LogIntegrasi
    {
        $link = PautanAduan::query()->where('id_tiket', $tiket->id)->first();
        if ($link === null) {
            return null;
        }
        $n = LogIntegrasi::query()->where('id_tiket', $tiket->id)->where('arah', 'keluar')->count() + 1;
        $payload = [
            $this->petaKeluar('medan', 'nombor_rujukan') => $tiket->nombor_rujukan,
            'status' => $this->petaKeluar('status', $tiket->status),
            'status_tempatan' => $tiket->status,
            'keterukan' => $this->petaKeluar('keterukan', (string) $tiket->keterukan),
            $this->petaKeluar('medan', 'penyelesaian') => in_array($tiket->status, ['selesai', 'disahkan', 'tutup'], true) ? mb_strimwidth((string) Penyamar::teks((string) $tiket->penyelesaian), 0, 2000, '…') : null,
            'tarikh' => now()->toIso8601String(),
        ];
        $row = LogIntegrasi::query()->create([
            'id_tiket' => $tiket->id,
            'arah' => 'keluar',
            'endpoint' => 'aduan/'.$link->nombor_aduan_berpusat.'/status',
            'kunci_idempotensi' => 'tkt:'.$tiket->id.':'.$n.':'.$tiket->status,
            'ringkasan_payload' => $tiket->nombor_rujukan.' → '.$payload['status'],
            'muatan' => $payload,
            'status' => 'menunggu',
            'cap_masa' => now(),
        ]);
        $link->forceFill(['status_penyegerakan' => 'menunggu'])->save();
        $this->cuba($row);

        return $row;
    }

    public function cuba(LogIntegrasi $row): bool
    {
        $link = $row->id_tiket !== null ? PautanAduan::query()->where('id_tiket', $row->id_tiket)->first() : null;
        if ($link === null) {
            return false;
        }
        $started = microtime(true);
        try {
            if ($this->app->bound(Integrations::class)) {
                $settings = $this->app->make(Integrations::class)->settings(self::KOD_INTEGRASI);
                if ($settings !== null && ! $this->app->make(Integrations::class)->allow(self::KOD_INTEGRASI)) {
                    throw new \RuntimeException((string) __('tkt::tkt.integrasi.had_kadar'));
                }
            }
            /** @var array<string, scalar|null> $payload */
            $payload = $row->muatan ?? [];
            $this->remote->pushStatus($link->nombor_aduan_berpusat, $payload, (string) $row->kunci_idempotensi);
            $row->forceFill(['status' => 'berjaya', 'kod_status' => 200, 'bilangan_cuba_semula' => $row->bilangan_cuba_semula + 1, 'mesej_ralat' => null, 'cuba_seterusnya_pada' => null])->save();
            $link->forceFill(['status_penyegerakan' => 'berjaya', 'status_terakhir_disegerak' => (string) ($payload['status_tempatan'] ?? ''), 'cap_masa_penyegerakan_akhir' => now()])->save();
            $this->adm(true, 'pushStatus', null, $started);

            return true;
        } catch (Throwable $e) {
            $tries = $row->bilangan_cuba_semula + 1;
            $row->forceFill([
                'status' => 'gagal',
                'bilangan_cuba_semula' => $tries,
                'mesej_ralat' => mb_substr($e->getMessage(), 0, 1000),
                'cuba_seterusnya_pada' => $tries >= self::MAKS_CUBAAN ? null : now()->addMinutes(min(60, 2 ** $tries)),
            ])->save();
            $link->forceFill(['status_penyegerakan' => 'gagal'])->save();
            $this->adm(false, 'pushStatus', $e->getMessage(), $started);
            if ($tries >= self::MAKS_CUBAAN) {
                $tiket = Tiket::query()->find($row->id_tiket);
                if ($tiket !== null) {
                    $this->jejak->hantar('tkt.penyegerakan_gagal', [], $tiket, ['cubaan' => $tries], 'tkt:segerak:'.$row->id);
                }
            }

            return false;
        }
    }

    /**
     * Retry what is due (or everything failed when $semua).
     *
     * @return array{berjaya: int, gagal: int}
     */
    public function ulangCuba(bool $semua = false): array
    {
        $out = ['berjaya' => 0, 'gagal' => 0];
        LogIntegrasi::query()->where('arah', 'keluar')->whereIn('status', ['menunggu', 'gagal'])
            ->when(! $semua, fn ($q) => $q->where('bilangan_cuba_semula', '<', self::MAKS_CUBAAN)
                ->where(fn ($w) => $w->whereNull('cuba_seterusnya_pada')->orWhere('cuba_seterusnya_pada', '<=', now())))
            ->orderBy('id')->each(function (LogIntegrasi $row) use (&$out) {
                $this->cuba($row) ? $out['berjaya']++ : $out['gagal']++;
            });

        return $out;
    }

    /**
     * SRS-TKT-16 reconciliation: linked tickets whose last delivered status
     * differs from the current one, or whose last push failed.
     *
     * @return list<array{id_tiket: string, nombor_rujukan: string, nombor_aduan: string, status_semasa: string, status_disegerak: string|null, status_penyegerakan: string, cubaan: int, ralat: string|null, akhir: string|null}>
     */
    public function rekonsiliasi(): array
    {
        return PautanAduan::query()->join('tkt_tiket', 'tkt_tiket.id', '=', 'tkt_pautan_aduan.id_tiket')
            ->where(fn ($q) => $q->where('tkt_pautan_aduan.status_penyegerakan', '!=', 'berjaya')
                ->orWhereColumn('tkt_pautan_aduan.status_terakhir_disegerak', '!=', 'tkt_tiket.status')
                ->orWhereNull('tkt_pautan_aduan.status_terakhir_disegerak'))
            ->orderByDesc('tkt_pautan_aduan.updated_at')
            ->get(['tkt_pautan_aduan.*', 'tkt_tiket.nombor_rujukan', 'tkt_tiket.status as status_semasa'])
            ->map(function (PautanAduan $p) {
                $last = LogIntegrasi::query()->where('id_tiket', $p->id_tiket)->where('arah', 'keluar')->orderByDesc('id')->first();

                return [
                    'id_tiket' => $p->id_tiket,
                    'nombor_rujukan' => (string) $p->getAttribute('nombor_rujukan'),
                    'nombor_aduan' => $p->nombor_aduan_berpusat,
                    'status_semasa' => (string) $p->getAttribute('status_semasa'),
                    'status_disegerak' => $p->status_terakhir_disegerak,
                    'status_penyegerakan' => $p->status_penyegerakan,
                    'cubaan' => (int) ($last->bilangan_cuba_semula ?? 0),
                    'ralat' => $last?->mesej_ralat,
                    'akhir' => $p->cap_masa_penyegerakan_akhir?->toIso8601String(),
                ];
            })->values()->all();
    }

    /** Re-queue a fresh push for every mismatched ticket. */
    public function selaras(): int
    {
        $n = 0;
        foreach ($this->rekonsiliasi() as $row) {
            $tiket = Tiket::query()->find($row['id_tiket']);
            if ($tiket !== null) {
                $this->catat($tiket);
                $n++;
            }
        }

        return $n;
    }

    private function logMasuk(string $endpoint, string $nombor, ?string $key, ?string $tiketId, string $status, int $code, string $summary): void
    {
        LogIntegrasi::query()->create([
            'id_tiket' => $tiketId, 'arah' => 'masuk', 'endpoint' => mb_substr($endpoint, 0, 200), 'kunci_idempotensi' => $key,
            'ringkasan_payload' => mb_substr($nombor.' — '.$summary, 0, 500), 'status' => $status, 'kod_status' => $code,
            'bilangan_cuba_semula' => 0, 'cap_masa' => now(),
        ]);
    }

    private function adm(bool $ok, string $operation, ?string $message, float $started, string $direction = 'keluar'): void
    {
        if (! $this->app->bound(Integrations::class)) {
            return;
        }
        try {
            $this->app->make(Integrations::class)->log(self::KOD_INTEGRASI, $ok, null, (int) ((microtime(true) - $started) * 1000), $message, $operation, 1, [], $direction);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Labels for the local statuses, for the integration screen. */
    public static function statusNama(string $status): string
    {
        return Lalai::STATUS[$status] ?? $status;
    }
}
