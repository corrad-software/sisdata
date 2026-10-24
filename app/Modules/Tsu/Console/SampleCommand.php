<?php

namespace App\Modules\Tsu\Console;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tsu\Models\HasilUkur;
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
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `php artisan tsu:sample --count=50` — SYNTHETIC Land Office matters for the
 * dev DB on existing (synthetic) lots: all seven types at different stages
 * (some late, some closed), stage documents (fake DMS ids, nothing uploaded),
 * costs, Land Office status history, a synthetic surveyor panel with
 * quotations, appointments, progress and survey results. Every urusan and
 * panel row is flagged `sintetik`. Master lots are NOT changed (no successor
 * lots / archiving — those happen only through the real flow). Notifications
 * are suppressed.
 */
class SampleCommand extends Command
{
    protected $signature = 'tsu:sample {--count=50 : Bilangan urusan sintetik} {--seed= : Benih rawak (boleh diulang)}';

    protected $description = 'Jana urusan tanah SINTETIK (tukar syarat, pecah sempadan, pelupusan, ukur…) untuk DB pembangunan (MOD-TSU)';

    private const BERAT = ['tukar_syarat' => 25, 'pecah_sempadan' => 15, 'pecah_bahagian' => 8, 'penyatuan' => 8, 'pelupusan' => 14, 'serah_balik_beri_milik' => 12, 'hak_milik_kekal' => 18];

    private const FIRMA = [
        ['Jurukur Sintetik Bestari Sdn Bhd', 'JTB-S-0101'], ['Ukur Contoh Selangor & Rakan', 'JTB-S-0102'], ['Geomatik Ujian Klang Sdn Bhd', 'JTB-S-0103'],
        ['Juruukur Demo Hulu Langat', 'JTB-S-0104'], ['Pemetaan Sampel Sepang Sdn Bhd', 'JTB-S-0105'], ['Kadaster Rekaan Gombak & Co', 'JTB-S-0106'],
    ];

    public function handle(UrusanService $service): int
    {
        $count = max(1, (int) $this->option('count'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });

        $users = User::query()->where('is_active', true)->orderBy('id')->limit(20)->get();
        if ($users->isEmpty()) {
            $this->error('Tiada pengguna aktif.');

            return self::FAILURE;
        }
        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->where('status_lot', 'aktif')->orderByDesc('sintetik')->inRandomOrder()->limit(max(60, $count * 3))->get(['id', 'kod_mukim', 'no_lot', 'keluasan']);
        if ($lots->count() < 2) {
            $this->error('Tiada lot aktif — jalankan ref:sample-lots / pro:import-staging dahulu.');

            return self::FAILURE;
        }
        $panel = $this->panel();
        $used = [];
        $made = 0;

        for ($i = 0; $i < $count; $i++) {
            $jenis = $this->pick();
            $need = $jenis === 'penyatuan' ? 2 : 1;
            $pickLots = $lots->filter(fn ($l) => ! isset($used[$l->id]))->take($need);
            if ($pickLots->count() < $need) {
                break;
            }
            if ($need === 2) {
                $same = $lots->filter(fn ($l) => ! isset($used[$l->id]) && $l->kod_mukim === $pickLots->first()->kod_mukim)->take(2);
                $pickLots = $same->count() === 2 ? $same : $pickLots;
            }
            foreach ($pickLots as $l) {
                $used[$l->id] = true;
            }
            $user = $users[mt_rand(0, $users->count() - 1)];
            $mula = today()->subDays(mt_rand(5, 420));
            $data = [
                'jenis_urusan' => $jenis, 'lot_ids' => $pickLots->pluck('id')->all(), 'tarikh_mula' => $mula->toDateString(),
                'pejabat_tanah' => 'Pejabat Tanah dan Daerah (sintetik)', 'keterangan' => 'Data contoh SINTETIK — bukan urusan sebenar.',
                'kaedah_pelupusan' => $jenis === 'pelupusan' ? ['jualan', 'tukaran', 'pindah_milik'][mt_rand(0, 2)] : null,
                'justifikasi' => $jenis === 'pelupusan' ? 'Justifikasi sintetik: tanah tidak ekonomik untuk dibangunkan oleh MAIS.' : null,
                'butiran' => match ($jenis) {
                    'tukar_syarat', 'serah_balik_beri_milik' => ['kategori_guna_tanah_baharu' => ['BANGUNAN', 'PERUSAHAAN', 'PERTANIAN'][mt_rand(0, 2)], 'syarat_nyata_baharu' => 'Bangunan institusi agama (sintetik)'],
                    'pecah_sempadan', 'pecah_bahagian' => ['bilangan_lot_dicadang' => mt_rand(2, 4)],
                    'pelupusan' => ['pihak_penerima' => 'Agensi kerajaan negeri (sintetik)'],
                    default => [],
                },
            ];
            try {
                $u = $service->create($data, $user, true);
            } catch (\Throwable $e) {
                $this->warn('Langkau: '.$e->getMessage());

                continue;
            }
            $this->advance($u, $user, $mula, $panel);
            $made++;
        }

        $this->info("{$made} urusan sintetik dijana (".count($panel).' jurukur panel sintetik).');

        return self::SUCCESS;
    }

    private function pick(): string
    {
        $r = mt_rand(1, array_sum(self::BERAT));
        foreach (self::BERAT as $k => $w) {
            if (($r -= $w) <= 0) {
                return $k;
            }
        }

        return 'tukar_syarat';
    }

    /** @return list<PanelJurukur> */
    private function panel(): array
    {
        $out = [];
        foreach (self::FIRMA as $i => [$nama, $lesen]) {
            $out[] = PanelJurukur::query()->firstOrCreate(['no_lesen_jurukur' => $lesen], [
                'nama_firma' => $nama, 'nama_jurukur_bertanggungjawab' => 'Jurukur Sintetik '.($i + 1),
                'maklumat_perhubungan' => ['emel' => 'jurukur'.($i + 1).'@contoh.test', 'telefon' => '03-0000 00'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                'pengalaman_tahun' => 3 + $i * 3, 'status_panel' => $i === 5 ? 'digantung' : 'aktif', 'tarikh_tamat_lesen' => today()->addYears(2)->toDateString(), 'sintetik' => true,
            ]);
        }

        return $out;
    }

    /** @param list<PanelJurukur> $panel */
    private function advance(UrusanTanah $u, User $user, Carbon $mula, array $panel): void
    {
        $stages = UrusanPeringkat::query()->where('urusan_id', $u->id)->orderBy('no_urutan')->get();
        $done = mt_rand(0, $stages->count());
        if (in_array($u->jenis_urusan, Lalai::JENIS_LOT_BAHARU, true)) {
            // Synthetic data never creates successor lots / archives master lots.
            $done = min($done, (int) $stages->search(fn ($s) => $s->kesan === 'cipta_lot_baharu'));
        }
        if ($u->jenis_urusan === 'pelupusan') {
            $done = min($done, (int) $stages->search(fn ($s) => $s->kesan === 'arkib_lot_dilupus'));
        }
        $tarikh = $mula->copy();
        $lot = DB::table('urusan_lot')->where('urusan_id', $u->id)->value('lot_id');
        $lantikan = null;

        DB::transaction(function () use ($u, $user, $stages, $done, &$tarikh, $lot, $panel, &$lantikan) {
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
                        $u->forceFill(['nilai_penilaian' => Wang::norm((string) mt_rand(150000, 4500000))])->save();
                    }
                }
                foreach ($s->senarai_kategori_kos as $kat) {
                    $paid = $idx < $done;
                    KosUrusan::query()->create([
                        'urusan_id' => $u->id, 'peringkat_id' => $s->id, 'kategori_kos' => $kat, 'keterangan' => (Lalai::KATEGORI_KOS[$kat] ?? $kat).' (sintetik)',
                        'amaun' => Wang::norm($kat === 'premium' ? mt_rand(5000, 250000) : mt_rand(100, 9000)), 'status_bayaran' => $paid ? 'dibayar' : 'belum_bayar',
                        'no_invois' => 'INV-S-'.mt_rand(10000, 99999), 'tarikh_invois' => $start->toDateString(), 'tarikh_akhir_bayaran' => $start->copy()->addDays(30)->toDateString(),
                        'tarikh_bayaran' => $paid ? $start->copy()->addDays(mt_rand(3, 25))->toDateString() : null, 'no_resit' => $paid ? 'RS-S-'.mt_rand(100000, 999999) : null, 'id_pencipta' => $user->id,
                    ]);
                }
                if ($s->kod === 'jemputan_ukur' && $idx < $done) {
                    $lantikan = $this->survey($u, $user, $start, $panel);
                }
                if ($s->kod === 'kerja_ukur' && $lantikan !== null) {
                    $this->progress($lantikan, $user, $idx < $done);
                }
                if (in_array($s->kod, ['pengesahan_hasil_ukur'], true) && $lantikan !== null && $lot !== null) {
                    $this->result($u, $lantikan, $lot, $user, $idx < $done);
                }
                if ($idx < $done) {
                    $spent = (int) round($s->tempoh_sasaran_hari * (mt_rand(40, 150) / 100));
                    $tarikh = $start->copy()->addDays(max(1, $spent));
                    if ($tarikh->gt(today())) {
                        $tarikh = today()->copy();
                    }
                    $s->forceFill(['status' => 'selesai', 'tarikh_selesai' => $tarikh->toDateString(), 'id_pegawai_selesai' => $user->id])->save();
                    if ($s->perlu_kelulusan) {
                        UrusanKeputusan::query()->create(['urusan_id' => $u->id, 'peringkat_id' => $s->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'lulus', 'tarikh_keputusan' => $tarikh, 'justifikasi' => 'Kelulusan sintetik.', 'id_pelulus' => $user->id, 'indikator_mfa' => true]);
                    }
                    if (in_array($s->kesan, ['perlu_rujukan_ptd', 'perlu_rujukan_etanah'], true)) {
                        $this->ptd($u, $user, $tarikh);
                    }
                } else {
                    $s->forceFill(['status' => 'aktif'])->save();
                    $u->forceFill(['peringkat_semasa' => $s->no_urutan, 'status' => 'aktif'])->save();
                }
            }
            if ($done >= $stages->count()) {
                $u->forceFill(['status' => 'selesai', 'tarikh_siap' => $tarikh->toDateString(), 'peringkat_semasa' => $stages->last()?->no_urutan])->save();
            }
        });

        if ($u->refresh()->status === 'selesai' && mt_rand(0, 1) === 1) {
            UrusanKeputusan::query()->create(['urusan_id' => $u->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'tutup', 'justifikasi' => 'Ditutup (sintetik).', 'id_pelulus' => $user->id]);
            $u->forceFill(['status' => 'ditutup', 'tarikh_tutup' => now(), 'dikunci' => true])->save();
        }
    }

    /** @param list<PanelJurukur> $panel */
    private function survey(UrusanTanah $u, User $user, Carbon $start, array $panel): LantikanJurukur
    {
        $sh = new SebutHargaUkur;
        $sh->forceFill([
            'urusan_id' => $u->id, 'no_jemputan' => Penomboran::next('TSU-SH', 4), 'tarikh_jemputan' => $start->toDateString(), 'tarikh_tutup' => $start->copy()->addDays(14),
            'skop_kerja' => 'Kerja ukur sintetik bagi '.$u->no_rujukan_mais, 'status' => 'diluluskan', 'id_pencipta' => $user->id, 'id_penilai' => $user->id, 'id_pelulus' => $user->id,
            'tarikh_kelulusan' => $start->copy()->addDays(20), 'justifikasi_pemilihan' => 'Markah keseluruhan tertinggi (sintetik).',
        ])->save();
        $best = null;
        foreach (array_slice($panel, 0, 3 + mt_rand(0, 1)) as $j) {
            $t = SebutHargaTawaran::query()->create([
                'sebutharga_id' => $sh->id, 'jurukur_id' => $j->id, 'status' => 'diterima', 'harga_tawaran' => Wang::norm(mt_rand(8000, 60000)),
                'tempoh_siap_hari' => mt_rand(30, 120), 'pengalaman_tahun' => $j->pengalaman_tahun, 'tarikh_terima' => $start->copy()->addDays(mt_rand(2, 13)), 'markah' => mt_rand(55, 95),
            ]);
            $best = $best === null || (float) $t->markah > (float) $best->markah ? $t : $best;
        }
        $best->forceFill(['indikator_disyorkan' => true])->save();
        $sh->forceFill(['id_tawaran_disyorkan' => $best->id])->save();
        $mula = $start->copy()->addDays(25);
        $l = new LantikanJurukur;
        $l->forceFill([
            'urusan_id' => $u->id, 'sebutharga_id' => $sh->id, 'jurukur_id' => $best->jurukur_id, 'no_surat_setuju_terima' => 'SST-S-'.mt_rand(1000, 9999),
            'tarikh_surat_setuju_terima' => $mula->toDateString(), 'nilai_kontrak' => $best->harga_tawaran, 'tarikh_mula' => $mula->toDateString(), 'tempoh_siap_hari' => (int) $best->tempoh_siap_hari,
            'tarikh_jangka_siap' => $mula->copy()->addDays((int) $best->tempoh_siap_hari)->toDateString(), 'skop_kerja' => $sh->skop_kerja, 'status' => 'aktif', 'id_pencipta' => $user->id,
        ])->save();

        return $l;
    }

    private function progress(LantikanJurukur $l, User $user, bool $finished): void
    {
        $steps = $finished ? [25, 50, 75, 100] : array_slice([20, 40, 60], 0, mt_rand(1, 3));
        $n = count($steps);
        foreach ($steps as $i => $p) {
            $on = $l->tarikh_mula->copy()->addDays((int) round($l->tempoh_siap_hari * ($i + 1) / ($n + 1)));
            if ($on->gt(today())) {
                $on = today()->copy();
            }
            $expected = $l->peratusDijangka($on);
            KemajuanKerjaUkur::query()->create(['lantikan_id' => $l->id, 'milestone' => ['Kerja lapangan', 'Pengiraan', 'Penyediaan pelan', 'Penyerahan'][$i] ?? 'Kemajuan', 'peratus_siap' => $p, 'peratus_dijangka' => $expected, 'lewat' => $p + 10 < $expected, 'tarikh_lapor' => $on->toDateString(), 'id_pelapor' => $user->id]);
            $l->forceFill(['peratus_siap_semasa' => $p, 'lewat' => $p + 10 < $expected])->save();
        }
    }

    private function result(UrusanTanah $u, LantikanJurukur $l, string $lotId, User $user, bool $accepted): void
    {
        $g = DB::selectOne('SELECT ST_AsHEXEWKB(ST_Multi(geometri)) AS hex, ST_Area(geometri::geography) AS m2 FROM data_spatial WHERE lot_id = ? AND semasa', [$lotId]);
        if ($g === null) {
            return;
        }
        $declared = round((float) $g->m2 * (mt_rand(94, 106) / 100), 4);
        $sisihan = $declared > 0 ? round(abs((float) $g->m2 - $declared) / $declared * 100, 2) : null;
        $h = new HasilUkur;
        $h->forceFill([
            'urusan_id' => $u->id, 'lantikan_id' => $l->id, 'lot_id' => $u->jenis_urusan === 'hak_milik_kekal' ? $lotId : null,
            'no_lot_cadangan' => in_array($u->jenis_urusan, Lalai::JENIS_LOT_BAHARU, true) ? 'PT '.mt_rand(90000, 99999) : null, 'format_fail' => ['dxf', 'shp', 'geojson'][mt_rand(0, 2)],
            'sistem_koordinat' => 3380, 'keluasan_dikira' => round((float) $g->m2, 4), 'keluasan_dokumen' => $declared, 'sisihan_peratus' => $sisihan, 'toleransi_peratus' => 5,
            'dalam_toleransi' => $sisihan !== null && $sisihan <= 5, 'status_pengesahan' => $accepted ? 'diterima' : 'menunggu', 'id_pemuat_naik' => $user->id,
            'id_pegawai_sah' => $accepted ? $user->id : null, 'tarikh_sah' => $accepted ? now() : null, 'geometri' => DB::raw("ST_GeomFromEWKB(decode('".$g->hex."', 'hex'))"),
        ])->save();
        if ($accepted) {
            $l->forceFill(['status' => 'siap', 'tarikh_siap' => $l->tarikh_jangka_siap->copy()->addDays(mt_rand(-10, 20))->min(today())->toDateString(), 'peratus_siap_semasa' => 100])->save();
        }
    }

    private function ptd(UrusanTanah $u, User $user, Carbon $on): void
    {
        $ref = 'PTD/'.$u->kod_daerah.'/'.$on->format('Y').'/S'.mt_rand(10000, 99999);
        $statuses = array_slice(['diterima', 'dalam_semakan', 'lawatan_tapak', 'ulasan_jabatan', 'dibentang_mmkn', 'lulus'], 0, mt_rand(1, 6));
        $manual = mt_rand(0, 1) === 1;
        foreach ($statuses as $i => $s) {
            KemaskiniStatusPtd::query()->create(['urusan_id' => $u->id, 'sumber' => $manual ? 'manual' : 'api', 'status_ptd' => $s, 'tarikh_status' => $on->copy()->addDays($i * 12)->min(today())->toDateString(), 'rujukan_ptd' => $ref, 'id_pengguna' => $manual ? $user->id : null, 'catatan' => 'Status sintetik.']);
        }
        $u->forceFill([
            'no_rujukan_ptd' => $ref, 'status_ptd' => end($statuses), 'sumber_status_ptd' => $manual ? 'manual' : 'api', 'tarikh_status_ptd' => now(),
            'tarikh_susulan_ptd' => $manual ? today()->addDays(mt_rand(1, 14))->toDateString() : null,
        ])->save();
    }
}
