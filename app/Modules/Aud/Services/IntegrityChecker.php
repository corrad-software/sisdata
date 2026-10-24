<?php

namespace App\Modules\Aud\Services;

use App\Modules\Aud\Events\AuditIntegrityBroken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SRS-AUD-07: recomputes the whole chain (archive + online, by no_urutan) and
 * checks that sequence numbers are gapless, each cincangan_sebelum equals the
 * previous record's hash, and each stored hash matches the recomputed one.
 * Also catches tail truncation by comparing with the last passing check.
 */
class IntegrityChecker
{
    private const CHUNK = 1000;

    /**
     * @return array{status: string, checked: int, last: int|null, bad: int|null, detail: string|null, id: int}
     */
    public function run(string $trigger = 'jadual', ?int $userId = null): array
    {
        $started = Carbon::now('UTC');
        $checked = 0;
        $expectedSeq = null;
        $previousHash = null;
        $bad = null;
        $detail = null;

        try {
            $after = 0;
            while ($bad === null) {
                $rows = $this->chunk($after);
                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    $seq = (int) $row['rekod']->no_urutan;
                    $expectedSeq ??= 1; // archive is never pruned, so the chain always starts at 1

                    if ($seq !== $expectedSeq) {
                        [$bad, $detail] = [$expectedSeq, "Jujukan terputus: dijangka no_urutan {$expectedSeq}, dijumpai {$seq} (rekod dipadam?)"];
                        break;
                    }

                    if ($row['rekod']->cincangan_sebelum !== ($previousHash ?? ChainHasher::GENESIS)) {
                        [$bad, $detail] = [$seq, "cincangan_sebelum bagi no_urutan {$seq} tidak sepadan dengan rekod sebelumnya"];
                        break;
                    }

                    if (ChainHasher::hash($row['rekod'], $row['perubahan']) !== $row['rekod']->cincangan_rekod) {
                        [$bad, $detail] = [$seq, "Kandungan rekod no_urutan {$seq} tidak sepadan dengan cincangannya (rekod diubah)"];
                        break;
                    }

                    $previousHash = $row['rekod']->cincangan_rekod;
                    $expectedSeq = $seq + 1;
                    $after = $seq;
                    $checked++;
                }
            }

            $last = $expectedSeq === null ? null : $expectedSeq - 1;

            if ($bad === null) {
                $previousMax = DB::table('audit_integriti_semakan')->where('status', 'lulus')->max('no_urutan_terakhir');
                if ($previousMax !== null && ($last ?? 0) < (int) $previousMax) {
                    [$bad, $detail] = [($last ?? 0) + 1, "Rekod terakhir hilang: semakan lepas mengesahkan sehingga no_urutan {$previousMax}, kini hanya ".($last ?? 0)];
                }
            }
        } catch (Throwable $e) {
            $last = null;
            [$bad, $detail] = [0, 'Semakan tidak dapat diselesaikan: '.$e->getMessage()];
        }

        $status = $bad === null ? 'lulus' : 'gagal';

        $id = (int) DB::table('audit_integriti_semakan')->insertGetId([
            'dimulakan_pada' => $started->format('Y-m-d H:i:s.uP'),
            'selesai_pada' => Carbon::now('UTC')->format('Y-m-d H:i:s.uP'),
            'status' => $status,
            'bilangan_disemak' => $checked,
            'no_urutan_terakhir' => $last,
            'no_urutan_rosak' => $bad,
            'butiran' => $detail,
            'dicetuskan_oleh' => $trigger,
            'id_pengguna' => $userId,
        ]);

        if ($bad !== null) {
            $this->alert($bad, (string) $detail);
        }

        return ['status' => $status, 'checked' => $checked, 'last' => $last, 'bad' => $bad, 'detail' => $detail, 'id' => $id];
    }

    private function alert(int $bad, string $detail): void
    {
        try {
            logger()->build(['driver' => 'single', 'path' => (string) config('aud.laluan_insiden')])
                ->critical('Integriti audit trail gagal', ['no_urutan_rosak' => $bad, 'butiran' => $detail]);
            logger()->critical('[AUD] Integriti audit trail gagal', ['no_urutan_rosak' => $bad, 'butiran' => $detail]);
        } catch (Throwable) {
            // logging must not stop the alert event
        }

        AuditIntegrityBroken::dispatch($bad, $detail);
    }

    /**
     * @return list<array{rekod: object, perubahan: list<array<string, mixed>>}>
     */
    private function chunk(int $after): array
    {
        $columns = ['id', 'cincangan_rekod', ...ChainHasher::FIELDS];

        $archive = DB::table('audit_arkib')->select([...$columns, 'perubahan'])
            ->where('no_urutan', '>', $after)->orderBy('no_urutan')->limit(self::CHUNK)->get()
            ->map(fn ($r) => ['rekod' => $r, 'perubahan' => $r->perubahan ? (array) json_decode($r->perubahan, true) : []]);

        $online = DB::table('audit_rekod')->select($columns)
            ->where('no_urutan', '>', $after)->orderBy('no_urutan')->limit(self::CHUNK)->get();

        $changes = $online->isEmpty() ? collect() : DB::table('audit_perubahan_medan')
            ->whereIn('id_audit', $online->pluck('id'))->orderBy('id')->get()->groupBy('id_audit');

        $online = $online->map(fn ($r) => ['rekod' => $r, 'perubahan' => array_map(fn ($c) => (array) $c, $changes->get($r->id)?->all() ?? [])]);

        return $archive->concat($online)
            ->sortBy(fn ($r) => (int) $r['rekod']->no_urutan)
            ->take(self::CHUNK)
            ->values()
            ->all();
    }
}
