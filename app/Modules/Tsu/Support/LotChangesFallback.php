<?php

namespace App\Modules\Tsu\Support;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ptn\Contracts\LotMasterUpdates;
use App\Modules\Tsu\Contracts\LotChanges;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Stand-in LotChanges until MOD-PRO binds its own (bindIf). It stays as close
 * to MOD-PRO's own rules as possible without using its internals:
 *  - conditions go through Ptn\Contracts\LotMasterUpdates (the shared
 *    stand-in for approved master-field updates, replaced when PRO binds it);
 *  - a new AREA is never written: a pending `versi_rekod_induk` proposal is
 *    created and MOD-PRO's own flow `pro.perubahan_kritikal` is started, so
 *    MOD-PRO's listener applies it after the second officer confirms;
 *  - a surveyed geometry becomes a new data_spatial version with status
 *    `belum_disahkan` (same as an upload in MOD-PRO) for PRO's GIS officer;
 *  - successor lots / archiving write lot_tanah with versi_rekod and an
 *    explicit before/after audit entry (query builder → no model observer).
 */
class LotChangesFallback implements LotChanges
{
    public function __construct(private LotMasterUpdates $master, private AuditLogger $audit, private WorkflowEngine $workflow) {}

    public function applyConditions(string $lotId, array $changes, User $approvedBy, string $reason): void
    {
        $changes = array_intersect_key($changes, array_flip(Lalai::MEDAN_SYARAT));
        if ($changes !== []) {
            $this->master->apply($lotId, $changes, $approvedBy, $reason);
        }
    }

    public function proposeArea(string $lotId, string $keluasanM2, User $actor, string $reason): ?string
    {
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        if ($lot === null) {
            throw new InvalidArgumentException('Lot tidak dijumpai.');
        }
        if ($lot->unit_keluasan === 'm2' && $lot->keluasan !== null && bccomp((string) $lot->keluasan, $keluasanM2, 2) === 0) {
            return null;
        }
        if (DB::table('versi_rekod_induk')->where('entiti', 'lot_tanah')->where('id_rekod', $lotId)->where('status_pengesahan', 'menunggu')->exists()) {
            return null; // MOD-PRO allows one pending critical change at a time
        }
        $id = (string) Str::uuid();
        DB::table('versi_rekod_induk')->insert([
            'id' => $id, 'entiti' => 'lot_tanah', 'id_rekod' => $lotId,
            'nilai_sebelum' => json_encode(['keluasan' => $lot->keluasan, 'unit_keluasan' => $lot->unit_keluasan]),
            'nilai_selepas' => json_encode(['keluasan' => $keluasanM2, 'unit_keluasan' => 'm2']),
            'kritikal' => true, 'id_pengguna' => $actor->id, 'justifikasi' => $reason, 'status_pengesahan' => 'menunggu',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $instance = $this->workflow->start('pro.perubahan_kritikal', 'versi_rekod_induk', $id, $actor, [
                'kod_daerah' => $lot->kod_daerah, 'bahagian_pemilik' => $lot->bahagian_pemilik, 'medan' => 'keluasan',
                'pautan' => '/admin/pro/lot/'.$lotId.'?tab=dokumen',
            ], 'Keluasan baharu hasil ukur — lot '.$lot->no_lot);
            DB::table('versi_rekod_induk')->where('id', $id)->update(['id_aliran_kerja' => $instance]);
        } catch (Throwable $e) {
            report($e); // proposal stays pending; MOD-PRO's inbox shows it
        }
        $this->audit->record('cadang', 'tsu', 'lot_tanah', $lotId, changes: ['keluasan' => [$lot->keluasan, $keluasanM2]], meta: ['sebab' => $reason, 'id_versi' => $id]);

        return $id;
    }

    public function proposeGeometry(string $lotId, string $hexEwkb, array $meta, User $actor): int
    {
        return DB::transaction(function () use ($lotId, $hexEwkb, $meta, $actor) {
            DB::table('lot_tanah')->where('id', $lotId)->lockForUpdate()->value('id');
            $versi = (int) DB::table('data_spatial')->where('lot_id', $lotId)->max('versi') + 1;
            DB::table('data_spatial')->where('lot_id', $lotId)->where('semasa', true)->update(['semasa' => false, 'updated_at' => now()]);
            $id = $this->insertSpatial($lotId, $hexEwkb, $meta + ['status_pengesahan' => 'belum_disahkan'], $actor, $versi);
            $this->audit->record('cadang', 'tsu', 'data_spatial', $id, meta: ['lot_id' => $lotId, 'sumber' => 'tsu.hasil_ukur', 'klasifikasi' => 'dalaman']);

            return $id;
        });
    }

    public function createSuccessor(array $lot, string $hexEwkb, User $actor, string $reason, string $sourceRef): string
    {
        return DB::transaction(function () use ($lot, $hexEwkb, $actor, $reason, $sourceRef) {
            $id = (string) Str::uuid();
            $area = $lot['keluasan'] ?? (string) round((float) DB::selectOne("SELECT ST_Area(ST_GeomFromEWKB(decode(?, 'hex'))::geography) AS m2", [$hexEwkb])->m2, 4);
            $row = [
                'id' => $id, 'no_lot' => $lot['no_lot'], 'no_lot_normal' => self::normal($lot['no_lot']),
                'kod_mukim' => $lot['kod_mukim'], 'kod_daerah' => $lot['kod_daerah'], 'kod_negeri' => '10',
                'keluasan' => $area, 'unit_keluasan' => 'm2',
                'kategori_guna_tanah' => $lot['kategori_guna_tanah'] ?? null, 'syarat_nyata' => $lot['syarat_nyata'] ?? null, 'zoning' => $lot['zoning'] ?? null,
                'bahagian_pemilik' => $lot['bahagian_pemilik'] ?? null, 'status_lot' => 'aktif', 'klasifikasi' => $lot['klasifikasi'] ?? 'dalaman',
                'versi_rekod' => 1, 'sintetik' => (bool) ($lot['sintetik'] ?? false), 'sumber_sistem' => 'MOD-TSU', 'kunci_asal' => $sourceRef,
                'id_pencipta' => $actor->id, 'catatan' => $reason, 'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('lot_tanah')->insert($row);
            DB::table('versi_rekod_induk')->insert([
                'id' => (string) Str::uuid(), 'entiti' => 'lot_tanah', 'id_rekod' => $id, 'no_versi' => 1, 'nilai_sebelum' => null,
                'nilai_selepas' => json_encode(array_diff_key($row, array_flip(['id', 'created_at', 'updated_at', 'no_lot_normal']))),
                'kritikal' => false, 'id_pengguna' => $actor->id, 'justifikasi' => $reason, 'status_pengesahan' => 'berkuat_kuasa',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->insertSpatial($id, $hexEwkb, ['sumber_data' => 'juruukur', 'status_pengesahan' => 'disahkan_juruukur', 'id_pegawai_pengesah' => $actor->id, 'tarikh_pengesahan' => now(), 'rujukan_fail_sumber' => $sourceRef], $actor, 1);
            $this->audit->record('cipta', 'tsu', 'lot_tanah', $id, changes: ['no_lot' => [null, $lot['no_lot']], 'status_lot' => [null, 'aktif']], meta: ['sumber' => $sourceRef, 'sebab' => $reason]);

            return $id;
        });
    }

    public function archive(string $lotId, User $actor, string $reason): void
    {
        DB::transaction(function () use ($lotId, $reason) {
            $old = DB::table('lot_tanah')->where('id', $lotId)->lockForUpdate()->first();
            if ($old === null) {
                throw new InvalidArgumentException('Lot tidak dijumpai.');
            }
            if ($old->status_lot === 'arkib') {
                return;
            }
            DB::table('lot_tanah')->where('id', $lotId)->update([
                'status_sebelum_arkib' => $old->status_lot, 'status_lot' => 'arkib', 'diarkib_pada' => now(),
                'versi_rekod' => DB::raw('versi_rekod + 1'), 'updated_at' => now(),
            ]);
            $this->audit->record('arkib', 'tsu', 'lot_tanah', $lotId, changes: ['status_lot' => [$old->status_lot, 'arkib']], meta: ['sebab' => $reason]);
        });
    }

    /** @param array<string, mixed> $meta */
    private function insertSpatial(string $lotId, string $hexEwkb, array $meta, User $actor, int $versi): int
    {
        $area = (float) DB::selectOne("SELECT ST_Area(ST_GeomFromEWKB(decode(?, 'hex'))::geography) AS m2", [$hexEwkb])->m2;
        $row = DB::selectOne(
            "INSERT INTO data_spatial (lot_id, geometri, srid_asal, format_asal, keluasan_dikira, sumber_data, rujukan_fail_sumber, id_dokumen_sumber,
                status_pengesahan, id_pegawai_pengesah, tarikh_pengesahan, id_pemuat_naik, semasa, versi, sumber_sistem, created_at, updated_at)
             VALUES (?, ST_Multi(ST_GeomFromEWKB(decode(?, 'hex'))), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, true, ?, 'MOD-TSU', now(), now()) RETURNING id",
            [
                $lotId, $hexEwkb, (int) ($meta['srid_asal'] ?? 4326), (string) ($meta['format_asal'] ?? 'geojson'), round($area, 4),
                (string) ($meta['sumber_data'] ?? 'juruukur'), $meta['rujukan_fail_sumber'] ?? null, $meta['id_dokumen_sumber'] ?? null,
                (string) $meta['status_pengesahan'], $meta['id_pegawai_pengesah'] ?? null, $meta['tarikh_pengesahan'] ?? null, $actor->id, $versi,
            ],
        );
        DB::update('UPDATE lot_tanah SET koordinat_pusat = ST_PointOnSurface(ds.geometri)::geometry(Point, 4326) FROM data_spatial ds WHERE ds.id = ? AND lot_tanah.id = ds.lot_id', [$row->id]);

        return (int) $row->id;
    }

    /** Same normalisation idea as MOD-PRO's no_lot_normal ("LOT 12", "PT 34"). */
    public static function normal(string $raw): string
    {
        $s = trim((string) preg_replace('/[^A-Z0-9]+/', ' ', Str::upper(Str::ascii($raw))));
        if (preg_match('/^(LOT|L|PT)?(?:NO)?0*([0-9]+[A-Z]?)$/', str_replace(' ', '', $s), $m)) {
            return ($m[1] === 'PT' ? 'PT' : 'LOT').' '.$m[2];
        }

        return $s;
    }
}
