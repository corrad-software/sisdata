<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Services\Laporan;
use App\Modules\Cki\Support\Kod;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Code lists for the MOD-CKI frontend, and the lot picker (through MOD-PRO's LotRegistry). */
class RujukanController
{
    public function index(): JsonResponse
    {
        $current = fn (string $t) => DB::table($t)->where('aktif', true)->whereNull('sah_hingga')->orderBy('kod');

        return response()->json(['data' => [
            'jenis_cukai' => Kod::list(Kod::JENIS_CUKAI),
            'pihak_pengeluar' => Kod::list(Kod::PIHAK_PENGELUAR),
            'status_bayaran' => Kod::list(Kod::STATUS_BAYARAN),
            'sumber_rekod' => Kod::list(Kod::SUMBER_REKOD),
            'kaedah_bayaran' => Kod::list(Kod::KAEDAH_BAYARAN),
            'medan_kaedah' => Kod::MEDAN_KAEDAH,
            'status_transaksi' => Kod::list(Kod::STATUS_TRANSAKSI),
            'kumpulan_umur' => Kod::list(Kod::KUMPULAN_UMUR),
            'jenis_sisihan' => Kod::list(Kod::JENIS_SISIHAN),
            'jenis_laporan' => Kod::list(Laporan::JENIS),
            'daerah' => $current('ref_daerah')->get(['kod', 'nama'])->map(fn ($r) => ['kod' => (string) $r->kod, 'nama' => (string) $r->nama])->all(),
            'mukim' => $current('ref_mukim')->get(['kod', 'nama', 'daerah_kod'])->map(fn ($r) => ['kod' => (string) $r->kod, 'nama' => (string) $r->nama, 'daerah_kod' => (string) $r->daerah_kod])->all(),
            'agensi' => Liputan::query()->orderBy('jenis_agensi')->orderBy('nama_agensi')->get(['kod_agensi', 'nama_agensi', 'jenis_agensi'])
                ->map(fn ($l) => ['kod' => $l->kod_agensi, 'nama' => $l->nama_agensi, 'jenis' => $l->jenis_agensi])->all(),
            'pbt' => $current('ref_pbt')->get(['kod', 'nama'])->map(fn ($r) => ['kod' => (string) $r->kod, 'nama' => (string) $r->nama])->all(),
        ]]);
    }

    public function lots(Request $request, LotRegistry $lots): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => $q === '' ? [] : $lots->search($q, $request->user(), 15)]);
    }
}
