<?php

namespace App\Modules\Adu\Console;

use App\Models\User;
use App\Modules\Adu\Contracts\ReporterMessenger;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\ResolusiAduan;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Services\Kronologi;
use App\Modules\Adu\Services\SlaAduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\LotSpatial;
use App\Modules\Ntf\Contracts\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan adu:sample --count=150` — SYNTHETIC complaints for the dev DB:
 * spread over the last 12 months, all districts with lots, every category,
 * channel and status, linked to existing (synthetic) lots; ~20% anonymous,
 * ~8% outside any MAIS lot. Reporters are generated names/contacts and every
 * row is flagged `sintetik`. Goes through the real intake (triage, SLA,
 * timeline) with the clock moved back; notifications are suppressed.
 */
class SampleCommand extends Command
{
    protected $signature = 'adu:sample {--count=150 : Bilangan aduan sintetik} {--seed= : Benih rawak (boleh diulang)}';

    protected $description = 'Jana aduan SINTETIK merentas daerah/kategori/status untuk DB pembangunan (MOD-ADU)';

    private const NAMA = ['Ahmad', 'Siti', 'Muhammad', 'Nur', 'Aisyah', 'Hafiz', 'Farah', 'Azman', 'Zulkifli', 'Rosnah', 'Kamal', 'Hidayah', 'Faizal', 'Salmah', 'Iskandar', 'Liyana'];

    private const BAPA = ['Abdullah', 'Ismail', 'Hassan', 'Osman', 'Yusof', 'Ibrahim', 'Rahman', 'Salleh', 'Hamid', 'Ali'];

    private const TEKS = [
        'PENCEROBOHAN' => ['Terdapat individu menduduki tanah ini tanpa kebenaran dan membina pondok.', 'Tanah wakaf diceroboh untuk bercucuk tanam tanpa izin.', 'Penghuni haram dikesan di kawasan lot berhampiran surau.'],
        'BINAAN_HARAM' => ['Struktur gerai dibina tanpa kebenaran di tepi jalan.', 'Rumah separuh kekal sedang dibina di atas tanah ini.', 'Pagar baru didirikan menutup laluan awam.'],
        'PEMBUANGAN_SAMPAH' => ['Longgokan sampah dan sisa binaan dibuang di kawasan lapang.', 'Sampah domestik dibuang setiap malam di hujung lot.'],
        'KEROSAKAN_ASET' => ['Bangunan dewan rosak teruk, bumbung bocor.', 'Kecurian kabel dan besi pagar di tapak ini.', 'Pintu pagar pecah akibat vandalisme.'],
        'PERTIKAIAN_SEMPADAN' => ['Jiran menuntut sebahagian tanah, batu sempadan telah dialihkan.', 'Pertikaian sempadan dengan pemilik lot bersebelahan.'],
        'ISU_PENYEWA' => ['Penyewa kedai tidak menyelenggara premis dan menyewakan semula.', 'Pemajak menggunakan tanah bukan untuk tujuan asal.'],
        'TUNGGAKAN' => ['Penyewa tidak membayar sewa beberapa bulan.'],
        'PENCEMARAN' => ['Tumpahan minyak dan bau busuk dari bengkel di atas tanah ini.', 'Asap pembakaran terbuka setiap petang.'],
        'KESELAMATAN' => ['Kawasan semak menjadi tempat penagih dadah berkumpul.', 'Bangunan usang berbahaya, risiko runtuh kepada orang awam.'],
        'LAIN' => ['Pokok tumbang menghalang laluan ke tanah perkuburan.', 'Pertanyaan mengenai status tanah ini.'],
    ];

    public function handle(LotSpatial $spatial): int
    {
        $count = max(1, (int) $this->option('count'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }

        // Suppress notifications/e-mails for generated data.
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });
        app()->instance(ReporterMessenger::class, new class implements ReporterMessenger
        {
            public function email(string $to, string $subject, string $body): void {}

            public function sms(string $to, string $text): void {}
        });
        $service = app(AduanService::class);
        $sla = app(SlaAduan::class);
        $kronologi = app(Kronologi::class);

        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->inRandomOrder()->limit(400)->get(['id', 'kod_daerah']);
        if ($lots->isEmpty()) {
            $this->error('Tiada lot dalam lot_tanah — jalankan `php artisan ref:sample-lots` / import MOD-PRO dahulu.');

            return self::FAILURE;
        }
        $officers = User::query()->where('is_active', true)->where('email', 'not like', '%@sistem.sisdata.local')->orderBy('id')->limit(8)->get();
        $categories = array_keys(Lalai::SLA);
        $channels = ['dalaman', 'portal', 'portal', 'kaunter', 'emel', 'telefon', 'lapangan', 'sistem_aduan_pusat'];
        $made = 0;

        $bar = $this->output->createProgressBar($count);
        for ($i = 0; $i < $count; $i++) {
            $realNow = CarbonImmutable::now();
            $received = $realNow->subDays(mt_rand(0, 365))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $age = (int) $received->diffInDays($realNow);
            Carbon::setTestNow($received);
            $kategori = $categories[array_rand($categories)];
            $lot = $lots[mt_rand(0, $lots->count() - 1)];
            $outside = mt_rand(1, 100) <= 8;
            $point = $outside ? ['lat' => 2.75 + mt_rand(0, 9000) / 10000, 'lng' => 101.2 + mt_rand(0, 8000) / 10000] : $spatial->pusatLot((string) $lot->id);
            $anon = mt_rand(1, 100) <= 20;
            $name = self::NAMA[array_rand(self::NAMA)].' bin '.self::BAPA[array_rand(self::BAPA)];
            $saluran = $channels[array_rand($channels)];

            $aduan = $service->terima([
                'kategori' => $kategori,
                'keterangan' => '[SINTETIK] '.self::TEKS[$kategori][array_rand(self::TEKS[$kategori])],
                'lot_id' => $outside || $point === null ? null : (string) $lot->id,
                'lat' => $point['lat'] ?? null,
                'lng' => $point['lng'] ?? null,
                'kod_daerah' => $outside ? null : (string) $lot->kod_daerah,
                'tahap_keutamaan' => ['rendah', 'sederhana', 'sederhana', 'tinggi', 'kritikal'][mt_rand(0, 4)],
                'lokasi_teks' => $outside ? 'Lokasi dipilih pada peta (sintetik)' : null,
                'sintetik' => true,
            ], $saluran, $saluran === 'portal' || $saluran === 'sistem_aduan_pusat' ? null : $officers->first(), $saluran === 'sistem_aduan_pusat' ? 'SAP-SINTETIK-'.$received->format('YmdHis').'-'.$i : null,
                $anon ? null : ['nama' => $name, 'no_telefon' => '01'.mt_rand(2, 9).'-'.mt_rand(1000000, 9999999), 'emel' => 'pengadu'.($i + 1).'@contoh.sintetik.test', 'alamat' => 'No. '.mt_rand(1, 99).', Jalan Contoh '.mt_rand(1, 20).' (sintetik)'], $anon);

            $this->progress($aduan, $officers->all(), $received, $age, $sla, $kronologi);
            Carbon::setTestNow();
            $made++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("{$made} aduan sintetik dijana.");

        return self::SUCCESS;
    }

    /** @param list<User> $officers */
    private function progress(Aduan $aduan, array $officers, CarbonImmutable $received, int $age, SlaAduan $sla, Kronologi $kronologi): void
    {
        $roll = mt_rand(1, 100);
        $target = $roll <= 15 ? 'baharu' : ($roll <= 35 ? 'ditugaskan' : ($roll <= 55 ? 'dalam_siasatan' : ($roll <= 63 ? 'dirujuk' : ($roll <= 97 ? 'ditutup' : 'pendua'))));
        if ($age < 3 && $target === 'ditutup') {
            $target = 'dalam_siasatan';
        }
        if ($target === 'baharu' || $officers === [] || $aduan->status_bukan_tanah_mais && $target !== 'ditutup') {
            return;
        }
        $officer = $officers[array_rand($officers)];
        $step = fn (int $days) => Carbon::setTestNow($received->addDays(min($days, max(0, $age))));

        $step(1);
        if ($aduan->id_pegawai === null) {
            $aduan->update(['id_pegawai' => $officer->id, 'status' => 'ditugaskan']);
            $kronologi->rekod($aduan, 'tugas', 'Ditugaskan kepada '.$officer->name.' (sintetik)', $officer, 'baharu', 'ditugaskan');
        }
        if ($target === 'ditugaskan') {
            return;
        }
        $step(2);
        $aduan->update(['status' => 'dalam_siasatan']);
        $sla->respons($aduan);
        $kronologi->rekod($aduan, 'mula_siasatan', null, $officer, 'ditugaskan', 'dalam_siasatan');
        $step(mt_rand(3, 10));
        $kronologi->rekod($aduan, 'tindakan', 'Lawatan tapak dan pengesahan maklumat (sintetik).', $officer);
        if ($target === 'dalam_siasatan') {
            return;
        }
        if ($target === 'dirujuk') {
            $jenis = Lalai::BAHAGIAN_KATEGORI[$aduan->kategori] ?? 'penguatkuasaan';
            $aduan->update(['status' => 'dirujuk', 'bahagian_dirujuk' => $jenis]);
            $kronologi->rekod($aduan, 'rujuk', 'Dirujuk kepada '.Lalai::JENIS_RUJUKAN[$jenis].' (sintetik)', $officer, 'dalam_siasatan', 'dirujuk');

            return;
        }
        if ($target === 'pendua') {
            $aduan->update(['status' => 'pendua', 'tarikh_tutup' => now()]);
            $sla->selesai($aduan);
            $kronologi->rekod($aduan, 'pendua', 'Ditanda pendua (sintetik)', $officer, 'dalam_siasatan', 'pendua');

            return;
        }
        $step(mt_rand(5, 60));
        $kat = $aduan->status_bukan_tanah_mais ? 'bukan_tanah_mais' : ['selesai_tindakan', 'selesai_penguatkuasaan', 'tiada_asas', 'dirujuk_agensi'][mt_rand(0, 3)];
        $approver = collect($officers)->first(fn (User $u) => $u->id !== $officer->id) ?? $officer;
        ResolusiAduan::query()->create([
            'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan, 'kategori_resolusi' => $kat,
            'keterangan_resolusi' => 'Isu diselesaikan (sintetik).', 'justifikasi' => 'Disahkan melalui lawatan tapak (sintetik).',
            'id_pegawai_penyelesai' => $officer->id, 'id_penyelia_pelulus' => $approver->id, 'status' => 'lulus', 'tarikh_tutup' => now(),
            'status_makluman_pengadu' => $aduan->status_tanpa_nama ? 'tidak_berkenaan' : 'dihantar',
        ]);
        $aduan->update(['status' => 'ditutup', 'tarikh_tutup' => now(), 'id_pegawai' => $aduan->id_pegawai ?? $officer->id]);
        $sla->selesai($aduan);
        $kronologi->rekod($aduan, 'penutupan_diluluskan', 'Diluluskan penyelia (sintetik)', $approver, 'dalam_siasatan', 'ditutup');
    }
}
