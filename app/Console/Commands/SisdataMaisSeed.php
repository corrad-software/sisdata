<?php

namespace App\Console\Commands;

use App\Console\MaisSeed\AduanBuilder;
use App\Console\MaisSeed\AliranHelper;
use App\Console\MaisSeed\AsetBuilder;
use App\Console\MaisSeed\CukaiBuilder;
use App\Console\MaisSeed\Dataset;
use App\Console\MaisSeed\DokumenBuilder;
use App\Console\MaisSeed\KesBuilder;
use App\Console\MaisSeed\Konteks;
use App\Console\MaisSeed\LotBuilder;
use App\Console\MaisSeed\PemantauanBuilder;
use App\Console\MaisSeed\PemerolehanBuilder;
use App\Console\MaisSeed\Rawak;
use App\Console\MaisSeed\SewaanBuilder;
use App\Console\MaisSeed\UrusanBuilder;
use App\Modules\Adu\Contracts\ReporterMessenger;
use App\Modules\Ast\Services\PenyelenggaraanService;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Services\AgihanService;
use App\Modules\Pjk\Services\KontrakService;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Ptn\Services\RisikoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * php artisan sisdata:mais-seed [--force] [--data=…] [--had=N]
 *
 * Loads the curated public dataset of MAIS / PWS / Baitulmal / LZS
 * properties (database/data/mais/hartanah_mais.json) into SISDATA as
 * "Profil Hartanah 360°" demo records across every process, through the
 * modules' own services so business rules, numbering, versioning, audit and
 * WFE apply. Real: name, type, stream (when public), address, coordinates,
 * OSM outline, public facts. Everything else is SYNTHETIC and flagged.
 *
 * Independent from sisdata:demo-seed (works next to its 500 synthetic
 * lots); MAIS records carry lineage sumber_sistem = "mais_awam". Re-run
 * safe: a property whose lot already exists is skipped. Lives in
 * app/Console (not a module) because it orchestrates module internals,
 * like SisdataDemoSeed.
 */
class SisdataMaisSeed extends Command
{
    protected $signature = 'sisdata:mais-seed
        {--force : Run even though MAIS records already exist (only missing properties are added; derived steps re-run)}
        {--data= : Dataset file (default database/data/mais/hartanah_mais.json)}
        {--had=0 : Maximum number of properties (0 = all)}';

    protected $description = 'Benihkan profil hartanah MAIS sebenar (data awam) dengan data demo sintetik merentas semua modul';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Tidak dibenarkan dalam persekitaran produksi.');

            return self::FAILURE;
        }
        foreach (['ref_mukim' => 'php artisan ref:seed', 'saluran_pemerolehan' => 'php artisan sisdata:demo-seed (atau seeder MOD-PER)', 'users' => 'php artisan sisdata:demo-seed'] as $table => $hint) {
            if (! DB::table($table)->exists()) {
                $this->components->error("Jadual {$table} kosong — jalankan {$hint} dahulu.");

                return self::FAILURE;
            }
        }
        $existing = DB::table('lot_tanah')->where('sumber_sistem', Konteks::SUMBER)->whereNull('deleted_at')->pluck('id', 'kunci_asal')->all();
        if ($existing !== [] && ! $this->option('force')) {
            $this->components->info(count($existing).' hartanah MAIS sudah dibenihkan dalam pangkalan data ini — tiada apa dibuat. Gunakan --force untuk menambah yang belum ada.');

            return self::SUCCESS;
        }

        ini_set('memory_limit', '2G');
        $started = microtime(true);
        $this->components->info('Pangkalan data: '.DB::connection()->getDatabaseName().' (APP_ENV='.app()->environment().')');
        $data = Dataset::load((string) ($this->option('data') ?: Dataset::LALAI));
        $k = new Konteks;
        // Members of the public in the demo never receive real e-mail / SMS.
        app()->instance(ReporterMessenger::class, new class implements ReporterMessenger
        {
            public function email(string $to, string $subject, string $body): void {}

            public function sms(string $to, string $text): void {}
        });

        $props = $this->sediakan($data->hartanah, (int) $this->option('had'));
        $this->components->info(count($props).' hartanah dalam set data; '.count($existing).' sudah wujud.');

        // Pass 1 — one transaction per property: lot → acquisition/title → leases → tax → assets → complaints → legal → land office → documents.
        $lots = [];
        $bar = $this->output->createProgressBar(count($props));
        foreach ($props as $h) {
            if (isset($existing[$h['kunci']])) {
                $lots[$h['kunci']] = (string) $existing[$h['kunci']];
                $bar->advance();

                continue;
            }
            try {
                $lots[$h['kunci']] = DB::transaction(fn () => $this->satu($h, $k));
                $k->tambah('hartanah');
            } catch (Throwable $e) {
                $k->amaran($h['kunci'].' ('.$h['nama'].'): '.$e->getMessage());
                $k->tambah('hartanah_gagal');
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Pass 2 — monitoring needs this year's schedule, generated for all lots at once.
        $this->components->task('Pemantauan (MOD-PTN) + aduan daripada penemuan', function () use ($props, $lots, $k) {
            $ptn = app(PemantauanBuilder::class);
            $ptn->jadualTahunan($k);
            foreach ($props as $h) {
                $lotId = $lots[$h['kunci']] ?? null;
                if ($lotId === null || DB::table('rekod_lawatan')->where('lot_id', $lotId)->exists()) {
                    continue;
                }
                try {
                    DB::transaction(fn () => $ptn->bina(LotTanah::query()->withoutGlobalScope('access')->findOrFail($lotId), $h, new Rawak('ptn:'.$h['kunci']), $k));
                } catch (Throwable $e) {
                    $k->amaran($h['kunci'].' PTN: '.$e->getMessage());
                }
            }
        });

        $lotIds = array_values($lots);
        $runStart = now()->subSeconds((int) ceil(microtime(true) - $started) + 1);
        $this->components->task('Keputusan aliran WFE (campuran lulus / tolak / menunggu)', fn () => $this->aliran($lotIds, $runStart, $k));
        $this->components->task('Status kontrak, agihan manfaat wakaf', fn () => $this->pjk($lotIds, $k));
        $this->components->task('Skor risiko & keperluan penyelenggaraan', function () use ($lotIds, $k) {
            try {
                app(RisikoService::class)->kira($lotIds);
                $r = app(PenyelenggaraanService::class)->tarikKeperluanPtn($k->admin, $lotIds);
                $k->tambah('kerja_dari_ptn', $r['dicipta']);
            } catch (Throwable $e) {
                $k->amaran('Risiko/penyelenggaraan: '.$e->getMessage());
            }
        });

        $this->ringkasan($props, $lots, $k, microtime(true) - $started);

        return ($k->kiraan['hartanah_gagal'] ?? 0) > 0 && ($k->kiraan['hartanah'] ?? 0) === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Derived, deterministic fields: location codes, owning division and
     * which properties carry the special demo stories.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sediakan(array $rows, int $limit): array
    {
        $out = [];
        foreach ($rows as $h) {
            if (empty($h['kod_mukim']) || empty($h['kod_daerah'])) {
                $loc = Dataset::lokasi((float) $h['lat'], (float) $h['lon']);
                if ($loc === null) {
                    continue;
                }
                $h += $loc;
                $h['kod_mukim'] = $h['kod_mukim'] ?: $loc['kod_mukim'];
                $h['kod_daerah'] = $h['kod_daerah'] ?: $loc['kod_daerah'];
            }
            $f = new Rawak('flag:'.$h['kunci']);
            $h['bahagian'] = match ($h['aliran']) {
                'wakaf' => 'wakaf', 'baitulmal' => 'baitulmal', 'hartanah_am', 'zakat_lzs' => 'hartanah',
                // Not stated publicly: a demo assumption by type (flagged in the lot's catatan).
                default => in_array($h['jenis'], ['masjid', 'surau', 'kubur', 'sekolah_agama'], true) ? ($f->chance(80) ? 'wakaf' : 'hartanah')
                    : $f->weighted(['wakaf' => 45, 'hartanah' => 40, 'baitulmal' => 15]),
            };
            // Acquisitions still in progress: mostly land / shops being added to the portfolio, rarely an old institution.
            $h['_per_dalam_proses'] = $f->chance(in_array($h['jenis'], ['tanah_kosong', 'pertanian', 'lain'], true) ? 25 : 3);
            $h['_ceroboh'] = ! in_array($h['jenis'], ['hotel', 'bangunan_pejabat'], true) && $f->chance(9);
            $h['_kes'] = $h['_ceroboh'] ? $f->chance(60) : $f->chance(4);
            $h['_tsu'] = $f->chance(in_array($h['jenis'], ['tanah_kosong', 'pertanian'], true) ? 70 : 9);
            $out[] = $h;
            if ($limit > 0 && count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $h */
    private function satu(array $h, Konteks $k): string
    {
        $r = new Rawak($h['kunci']);
        $lot = app(LotBuilder::class)->bina($h, $r, $k);
        $per = app(PemerolehanBuilder::class)->bina($lot, $h, new Rawak('per:'.$h['kunci']), $k);
        $premis = app(SewaanBuilder::class)->bina($lot, $h, new Rawak('pjk:'.$h['kunci']), $k);
        app(CukaiBuilder::class)->bina($lot, $h, new Rawak('cki:'.$h['kunci']), $k);
        app(AsetBuilder::class)->bina($lot, $h, new Rawak('ast:'.$h['kunci']), $k, $premis);
        app(AduanBuilder::class)->bina($lot, $h, new Rawak('adu:'.$h['kunci']), $k);
        if ($h['_kes']) {
            app(KesBuilder::class)->bina($lot, $h, new Rawak('kes:'.$h['kunci']), $k);
        }
        if ($h['_tsu']) {
            app(UrusanBuilder::class)->bina($lot, $h, new Rawak('tsu:'.$h['kunci']), $k);
        }
        $contracts = Kontrak::query()->withoutGlobalScope('access')->where('lot_id', $lot->id)->orderBy('tarikh_mula')->get()->all();
        app(DokumenBuilder::class)->bina($lot, $h, new Rawak('dms:'.$h['kunci']), $k, $per['hak_milik']?->no_hak_milik, $contracts);

        return $lot->id;
    }

    /**
     * Pending approvals created by this run (contracts, acquisitions): decide
     * about a third each way so the WFE inbox shows every state.
     *
     * @param  list<string>  $lotIds
     */
    private function aliran(array $lotIds, Carbon $since, Konteks $k): void
    {
        $helper = app(AliranHelper::class);
        $ids = DB::table('wf_instans')->where('status', 'berjalan')->where('created_at', '>=', $since)
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('jenis_subjek', 'pemerolehan')->whereIn('id_rekod', DB::table('pemerolehan')->whereIn('lot_id', $lotIds)->select(DB::raw('id::text'))))
                ->orWhere(fn ($q2) => $q2->where('kod_aliran', 'pjk.kelulusan_kontrak')->whereIn('id_rekod', DB::table('kontrak_pajakan')->whereIn('lot_id', $lotIds)->select(DB::raw('id::text')))))
            ->orderBy('id')->pluck('id')->all();
        foreach ($ids as $i => $id) {
            $choice = ['lulus', 'tunggu', 'tolak', 'lulus', 'tunggu'][$i % 5];
            if ($choice === 'tunggu') {
                $k->tambah('wfe_menunggu');

                continue;
            }
            try {
                $status = $helper->putus((string) $id, $choice, $k->pelulus, $choice === 'lulus' ? 'Diluluskan (demo SINTETIK)' : 'Ditolak (demo SINTETIK)');
                $k->tambah('wfe_'.($status ?? 'tiada'));
            } catch (Throwable $e) {
                $k->amaran('WFE '.$id.': '.$e->getMessage());
            }
        }
    }

    /** @param list<string> $lotIds */
    private function pjk(array $lotIds, Konteks $k): void
    {
        try {
            app(KontrakService::class)->refreshStatuses(Carbon::today());
        } catch (Throwable $e) {
            $k->amaran('PJK status kontrak: '.$e->getMessage());
        }
        // SRS-PJK-22..25: benefit distribution for the last three months of waqf/purchased premises; older two confirmed by a second officer.
        $premisIds = PremisAset::query()->withoutGlobalScope('access')->whereIn('lot_id', $lotIds)->pluck('id')->map(fn ($v) => (int) $v)->all();
        if ($premisIds === [] || ! DB::table('dasar_agihan_manfaat')->where('status', 'aktif')->exists()) {
            return;
        }
        $agihan = app(AgihanService::class);
        foreach ([3, 2, 1] as $back) {
            $tempoh = now()->subMonthsNoOverflow($back)->format('Y-m');
            try {
                $rows = $agihan->calculate($tempoh, $premisIds, $k->pegawai);
                $ids = array_values(array_filter(array_map(fn ($r) => isset($r['id']) && ($r['status_pengesahan'] ?? '') === 'draf' ? (int) $r['id'] : null, $rows)));
                $k->tambah('agihan_manfaat', count($ids));
                if ($back > 1 && $ids !== []) {
                    $k->tambah('agihan_disahkan', $agihan->confirm($ids, $k->pelulus[0] ?? $k->admin));
                }
            } catch (Throwable $e) {
                $k->amaran('Agihan '.$tempoh.': '.$e->getMessage());
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $props
     * @param  array<string, string>  $lots
     */
    private function ruangan(array $props, array $lots, string $field): array
    {
        $c = [];
        foreach ($props as $h) {
            if (isset($lots[$h['kunci']])) {
                $c[(string) $h[$field]] = ($c[(string) $h[$field]] ?? 0) + 1;
            }
        }
        ksort($c);

        return array_map(fn ($key, $n) => [$key, $n], array_keys($c), $c);
    }

    /**
     * @param  list<array<string, mixed>>  $props
     * @param  array<string, string>  $lots
     */
    private function ringkasan(array $props, array $lots, Konteks $k, float $seconds): void
    {
        $this->newLine();
        $this->table(['Daerah', 'Hartanah'], $this->ruangan($props, $lots, 'kod_daerah'));
        $this->table(['Jenis', 'Hartanah'], $this->ruangan($props, $lots, 'jenis'));
        $this->table(['Aliran (sumber awam)', 'Hartanah'], $this->ruangan($props, $lots, 'aliran'));
        $counts = $k->kiraan;
        ksort($counts);
        $this->table(['Rekod dicipta', 'Bilangan'], array_map(fn ($key, $n) => [$key, $n], array_keys($counts), $counts));
        foreach (array_slice($k->amaran, 0, 40) as $w) {
            $this->components->warn($w);
        }
        if (count($k->amaran) > 40) {
            $this->components->warn((count($k->amaran) - 40).' amaran lagi tidak dipaparkan.');
        }
        $this->components->info(sprintf('Selesai dalam %.1f s. Rekod MAIS: lot_tanah.sumber_sistem = "%s" (data awam + SINTETIK).', $seconds, Konteks::SUMBER));
    }
}
