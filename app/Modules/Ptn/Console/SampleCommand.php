<?php

namespace App\Modules\Ptn\Console;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\ItemSenaraiSemak;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Services\LaporanService;
use App\Modules\Ptn\Services\PenemuanService;
use App\Modules\Ptn\Services\Penjadualan;
use App\Modules\Ptn\Services\RisikoService;
use App\Modules\Ptn\Services\TindakanService;
use App\Modules\Ptn\Support\Geometri;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `php artisan ptn:sample --count=200` — SYNTHETIC monitoring data for the
 * dev DB on the existing (synthetic) lots: this year's risk-based schedule,
 * `count` completed visits over the last 10 months (checklists, synthetic
 * geotagged photos — some outside the lot boundary, a few sync conflicts),
 * findings (encroachment / dumping ones open complaints in MOD-ADU through
 * ComplaintIntake), corrective actions (some overdue, some verified),
 * maintenance needs, validated + locked reports, and six months of risk
 * history for the trend. Everything is flagged `sintetik`; notifications are
 * suppressed and photos/PDFs are not pushed to object storage.
 */
class SampleCommand extends Command
{
    protected $signature = 'ptn:sample {--count=200 : Bilangan lawatan sintetik} {--seed= : Benih rawak (boleh diulang)}';

    protected $description = 'Jana data pemantauan tanah SINTETIK (jadual, lawatan, penemuan, tindakan) untuk DB pembangunan (MOD-PTN)';

    private const RINGKASAN = [
        'baik' => ['Tapak dalam keadaan baik, tiada isu ketara.', 'Tanah bersih dan sempadan jelas.', 'Tiada aktiviti tanpa kebenaran dikesan.'],
        'isu' => ['Terdapat beberapa isu yang memerlukan tindakan susulan.', 'Semak samun tinggi dan sampah di bahagian belakang lot.', 'Tanda sempadan sebahagiannya hilang.'],
        'kritikal' => ['Pencerobohan aktif dikesan — struktur kekal sedang dibina.', 'Pembuangan sisa binaan secara besar-besaran.', 'Penghuni tanpa kebenaran menduduki tapak.'],
    ];

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });
        // Synthetic PDFs are not pushed to object storage (the locked snapshot re-renders on demand).
        app()->instance(DocumentStore::class, new class implements DocumentStore
        {
            public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
            {
                throw new DocumentRejected('Data sintetik — tidak disimpan dalam DMS.', 'sintetik');
            }

            public function forOwner(string $ownerType, string|int $ownerId, User $user): array
            {
                return [];
            }

            public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
            {
                return null;
            }
        });

        if (! DB::table('lot_tanah')->whereNull('deleted_at')->exists()) {
            $this->error('Tiada lot dalam lot_tanah — import lot MOD-PRO dahulu.');

            return self::FAILURE;
        }
        $officers = User::query()->where('is_active', true)->where('email', 'not like', '%@sistem.sisdata.local')->orderBy('id')->limit(8)->get();
        if ($officers->isEmpty()) {
            $this->error('Tiada pengguna aktif untuk dijadikan pegawai lapangan.');

            return self::FAILURE;
        }
        $risiko = app(RisikoService::class);
        $jadual = app(Penjadualan::class);
        $geo = app(Geometri::class);
        $penemuan = app(PenemuanService::class);
        $laporan = app(LaporanService::class);
        $tindakan = app(TindakanService::class);

        $this->components->task('Kira risiko awal semua lot', fn () => $risiko->kira());
        $year = now()->year;
        $r = $jadual->janaTahunan($year, null, null, false, true);
        $this->components->info("Jadual {$year}: {$r['dijana']} dijana bagi {$r['lot']} lot.");

        $due = JadualPemantauan::query()->withoutGlobalScope('access')->where('tahun', $year)->where('status_pelaksanaan', 'dirancang')
            ->where('tarikh_sasaran', '<=', now()->toDateString())->inRandomOrder()->limit($count)->get();
        // Older visits (previous year) for comparison and "days between visits".
        $extra = max(0, $count - $due->count());
        $bar = $this->output->createProgressBar($due->count() + $extra);
        $made = ['lawatan' => 0, 'isu' => 0, 'aduan' => 0, 'tindakan' => 0, 'konflik' => 0, 'luar' => 0];

        foreach ($due as $j) {
            $officer = $officers[mt_rand(0, $officers->count() - 1)];
            $j->forceFill(['id_pegawai_bertugas' => $officer->id, 'status_pelaksanaan' => 'ditugaskan'])->save();
            $date = Carbon::parse($j->tarikh_sasaran)->addDays(mt_rand(-5, 25));
            if ($date->isFuture()) {
                $date = now()->subDays(mt_rand(0, 3));
            }
            $this->lawatan($j->lot_id, $j, $officer, $date, $geo, $penemuan, $laporan, $tindakan, $made);
            $bar->advance();
        }
        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->inRandomOrder()->limit($extra)->pluck('id');
        foreach ($lots as $lotId) {
            $officer = $officers[mt_rand(0, $officers->count() - 1)];
            $this->lawatan((string) $lotId, null, $officer, now()->subYear()->subDays(mt_rand(0, 120)), $geo, $penemuan, $laporan, $tindakan, $made);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Open schedules: assign half of the upcoming ones so the field app has tasks.
        JadualPemantauan::query()->withoutGlobalScope('access')->where('tahun', $year)->where('status_pelaksanaan', 'dirancang')
            ->where('tarikh_sasaran', '<=', now()->addDays(60)->toDateString())->inRandomOrder()->limit(80)->get()
            ->each(fn (JadualPemantauan $j) => $j->forceFill(['id_pegawai_bertugas' => $officers[mt_rand(0, $officers->count() - 1)]->id, 'status_pelaksanaan' => 'ditugaskan'])->save());

        $risiko->kira();
        $this->sejarahRisiko();
        $tindakan->semakTertunggak();

        $this->table(['Lawatan', 'Isu', 'Aduan dibuka', 'Tindakan', 'Konflik', 'Gambar luar sempadan'], [array_values($made)]);
        $this->components->info('Data SINTETIK MOD-PTN dijana.');

        return self::SUCCESS;
    }

    /** @param array<string, int> $made */
    private function lawatan(string $lotId, ?JadualPemantauan $j, User $officer, Carbon $date, Geometri $geo, PenemuanService $penemuan, LaporanService $laporan, TindakanService $tindakan, array &$made): void
    {
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        if ($lot === null) {
            return;
        }
        $templat = $j?->id_templat ? TemplatSenaraiSemak::query()->find($j->id_templat) : app(Penjadualan::class)->templatUntuk($lot->kategori_guna_tanah);
        $severity = mt_rand(1, 100);
        $level = $severity <= 65 ? 'baik' : ($severity <= 92 ? 'isu' : 'kritikal');
        $konflik = $j !== null && mt_rand(1, 100) <= 3;
        $centre = $geo->pusat($lotId);
        $outside = mt_rand(1, 100) <= 10;

        $v = new RekodLawatan;
        $v->forceFill([
            'no_rujukan' => 'PTN-'.$date->format('Y').'-'.str_pad((string) DB::selectOne("SELECT nextval('ptn_no_lawatan_seq') AS n")->n, 6, '0', STR_PAD_LEFT),
            'id_jadual' => $j?->id, 'lot_id' => $lotId, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => $lot->klasifikasi ?? 'dalaman',
            'id_templat' => $templat?->id, 'versi_templat' => $templat?->versi, 'tarikh_lawatan' => $date->toDateString(),
            'masa_mula' => $date->copy()->setTime(mt_rand(8, 15), mt_rand(0, 59)), 'id_pegawai' => $officer->id,
            'keadaan_tapak' => match ($level) {
                'baik' => mt_rand(0, 4) ? 'baik' : 'sederhana', 'isu' => ['sederhana', 'buruk', 'terbiar'][mt_rand(0, 2)], default => 'kritikal',
            },
            'ringkasan_penemuan' => self::RINGKASAN[$level][mt_rand(0, 2)],
            'cadangan_tindakan' => $level === 'baik' ? 'Teruskan pemantauan berkala.' : 'Tindakan pembetulan segera dan lawatan susulan dalam 30 hari.',
            'status_lawatan' => $konflik ? 'konflik' : 'draf', 'sebab_konflik' => $konflik ? (string) __('ptn::ptn.konflik.diagih_semula') : null,
            'id_luar_talian' => (string) Str::uuid(), 'sumber' => 'pwa', 'disegerak_pada' => $date->copy()->addHours(mt_rand(1, 48)),
            'data_tambahan' => $level !== 'baik' && mt_rand(0, 1) ? ['penyelenggaraan' => [['jenis' => ['pembersihan', 'pagar', 'papan_tanda', 'sempadan'][mt_rand(0, 3)], 'keterangan' => 'Keperluan penyelenggaraan dikenal pasti semasa lawatan (sintetik).', 'keutamaan' => 'sederhana']], 'cadangan' => []] : ['penyelenggaraan' => [], 'cadangan' => []],
            'sintetik' => true,
        ])->save();
        $made['lawatan']++;
        if ($centre) {
            $lat = $centre['lat'] + ($outside ? 0.01 : 0);
            $lng = $centre['lng'];
            $jarak = $geo->jarak($lotId, $lat, $lng);
            DB::update('UPDATE rekod_lawatan SET koordinat_lawatan = ST_SetSRID(ST_MakePoint(?, ?), 4326), jarak_dari_lot_m = ?, dalam_lingkungan = ?, ketepatan_gps_m = ? WHERE id = ?', [$lng, $lat, $jarak, $jarak !== null && $jarak <= 10, mt_rand(3, 15), $v->id]);
        }

        $bad = 0;
        foreach ($templat->senarai_item ?? [] as $it) {
            $status = 'baik';
            if ($level !== 'baik' && mt_rand(1, 100) <= ($level === 'kritikal' ? 30 : 20)) {
                $status = $level === 'kritikal' && $bad === 0 ? 'kritikal' : 'isu';
                $bad++;
            }
            ItemSenaraiSemak::query()->create([
                'id_lawatan' => $v->id, 'id_templat_senarai_semak' => $templat?->id, 'kod_item' => $it['kod'], 'kategori_item' => $it['kategori'], 'label' => $it['label'],
                'status_item' => $status, 'catatan' => $status === 'baik' ? null : 'Diperhatikan semasa lawatan (sintetik).',
                'kategori_isu' => $status === 'baik' ? null : ($it['kategori_isu'] ?? 'lain'), 'tahap_keterukan' => $status === 'baik' ? null : ($status === 'kritikal' ? 'kritikal' : ['sederhana', 'tinggi'][mt_rand(0, 1)]),
            ]);
        }
        $photos = mt_rand(2, 4);
        for ($i = 0; $i < $photos; $i++) {
            $g = new GambarLawatan;
            $isOut = $outside && $i === 0;
            $lat = $centre ? $centre['lat'] + ($isOut ? 0.01 : (mt_rand(-3, 3) / 100000)) : null;
            $lng = $centre ? $centre['lng'] + ($isOut ? 0 : (mt_rand(-3, 3) / 100000)) : null;
            $jarak = $lat !== null ? $geo->jarak($lotId, $lat, $lng) : null;
            $in = $jarak !== null && $jarak <= 10;
            $g->forceFill([
                'id_lawatan' => $v->id, 'tarikh_masa' => $v->masa_mula?->copy()->addMinutes(5 + $i * 7), 'arah_kamera' => mt_rand(0, 359), 'ketepatan_m' => mt_rand(3, 12),
                'sumber_gambar' => mt_rand(1, 10) === 1 ? 'galeri' : 'kamera', 'status_pengesahan_lingkungan' => $lat === null ? 'tiada_koordinat' : ($in ? 'dalam' : 'luar'),
                'jarak_dari_lot_m' => $jarak, 'perlu_semakan' => ! $in, 'tag_rekod_tanah' => $lotId, 'tag_lapisan_gis' => 'ptn.pemantauan',
                'jenis' => 'umum', 'kapsyen' => 'Gambar tapak '.($i + 1).' (sintetik)', 'nama_fail' => 'sintetik-'.($i + 1).'.jpg',
                'imej_kecil' => $this->imej($level, $i), 'id_luar_talian' => (string) Str::uuid(),
            ])->save();
            if ($lat !== null) {
                DB::update('UPDATE gambar_lawatan SET koordinat_gambar = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?', [$lng, $lat, $g->id]);
            }
            $made['luar'] += $in ? 0 : 1;
        }
        if ($konflik) {
            $made['konflik']++;

            return;
        }

        $v->forceFill(['status_lawatan' => 'dihantar', 'status_pengesahan' => 'menunggu', 'masa_tamat' => $v->masa_mula?->copy()->addMinutes(45)])->save();
        $penemuan->proses($v, $officer);
        $issues = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->get();
        $made['isu'] += $issues->count();
        $made['aduan'] += $issues->pluck('id_aduan_berkaitan')->filter()->unique()->count();
        DB::table('isu_pemantauan')->where('id_lawatan', $v->id)->update(['sintetik' => true]);

        $r = $laporan->jana($v, $officer);
        if (mt_rand(1, 100) <= 75) {
            $approver = User::query()->whereKeyNot($officer->id)->where('is_active', true)->inRandomOrder()->first() ?? $officer;
            $laporan->keputusan($r, 'lulus', $approver->id, 'Disahkan (sintetik).');
        }

        foreach ($issues as $isu) {
            if (mt_rand(1, 100) > 70) {
                continue;
            }
            $target = $date->copy()->addDays(mt_rand(14, 90));
            $t = $tindakan->cipta($isu, ['langkah_tindakan' => ['Keluarkan notis kepada penceroboh', 'Pembersihan tapak oleh kontraktor', 'Pasang semula tanda sempadan', 'Rujuk kepada Bahagian Penguatkuasaan'][mt_rand(0, 3)], 'id_pegawai_bertanggungjawab' => $officer->id, 'tarikh_sasaran' => $target->toDateString()], $officer);
            $made['tindakan']++;
            if ($target->isPast() && mt_rand(1, 100) <= 60) {
                $t->forceFill(['status_kemajuan' => 'menunggu_pengesahan', 'peratus_kemajuan' => 100, 'tarikh_siap' => $target->copy()->subDays(mt_rand(0, 10))->toDateString()])->save();
                $tindakan->keputusan($t, 'lulus', $officer->id, 'Disahkan (sintetik).');
            } elseif (mt_rand(0, 1)) {
                $t->forceFill(['status_kemajuan' => 'dalam_tindakan', 'peratus_kemajuan' => mt_rand(10, 80)])->save();
            }
            TindakanPembetulan::query()->withoutGlobalScope('access')->whereKey($t->id)->update(['sintetik' => true]);
        }
    }

    /** Six monthly synthetic snapshots behind the current rating, for the risk trend. */
    private function sejarahRisiko(): void
    {
        $current = DB::table('risk_rating_lot')->where('semasa', true)->get();
        $rows = [];
        foreach ($current as $r) {
            for ($m = 6; $m >= 1; $m--) {
                $s = max(0, min(100, (float) $r->skor_keseluruhan + mt_rand(-15, 10)));
                $rows[] = [
                    'lot_id' => $r->lot_id, 'kod_daerah' => $r->kod_daerah, 'kod_mukim' => $r->kod_mukim, 'klasifikasi' => $r->klasifikasi,
                    'tarikh_kiraan' => now()->subMonths($m)->endOfMonth()->subDay(), 'skor_pencerobohan' => $r->skor_pencerobohan, 'skor_tunggakan_cukai' => $r->skor_tunggakan_cukai,
                    'skor_pertikaian' => $r->skor_pertikaian, 'skor_dokumen' => $r->skor_dokumen, 'skor_tamat_kontrak' => $r->skor_tamat_kontrak, 'skor_potensi_hasil' => $r->skor_potensi_hasil,
                    'skor_keseluruhan' => $s, 'kod_warna' => $s >= 60 ? 'merah' : ($s >= 30 ? 'kuning' : 'hijau'), 'paksa_merah' => false,
                    'butiran' => json_encode(['sintetik' => true]), 'semasa' => false, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if (count($rows) >= 1200) {
                DB::table('risk_rating_lot')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('risk_rating_lot')->insert($rows);
        }
    }

    /** Tiny generated JPEG thumbnail (coloured by condition) — synthetic, never a real photo. */
    private function imej(string $level, int $i): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $im = imagecreatetruecolor(240, 160);
        [$r, $g, $b] = ['baik' => [134, 239, 172], 'isu' => [253, 224, 71], 'kritikal' => [252, 165, 165]][$level];
        imagefill($im, 0, 0, (int) imagecolorallocate($im, $r, $g, $b));
        $dark = (int) imagecolorallocate($im, 39, 39, 42);
        imagefilledrectangle($im, 0, 110 - $i * 8, 240, 160, (int) imagecolorallocate($im, 120, 113, 108));
        imagestring($im, 5, 12, 12, 'GAMBAR SINTETIK', $dark);
        imagestring($im, 3, 12, 34, 'Keadaan: '.(Lalai::STATUS_ITEM[$level] ?? $level).' #'.($i + 1), $dark);
        ob_start();
        imagejpeg($im, null, 60);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
