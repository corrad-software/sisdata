<?php

namespace App\Modules\Ast\Console;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Services\AsetService;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `php artisan ast:import-gis-titik [--dry-run]` — one-off migration of the
 * interim MOD-GIS asset points (gis_aset_titik, read-only) into the asset
 * register, so the real layer `ast.aset_fizikal` carries them. Idempotent on
 * id_sumber_gis; points without a lot are reported and skipped (an asset
 * must belong to a lot, SRS §4.1). No approval flow is started for imported
 * records (they have no acquisition cost).
 */
class ImportGisCommand extends Command
{
    protected $signature = 'ast:import-gis-titik {--dry-run : Papar sahaja, jangan simpan}';

    protected $description = 'Import titik aset interim MOD-GIS (gis_aset_titik) ke dalam inventori aset fizikal (MOD-AST)';

    private const KATEGORI = ['bangunan' => 'BANGUNAN_PEJABAT', 'premis' => 'KEDAI', 'infrastruktur' => 'INFRASTRUKTUR', 'lain' => 'STRUKTUR'];

    private const JENIS = ['bangunan' => 'bangunan', 'premis' => 'bangunan', 'infrastruktur' => 'infrastruktur', 'lain' => 'lain'];

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('gis_aset_titik')) {
            $this->components->warn('Jadual gis_aset_titik tiada — tiada apa untuk diimport.');

            return self::SUCCESS;
        }
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });
        $service = app(AsetService::class);
        $rows = DB::table('gis_aset_titik')->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'id_lot', 'nama', 'kategori', 'status_keadaan', 'catatan', 'id_pengguna', DB::raw('ST_Y(geom) AS lat'), DB::raw('ST_X(geom) AS lng')]);
        $kategori = AsetKategori::query()->pluck('id', 'kod')->all();
        $admin = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        $done = AsetFizikal::query()->withoutGlobalScope('access')->whereNotNull('id_sumber_gis')->pluck('id_sumber_gis')->map(fn ($v) => (int) $v)->all();
        $ok = $skip = $dup = $fail = 0;
        foreach ($rows as $r) {
            if (in_array((int) $r->id, $done, true)) {
                $dup++;

                continue;
            }
            if ($r->id_lot === null || ! DB::table('lot_tanah')->where('id', $r->id_lot)->whereNull('deleted_at')->exists()) {
                $this->line("  - #{$r->id} {$r->nama}: tiada lot — dilangkau");
                $skip++;

                continue;
            }
            if ($this->option('dry-run')) {
                $ok++;

                continue;
            }
            $by = ($r->id_pengguna ? User::query()->find($r->id_pengguna) : null) ?? $admin;
            if ($by === null) {
                $this->components->error('Tiada pengguna untuk dijadikan pendaftar.');

                return self::FAILURE;
            }
            try {
                // Console: no logged-in user, so LotRegistry::find() is unscoped.
                $a = $service->daftar([
                    'lot_id' => $r->id_lot, 'id_kategori' => $kategori[self::KATEGORI[$r->kategori] ?? 'STRUKTUR'] ?? array_values($kategori)[0],
                    'jenis_aset' => self::JENIS[$r->kategori] ?? 'lain', 'nama_aset' => $r->nama, 'keterangan' => $r->catatan,
                    'latitud' => $r->lat, 'longitud' => $r->lng, 'status_kondisi' => in_array($r->status_keadaan, ['baik', 'perlu_perhatian', 'kritikal'], true) ? $r->status_keadaan : 'baik',
                    'id_pegawai_bertanggungjawab' => $r->id_pengguna, 'id_sumber_gis' => (int) $r->id,
                ], $by);
                $ok++;
                $this->line("  + #{$r->id} → {$a->no_rujukan_aset}");
            } catch (Throwable $e) {
                $fail++;
                $this->line("  ! #{$r->id} {$r->nama}: ".$e->getMessage());
            }
        }
        $this->components->info(($this->option('dry-run') ? '[cuba] ' : '')."{$ok} diimport, {$dup} sudah wujud, {$skip} tanpa lot, {$fail} gagal (daripada ".count($rows).' titik).');

        return self::SUCCESS;
    }
}
