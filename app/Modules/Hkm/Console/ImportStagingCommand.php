<?php

namespace App\Modules\Hkm\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Models\SekatanKepentingan;
use App\Modules\Hkm\Models\WarisPemilikBersama;
use App\Modules\Hkm\Services\TitleService;
use App\Modules\Hkm\Services\VaultService;
use App\Modules\Hkm\Support\LotLookup;
use App\Modules\Hkm\Support\RefLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * php artisan hkm:import-staging [--had=…] [--kering]
 *
 * Imports MOD-REF's staging titles/owners (staging_hak_milik, staging_pemilik
 * — SYNTHETIC today) into hak_milik + waris_pemilik_bersama + kronologi +
 * sekatan + daftar peti besi. Run after MOD-PRO has imported lots: a staging
 * title is matched to lot_tanah by lineage (same id, or kunci_asal /
 * staging_lot_tanah_id columns if MOD-PRO keeps them), else by mukim + no_lot
 * through LotRegistry. Titles whose lot isn't imported yet are skipped; titles
 * already imported (sumber_sistem + kunci_asal) are skipped — safe to re-run.
 */
class ImportStagingCommand extends Command
{
    protected $signature = 'hkm:import-staging {--had=0 : Maximum titles to import (0 = all)} {--kering : Dry run — report matches without writing}';

    protected $description = 'Import staging titles and owners (synthetic) into the MOD-HKM title register';

    public const VAULT_CODE = 'PB-MAIS-01';

    public function handle(TitleService $titles, VaultService $vault, LotLookup $lots, RefLookup $ref, JobProgress $progress): int
    {
        if (! Schema::hasTable('staging_hak_milik')) {
            $this->components->error('Jadual staging_hak_milik tiada.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('kering');
        $limit = max(0, (int) $this->option('had'));
        $lotHasKunci = Schema::hasColumn('lot_tanah', 'kunci_asal');
        $lotHasStaging = Schema::hasColumn('lot_tanah', 'staging_lot_tanah_id');
        $stats = ['diimport' => 0, 'tiada_lot' => 0, 'sudah_ada' => 0, 'jenis_tidak_sah' => 0, 'ralat' => 0];

        $rows = DB::table('staging_hak_milik as h')
            ->join('staging_lot_tanah as l', 'l.id', '=', 'h.staging_lot_tanah_id')
            ->orderBy('h.kunci_asal')
            ->select(['h.*', 'l.id as s_lot_id', 'l.no_lot as s_no_lot', 'l.mukim_kod as s_mukim', 'l.kunci_asal as s_lot_kunci',
                'l.sumber_sistem as s_lot_sumber', 'l.keluasan as s_keluasan', 'l.unit_keluasan as s_unit', 'l.kategori_guna_tanah_kod as s_kategori',
                'l.syarat_nyata as s_syarat', 'l.sekatan_kepentingan as s_sekatan', 'l.sintetik as s_sintetik']);

        $vaultModel = null;

        foreach ($rows->cursor() as $row) {
            if ($limit > 0 && $stats['diimport'] >= $limit) {
                break;
            }

            if (HakMilik::query()->withoutGlobalScope('access')->where('sumber_sistem', $row->sumber_sistem)->where('kunci_asal', $row->kunci_asal)->exists()) {
                $stats['sudah_ada']++;

                continue;
            }

            $lotId = $this->matchLot($row, $lots, $lotHasKunci, $lotHasStaging);
            if ($lotId === null) {
                $stats['tiada_lot']++;

                continue;
            }
            if (! $ref->validJenis($row->jenis_hak_milik_kod)) {
                $stats['jenis_tidak_sah']++;

                continue;
            }
            if ($dry) {
                $stats['diimport']++;

                continue;
            }

            try {
                AuditScope::withReason('Import data staging ('.$row->sumber_sistem.' '.$row->kunci_asal.')', function () use ($row, $lotId, $titles, $vault, $ref, &$vaultModel) {
                    DB::transaction(function () use ($row, $lotId, $titles, $vault, $ref, &$vaultModel) {
                        $people = DB::table('staging_pemilik')->where('staging_hak_milik_id', $row->id)->orderBy('kunci_asal')->get();
                        $registered = $people->firstWhere('peranan', 'pemilik_berdaftar');
                        $earlier = $people->where('peranan', 'pemilik_asal')->pluck('nama')->values()->all();

                        $title = $titles->register($lotId, [
                            'no_hak_milik' => $row->no_hak_milik,
                            'jenis_hak_milik' => $row->jenis_hak_milik_kod,
                            'kategori_tanah' => $row->s_kategori && $ref->validKategori($row->s_kategori) ? $row->s_kategori : null,
                            'syarat_nyata' => $row->s_syarat,
                            'keluasan' => $row->s_keluasan,
                            'unit_keluasan' => $row->s_unit === 'hektar' ? 'hektar' : 'm2',
                            'pemilik_berdaftar' => $registered->nama ?? 'Majlis Agama Islam Selangor',
                            'tarikh_pendaftaran' => $row->tarikh_daftar,
                            'tarikh_tamat_tempoh' => $row->tarikh_tamat,
                            'tempoh_pajakan_tahun' => $row->tempoh_pajakan_tahun,
                        ], null, null, [
                            'sebab' => 'Import data staging',
                            'sumber' => 'import',
                            'sumber_sistem' => $row->sumber_sistem,
                            'kunci_asal' => $row->kunci_asal,
                            'pemilik_terdahulu' => $earlier,
                        ]);

                        foreach ($people->where('peranan', '!=', 'pemilik_berdaftar') as $p) {
                            [$num, $den] = $this->fraction($p->bahagian);
                            WarisPemilikBersama::query()->create([
                                'hak_milik_id' => $title->id,
                                'nama' => $p->nama,
                                'no_pengenalan' => $p->no_pengenalan,
                                'hubungan' => $p->peranan === 'pewakaf' ? 'pewakaf' : ($p->peranan === 'pemegang_amanah' ? 'pemegang_amanah' : 'pemilik_asal'),
                                'pecahan_bahagian_pembilang' => $num,
                                'pecahan_bahagian_penyebut' => $den,
                                'sumber_pengesahan' => 'import_data',
                                'status_pengesahan' => 'belum_disahkan',
                                'sintetik' => (bool) $p->sintetik,
                                'kunci_asal' => $p->kunci_asal,
                            ]);
                        }

                        foreach (array_filter([$row->sekatan_kepentingan, $row->s_sekatan]) as $text) {
                            SekatanKepentingan::query()->create([
                                'hak_milik_id' => $title->id,
                                'jenis_sekatan' => 'sekatan_kepentingan',
                                'keterangan' => $text,
                                'tarikh_masuk' => $row->tarikh_daftar,
                                'status_sekatan' => 'aktif',
                            ]);
                        }

                        if ($row->lokasi_simpanan_fizikal) {
                            $vaultModel ??= PetiBesi::query()->firstOrCreate(['kod_peti_besi' => self::VAULT_CODE], [
                                'nama' => 'Bilik Kebal MAIS (sintetik)',
                                'lokasi_bangunan' => 'Menara MAIS, Shah Alam',
                                'tingkat' => 'B1',
                                'bilik' => 'Bilik Kebal',
                                'status_peti_besi' => 'aktif',
                                'catatan' => 'Dicipta oleh hkm:import-staging bagi data sintetik.',
                            ]);
                            preg_match('/Rak\s+([A-Z0-9\-]+)/u', $row->lokasi_simpanan_fizikal, $rak);
                            preg_match('/Fail\s+([0-9A-Z\-]+)/u', $row->lokasi_simpanan_fizikal, $fail);
                            $vault->deposit($title, [
                                'peti_besi_id' => $vaultModel->id,
                                'lokasi_rak' => $rak[1] ?? null,
                                'no_fail' => $fail[1] ?? null,
                                'catatan_lokasi' => $row->lokasi_simpanan_fizikal,
                            ], null);
                        }
                    });
                });
                $stats['diimport']++;
                $progress->processed();
            } catch (Throwable $e) {
                $stats['ralat']++;
                $this->components->warn("{$row->kunci_asal}: {$e->getMessage()}");
            }
        }

        $this->components->info(($dry ? '[Kering] ' : '').'Import hak milik daripada staging selesai.');
        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }

    private function matchLot(object $row, LotLookup $lots, bool $byKunci, bool $byStaging): ?string
    {
        if (DB::table('lot_tanah')->where('id', $row->s_lot_id)->whereNull('deleted_at')->exists()) {
            return $row->s_lot_id;
        }
        if ($byStaging && ($id = DB::table('lot_tanah')->where('staging_lot_tanah_id', $row->s_lot_id)->whereNull('deleted_at')->value('id'))) {
            return (string) $id;
        }
        if ($byKunci && ($id = DB::table('lot_tanah')->where('kunci_asal', $row->s_lot_kunci)->whereNull('deleted_at')->value('id'))) {
            return (string) $id;
        }

        $number = trim((string) $row->s_no_lot);
        $normalised = preg_replace('/^(PT|LOT)\s*[.:]?\s*0*(\d+)$/i', '$1 $2', $number) ?? $number;
        foreach (array_unique([$number, $normalised, ucfirst(strtolower($normalised)), strtoupper($normalised)]) as $candidate) {
            if ($id = $lots->findByNumber((string) $row->s_mukim, $candidate)) {
                return $id;
            }
        }

        return null;
    }

    /** @return array{0: int|null, 1: int|null} */
    private function fraction(?string $text): array
    {
        if ($text !== null && preg_match('/^\s*(\d+)\s*\/\s*(\d+)\s*$/', $text, $m) && (int) $m[2] > 0 && (int) $m[1] <= (int) $m[2]) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [null, null];
    }
}
