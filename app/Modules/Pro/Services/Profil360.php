<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Profil Hartanah 360° (SRS-PRO-26..28, 30): one tabbed view assembled from
 * every registered ProfileSection. Sections expected from modules that do
 * not exist yet are shown as "belum tersedia" until that module registers a
 * section with the same key — no change here needed when it does.
 */
class Profil360
{
    public const TABS = ['identiti', 'kewangan', 'pemanfaatan', 'risiko', 'ruang', 'dokumen'];

    /** key => [tab, owning module] for sections other modules will contribute. */
    public const EXPECTED = [
        'hak_milik' => ['identiti', 'MOD-HKM'],
        'pemerolehan' => ['identiti', 'MOD-PER'],
        'penilaian' => ['kewangan', 'MOD-PRO/G3'],
        'cukai' => ['kewangan', 'MOD-CKI'],
        'pajakan' => ['pemanfaatan', 'MOD-PJK'],
        'pemantauan' => ['pemanfaatan', 'MOD-PTN'],
        'aduan' => ['risiko', 'MOD-ADU'],
        'kes_mahkamah' => ['risiko', 'MOD-KES'],
        'urusan_tanah' => ['risiko', 'MOD-TSU'],
        'aset_fizikal' => ['ruang', 'MOD-AST'],
    ];

    public function __construct(
        private ProfileSectionRegistry $registry,
        private LotPresenter $presenter,
        private SpatialService $spatial,
        private AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function build(LotTanah $lot, User $user): array
    {
        $sections = $this->registry->all();
        $tabs = [];
        foreach (self::TABS as $tab) {
            $tabs[$tab] = ['kod' => $tab, 'label' => (string) __('pro::pro.tab.'.$tab), 'seksyen' => []];
        }

        $timeline = [];
        $series = [];
        $flags = [];
        $registeredKeys = [];

        foreach ($sections as $section) {
            $registeredKeys[] = $section->key();
            $tab = in_array($section->tab(), self::TABS, true) ? $section->tab() : 'identiti';
            try {
                $data = $section->forLot($lot->id, $user);
                $events = $section->timeline($lot->id, $user);
                if ($section instanceof FinancialSeries) {
                    $series = [...$series, ...$section->financialSeries($lot->id, $user)];
                }
            } catch (Throwable $e) {
                report($e);
                $tabs[$tab]['seksyen'][] = $this->sectionOut($section, 'ralat', null);

                continue;
            }

            if ($data === null) {
                continue; // nothing for this lot, or the user may not see it
            }
            $tabs[$tab]['seksyen'][] = $this->sectionOut($section, $data['items'] === [] ? 'kosong' : 'ada', $data);
            foreach ($data['flags'] ?? [] as $flag) {
                $flags[] = [...$flag, 'seksyen' => $section->key()];
            }
            foreach ($events as $event) {
                $timeline[] = [...$event, 'seksyen' => $section->key(), 'modul_label' => $section->label()];
            }
        }

        foreach (self::EXPECTED as $key => [$tab, $module]) {
            if (! in_array($key, $registeredKeys, true)) {
                $tabs[$tab]['seksyen'][] = [
                    'key' => $key,
                    'label' => (string) __('pro::pro.seksyen.'.$key),
                    'modul' => $module,
                    'status' => 'belum_tersedia',
                    'mesej' => (string) __('pro::pro.belum_tersedia', ['modul' => $module]),
                    'items' => [],
                    'flags' => [],
                ];
            }
        }

        usort($timeline, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return [
            'lot' => $this->presenter->detail($lot, $user),
            'tab' => array_values($tabs),
            'bendera' => $flags,
            'garis_masa' => $timeline,
            'kewangan' => $this->financial($series),
            'geojson' => $this->spatial->feature($lot),
        ];
    }

    /**
     * SRS-PRO-28 data shape: monthly + yearly buckets of income and costs with
     * net return. Empty until G3 modules (PJK, CKI, AST, KES) report events.
     *
     * @param  list<array{tarikh: string, jenis: string, amaun: float, keterangan?: string, link?: string}>  $series
     * @return array<string, mixed>
     */
    public function financial(array $series): array
    {
        $types = [...FinancialSeries::HASIL, ...FinancialSeries::KOS];
        $bucket = function (string $format) use ($series, $types): array {
            /** @var array<string, array<string, float|string>> $out */
            $out = [];
            foreach ($series as $e) {
                $period = date($format, (int) strtotime($e['tarikh']));
                $out[$period] ??= ['tempoh' => $period, ...array_fill_keys($types, 0.0), 'jumlah_hasil' => 0.0, 'jumlah_kos' => 0.0, 'pulangan_bersih' => 0.0];
                $amount = (float) $e['amaun'];
                $out[$period][$e['jenis']] = (float) ($out[$period][$e['jenis']] ?? 0.0) + $amount;
                if (in_array($e['jenis'], FinancialSeries::HASIL, true)) {
                    $out[$period]['jumlah_hasil'] = (float) $out[$period]['jumlah_hasil'] + $amount;
                } elseif (in_array($e['jenis'], FinancialSeries::KOS, true)) {
                    $out[$period]['jumlah_kos'] = (float) $out[$period]['jumlah_kos'] + $amount;
                }
                $out[$period]['pulangan_bersih'] = (float) $out[$period]['jumlah_hasil'] - (float) $out[$period]['jumlah_kos'];
            }
            ksort($out);

            return array_values($out);
        };

        $yearly = $bucket('Y');

        return [
            'tersedia' => $series !== [],
            'mesej' => $series === [] ? (string) __('pro::pro.kewangan_belum_tersedia') : null,
            'jenis' => $types,
            'bulanan' => $bucket('Y-m'),
            'tahunan' => $yearly,
            'jumlah' => [
                'hasil' => array_sum(array_column($yearly, 'jumlah_hasil')),
                'kos' => array_sum(array_column($yearly, 'jumlah_kos')),
                'pulangan_bersih' => array_sum(array_column($yearly, 'pulangan_bersih')),
            ],
        ];
    }

    /** SRS-PRO-30: every view / export / print / API read of a lot profile. */
    public function logAccess(LotTanah $lot, ?User $user, string $type, string $channel = 'web', array $details = []): void
    {
        DB::table('log_akses_profil')->insert([
            'lot_id' => $lot->id,
            'id_pengguna' => $user?->id,
            'jenis_capaian' => $type,
            'saluran' => $channel,
            'alamat_ip' => request()->ip(),
            'butiran' => $details === [] ? null : json_encode($details),
            'tarikh_masa' => now(),
        ]);
        $this->audit->record($type === 'api' ? 'lihat' : $type, 'pro', 'lot_tanah', $lot->id, meta: [
            'klasifikasi' => $lot->klasifikasi,
            'capaian_profil' => $type,
            'saluran' => $channel,
            ...$details,
        ]);
    }

    /**
     * @param  array{items: list<array<string, mixed>>, flags?: list<array{level: string, label: string}>, link?: string}|null  $data
     * @return array<string, mixed>
     */
    private function sectionOut(ProfileSection $section, string $status, ?array $data): array
    {
        return [
            'key' => $section->key(),
            'label' => $section->label(),
            'status' => $status,
            'items' => $data['items'] ?? [],
            'flags' => $data['flags'] ?? [],
            'link' => $data['link'] ?? null,
        ];
    }
}
