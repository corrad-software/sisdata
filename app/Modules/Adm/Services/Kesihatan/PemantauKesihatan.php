<?php

namespace App\Modules\Adm\Services\Kesihatan;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Models\Integrasi;
use App\Modules\Adm\Models\Kesihatan;
use App\Modules\Adm\Models\LogRalat;
use App\Modules\Adm\Services\Amaran;
use App\Modules\Adm\Services\IntegrasiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * SRS-ADM-16: one health screen — services, queue, integrations, storage,
 * database, latest errors. SRS-ADM-17: `adm:semak-kesihatan` (scheduled job)
 * stores a snapshot, probes every active integration and alerts Bahagian ICT
 * when a service is down, the queue or storage passes its threshold, or an
 * integration fails repeatedly.
 */
class PemantauKesihatan
{
    /** @var list<Semakan>|null */
    private ?array $checks = null;

    public function __construct(private Parameters $parameters, private Amaran $amaran, private IntegrasiService $integrations) {}

    /** @param list<Semakan> $checks */
    public function useChecks(array $checks): void
    {
        $this->checks = $checks;
    }

    /** @return list<Semakan> */
    public function checks(): array
    {
        if ($this->checks !== null) {
            return $this->checks;
        }

        $timeout = (float) config('adm.kesihatan.tamat_masa', 2);
        $checks = [new SemakanPangkalanData, new SemakanRedis, new SemakanBarisGilir($this->parameters), new SemakanStoran($this->parameters)];

        foreach ((array) config('adm.kesihatan.http', []) as $kod => $c) {
            $checks[] = new SemakanHttp((string) $kod, (string) $c['label'], (string) $c['url'], $timeout);
        }
        foreach ((array) config('adm.kesihatan.soket', []) as $kod => $c) {
            $checks[] = new SemakanSoket((string) $kod, (string) $c['label'], (string) $c['alamat'], $c['hantar'] ?? null, $c['jangka'] ?? null, $timeout);
        }

        return $this->checks = $checks;
    }

    /**
     * @return list<array{kod: string, label: string, status: string, mesej: ?string, masa_ms: int, butiran: array<string, mixed>}>
     */
    public function run(): array
    {
        $results = [];
        foreach ($this->checks() as $check) {
            $started = microtime(true);
            try {
                $r = $check->jalankan();
            } catch (Throwable $e) {
                $r = ['status' => Semakan::GAGAL, 'mesej' => class_basename($e)];
            }
            $results[] = [
                'kod' => $check->kod(),
                'label' => $check->label(),
                'status' => $r['status'],
                'mesej' => $r['mesej'] ?? null,
                'masa_ms' => (int) round((microtime(true) - $started) * 1000),
                'butiran' => $r['butiran'] ?? [],
            ];
        }

        return $results;
    }

    /** @return array<string, mixed> */
    public function overview(bool $fresh = false): array
    {
        $key = 'adm:kesihatan:semasa';
        if ($fresh) {
            Cache::forget($key);
        }
        $services = Cache::remember($key, 15, fn () => $this->run());
        $byCode = array_column($services, null, 'kod');

        return [
            'status_keseluruhan' => self::worst(array_column($services, 'status')),
            'perkhidmatan' => $services,
            'baris_gilir' => $byCode['baris_gilir']['butiran'] ?? null,
            'storan' => $byCode['storan']['butiran'] ?? null,
            'pangkalan_data' => $byCode['pangkalan_data']['butiran'] ?? null,
            'integrasi' => $this->integrationStatus(),
            'ralat_terkini' => LogRalat::query()->whereIn('aras_keterukan', ['error', 'critical', 'alert', 'emergency'])
                ->latest('tarikh_masa')->limit(10)
                ->get(['id', 'tarikh_masa', 'aras_keterukan', 'modul', 'kod_ralat', 'mesej', 'id_korelasi'])
                ->map(fn (LogRalat $l) => [...$l->toArray(), 'mesej' => Str::limit($l->mesej, 200)])
                ->all(),
            'semakan_terakhir' => Kesihatan::query()->max('dicatat_pada'),
            'dijana_pada' => now()->toIso8601String(),
        ];
    }

    /**
     * Scheduled check: snapshot + integration probes + threshold alerts.
     *
     * @return list<array<string, mixed>>
     */
    public function checkAndAlert(): array
    {
        $results = $this->run();
        Cache::put('adm:kesihatan:semasa', $results, 15);
        $now = now();

        foreach ($results as $r) {
            Kesihatan::query()->create([
                'komponen' => $r['kod'],
                'status' => $r['status'],
                'masa_respons_ms' => $r['masa_ms'],
                'mesej' => $r['mesej'] !== null ? Str::limit($r['mesej'], 500, '') : null,
                'butiran' => $r['butiran'],
                'dicatat_pada' => $now,
            ]);

            if ($r['status'] !== Semakan::OK) {
                $this->amaran->ict('adm.amaran_kesihatan', [
                    'komponen' => $r['label'],
                    'status' => $r['status'],
                    'mesej' => (string) $r['mesej'],
                ], '/admin/pentadbiran/kesihatan', 'kesihatan:'.$r['kod'].':'.$r['status'].':'.$now->format('YmdH'));
            }
        }

        // Probe every active HTTP integration; repeated failures alert inside IntegrasiService.
        foreach (Integrasi::query()->where('status', 'aktif')->get() as $integrasi) {
            if (preg_match('#^https?://#i', $integrasi->endpoint)) {
                $this->integrations->test($integrasi);
            }
        }

        return $results;
    }

    /** @return list<array<string, mixed>> */
    private function integrationStatus(): array
    {
        $threshold = max(1, (int) $this->parameters->get('adm.ambang_integrasi_gagal', 3));

        return Integrasi::query()->orderBy('nama')->get()->map(fn (Integrasi $i) => [
            'kod' => $i->kod,
            'nama' => $i->nama,
            'status' => $i->status,
            'kegagalan_berturut' => $i->kegagalan_berturut,
            'terakhir_berjaya_pada' => $i->terakhir_berjaya_pada?->toIso8601String(),
            'terakhir_gagal_pada' => $i->terakhir_gagal_pada?->toIso8601String(),
            'status_kesihatan' => match (true) {
                $i->status !== 'aktif' => 'tidak_aktif',
                $i->kegagalan_berturut >= $threshold => Semakan::GAGAL,
                $i->kegagalan_berturut > 0 => Semakan::AMARAN,
                default => Semakan::OK,
            },
        ])->all();
    }

    /** @param list<string> $statuses */
    public static function worst(array $statuses): string
    {
        return in_array(Semakan::GAGAL, $statuses, true) ? Semakan::GAGAL
            : (in_array(Semakan::AMARAN, $statuses, true) ? Semakan::AMARAN : Semakan::OK);
    }
}
