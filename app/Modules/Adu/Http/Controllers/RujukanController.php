<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Code lists for the MOD-ADU screens (Malay labels). */
class RujukanController extends Controller
{
    public function __invoke(Rujukan $ref): JsonResponse
    {
        $pairs = fn (array $map) => collect($map)->map(fn ($nama, $kod) => ['kod' => (string) $kod, 'nama' => (string) $nama])->values();
        $mukim = DB::table('ref_mukim')->whereNull('sah_hingga')->orderBy('nama')->get(['kod', 'nama', 'daerah_kod'])
            ->map(fn ($m) => ['kod' => (string) $m->kod, 'nama' => (string) $m->nama, 'kod_daerah' => (string) $m->daerah_kod])->values();

        return response()->json(['data' => [
            'kategori' => $pairs($ref->kategori()),
            'subkategori' => collect($ref->subkategori())->map(fn ($subs) => $pairs($subs)),
            'saluran' => $pairs(Lalai::SALURAN),
            'saluran_dalaman' => $pairs(array_intersect_key(Lalai::SALURAN, array_flip(AduanController::SALURAN_DALAMAN))),
            'status' => $pairs(Lalai::STATUS),
            'keutamaan' => $pairs(Lalai::KEUTAMAAN),
            'kategori_resolusi' => $pairs(Lalai::KATEGORI_RESOLUSI),
            'jenis_rujukan' => $pairs(Lalai::JENIS_RUJUKAN),
            'bahagian_kategori' => Lalai::BAHAGIAN_KATEGORI,
            'daerah' => $pairs($ref->daerah()),
            'mukim' => $mukim,
        ]]);
    }
}
