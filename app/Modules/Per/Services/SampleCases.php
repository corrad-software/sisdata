<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Per\Models\DokumenPemerolehan;
use App\Modules\Per\Models\Kelulusan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Support\ChannelCatalog;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * `per:sample-cases` — SYNTHETIC acquisition cases across all 11 channels and
 * stages for the dev DB. Lots come from the synthetic staging lots
 * (`ref:sample-lots`): a lot already registered in lot_tanah is linked
 * through LotRegistry, otherwise the case points at an expected lot.
 * Approvals are simulated (no WFE instance) and documents are rows without a
 * DMS file — this is demo data only, flagged sumber_rekod = "sintetik".
 */
class SampleCases
{
    private const NAMA = ['Ahmad bin Ismail', 'Siti Khadijah binti Omar', 'Mohd Faizal bin Rahman', 'Nur Aisyah binti Hassan', 'Abdul Rahim bin Yusof', 'Zainab binti Ali', 'Kamarul bin Ahmad', 'Halimah binti Salleh'];

    public function __construct(private LotLinker $lots, private Checklist $checklist) {}

    /** @return array{dicipta: int, dipautkan_lot: int, selesai: int} */
    public function generate(int $count, ?int $seed = null): array
    {
        mt_srand($seed ?? 20261027);
        $channels = Saluran::query()->with(['peringkat', 'senaraiSemak'])->orderBy('susunan')->get()->values();
        $places = $this->places();
        $officers = User::query()->where('is_active', true)->orderBy('id')->limit(10)->pluck('id')->all();
        $stats = ['dicipta' => 0, 'dipautkan_lot' => 0, 'selesai' => 0];

        for ($i = 0; $i < $count; $i++) {
            $channel = $channels[$i % $channels->count()];
            $place = $places[mt_rand(0, count($places) - 1)];
            $start = Carbon::now()->subDays(mt_rand(20, 1000))->startOfDay();
            $officer = $officers === [] ? null : $officers[array_rand($officers)];

            $case = DB::transaction(fn () => $this->one($channel, $place, $start, $officer, $i));
            $stats['dicipta']++;
            $stats['dipautkan_lot'] += $case->lot_id !== null ? 1 : 0;
            $stats['selesai'] += $case->status_pemerolehan === Pemerolehan::SELESAI ? 1 : 0;
        }

        return $stats;
    }

    /** @param array{kod_mukim: string, no_lot: string} $place */
    private function one(Saluran $channel, array $place, Carbon $start, ?int $officer, int $i): Pemerolehan
    {
        $code = $channel->kod_saluran;
        $resolved = $this->lots->resolve(null, $place['kod_mukim'], $place['no_lot'], null);
        $stages = $channel->peringkat->where('aktif', true)->values();
        // Spread cases over every stage; ~1 in 4 runs to the end.
        $target = mt_rand(0, 3) === 0 ? $stages->count() : mt_rand(0, $stages->count() - 1);
        $value = (string) (mt_rand(5, 400) * 10000);

        $case = Pemerolehan::query()->create([
            'no_kes' => sprintf('PER/%s/%05d', $start->format('Y'), (int) DB::selectOne("SELECT nextval('pemerolehan_no_kes_seq') AS n")->n),
            'id_saluran' => $channel->id,
            'tajuk' => sprintf('%s — %s (SINTETIK)', $channel->nama_saluran, $place['no_lot']),
            'lot_id' => $resolved['lot_id'],
            'no_lot_dijangka' => $resolved['no_lot_dijangka'],
            'kod_mukim' => $resolved['kod_mukim'],
            'kod_daerah' => $resolved['kod_daerah'],
            'tarikh_mula_kes' => $start->toDateString(),
            'status_pemerolehan' => Pemerolehan::DALAM_PROSES,
            'kod_bahagian' => $channel->bahagian_bertanggungjawab,
            'id_pegawai_bertanggungjawab' => $officer,
            'id_pendaftar' => $officer,
            'nilai_pasaran' => (string) ((int) $value * 1.1),
            'nilai_pemerolehan' => $value,
            'sumber_dana' => ['Dana Baitulmal', 'Dana Wakaf', 'Peruntukan Hartanah MAIS', null][mt_rand(0, 3)],
            'butiran_saluran' => [],
            'tempoh_sasaran_hari' => $channel->tempoh_sasaran_hari,
            'sumber_rekod' => 'sintetik',
            'catatan' => 'Data SINTETIK untuk pembangunan.',
            'klasifikasi' => 'sulit',
        ]);

        foreach ($stages as $s) {
            $case->peringkat()->create([
                'kod_peringkat' => $s->kod_peringkat, 'nama_peringkat' => $s->nama_peringkat, 'jenis' => $s->jenis,
                'susunan' => $s->susunan, 'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari,
            ]);
        }

        [$public, $personal] = $this->fieldValues($code, $start, $value, $i);
        $case->forceFill(['butiran_saluran' => $public, 'butiran_peribadi' => $personal])->save();
        $this->parties($case, $code, $i);

        // Walk the stages with back-dated timestamps.
        $case->load('peringkat');
        $clock = $start->copy()->setTime(9, 0);
        foreach ($case->peringkat as $idx => $stage) {
            $stage->forceFill(['tarikh_masuk' => $clock->copy(), 'status_peringkat' => PeringkatPemerolehan::SEMASA, 'id_pegawai_bertanggungjawab' => $officer])->save();
            $case->forceFill(['peringkat_semasa' => $stage->kod_peringkat])->save();
            $this->documentsFor($case, $stage->kod_peringkat, $clock);
            if ($idx >= $target) {
                if ($stage->jenis === 'kelulusan') {
                    $case->forceFill(['status_pemerolehan' => Pemerolehan::MENUNGGU_KELULUSAN])->save();
                }
                break;
            }
            $days = max(1, (int) round($stage->tempoh_sasaran_hari * mt_rand(40, 160) / 100));
            $clock = $clock->copy()->addDays($days);
            if ($clock->isFuture()) {
                $clock = now()->subDay();
            }
            $stage->forceFill(['tarikh_keluar' => $clock->copy(), 'status_peringkat' => PeringkatPemerolehan::SELESAI, 'tempoh_hari' => $days])->save();
            if ($stage->jenis === 'kelulusan') {
                Kelulusan::query()->create([
                    'id_pemerolehan' => $case->id, 'aras_kelulusan' => 'keputusan_akhir', 'tindakan' => 'lulus',
                    'tarikh_masa' => $clock->copy(), 'justifikasi' => 'Kelulusan SINTETIK (tiada instans WFE).',
                ]);
                $case->forceFill(['status_pemerolehan' => Pemerolehan::DILULUSKAN])->save();
            }
        }

        if ($target >= $stages->count()) {
            $this->close($case, $clock);
        }

        $this->checklist->refresh($case);

        return $case->refresh();
    }

    private function close(Pemerolehan $case, Carbon $when): void
    {
        $titleId = null;
        if ($case->lot_id !== null) {
            try {
                $actor = User::query()->find($case->id_pendaftar) ?? User::query()->orderBy('id')->first();
                if ($actor !== null) {
                    $titleId = app(TitleRegistry::class)->registerFromAcquisition($case->lot_id, [
                        'no_hak_milik' => 'GRN '.mt_rand(10000, 99999), 'jenis_hak_milik' => 'geran', 'tarikh_pendaftaran' => $when->toDateString(),
                        'pemilik_berdaftar' => 'Majlis Agama Islam Selangor',
                    ], $actor, ['kod_peti_besi' => 'PB-0'.mt_rand(1, 4), 'no_fail' => 'F-'.mt_rand(100, 999), 'lokasi_rak' => 'R'.mt_rand(1, 9).'-'.mt_rand(1, 6)], 'pemerolehan:'.$case->id);
                }
            } catch (Throwable) {
                $titleId = null; // MOD-HKM not available: leave the case at its final stage, approved.
            }
        }
        if ($titleId === null) {
            return;
        }

        $current = $case->load('peringkat')->peringkatSemasa();
        if ($current !== null) {
            $current->forceFill(['tarikh_keluar' => $when, 'status_peringkat' => PeringkatPemerolehan::SELESAI, 'tempoh_hari' => $current->hariBerjalan()])->save();
        }
        $case->forceFill([
            'status_pemerolehan' => Pemerolehan::SELESAI, 'peringkat_semasa' => null, 'id_hak_milik_dijana' => $titleId,
            'tarikh_selesai' => $when->toDateString(), 'tarikh_pemerolehan' => $when->toDateString(),
            'tempoh_keseluruhan_hari' => (int) $case->tarikh_mula_kes->diffInDays($when->copy()->startOfDay()),
        ])->save();
    }

    private function documentsFor(Pemerolehan $case, string $stageCode, Carbon $when): void
    {
        foreach ($case->saluran->senaraiSemak as $item) {
            if ($item->dijana_sistem || $item->peringkat_diperlukan !== $this->nextStageCode($case, $stageCode)) {
                continue;
            }
            if (mt_rand(0, 9) === 0) {
                continue; // some cases have missing documents
            }
            DokumenPemerolehan::query()->create([
                'id_pemerolehan' => $case->id, 'id_semak' => $item->id, 'jenis_dokumen' => $item->jenis_dokumen,
                'rujukan_dms' => (string) Str::uuid(), 'tarikh_terima' => $when->toDateString(), 'status_dokumen' => 'diterima',
                'kod_peti_besi' => 'PB-0'.mt_rand(1, 4), 'no_fail' => 'F-'.mt_rand(100, 999), 'lokasi_rak' => 'R'.mt_rand(1, 9),
                'status_dokumen_asal' => 'asal_dalam_simpanan', 'catatan' => 'Dokumen SINTETIK (tiada fail DMS).',
            ]);
        }
    }

    private function nextStageCode(Pemerolehan $case, string $current): string
    {
        $codes = $case->peringkat->pluck('kod_peringkat')->values()->all();
        $idx = array_search($current, $codes, true);

        return $idx !== false && isset($codes[$idx + 1]) ? $codes[$idx + 1] : ChannelCatalog::TUTUP;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function fieldValues(string $code, Carbon $start, string $value, int $i): array
    {
        $public = [];
        $personal = [];
        foreach (ChannelCatalog::fields($code) as $key => $f) {
            $v = match ($f['jenis']) {
                'tarikh' => $start->copy()->addDays(mt_rand(0, 60))->toDateString(),
                'wang' => (string) round((float) $value * mt_rand(1, 30) / 100, 2),
                'nombor' => (string) mt_rand(500, 20000),
                'pilihan' => $f['mesti'] ?? array_key_first($f['pilihan']),
                'teks_panjang' => 'Keterangan sintetik bagi '.str_replace('_', ' ', $key).'.',
                default => ! empty($f['peribadi']) ? self::NAMA[($i + crc32($key)) % count(self::NAMA)].' (SINTETIK)' : strtoupper(substr($key, 0, 3)).'-'.mt_rand(1000, 9999).'/'.$start->format('Y'),
            };
            if ($key === 'no_kes_faraid') {
                $v = 'FRD-'.mt_rand(1000, 9999);
            }
            if ($key === 'tarikh_akhir_bayaran') {
                $v = now()->addDays(mt_rand(-10, 45))->toDateString();
            }
            if (! empty($f['peribadi'])) {
                $personal[$key] = $v;
            } else {
                $public[$key] = $v;
            }
        }

        return [$public, $personal];
    }

    private function parties(Pemerolehan $case, string $code, int $i): void
    {
        $roles = ChannelCatalog::partyRoles()[$code] ?? [];
        foreach (array_slice($roles, 0, 2) as $n => $role) {
            $case->pihak()->create([
                'peranan_pihak' => $role,
                'nama' => self::NAMA[($i + $n) % count(self::NAMA)].' (SINTETIK)',
                'hubungan' => $role === 'waris' ? 'Anak' : null,
                'jenis_dokumen_pengenalan' => 'mykad',
                'no_dokumen_pengenalan' => sprintf('%02d%02d%02d-10-%04d', mt_rand(50, 99), mt_rand(1, 12), mt_rand(1, 28), mt_rand(0, 9999)),
                'status_pengesahan' => mt_rand(0, 1) ? 'disahkan' : 'belum_disahkan',
                'sumber_pengesahan' => 'manual',
            ]);
        }
    }

    /** @return list<array{kod_mukim: string, no_lot: string}> */
    private function places(): array
    {
        $rows = [];
        try {
            $rows = DB::table('staging_lot_tanah')->select(['mukim_kod', 'no_lot'])->whereRaw('length(mukim_kod) = 6')->limit(300)->get()
                ->map(fn ($r) => ['kod_mukim' => (string) $r->mukim_kod, 'no_lot' => (string) $r->no_lot])->all();
        } catch (Throwable) {
            $rows = [];
        }
        if ($rows === []) {
            $mukim = DB::table('ref_mukim')->whereNull('sah_hingga')->pluck('kod')->map(fn ($k) => (string) $k)->all() ?: ['100803', '100101', '100501'];
            for ($n = 0; $n < 120; $n++) {
                $rows[] = ['kod_mukim' => $mukim[$n % count($mukim)], 'no_lot' => 'PT '.(90000 + $n)];
            }
        }

        return array_values($rows);
    }
}
