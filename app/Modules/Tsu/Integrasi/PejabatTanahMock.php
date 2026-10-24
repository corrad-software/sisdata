<?php

namespace App\Modules\Tsu\Integrasi;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tsu\Contracts\PejabatTanahGateway;
use App\Modules\Tsu\Contracts\PejabatTanahTidakTersedia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Local mock of the central Land Office provider (SRS-TSU-22) until MAIS
 * supplies the real one. Coverage = parameter tsu.daerah_liputan_api. A
 * submitted application advances one structured status every
 * `tsu.mock_ptd_hari_per_status` days; an outage can be simulated with
 * setDown() (cache) to exercise the manual fallback (SRS-INT-10).
 */
class PejabatTanahMock implements PejabatTanahGateway
{
    public const ALIRAN = ['diterima', 'dalam_semakan', 'lawatan_tapak', 'ulasan_jabatan', 'dibentang_mmkn', 'lulus'];

    private const PREFIX = 'tsu:mockptd:';

    public function __construct(private Parameters $parameters) {}

    public function covers(string $kodDaerah): bool
    {
        $list = $this->parameters->get('tsu.daerah_liputan_api', ['1001', '1002', '1008']);
        if (is_string($list)) {
            $list = json_decode($list, true) ?: array_map('trim', explode(',', $list));
        }

        return in_array($kodDaerah, array_map('strval', (array) $list), true);
    }

    public function status(string $noRujukanPtd, string $kodDaerah): ?array
    {
        $this->assertUp($kodDaerah);
        if (! str_starts_with($noRujukanPtd, 'PTD/')) {
            return null;
        }
        $submitted = Cache::get(self::PREFIX.'hantar:'.$noRujukanPtd);
        $start = $submitted ? CarbonImmutable::parse($submitted) : CarbonImmutable::today()->subDays(((int) hexdec(substr(hash('crc32b', $noRujukanPtd), 0, 6))) % 120);
        $per = max(1, (int) $this->parameters->get('tsu.mock_ptd_hari_per_status', 15));
        $step = min(count(self::ALIRAN) - 1, intdiv((int) $start->diffInDays(CarbonImmutable::today()), $per));

        return ['status' => self::ALIRAN[$step], 'tarikh' => $start->addDays($step * $per)->format('Y-m-d'), 'catatan' => 'Status daripada penyedia integrasi berpusat (mock).'];
    }

    public function submit(array $permohonan): string
    {
        $this->assertUp($permohonan['kod_daerah']);
        $ref = 'PTD/'.$permohonan['kod_daerah'].'/'.now()->format('Y').'/'.strtoupper(substr(hash('crc32b', $permohonan['no_rujukan_mais']), 0, 6));
        Cache::put(self::PREFIX.'hantar:'.$ref, CarbonImmutable::today()->format('Y-m-d'), now()->addYears(2));

        return $ref;
    }

    public function setDown(bool $down): void
    {
        $down ? Cache::put(self::PREFIX.'down', true, now()->addDay()) : Cache::forget(self::PREFIX.'down');
    }

    private function assertUp(string $kodDaerah): void
    {
        if (Cache::get(self::PREFIX.'down') || ! $this->covers($kodDaerah)) {
            throw new PejabatTanahTidakTersedia(__('tsu::tsu.ptd.tidak_tersedia'));
        }
    }
}
