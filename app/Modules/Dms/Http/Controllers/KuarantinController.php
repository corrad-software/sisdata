<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Models\DokumenKuarantin;
use App\Modules\Dms\Services\Storage\DocumentVault;
use Illuminate\Http\JsonResponse;

/** SRS-DMS-07: quarantined uploads — list for admins, and destroy. Never downloadable. */
class KuarantinController extends DmsController
{
    public function index(): JsonResponse
    {
        $items = DokumenKuarantin::query()->leftJoin('users', 'users.id', '=', 'dokumen_kuarantin.dimuat_naik_oleh')
            ->select('dokumen_kuarantin.*', 'users.name as pemuat_naik')
            ->latest('dokumen_kuarantin.created_at')
            ->limit(500)
            ->get()
            ->map(fn (DokumenKuarantin $k) => [
                'id' => $k->id,
                'nama_fail' => $k->nama_fail,
                'saiz_bait' => $k->saiz_bait,
                'cincangan_fail' => $k->cincangan_fail,
                'nama_ancaman' => $k->nama_ancaman,
                'induk' => $k->jenis_entiti_induk ? $k->jenis_entiti_induk.':'.$k->id_rekod_induk : null,
                'pemuat_naik' => $k->getAttribute('pemuat_naik'),
                'dikuarantin_pada' => $k->created_at->toIso8601String(),
                'dimusnahkan_pada' => $k->dimusnahkan_pada?->toIso8601String(),
            ]);

        return response()->json(['data' => $items->values()]);
    }

    public function destroy(DokumenKuarantin $kuarantin, DocumentVault $vault, AuditLogger $audit): JsonResponse
    {
        if ($kuarantin->kunci_storan !== null) {
            $vault->delete($kuarantin->kunci_storan);
        }
        $kuarantin->forceFill(['kunci_storan' => null, 'kunci_data_bersulit' => null, 'dimusnahkan_pada' => now()])->save();
        $audit->record('musnah_kuarantin', 'dms', 'dokumen_kuarantin', $kuarantin->id, meta: ['nama_fail' => $kuarantin->nama_fail, 'cincangan' => $kuarantin->cincangan_fail]);

        return response()->json(['message' => __('dms::mesej.kuarantin_dimusnahkan')]);
    }
}
