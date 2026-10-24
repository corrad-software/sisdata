<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\Lifecycle\QuotaService;
use App\Modules\Dms\Services\Upload\FileTypes;
use Illuminate\Http\JsonResponse;

/**
 * Upload limits for the upload dialog, the DMS parameters (owned and edited
 * in MOD-ADM, SRS-ADM-01 — shown here read-only) and quota (SRS-DMS-25).
 */
class KonfigurasiController extends DmsController
{
    public function upload(DmsSettings $settings): JsonResponse
    {
        $whitelist = $settings->whitelist();

        return response()->json(['data' => [
            'senarai_putih' => $whitelist,
            'sambungan' => FileTypes::extensionsFor($whitelist),
            'had_saiz_mb' => ['pejabat' => $settings->sizeLimitMb('pejabat'), 'spatial' => $settings->sizeLimitMb('spatial')],
            'kumpulan' => collect(FileTypes::TYPES)->map(fn (array $t) => $t[1]),
            'had_transaksi_mb' => $settings->transactionLimitMb(),
            'had_fail_kelompok' => $settings->batchLimit(),
            'kategori' => DokumenKategori::query()->where('aktif', true)->orderBy('nama')->get(['id', 'kod', 'nama', 'jenis_fail_dibenarkan', 'had_saiz_mb', 'klasifikasi_lalai']),
        ]]);
    }

    public function show(Parameters $parameters): JsonResponse
    {
        return response()->json(['data' => collect(DmsSettings::PARAMETERS)->map(fn (array $p, string $key) => [
            'kunci' => $key,
            'jenis' => $p[0],
            'label' => $p[2],
            'kritikal' => $p[3],
            'nilai' => $parameters->get($key, $p[1]),
            'lalai' => $p[1],
        ])->values()]);
    }

    public function quota(QuotaService $quota): JsonResponse
    {
        return response()->json(['data' => $quota->summary()]);
    }
}
