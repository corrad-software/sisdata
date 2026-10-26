<?php

namespace App\Console\MaisSeed;

use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetInsurans;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Services\AsetService;
use App\Modules\Ast\Services\PemeriksaanService;
use App\Modules\Ast\Services\PenyelenggaraanService;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * MOD-AST through AsetService / PemeriksaanService / PenyelenggaraanService,
 * like `ast:sample`: the main building named after the real property (year
 * built when public), let units linked to their MOD-PJK premises, a fence
 * and a MAIS signboard; valuation history, condition inspections,
 * preventive schedules, past work orders and an open one (≥ threshold →
 * real `ast.kelulusan_penyelenggaraan` WFE instance), takaful cover.
 * Costs, conditions and contractors are SYNTHETIC.
 */
class AsetBuilder
{
    private const KONTRAKTOR = ['Binaan Amanah Selangor', 'Kemas Teguh Services', 'Ihsan Elektrik & Mekanikal', 'Hijau Landskap Wakaf', 'Sejahtera Sanitasi'];

    private const KOS = [
        'BANGUNAN_PEJABAT' => [1500000, 9000000], 'MASJID_SURAU' => [400000, 6000000], 'SEKOLAH' => [600000, 5000000], 'KEDAI' => [180000, 600000],
        'KOMPLEKS' => [2500000, 25000000], 'KEDIAMAN' => [150000, 600000], 'KEMUDAHAN_AWAM' => [80000, 1500000], 'STRUKTUR' => [3000, 60000],
    ];

    /** @var array<string, AsetKategori>|null */
    private ?array $kategori = null;

    /** @var list<AsetKontraktor>|null */
    private ?array $kontraktor = null;

    public function __construct(private AsetService $aset, private PemeriksaanService $periksa, private PenyelenggaraanService $jaga, private AliranHelper $aliran) {}

    /**
     * @param  array<string, mixed>  $h
     * @param  list<PremisAset>  $premis
     */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k, array $premis): void
    {
        [, , , , $kod] = Profil::untuk($h['jenis']);
        $cats = $this->kategori();
        if (! isset($cats[$kod])) {
            $k->amaran($h['kunci'].': kategori aset '.$kod.' tiada (MOD-AST)');

            return;
        }
        $tahun = $this->tahunBina($h) ?? $r->int(1978, (int) now()->year - 2);
        $made = [];
        if (! in_array($h['jenis'], ['tanah_kosong', 'pertanian'], true)) {
            $made[] = $main = $this->satu($lot, $h, $r, $k, $cats[$kod], $h['nama'], $tahun, 'Aset utama hartanah awam "'.$h['nama'].'". Tahun bina '.($this->tahunBina($h) !== null ? 'daripada sumber awam' : 'SINTETIK').'; nilai dan keadaan SINTETIK.');
            foreach ($premis as $p) {
                if (! in_array($p->jenis_aset, ['unit_kedai', 'rumah', 'gerai'], true) || ! isset($cats[$p->jenis_aset === 'rumah' ? 'KEDIAMAN' : 'KEDAI'])) {
                    continue;
                }
                $made[] = $this->satu($lot, $h, $r, $k, $cats[$p->jenis_aset === 'rumah' ? 'KEDIAMAN' : 'KEDAI'], Str::limit($p->nama_premis, 180, ''), $tahun, 'Unit premis sewaan (MOD-PJK) — rekod SINTETIK.', ['id_premis_pjk' => $p->id, 'id_aset_induk' => $main->id]);
            }
        }
        if (isset($cats['STRUKTUR'])) {
            $made[] = $this->satu($lot, $h, $r, $k, $cats['STRUKTUR'], 'Pagar Sempadan — '.Str::limit($h['nama'], 120, ''), max($tahun, 1990), 'Struktur SINTETIK.', ['jenis_aset' => 'pagar']);
            if ($r->chance(60)) {
                $made[] = $this->satu($lot, $h, $r, $k, $cats['STRUKTUR'], 'Papan Tanda MAIS — '.Str::limit($h['nama'], 120, ''), max($tahun, 2005), 'Struktur SINTETIK.', ['jenis_aset' => 'papan_tanda']);
            }
        }
        foreach ($made as $a) {
            $this->nilai($a, $r, $k);
            $this->pemeriksaan($a, $r, $k);
            $this->penyelenggaraan($a->refresh(), $r, $k);
            $this->insurans($a, $r, $k);
        }
    }

    /**
     * @param  array<string, mixed>  $h
     * @param  array<string, mixed>  $extra
     */
    private function satu(LotTanah $lot, array $h, Rawak $r, Konteks $k, AsetKategori $cat, string $nama, int $tahun, string $keterangan, array $extra = []): AsetFizikal
    {
        $p = DB::selectOne('SELECT ST_Y(p) AS lat, ST_X(p) AS lng FROM (SELECT ST_PointOnSurface(ds.geometri) AS p FROM data_spatial ds WHERE ds.lot_id = ? AND ds.semasa LIMIT 1) s', [$lot->id]);
        [$min, $max] = self::KOS[$cat->kod] ?? [50000, 500000];
        $data = $extra + [
            'lot_id' => $lot->id, 'id_kategori' => $cat->id, 'jenis_aset' => $extra['jenis_aset'] ?? $cat->jenis_lalai, 'nama_aset' => Str::limit($nama, 200, ''),
            'keterangan' => $keterangan, 'alamat' => Str::limit((string) ($h['alamat'] ?? 'Selangor'), 250, ''),
            'latitud' => $p->lat ?? $h['lat'], 'longitud' => $p->lng ?? $h['lon'], 'tahun_pembinaan' => $tahun, 'tahun_perolehan' => min((int) now()->year - 1, $tahun + $r->int(0, 2)),
            'saiz_keluasan' => $cat->kod === 'STRUKTUR' ? $r->int(40, 600) : $r->int(150, 12000), 'unit_ukuran' => $cat->kod === 'STRUKTUR' ? 'm' : 'm2',
            'kos_perolehan' => round($r->int($min, $max) / 100) * 100, 'id_pegawai_bertanggungjawab' => $r->pick($k->lapangan)->id, 'sintetik' => true,
        ];
        try {
            $a = $this->aset->daftar($data, $k->admin);
        } catch (ValidationException $e) {
            // No `ast.kelulusan_perolehan` flow on this database: record the cost just under the approval threshold.
            $k->amaran($h['kunci'].': aset "'.$data['nama_aset'].'" didaftar tanpa aliran kelulusan ('.collect($e->errors())->flatten()->first().')');
            $a = $this->aset->daftar(['kos_perolehan' => 490000] + $data, $k->admin);
        }
        $k->tambah('aset_fizikal');
        // Cost above the acquisition threshold opened `ast.kelulusan_perolehan`: an
        // existing building was approved long ago — decide it; a few stay pending.
        if ($a->id_aliran !== null) {
            $k->tambah('wfe_ast');
            if ($r->chance(85)) {
                $this->aliran->putus((string) $a->id_aliran, 'lulus', $k->pelulus, 'Perolehan aset disahkan (SINTETIK)');
                $a->refresh();
            }
        }

        return $a;
    }

    private function nilai(AsetFizikal $a, Rawak $r, Konteks $k): void
    {
        if ($a->kos_perolehan === null || ! $r->chance(70)) {
            return;
        }
        $nilai = (float) $a->kos_perolehan;
        for ($y = max((int) ($a->tahun_perolehan ?? 2000) + 2, (int) now()->year - 10); $y <= (int) now()->year; $y += $r->int(3, 5)) {
            $nilai = round($nilai * $r->float(0.95, 1.3), -2);
            $this->aset->kemaskiniNilai($a, [
                'nilai' => $nilai, 'tarikh_penilaian' => Carbon::create($y, $r->int(1, 12), $r->int(1, 28))->min(now())->toDateString(),
                'kaedah_penilaian' => $r->weighted(['laporan_jpph' => 5, 'penilai_berdaftar' => 2, 'penilaian_dalaman' => 2]),
                'id_laporan_penilaian' => 'JPPH/SEL/'.$y.'/'.$r->int(1000, 9999), 'catatan' => 'SINTETIK',
            ], $k->admin);
            $k->tambah('aset_nilai');
        }
    }

    private function pemeriksaan(AsetFizikal $a, Rawak $r, Konteks $k): void
    {
        $tarikh = now()->subDays($r->int(60, 800));
        for ($i = 0, $n = $r->int(1, 3); $i < $n && ! $tarikh->isFuture(); $i++) {
            $kondisi = $r->weighted(['baik' => 68, 'perlu_perhatian' => 24, 'kritikal' => 8]);
            $this->periksa->rekod($a->refresh(), [
                'tarikh_pemeriksaan' => $tarikh->toDateString(), 'status_kondisi_dilapor' => $kondisi, 'id_luar_talian' => (string) Str::uuid(),
                'isu_dikenalpasti' => ['baik' => 'Tiada isu ketara.', 'perlu_perhatian' => 'Cat mengelupas dan kebocoran kecil pada bumbung.', 'kritikal' => 'Retakan struktur dan pendawaian terdedah.'][$kondisi].' (SINTETIK)',
                'tindakan_disyorkan' => $kondisi === 'baik' ? null : 'Jadualkan pembaikan.', 'latitud' => $a->latitud, 'longitud' => $a->longitud, 'ketepatan_gps_m' => $r->int(3, 25),
                'senarai_semak' => array_map(fn ($c) => ['kod' => $c, 'status' => $kondisi === 'baik' ? 'baik' : ($r->chance(65) ? 'baik' : 'isu')], array_slice(array_keys(Lalai::SENARAI_SEMAK), 0, 5)),
                'sintetik' => true,
            ], $r->pick($k->lapangan), $r->weighted(['pwa' => 5, 'manual' => 3, 'qr' => 2]));
            $k->tambah('aset_pemeriksaan');
            $tarikh = $tarikh->copy()->addDays($r->int(90, 300));
        }
    }

    private function penyelenggaraan(AsetFizikal $a, Rawak $r, Konteks $k): void
    {
        $jadual = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->get();
        foreach ($jadual as $j) {
            $due = $r->chance(12) ? now()->subDays($r->int(1, 120)) : now()->addDays($r->int(0, 30 * max(1, (int) $j->kitaran_bulan)));
            $j->forceFill(['tarikh_jatuh_tempoh' => $due->toDateString(), 'sintetik' => true, 'tarikh_terakhir_dilaksana' => $due->copy()->subMonthsNoOverflow((int) $j->kitaran_bulan)->toDateString()])->save();
        }
        $contractors = $this->kontraktor();
        for ($i = 0, $n = $r->int(0, 4); $i < $n; $i++) {
            $siap = now()->subDays($r->int(5, 1100));
            $jenis = $jadual->isNotEmpty() && $r->chance(50) ? $jadual->random()->jenis_penyelenggaraan : (string) array_rand(Lalai::JENIS_PENYELENGGARAAN);
            // Below the approval threshold: historical work needs no WFE instance.
            $kerja = $this->jaga->ciptaKerja($a, [
                'jenis_kerja' => $r->weighted(['pencegahan' => 5, 'pembaikan' => 4, 'kecemasan' => 1]), 'jenis_penyelenggaraan' => $jenis,
                'keterangan' => 'Kerja '.(Lalai::JENIS_PENYELENGGARAAN[$jenis] ?? $jenis).' (SINTETIK)', 'keutamaan' => $r->weighted(['rendah' => 2, 'sederhana' => 5, 'tinggi' => 2, 'kritikal' => 1]),
                'tarikh_dijadual' => $siap->copy()->subDays($r->int(3, 30))->toDateString(), 'kos_anggaran' => $r->int(3, 150) * 100, 'id_kontraktor' => $r->pick($contractors)->id, 'sintetik' => true,
            ], $k->admin);
            DB::table('rekod_penyelenggaraan')->where('id', $kerja->id)->update([
                'status' => 'selesai', 'tarikh_mula' => $siap->copy()->subDays($r->int(1, 10))->toDateString(), 'tarikh_siap' => $siap->toDateString(),
                'kos' => round($r->int(300, 15000) / 10) * 10, 'catatan_penyelesaian' => 'Kerja disiapkan (SINTETIK).',
            ]);
            $k->tambah('kerja_penyelenggaraan');
        }
        if ($r->chance(25) && $a->id_aset_induk === null) {
            $besar = $r->chance(60);
            try {
                $this->jaga->ciptaKerja($a, [
                    'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'bangunan', 'keterangan' => ($besar ? 'Naik taraf bumbung dan sistem elektrik' : 'Pembaikan tertangguh').' (SINTETIK)',
                    'tarikh_dijadual' => now()->addDays($r->int(-30, 45))->toDateString(), 'kos_anggaran' => $besar ? $r->int(25, 180) * 1000 : $r->int(5, 150) * 100,
                    'id_kontraktor' => $r->pick($contractors)->id, 'sintetik' => true,
                ], $k->pegawai);
                $k->tambah($besar ? 'wfe_ast' : 'kerja_terbuka');
            } catch (Throwable $e) {
                $k->amaran('AST kerja terbuka: '.$e->getMessage());
            }
        }
    }

    private function insurans(AsetFizikal $a, Rawak $r, Konteks $k): void
    {
        $cat = AsetKategori::query()->find($a->id_kategori);
        if ($cat === null || $cat->jenis_lalai !== 'bangunan' || $a->id_aset_induk !== null || ! $r->chance(60)) {
            return;
        }
        $mula = now()->subDays($r->int(0, 360));
        AsetInsurans::query()->create([
            'id_aset' => $a->id, 'no_polisi' => 'POL-S'.$r->int(100000, 999999), 'penanggung' => 'Syarikat Takaful Contoh (Sintetik)',
            'jenis_perlindungan' => 'Kebakaran dan bencana (takaful)', 'nilai_dilindungi' => $a->kos_perolehan, 'premium' => round((float) $a->kos_perolehan * 0.002, 2),
            'tarikh_mula' => $mula->toDateString(), 'tarikh_tamat' => $mula->copy()->addYear()->toDateString(), 'sintetik' => true,
            'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
        ]);
        $k->tambah('aset_insurans');
    }

    /** @param array<string, mixed> $h */
    private function tahunBina(array $h): ?int
    {
        foreach ((array) ($h['fakta'] ?? []) as $f) {
            if (in_array($f['medan'], ['tahun_dibina', 'tahun_siap', 'tahun_dibuka', 'tahun_dirasmikan'], true) && preg_match('/(19|20)\d{2}/', (string) $f['nilai'], $m)) {
                return min((int) $m[0], (int) now()->year);
            }
        }

        return null;
    }

    /** @return array<string, AsetKategori> */
    private function kategori(): array
    {
        return $this->kategori ??= AsetKategori::query()->where('status_aktif', true)->get()->keyBy('kod')->all();
    }

    /** @return list<AsetKontraktor> */
    private function kontraktor(): array
    {
        if ($this->kontraktor !== null) {
            return $this->kontraktor;
        }
        $out = [];
        foreach (self::KONTRAKTOR as $i => $s) {
            $out[] = AsetKontraktor::query()->firstOrCreate(['nama_syarikat' => $s.' Sdn Bhd (SINTETIK)'], [
                'no_pendaftaran' => 'SSM-MS'.str_pad((string) (200000 + $i * 7919), 7, '0', STR_PAD_LEFT), 'no_telefon' => '03-0000 1'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'emel' => 'kontraktor.mais'.$i.'@contoh.invalid', 'bidang' => array_slice(array_keys(Lalai::JENIS_PENYELENGGARAAN), $i % 8, 3),
                'tarikh_tamat_pendaftaran' => now()->addMonths(24)->toDateString(), 'status_aktif' => true, 'sintetik' => true,
            ]);
        }

        return $this->kontraktor = $out;
    }
}
