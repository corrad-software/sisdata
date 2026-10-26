<?php

namespace App\Console\MaisSeed;

use App\Models\User;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Tsu\Models\KemajuanKerjaUkur;
use App\Modules\Tsu\Models\KemaskiniStatusPtd;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\SebutHargaTawaran;
use App\Modules\Tsu\Models\SebutHargaUkur;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Services\UrusanService;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Penomboran;
use App\Modules\Tsu\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * MOD-TSU through UrusanService::create, advanced like `tsu:sample`: a
 * Land Office matter that suits the property (vacant land → tukar syarat,
 * large plots → pecah sempadan, temporary titles → hak milik kekal,
 * occasionally pelupusan), with stage documents (fake DMS ids), costs,
 * PTD status history and a surveyor appointment. Master lots are never
 * changed (no successor lots / archiving). All SYNTHETIC.
 */
class UrusanBuilder
{
    private const FIRMA = [
        ['Jurukur Contoh Amanah Sdn Bhd (Sintetik)', 'JTB-MS-0201'], ['Ukur Sampel Darul Ehsan & Rakan (Sintetik)', 'JTB-MS-0202'], ['Geomatik Rekaan Selangor (Sintetik)', 'JTB-MS-0203'],
    ];

    public function __construct(private UrusanService $service) {}

    /** @param array<string, mixed> $h */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): ?UrusanTanah
    {
        $jenis = match (true) {
            in_array($h['jenis'], ['tanah_kosong', 'pertanian'], true) => $r->weighted(['tukar_syarat' => 60, 'pecah_sempadan' => 25, 'pelupusan' => 15]),
            str_starts_with((string) $lot->no_lot, 'PT') => 'hak_milik_kekal',
            in_array($h['jenis'], ['komersial', 'hotel', 'bangunan_pejabat'], true) => $r->weighted(['tukar_syarat' => 50, 'pecah_bahagian' => 50]),
            default => $r->weighted(['tukar_syarat' => 40, 'serah_balik_beri_milik' => 35, 'hak_milik_kekal' => 25]),
        };
        $mula = today()->subDays($r->int(15, 500));
        $u = $this->service->create([
            'jenis_urusan' => $jenis, 'lot_ids' => [$lot->id], 'tarikh_mula' => $mula->toDateString(),
            'pejabat_tanah' => 'Pejabat Tanah dan Daerah '.$this->daerah($lot->kod_daerah),
            'keterangan' => 'Urusan contoh SINTETIK bagi '.$h['nama'].' — bukan urusan sebenar.',
            'kaedah_pelupusan' => $jenis === 'pelupusan' ? $r->pick(['tukaran', 'pindah_milik']) : null,
            'justifikasi' => $jenis === 'pelupusan' ? 'Justifikasi SINTETIK: cadangan istibdal / tukaran dengan tanah yang lebih bermanfaat (tertakluk dasar MAIS).' : null,
            'butiran' => match ($jenis) {
                'tukar_syarat', 'serah_balik_beri_milik' => ['kategori_guna_tanah_baharu' => $h['jenis'] === 'pertanian' ? 'BANGUNAN' : $r->pick(['BANGUNAN', 'PERUSAHAAN']), 'syarat_nyata_baharu' => 'Bangunan perniagaan / institusi (SINTETIK)'],
                'pecah_sempadan', 'pecah_bahagian' => ['bilangan_lot_dicadang' => $r->int(2, 6)],
                'pelupusan' => ['pihak_penerima' => 'Agensi kerajaan negeri (SINTETIK)'],
                default => [],
            },
        ], $k->pegawai, true);
        $k->tambah('urusan_tanah');
        $this->maju($u, $k->pegawai, $mula, $r);

        return $u->refresh();
    }

    private function maju(UrusanTanah $u, User $user, Carbon $mula, Rawak $r): void
    {
        $stages = UrusanPeringkat::query()->where('urusan_id', $u->id)->orderBy('no_urutan')->get();
        $done = $r->int(0, $stages->count());
        if (in_array($u->jenis_urusan, Lalai::JENIS_LOT_BAHARU, true)) {
            $done = min($done, (int) $stages->search(fn ($s) => $s->kesan === 'cipta_lot_baharu'));
        }
        if ($u->jenis_urusan === 'pelupusan') {
            $done = min($done, (int) $stages->search(fn ($s) => $s->kesan === 'arkib_lot_dilupus'));
        }
        $tarikh = $mula->copy();
        $lantikan = null;
        foreach ($stages as $idx => $s) {
            if ($idx > $done) {
                break;
            }
            $start = $tarikh->copy();
            $s->forceFill(['tarikh_mula' => $start->toDateString(), 'tarikh_sasaran' => $start->copy()->addDays($s->tempoh_sasaran_hari)->toDateString()]);
            foreach ($s->senarai_dokumen_wajib as $kat) {
                UrusanDokumen::query()->create([
                    'urusan_id' => $u->id, 'peringkat_id' => $s->id, 'id_dokumen_dms' => 'sintetik:'.Str::uuid(), 'kategori_dokumen' => $kat,
                    'nama_fail' => $kat.'-sintetik.pdf', 'tarikh_dokumen' => $start->toDateString(), 'tarikh_muat_naik' => $start, 'id_pemuat_naik' => $user->id,
                    'tarikh_luput' => $kat === 'laporan_penilaian' ? $start->copy()->addMonths(12)->toDateString() : null,
                ]);
                if ($kat === 'laporan_penilaian') {
                    $u->forceFill(['nilai_penilaian' => Wang::norm((string) $r->int(150000, 4500000))])->save();
                }
            }
            foreach ($s->senarai_kategori_kos as $kat) {
                $paid = $idx < $done;
                KosUrusan::query()->create([
                    'urusan_id' => $u->id, 'peringkat_id' => $s->id, 'kategori_kos' => $kat, 'keterangan' => (Lalai::KATEGORI_KOS[$kat] ?? $kat).' (SINTETIK)',
                    'amaun' => Wang::norm($kat === 'premium' ? $r->int(5000, 250000) : $r->int(100, 9000)), 'status_bayaran' => $paid ? 'dibayar' : 'belum_bayar',
                    'no_invois' => 'INV-S-'.$r->int(10000, 99999), 'tarikh_invois' => $start->toDateString(), 'tarikh_akhir_bayaran' => $start->copy()->addDays(30)->toDateString(),
                    'tarikh_bayaran' => $paid ? $start->copy()->addDays($r->int(3, 25))->toDateString() : null, 'no_resit' => $paid ? 'RS-S-'.$r->int(100000, 999999) : null, 'id_pencipta' => $user->id,
                ]);
            }
            if ($s->kod === 'jemputan_ukur' && $idx < $done) {
                $lantikan = $this->ukur($u, $user, $start, $r);
            }
            if ($s->kod === 'kerja_ukur' && $lantikan !== null) {
                $steps = $idx < $done ? [25, 50, 75, 100] : [20, 40];
                foreach ($steps as $i => $p) {
                    $on = $lantikan->tarikh_mula->copy()->addDays((int) round($lantikan->tempoh_siap_hari * ($i + 1) / (count($steps) + 1)))->min(today());
                    KemajuanKerjaUkur::query()->create(['lantikan_id' => $lantikan->id, 'milestone' => ['Kerja lapangan', 'Pengiraan', 'Penyediaan pelan', 'Penyerahan'][$i], 'peratus_siap' => $p, 'peratus_dijangka' => $lantikan->peratusDijangka($on), 'lewat' => false, 'tarikh_lapor' => $on->toDateString(), 'id_pelapor' => $user->id]);
                    $lantikan->forceFill(['peratus_siap_semasa' => $p])->save();
                }
            }
            if ($idx < $done) {
                $tarikh = $start->copy()->addDays(max(1, (int) round($s->tempoh_sasaran_hari * $r->int(40, 150) / 100)))->min(today());
                $s->forceFill(['status' => 'selesai', 'tarikh_selesai' => $tarikh->toDateString(), 'id_pegawai_selesai' => $user->id])->save();
                if ($s->perlu_kelulusan) {
                    UrusanKeputusan::query()->create(['urusan_id' => $u->id, 'peringkat_id' => $s->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'lulus', 'tarikh_keputusan' => $tarikh, 'justifikasi' => 'Kelulusan SINTETIK (rekod sejarah).', 'id_pelulus' => $user->id, 'indikator_mfa' => true]);
                }
                if (in_array($s->kesan, ['perlu_rujukan_ptd', 'perlu_rujukan_etanah'], true)) {
                    $this->ptd($u, $user, $tarikh, $r);
                }
            } else {
                $s->forceFill(['status' => 'aktif'])->save();
                $u->forceFill(['peringkat_semasa' => $s->no_urutan, 'status' => 'aktif'])->save();
            }
        }
        if ($done >= $stages->count()) {
            $u->forceFill(['status' => 'selesai', 'tarikh_siap' => $tarikh->toDateString(), 'peringkat_semasa' => $stages->last()?->no_urutan])->save();
        }
    }

    private function ukur(UrusanTanah $u, User $user, Carbon $start, Rawak $r): LantikanJurukur
    {
        $panel = [];
        foreach (self::FIRMA as $i => [$nama, $lesen]) {
            $panel[] = PanelJurukur::query()->firstOrCreate(['no_lesen_jurukur' => $lesen], [
                'nama_firma' => $nama, 'nama_jurukur_bertanggungjawab' => 'Jurukur Sintetik MAIS '.($i + 1),
                'maklumat_perhubungan' => ['emel' => 'jurukur.mais'.($i + 1).'@contoh.invalid', 'telefon' => '03-0000 01'.$i.'0'],
                'pengalaman_tahun' => 5 + $i * 4, 'status_panel' => 'aktif', 'tarikh_tamat_lesen' => today()->addYears(2)->toDateString(), 'sintetik' => true,
            ]);
        }
        $sh = new SebutHargaUkur;
        $sh->forceFill([
            'urusan_id' => $u->id, 'no_jemputan' => Penomboran::next('TSU-SH', 4), 'tarikh_jemputan' => $start->toDateString(), 'tarikh_tutup' => $start->copy()->addDays(14),
            'skop_kerja' => 'Kerja ukur SINTETIK bagi '.$u->no_rujukan_mais, 'status' => 'diluluskan', 'id_pencipta' => $user->id, 'id_penilai' => $user->id, 'id_pelulus' => $user->id,
            'tarikh_kelulusan' => $start->copy()->addDays(20), 'justifikasi_pemilihan' => 'Markah keseluruhan tertinggi (SINTETIK).',
        ])->save();
        $best = null;
        foreach ($panel as $j) {
            $t = SebutHargaTawaran::query()->create([
                'sebutharga_id' => $sh->id, 'jurukur_id' => $j->id, 'status' => 'diterima', 'harga_tawaran' => Wang::norm($r->int(8000, 60000)),
                'tempoh_siap_hari' => $r->int(30, 120), 'pengalaman_tahun' => $j->pengalaman_tahun, 'tarikh_terima' => $start->copy()->addDays($r->int(2, 13)), 'markah' => $r->int(55, 95),
            ]);
            $best = $best === null || (float) $t->markah > (float) $best->markah ? $t : $best;
        }
        $best->forceFill(['indikator_disyorkan' => true])->save();
        $sh->forceFill(['id_tawaran_disyorkan' => $best->id])->save();
        $mula = $start->copy()->addDays(25);
        $l = new LantikanJurukur;
        $l->forceFill([
            'urusan_id' => $u->id, 'sebutharga_id' => $sh->id, 'jurukur_id' => $best->jurukur_id, 'no_surat_setuju_terima' => 'SST-S-'.$r->int(1000, 9999),
            'tarikh_surat_setuju_terima' => $mula->toDateString(), 'nilai_kontrak' => $best->harga_tawaran, 'tarikh_mula' => $mula->toDateString(), 'tempoh_siap_hari' => (int) $best->tempoh_siap_hari,
            'tarikh_jangka_siap' => $mula->copy()->addDays((int) $best->tempoh_siap_hari)->toDateString(), 'skop_kerja' => $sh->skop_kerja, 'status' => 'aktif', 'id_pencipta' => $user->id,
        ])->save();

        return $l;
    }

    private function ptd(UrusanTanah $u, User $user, Carbon $on, Rawak $r): void
    {
        $ref = 'PTD/'.$u->kod_daerah.'/'.$on->format('Y').'/S'.$r->int(10000, 99999);
        $statuses = array_slice(['diterima', 'dalam_semakan', 'lawatan_tapak', 'ulasan_jabatan', 'dibentang_mmkn', 'lulus'], 0, $r->int(1, 6));
        $manual = $r->chance(50);
        foreach ($statuses as $i => $s) {
            KemaskiniStatusPtd::query()->create(['urusan_id' => $u->id, 'sumber' => $manual ? 'manual' : 'api', 'status_ptd' => $s, 'tarikh_status' => $on->copy()->addDays($i * 12)->min(today())->toDateString(), 'rujukan_ptd' => $ref, 'id_pengguna' => $manual ? $user->id : null, 'catatan' => 'Status SINTETIK.']);
        }
        $u->forceFill([
            'no_rujukan_ptd' => $ref, 'status_ptd' => end($statuses), 'sumber_status_ptd' => $manual ? 'manual' : 'api', 'tarikh_status_ptd' => now(),
            'tarikh_susulan_ptd' => $manual ? today()->addDays($r->int(1, 14))->toDateString() : null,
        ])->save();
    }

    private function daerah(string $kod): string
    {
        return [
            '1001' => 'Klang', '1002' => 'Kuala Langat', '1004' => 'Kuala Selangor', '1005' => 'Sabak Bernam', '1006' => 'Hulu Langat',
            '1007' => 'Hulu Selangor', '1008' => 'Petaling', '1009' => 'Gombak', '1010' => 'Sepang',
        ][$kod] ?? $kod;
    }
}
