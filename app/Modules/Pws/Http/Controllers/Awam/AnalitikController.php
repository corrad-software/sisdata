<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PWS-27: the portal pushes aggregated daily page statistics (no visitor
 * identifiers — page, referrer category, counts). Re-sending the same day
 * replaces the figures (idempotent upsert).
 */
class AnalitikController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rekod' => ['required', 'array', 'min:1', 'max:500'],
            'rekod.*.tarikh' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rekod.*.halaman' => ['required', 'string', 'max:200', 'regex:/^\/[^\s?#]*$/'],
            'rekod.*.sumber_rujukan' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9._\-]+$/'],
            'rekod.*.bilangan_lawatan' => ['required', 'integer', 'min:0', 'max:10000000'],
            'rekod.*.tempoh_purata' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'rekod.*.borang_dimulakan' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'rekod.*.borang_dihantar' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);
        $rows = array_map(fn (array $r) => [
            'tarikh' => $r['tarikh'], 'halaman' => $r['halaman'], 'sumber_rujukan' => $r['sumber_rujukan'] ?? 'terus',
            'bilangan_lawatan' => $r['bilangan_lawatan'], 'tempoh_purata' => $r['tempoh_purata'] ?? null,
            'borang_dimulakan' => $r['borang_dimulakan'] ?? 0, 'borang_dihantar' => $r['borang_dihantar'] ?? 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $data['rekod']);
        DB::table('pws_analitik_lawatan')->upsert($rows, ['tarikh', 'halaman', 'sumber_rujukan'], ['bilangan_lawatan', 'tempoh_purata', 'borang_dimulakan', 'borang_dihantar', 'updated_at']);

        return response()->json(['data' => ['diterima' => count($rows)]]);
    }
}
