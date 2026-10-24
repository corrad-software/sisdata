<?php

namespace App\Modules\Pro\Services;

use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Loads MOD-REF's staging lots (staging_lot_tanah + geometry) into the master
 * register (lot_tanah + data_spatial) through the same quality rules as the
 * form and the import wizard. Idempotent: a staging row whose lineage
 * (sumber_sistem, fail_asal, kunci_asal) is already in lot_tanah is skipped.
 * Every created lot keeps that lineage plus the migrasi_larian of this run
 * (SRS-DAT-08).
 */
class StagingImporter
{
    public function __construct(private LotService $lots, private SpatialService $spatial) {}

    /**
     * @return array{larian: string, dibaca: int, berjaya: int, dilangkau: int, ditolak: list<array{kunci_asal: string, sebab: string}>, geometri: int}
     */
    public function run(?string $larianSumber = null, ?int $limit = null, bool $dryRun = false): array
    {
        $rows = DB::table('staging_lot_tanah')
            ->when($larianSumber !== null, fn ($q) => $q->where('migrasi_larian_id', $larianSumber))
            ->orderBy('kunci_asal')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get(['id', 'migrasi_larian_id', 'no_lot', 'mukim_kod', 'daerah_kod', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah_kod', 'zoning',
                'syarat_nyata', 'wakaf', 'baitulmal', 'sintetik', 'sumber_sistem', 'fail_asal', 'kunci_asal',
                DB::raw('CASE WHEN geometri IS NULL THEN NULL ELSE ST_AsHEXEWKB(ST_Multi(geometri)) END AS geom_hex')]);

        $runId = (string) Str::uuid();
        $now = now();
        if (! $dryRun) {
            DB::table('migrasi_larian')->insert([
                'id' => $runId, 'jenis' => 'pro_import_lot_tanah', 'sumber_sistem' => 'staging_lot_tanah',
                'parameter' => json_encode(['larian_sumber' => $larianSumber, 'had' => $limit]),
                'status' => 'berjalan', 'mula_pada' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $result = ['larian' => $runId, 'dibaca' => $rows->count(), 'berjaya' => 0, 'dilangkau' => 0, 'ditolak' => [], 'geometri' => 0];

        foreach ($rows as $row) {
            $exists = DB::table('lot_tanah')->whereNull('deleted_at')
                ->where(['sumber_sistem' => $row->sumber_sistem, 'fail_asal' => $row->fail_asal, 'kunci_asal' => $row->kunci_asal])->exists();
            if ($exists) {
                $result['dilangkau']++;

                continue;
            }

            $input = [
                'no_lot' => $row->no_lot,
                'kod_mukim' => $row->mukim_kod,
                'kod_daerah' => $row->daerah_kod,
                'keluasan' => $row->keluasan,
                'unit_keluasan' => $row->unit_keluasan === 'meter_persegi' ? 'm2' : $row->unit_keluasan,
                'kategori_guna_tanah' => $row->kategori_guna_tanah_kod,
                'zoning' => $row->zoning !== null ? Str::limit((string) $row->zoning, 50, '') : null,
                'syarat_nyata' => $row->syarat_nyata,
                'bahagian_pemilik' => $row->wakaf ? 'wakaf' : ($row->baitulmal ? 'baitulmal' : 'hartanah'),
                'status_lot' => 'aktif',
                'klasifikasi' => 'dalaman',
            ];

            if ($dryRun) {
                $result['berjaya']++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $input, $runId, &$result) {
                    /** @var LotTanah $lot */
                    $lot = AuditScope::withReason('Import daripada staging_lot_tanah (larian '.$runId.')', fn () => $this->lots->create($input, null, [
                        'sintetik' => (bool) $row->sintetik,
                        'sumber_sistem' => $row->sumber_sistem,
                        'fail_asal' => $row->fail_asal,
                        'kunci_asal' => $row->kunci_asal,
                        'id_migrasi_larian' => $runId,
                        'catatan_versi' => 'Import staging '.$row->kunci_asal,
                    ]));

                    if ($row->geom_hex !== null) {
                        $this->spatial->store($lot, ['hex' => (string) $row->geom_hex, 'srid' => 4326, 'format' => 'wkt'], [
                            'sumber_data' => $row->sintetik ? 'sintetik' : 'lakaran',
                            'rujukan_fail_sumber' => $row->fail_asal,
                            'sumber_sistem' => $row->sumber_sistem,
                            'kunci_asal' => $row->kunci_asal,
                        ], null);
                        $result['geometri']++;
                    }
                    $result['berjaya']++;
                });
            } catch (ValidationException $e) {
                $result['ditolak'][] = ['kunci_asal' => (string) $row->kunci_asal, 'sebab' => collect($e->errors())->flatten()->implode(' ')];
            }
        }

        if (! $dryRun) {
            DB::table('migrasi_larian')->where('id', $runId)->update([
                'status' => 'selesai',
                'bilangan_diterima' => $result['berjaya'],
                'bilangan_ditolak' => count($result['ditolak']),
                'catatan' => $result['dilangkau'] > 0 ? $result['dilangkau'].' baris sudah diimport sebelum ini (dilangkau).' : null,
                'tamat_pada' => now(),
                'updated_at' => now(),
            ]);
        }

        return $result;
    }
}
