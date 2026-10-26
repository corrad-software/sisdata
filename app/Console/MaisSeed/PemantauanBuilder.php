<?php

namespace App\Console\MaisSeed;

use App\Models\User;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\ItemSenaraiSemak;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Services\LaporanService;
use App\Modules\Ptn\Services\PenemuanService;
use App\Modules\Ptn\Services\Penjadualan;
use App\Modules\Ptn\Services\TindakanService;
use App\Modules\Ptn\Support\Geometri;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * MOD-PTN like `ptn:sample`, on the MAIS lots: this year's risk-based
 * schedule (Penjadualan); most due schedule rows carried out + 1–2 visits last year, with
 * checklist, generated (clearly synthetic) photo thumbnails and findings.
 * Findings go through PenemuanService — encroachment / dumping opens a
 * MOD-ADU complaint through ComplaintIntake. Reports are generated and sent
 * to the real `ptn.pengesahan_laporan` WFE flow: most approved, a few
 * rejected, some left pending. Corrective actions, some overdue.
 */
class PemantauanBuilder
{
    private const RINGKASAN = [
        'baik' => ['Tapak dalam keadaan baik, tiada isu ketara.', 'Bangunan dan kawasan bersih; sempadan jelas.', 'Tiada aktiviti tanpa kebenaran dikesan.'],
        'isu' => ['Beberapa isu memerlukan tindakan susulan.', 'Semak samun dan sampah di bahagian belakang lot.', 'Tanda sempadan sebahagiannya hilang.'],
        'kritikal' => ['Pencerobohan aktif dikesan — struktur sementara dibina tanpa kebenaran.', 'Pembuangan sisa binaan secara besar-besaran di dalam lot.', 'Penghuni tanpa kebenaran menduduki sebahagian tapak.'],
    ];

    public function __construct(
        private Penjadualan $jadual,
        private Geometri $geo,
        private PenemuanService $penemuan,
        private LaporanService $laporan,
        private TindakanService $tindakan,
        private AliranHelper $aliran,
    ) {}

    public function jadualTahunan(Konteks $k): void
    {
        try {
            $r = $this->jadual->janaTahunan((int) now()->year, $k->admin, null, false, true);
            $k->tambah('jadual_pemantauan_dijana', $r['dijana']);
        } catch (Throwable $e) {
            $k->amaran('PTN: jadual tahunan tidak dijana ('.$e->getMessage().')');
        }
    }

    /** @param array<string, mixed> $h */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): void
    {
        $ceroboh = ! empty($h['_ceroboh']);
        // This year's schedule rows already due: most were carried out (the visit is linked), a few are overdue.
        $due = JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $lot->id)->where('tahun', (int) now()->year)
            ->where('status_pelaksanaan', 'dirancang')->where('tarikh_sasaran', '<=', now()->toDateString())->orderBy('tarikh_sasaran')->get();
        $visits = [];
        foreach ($due as $j) {
            if ($r->chance(85)) {
                $visits[] = [$j, Carbon::parse($j->tarikh_sasaran)->addDays($r->int(-5, 20))->min(now()->subDay())];
            }
        }
        // Last year's visits (not linked to this year's schedule) for comparison and trend.
        for ($i = 0, $n = $r->int(1, 2); $i < $n; $i++) {
            $visits[] = [null, now()->subYear()->subDays($r->int(0, 300))->startOfDay()];
        }
        usort($visits, fn ($a, $b) => $b[1] <=> $a[1]);
        foreach ($visits as $idx => [$j, $date]) {
            $officer = $r->pick($k->lapangan);
            if ($j !== null) {
                $j->forceFill(['id_pegawai_bertugas' => $officer->id, 'status_pelaksanaan' => 'ditugaskan'])->save();
            }
            // The most recent visit of an encroached lot is the critical one.
            $level = $ceroboh && $idx === 0 ? 'kritikal' : $r->weighted(['baik' => 68, 'isu' => 28, 'kritikal' => 4]);
            $this->lawatan($lot, $j, $officer, $date, $level, $r, $k);
        }
        // Upcoming rows: half already assigned to a field officer.
        JadualPemantauan::query()->withoutGlobalScope('access')->where('lot_id', $lot->id)->where('tahun', (int) now()->year)
            ->where('status_pelaksanaan', 'dirancang')->where('tarikh_sasaran', '>', now()->toDateString())->get()
            ->each(function (JadualPemantauan $j) use ($r, $k) {
                if ($r->chance(50)) {
                    $j->forceFill(['id_pegawai_bertugas' => $r->pick($k->lapangan)->id, 'status_pelaksanaan' => 'ditugaskan'])->save();
                }
            });
    }

    private function lawatan(LotTanah $lot, ?JadualPemantauan $j, User $officer, Carbon $date, string $level, Rawak $r, Konteks $k): void
    {
        $templat = $j?->id_templat ? TemplatSenaraiSemak::query()->find($j->id_templat) : $this->jadual->templatUntuk($lot->kategori_guna_tanah);
        $centre = $this->geo->pusat($lot->id);

        $v = new RekodLawatan;
        $v->forceFill([
            'no_rujukan' => 'PTN-'.$date->format('Y').'-'.str_pad((string) DB::selectOne("SELECT nextval('ptn_no_lawatan_seq') AS n")->n, 6, '0', STR_PAD_LEFT),
            'id_jadual' => $j?->id, 'lot_id' => $lot->id, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => $lot->klasifikasi,
            'id_templat' => $templat?->id, 'versi_templat' => $templat?->versi, 'tarikh_lawatan' => $date->toDateString(),
            'masa_mula' => $date->copy()->setTime($r->int(8, 15), $r->int(0, 59)), 'id_pegawai' => $officer->id,
            'keadaan_tapak' => match ($level) {
                'baik' => $r->chance(80) ? 'baik' : 'sederhana', 'isu' => $r->pick(['sederhana', 'buruk', 'terbiar']), default => 'kritikal',
            },
            'ringkasan_penemuan' => $r->pick(self::RINGKASAN[$level]).' (SINTETIK)',
            'cadangan_tindakan' => $level === 'baik' ? 'Teruskan pemantauan berkala.' : 'Tindakan pembetulan segera dan lawatan susulan dalam 30 hari.',
            'status_lawatan' => 'draf', 'id_luar_talian' => (string) Str::uuid(), 'sumber' => 'pwa', 'disegerak_pada' => $date->copy()->addHours($r->int(1, 48)),
            'data_tambahan' => $level !== 'baik' && $r->chance(50) ? ['penyelenggaraan' => [['jenis' => $r->pick(['pembersihan', 'pagar', 'papan_tanda', 'sempadan']), 'keterangan' => 'Keperluan penyelenggaraan dikenal pasti semasa lawatan (SINTETIK).', 'keutamaan' => 'sederhana']], 'cadangan' => []] : ['penyelenggaraan' => [], 'cadangan' => []],
            'sintetik' => true,
        ])->save();
        $k->tambah('rekod_lawatan');
        if ($centre !== null) {
            $jarak = $this->geo->jarak($lot->id, $centre['lat'], $centre['lng']);
            DB::update('UPDATE rekod_lawatan SET koordinat_lawatan = ST_SetSRID(ST_MakePoint(?, ?), 4326), jarak_dari_lot_m = ?, dalam_lingkungan = ?, ketepatan_gps_m = ? WHERE id = ?', [$centre['lng'], $centre['lat'], $jarak, $jarak !== null && $jarak <= 10, $r->int(3, 15), $v->id]);
        }

        $bad = 0;
        foreach ($templat->senarai_item ?? [] as $it) {
            $status = 'baik';
            $wantEncroach = $level === 'kritikal' && $bad === 0 && in_array($it['kategori_isu'] ?? '', ['pencerobohan', 'binaan_haram', 'pendudukan_haram'], true);
            if ($wantEncroach || ($level !== 'baik' && $r->chance($level === 'kritikal' ? 25 : 20))) {
                $status = $level === 'kritikal' && $bad === 0 ? 'kritikal' : 'isu';
                $bad++;
            }
            ItemSenaraiSemak::query()->create([
                'id_lawatan' => $v->id, 'id_templat_senarai_semak' => $templat?->id, 'kod_item' => $it['kod'], 'kategori_item' => $it['kategori'], 'label' => $it['label'],
                'status_item' => $status, 'catatan' => $status === 'baik' ? null : 'Diperhatikan semasa lawatan (SINTETIK).',
                'kategori_isu' => $status === 'baik' ? null : ($it['kategori_isu'] ?? 'lain'), 'tahap_keterukan' => $status === 'baik' ? null : ($status === 'kritikal' ? 'kritikal' : $r->pick(['sederhana', 'tinggi'])),
            ]);
        }
        for ($i = 0, $n = $r->int(2, 4); $i < $n; $i++) {
            $g = new GambarLawatan;
            $lat = $centre ? $centre['lat'] + $r->int(-3, 3) / 100000 : null;
            $lng = $centre ? $centre['lng'] + $r->int(-3, 3) / 100000 : null;
            $jarak = $lat !== null && $lng !== null ? $this->geo->jarak($lot->id, $lat, $lng) : null;
            $in = $jarak !== null && $jarak <= 10;
            $g->forceFill([
                'id_lawatan' => $v->id, 'tarikh_masa' => $v->masa_mula?->copy()->addMinutes(5 + $i * 7), 'arah_kamera' => $r->int(0, 359), 'ketepatan_m' => $r->int(3, 12),
                'sumber_gambar' => 'kamera', 'status_pengesahan_lingkungan' => $lat === null ? 'tiada_koordinat' : ($in ? 'dalam' : 'luar'),
                'jarak_dari_lot_m' => $jarak, 'perlu_semakan' => ! $in, 'tag_rekod_tanah' => $lot->id, 'tag_lapisan_gis' => 'ptn.pemantauan',
                'jenis' => 'umum', 'kapsyen' => 'Gambar tapak '.($i + 1).' (SINTETIK)', 'nama_fail' => 'sintetik-'.($i + 1).'.jpg',
                'imej_kecil' => self::imej($level, $i), 'id_luar_talian' => (string) Str::uuid(),
            ])->save();
            if ($lat !== null) {
                DB::update('UPDATE gambar_lawatan SET koordinat_gambar = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?', [$lng, $lat, $g->id]);
            }
        }

        $v->forceFill(['status_lawatan' => 'dihantar', 'status_pengesahan' => 'menunggu', 'masa_tamat' => $v->masa_mula?->copy()->addMinutes(45)])->save();
        $this->penemuan->proses($v, $officer);
        $issues = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->get();
        DB::table('isu_pemantauan')->where('id_lawatan', $v->id)->update(['sintetik' => true]);
        $k->tambah('isu_pemantauan', $issues->count());
        $k->tambah('aduan_dari_ptn', $issues->pluck('id_aduan_berkaitan')->filter()->unique()->count());

        $report = $this->laporan->jana($v, $officer);
        $k->tambah('laporan_lawatan');
        $report = LaporanLawatan::query()->withoutGlobalScope('access')->find($report->id);
        if ($report !== null && $report->id_aliran !== null) {
            $outcome = $r->weighted(['lulus' => 72, 'tolak' => 8, 'tunggu' => 20]);
            if ($outcome !== 'tunggu') {
                $this->aliran->putus((string) $report->id_aliran, $outcome, array_values(array_filter($k->pelulus, fn (User $u) => $u->id !== $officer->id)), 'Laporan lawatan disemak (SINTETIK)');
            }
            $k->tambah('wfe_ptn');
        }

        foreach ($issues as $isu) {
            if (! $r->chance(70)) {
                continue;
            }
            $target = $date->copy()->addDays($r->int(14, 90));
            try {
                $t = $this->tindakan->cipta($isu, ['langkah_tindakan' => $r->pick(['Keluarkan notis kepada penceroboh', 'Pembersihan tapak oleh kontraktor', 'Pasang semula tanda sempadan', 'Rujuk kepada Bahagian Penguatkuasaan']), 'id_pegawai_bertanggungjawab' => $officer->id, 'tarikh_sasaran' => $target->toDateString()], $officer);
            } catch (Throwable) {
                continue;
            }
            $k->tambah('tindakan_pembetulan');
            if ($target->isPast() && $r->chance(55)) {
                $t->forceFill(['status_kemajuan' => 'menunggu_pengesahan', 'peratus_kemajuan' => 100, 'tarikh_siap' => $target->copy()->subDays($r->int(0, 10))->toDateString()])->save();
                $this->tindakan->keputusan($t, 'lulus', $officer->id, 'Disahkan (SINTETIK).');
            } elseif ($r->chance(50)) {
                $t->forceFill(['status_kemajuan' => 'dalam_tindakan', 'peratus_kemajuan' => $r->int(10, 80)])->save();
            }
            TindakanPembetulan::query()->withoutGlobalScope('access')->whereKey($t->id)->update(['sintetik' => true]);
        }
    }

    /** Tiny generated JPEG thumbnail — clearly synthetic, never a real photo. */
    private static function imej(string $level, int $i): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $im = imagecreatetruecolor(240, 160);
        [$red, $green, $blue] = ['baik' => [134, 239, 172], 'isu' => [253, 224, 71], 'kritikal' => [252, 165, 165]][$level];
        imagefill($im, 0, 0, (int) imagecolorallocate($im, $red, $green, $blue));
        $dark = (int) imagecolorallocate($im, 39, 39, 42);
        imagefilledrectangle($im, 0, 110 - $i * 8, 240, 160, (int) imagecolorallocate($im, 120, 113, 108));
        imagestring($im, 5, 12, 12, 'GAMBAR SINTETIK', $dark);
        imagestring($im, 3, 12, 34, 'Data contoh MAIS #'.($i + 1), $dark);
        ob_start();
        imagejpeg($im, null, 60);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
