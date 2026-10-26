<?php

namespace App\Console\MaisSeed;

use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Models\SekatanKepentingan;
use App\Modules\Hkm\Models\WarisPemilikBersama;
use App\Modules\Hkm\Services\TitleService;
use App\Modules\Hkm\Services\VaultService;
use App\Modules\Per\Models\DokumenPemerolehan;
use App\Modules\Per\Models\Kelulusan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Services\ApprovalService;
use App\Modules\Per\Services\Checklist;
use App\Modules\Per\Support\ChannelCatalog;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * MOD-PER acquisition case (channel consistent with the stream) walked
 * through its stages with back-dated timestamps — like `per:sample-cases` —
 * and, when complete, the MOD-HKM title registered through TitleService
 * with MAIS (an organisation) as registered owner, deposited in the vault.
 * A handful of cases stay in progress (no title yet); those waiting at the
 * approval stage get a real `per.kelulusan` WFE instance.
 */
class PemerolehanBuilder
{
    public const PETI_BESI = 'PB-MAIS-AWAM';

    /** @var array<string, Saluran>|null */
    private ?array $saluran = null;

    public function __construct(private TitleService $titles, private VaultService $vault, private Checklist $checklist, private ApprovalService $approval) {}

    /**
     * @param  array<string, mixed>  $h
     * @return array{kes: Pemerolehan, hak_milik: HakMilik|null, instans: string|null}
     */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): array
    {
        $code = $this->kodSaluran($h, $r);
        $channel = $this->saluran()[$code] ?? $this->saluran()['pembelian_terus'] ?? null;
        if ($channel === null) {
            throw new \RuntimeException('Saluran pemerolehan belum dibenihkan (MOD-PER).');
        }
        $stages = $channel->peringkat->where('aktif', true)->values();
        $inProgress = ! empty($h['_per_dalam_proses']);
        $target = $inProgress ? $r->int(0, max(0, $stages->count() - 2)) : $stages->count();

        $year = $this->tahun($h, $r);
        $start = Carbon::create($year, $r->int(1, 12), $r->int(1, 28))->startOfDay();
        if ($inProgress) {
            $start = now()->subDays($r->int(30, 400))->startOfDay();
        }
        $value = (string) (match ($h['jenis']) {
            'hotel', 'bangunan_pejabat' => $r->int(80, 400) * 100000,
            'komersial', 'klinik', 'pusat_islam', 'institusi_pendidikan' => $r->int(15, 200) * 100000,
            'pertanian', 'tanah_kosong' => $r->int(5, 120) * 100000,
            default => $r->int(3, 60) * 100000,
        });

        $case = Pemerolehan::query()->create([
            'no_kes' => sprintf('PER/%s/%05d', $start->format('Y'), (int) DB::selectOne("SELECT nextval('pemerolehan_no_kes_seq') AS n")->n),
            'id_saluran' => $channel->id,
            'tajuk' => Str::limit($channel->nama_saluran.' — '.$h['nama'], 240, ''),
            'lot_id' => $lot->id,
            'kod_mukim' => $lot->kod_mukim,
            'kod_daerah' => $lot->kod_daerah,
            'tarikh_mula_kes' => $start->toDateString(),
            'status_pemerolehan' => Pemerolehan::DALAM_PROSES,
            'kod_bahagian' => $channel->bahagian_bertanggungjawab,
            'id_pegawai_bertanggungjawab' => $k->pegawai->id,
            'id_pendaftar' => $k->pegawai->id,
            'nilai_pasaran' => (string) round((float) $value * 1.1, 2),
            'nilai_pemerolehan' => $value,
            'sumber_dana' => ['wakaf' => 'Dana Wakaf', 'baitulmal' => 'Dana Baitulmal'][$h['bahagian']] ?? 'Peruntukan Hartanah MAIS',
            'butiran_saluran' => [],
            'tempoh_sasaran_hari' => $channel->tempoh_sasaran_hari,
            'sumber_rekod' => 'sintetik',
            'rujukan_luaran' => Konteks::SUMBER.':'.$h['kunci'],
            'catatan' => 'Kes pemerolehan SINTETIK bagi hartanah awam "'.$h['nama'].'" (sisdata:mais-seed). Tarikh, nilai dan dokumen ialah contoh.',
            'klasifikasi' => 'sulit',
        ]);
        foreach ($stages as $s) {
            $case->peringkat()->create([
                'kod_peringkat' => $s->kod_peringkat, 'nama_peringkat' => $s->nama_peringkat, 'jenis' => $s->jenis,
                'susunan' => $s->susunan, 'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari,
            ]);
        }
        $case->forceFill(['butiran_saluran' => $this->butiran($code, $start, $value, $h)])->save();
        $k->tambah('pemerolehan');

        $case->load('peringkat');
        $clock = $start->copy()->setTime(9, 0);
        $stoppedAtApproval = false;
        foreach ($case->peringkat as $idx => $stage) {
            $stage->forceFill(['tarikh_masuk' => $clock->copy(), 'status_peringkat' => PeringkatPemerolehan::SEMASA, 'id_pegawai_bertanggungjawab' => $k->pegawai->id])->save();
            $case->forceFill(['peringkat_semasa' => $stage->kod_peringkat])->save();
            $this->dokumen($case, $stage->kod_peringkat, $clock, $r);
            if ($idx >= $target) {
                $stoppedAtApproval = $stage->jenis === 'kelulusan';
                break;
            }
            $days = max(1, (int) round($stage->tempoh_sasaran_hari * $r->int(40, 150) / 100));
            $clock = $clock->copy()->addDays($days);
            if ($clock->isFuture()) {
                $clock = now()->subDay();
            }
            $stage->forceFill(['tarikh_keluar' => $clock->copy(), 'status_peringkat' => PeringkatPemerolehan::SELESAI, 'tempoh_hari' => $days])->save();
            if ($stage->jenis === 'kelulusan') {
                Kelulusan::query()->create([
                    'id_pemerolehan' => $case->id, 'aras_kelulusan' => 'keputusan_akhir', 'tindakan' => 'lulus',
                    'tarikh_masa' => $clock->copy(), 'justifikasi' => 'Kelulusan SINTETIK (rekod sejarah, tiada instans WFE).',
                ]);
                $case->forceFill(['status_pemerolehan' => Pemerolehan::DILULUSKAN])->save();
            }
        }

        $title = null;
        $instance = null;
        if (! $inProgress) {
            $title = $this->tutup($case, $lot, $h, $clock, $r, $k);
        } elseif ($stoppedAtApproval) {
            try {
                $instance = $this->approval->start($case->refresh(), $k->pegawai);
                $k->tambah('wfe_per');
            } catch (Throwable $e) {
                $case->forceFill(['status_pemerolehan' => Pemerolehan::MENUNGGU_KELULUSAN])->save();
                $k->amaran($h['kunci'].': aliran per.kelulusan tidak dimulakan ('.$e->getMessage().')');
            }
        }
        $this->checklist->refresh($case);

        return ['kes' => $case->refresh(), 'hak_milik' => $title, 'instans' => $instance];
    }

    /** @param array<string, mixed> $h */
    private function kodSaluran(array $h, Rawak $r): string
    {
        return match ($h['bahagian']) {
            'wakaf' => 'wakaf',
            'baitulmal' => $r->weighted(['faraid' => 35, 'serahan_hakmilik' => 30, 'daftar_kematian' => 20, 'perintah_arb' => 15]),
            default => in_array($h['jenis'], ['masjid', 'surau', 'kubur', 'sekolah_agama'], true)
                ? $r->weighted(['pemberimilikan_5a' => 70, 'tanah_warta' => 30])
                : $r->weighted(['pembelian_terus' => 75, 'pemberimilikan_5a' => 25]),
        };
    }

    /** @param array<string, mixed> $h */
    private function tahun(array $h, Rawak $r): int
    {
        foreach ((array) ($h['fakta'] ?? []) as $f) {
            if (in_array($f['medan'], ['tahun_diperoleh', 'tahun_diwakafkan', 'tahun_dibina', 'tahun_siap', 'tahun_dibuka'], true) && preg_match('/(19|20)\d{2}/', (string) $f['nilai'], $m)) {
                return max(1950, min((int) now()->year - 1, (int) $m[0] - ($f['medan'] === 'tahun_diperoleh' || $f['medan'] === 'tahun_diwakafkan' ? 0 : $r->int(1, 3))));
            }
        }

        return $r->int(1975, 2023);
    }

    /** @param array<string, mixed> $h @return HakMilik|null */
    private function tutup(Pemerolehan $case, LotTanah $lot, array $h, Carbon $when, Rawak $r, Konteks $k): ?HakMilik
    {
        $code = $case->saluran->kod_saluran ?? '';
        if ($code === 'tanah_warta') {
            // Gazetted reserve: no title document — the case closes on the gazette record.
            $this->selesai($case, $when, null);

            return null;
        }
        $pt = str_starts_with((string) $lot->no_lot, 'PT');
        $type = $pt ? $r->weighted(['HSD' => 60, 'HSM' => 40])
            : ($h['bahagian'] === 'wakaf' ? $r->weighted(['GRN' => 55, 'GM' => 30, 'PN' => 15]) : $r->weighted(['GRN' => 40, 'PN' => 30, 'GM' => 15, 'PM' => 15]));
        $prefix = ['GRN' => 'GRN', 'PN' => 'PN', 'GM' => 'GM', 'PM' => 'PM', 'HSD' => 'HS(D)', 'HSM' => 'HS(M)'][$type];
        $lease = in_array($type, ['PN', 'PM'], true) ? $r->pick([60, 99, 99]) : null;
        $registered = $when->copy()->min(now()->subDay());
        $data = [
            'no_hak_milik' => $this->noHakMilik($prefix, $lot->kod_mukim, $r),
            'jenis_hak_milik' => $type,
            'kategori_tanah' => $lot->kategori_guna_tanah,
            'syarat_nyata' => $lot->syarat_nyata,
            'keluasan' => $lot->keluasan,
            'unit_keluasan' => $lot->unit_keluasan,
            'pemilik_berdaftar' => 'Majlis Agama Islam Selangor',
            'tarikh_pendaftaran' => $registered->toDateString(),
            'tarikh_tamat_tempoh' => $lease ? $registered->copy()->addYears($lease)->toDateString() : null,
            'tempoh_pajakan_tahun' => $lease,
            'pejabat_pendaftaran' => in_array($type, ['GRN', 'PN', 'HSD'], true) ? 'Pejabat Tanah dan Galian Selangor' : 'Pejabat Tanah dan Daerah',
            'no_perserahan' => sprintf('%d/%d', $r->int(100, 99999), (int) $registered->format('Y')),
            'tarikh_perserahan' => $registered->toDateString(),
        ];
        $vault = $this->petiBesi();
        $valid = $this->titles->validate(['lot_id' => $lot->id] + $data);
        $title = $this->titles->register($lot->id, $valid, $k->pegawai, [
            'peti_besi_id' => $vault->id, 'no_fail' => 'MAIS/HM/'.strtoupper(substr(md5($h['kunci']), 0, 6)), 'lokasi_rak' => 'R'.$r->int(1, 12).'-'.$r->int(1, 8),
            'jenis_dokumen_asal' => 'dokumen_hak_milik_keluaran', 'tarikh_masuk_simpanan' => $registered->copy()->addDays($r->int(3, 40))->min(today())->toDateString(),
            'catatan_lokasi' => 'Bilik kebal MAIS (lokasi SINTETIK)',
        ], [
            'sebab' => 'Pendaftaran hasil pemerolehan '.$case->no_kes.' (sisdata:mais-seed)', 'sumber' => 'pemerolehan', 'id_pemerolehan' => $case->id,
            'sumber_sistem' => Konteks::SUMBER, 'kunci_asal' => 'HM-'.$h['kunci'], 'disahkan' => $r->chance(70),
        ]);
        $k->tambah('hak_milik');
        $k->tambah('daftar_peti_besi');

        if ($h['bahagian'] === 'wakaf') {
            if ($r->chance(70)) {
                $this->vault->deposit($title, [
                    'peti_besi_id' => $vault->id, 'jenis_dokumen_asal' => 'surat_ikatan_wakaf', 'no_fail' => 'MAIS/WKF/'.strtoupper(substr(md5('w'.$h['kunci']), 0, 6)),
                    'lokasi_rak' => 'W'.$r->int(1, 6).'-'.$r->int(1, 8), 'catatan_lokasi' => 'Surat ikatan wakaf (lokasi SINTETIK)',
                ], $k->pegawai);
                $k->tambah('daftar_peti_besi');
            }
            WarisPemilikBersama::query()->create([
                'hak_milik_id' => $title->id, 'nama' => 'Pewakaf (nama tidak didedahkan — SINTETIK)', 'hubungan' => 'pewakaf',
                'sumber_pengesahan' => 'dokumen_wakaf', 'status_pengesahan' => 'belum_disahkan', 'sintetik' => true, 'kunci_asal' => 'PWK-'.$h['kunci'],
                'catatan' => 'Identiti pewakaf sebenar tidak disimpan (PDPA); rekod contoh sahaja.',
            ]);
            SekatanKepentingan::query()->create([
                'hak_milik_id' => $title->id, 'jenis_sekatan' => 'sekatan_kepentingan', 'tarikh_masuk' => $registered->toDateString(), 'status_sekatan' => 'aktif',
                'keterangan' => 'Tanah wakaf — tidak boleh dijual, dipindah milik atau digadai; MAIS pemegang amanah tunggal (contoh SINTETIK).',
            ]);
            $k->tambah('sekatan');
        }
        if ($r->chance(12)) {
            SekatanKepentingan::query()->create([
                'hak_milik_id' => $title->id, 'jenis_sekatan' => $r->pick(['kaveat_persendirian', 'kaveat_pendaftar', 'syarat_nyata_terhad']),
                'no_rujukan_sekatan' => 'KVT/'.$r->int(100, 9999).'/'.now()->subYears($r->int(0, 6))->format('Y'), 'pihak_terlibat' => 'Pihak ketiga (SINTETIK)',
                'tarikh_masuk' => now()->subDays($r->int(60, 2000))->toDateString(), 'status_sekatan' => $r->chance(70) ? 'aktif' : 'ditarik_balik',
                'keterangan' => 'Bebanan contoh SINTETIK untuk demonstrasi semakan bebanan.',
            ]);
            $k->tambah('sekatan');
        }
        // Originals issued / returned (SRS-HKM-06).
        if ($r->chance(25)) {
            $entry = DaftarPetiBesi::query()->where('hak_milik_id', $title->id)->where('jenis_dokumen_asal', 'dokumen_hak_milik_keluaran')->first();
            if ($entry !== null) {
                try {
                    $issue = $this->vault->issue($entry, [
                        'penerima_nama' => 'Pegawai Unit Hartanah (SINTETIK)', 'penerima_jawatan' => 'Penolong Pegawai Tanah',
                        'tujuan_pengeluaran' => $r->pick(['Carian rasmi di Pejabat Tanah', 'Urusan tukar syarat di PTD', 'Semakan pindaan keluasan', 'Rujukan peguam panel']),
                        'tarikh_jangka_pulang' => today()->addDays($r->int(3, 30))->toDateString(),
                        'status_semasa_dikeluarkan' => $r->pick(['dikeluarkan', 'dalam_proses_pt']),
                    ], $k->pegawai);
                    $k->tambah('pengeluaran_dokumen');
                    if ($r->chance(60)) {
                        $this->vault->return($issue, ['keadaan_dokumen_pulang' => $r->pick(['baik', 'baik', 'rosak_sedikit']), 'catatan_pulang' => 'Dipulangkan (SINTETIK)'], $k->admin);
                    }
                } catch (Throwable $e) {
                    $k->amaran($h['kunci'].': pengeluaran dokumen asal gagal ('.$e->getMessage().')');
                }
            }
        }

        $this->selesai($case, $when, $title->id, $data);

        return $title;
    }

    /** @param array<string, mixed>|null $title */
    private function selesai(Pemerolehan $case, Carbon $when, ?string $titleId, ?array $title = null): void
    {
        $current = $case->load('peringkat')->peringkatSemasa();
        if ($current !== null) {
            $current->forceFill(['tarikh_keluar' => $when, 'status_peringkat' => PeringkatPemerolehan::SELESAI, 'tempoh_hari' => $current->hariBerjalan()])->save();
        }
        $case->forceFill([
            'status_pemerolehan' => Pemerolehan::SELESAI, 'peringkat_semasa' => null, 'id_hak_milik_dijana' => $titleId,
            'butiran_hak_milik' => $title === null ? null : ['hak_milik' => array_filter($title), 'peti_besi' => ['kod_peti_besi' => self::PETI_BESI]],
            'tarikh_selesai' => $when->toDateString(), 'tarikh_pemerolehan' => $when->toDateString(),
            'tempoh_keseluruhan_hari' => (int) $case->tarikh_mula_kes->diffInDays($when->copy()->startOfDay()),
        ])->save();
    }

    private function dokumen(Pemerolehan $case, string $stageCode, Carbon $when, Rawak $r): void
    {
        $codes = $case->peringkat->pluck('kod_peringkat')->values()->all();
        $idx = array_search($stageCode, $codes, true);
        $next = $idx !== false && isset($codes[$idx + 1]) ? $codes[$idx + 1] : ChannelCatalog::TUTUP;
        foreach ($case->saluran->senaraiSemak as $item) {
            if ($item->dijana_sistem || $item->peringkat_diperlukan !== $next || $r->chance(8)) {
                continue;
            }
            DokumenPemerolehan::query()->create([
                'id_pemerolehan' => $case->id, 'id_semak' => $item->id, 'jenis_dokumen' => $item->jenis_dokumen,
                'rujukan_dms' => (string) Str::uuid(), 'tarikh_terima' => $when->toDateString(), 'status_dokumen' => 'diterima',
                'kod_peti_besi' => self::PETI_BESI, 'no_fail' => 'F-'.$r->int(100, 999), 'lokasi_rak' => 'R'.$r->int(1, 9),
                'status_dokumen_asal' => 'asal_dalam_simpanan', 'catatan' => 'Dokumen SINTETIK (tiada fail DMS).',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $h
     * @return array<string, mixed>
     */
    private function butiran(string $code, Carbon $start, string $value, array $h): array
    {
        $out = [];
        foreach (ChannelCatalog::fields($code) as $key => $f) {
            if (! empty($f['peribadi'])) {
                continue; // no personal fields — not even synthetic ones — for real properties
            }
            $out[$key] = match ($f['jenis']) {
                'tarikh' => $start->copy()->addDays(abs(crc32($key)) % 60)->toDateString(),
                'wang' => (string) round((float) $value * ((abs(crc32($key)) % 30) + 1) / 100, 2),
                'nombor' => (string) ((abs(crc32($h['kunci'].$key)) % 19500) + 500),
                'pilihan' => $f['mesti'] ?? ($key === 'jenis_wakaf' ? (in_array($h['jenis'], ['masjid', 'surau', 'kubur', 'sekolah_agama'], true) ? 'khas' : 'am') : array_key_first($f['pilihan'])),
                'teks_panjang' => $key === 'tujuan_wakaf_mawquf_alaih' ? 'Untuk '.str_replace('_', ' ', $h['jenis']).' — '.$h['nama'].' (contoh)' : 'Keterangan contoh SINTETIK.',
                default => strtoupper(substr($key, 0, 3)).'-'.((abs(crc32($h['kunci'].$key)) % 9000) + 1000).'/'.$start->format('Y'),
            };
        }

        return $out;
    }

    private function noHakMilik(string $prefix, string $mukim, Rawak $r): string
    {
        for ($i = 0; $i < 40; $i++) {
            $no = $prefix.' '.$r->int(in_array($prefix, ['GRN', 'HS(D)'], true) ? 250001 : 40001, in_array($prefix, ['GRN', 'HS(D)'], true) ? 399999 : 79999);
            if (! HakMilik::query()->withoutGlobalScope('access')->where('kod_mukim', $mukim)->whereRaw('upper(no_hak_milik) = ?', [mb_strtoupper($no)])->exists()) {
                return $no;
            }
        }

        return $prefix.' '.$r->int(400000, 499999);
    }

    private function petiBesi(): PetiBesi
    {
        return PetiBesi::query()->firstOrCreate(['kod_peti_besi' => self::PETI_BESI], [
            'nama' => 'Bilik Kebal Hak Milik MAIS (demo)', 'lokasi_bangunan' => 'Menara MAIS, Shah Alam (lokasi SINTETIK)',
            'tingkat' => 'B1', 'bilik' => 'Bilik Kebal 2', 'status_peti_besi' => 'aktif', 'catatan' => 'Dicipta oleh sisdata:mais-seed untuk data demo.',
        ]);
    }

    /** @return array<string, Saluran> */
    private function saluran(): array
    {
        return $this->saluran ??= Saluran::query()->with(['peringkat', 'senaraiSemak'])->get()->keyBy('kod_saluran')->all();
    }
}
