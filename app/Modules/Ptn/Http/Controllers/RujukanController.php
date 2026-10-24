<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;
use App\Modules\Ptn\Support\Rujukan;
use Illuminate\Http\JsonResponse;

/** Code lists for the MOD-PTN screens and the field app (Malay labels). */
class RujukanController extends Controller
{
    public function __invoke(Rujukan $ref, KodWarna $warna): JsonResponse
    {
        $s = fn (array $m) => Rujukan::senarai($m);

        return response()->json(['data' => [
            'daerah' => $s($ref->daerah()), 'mukim' => $s($ref->mukim()), 'kategori_guna_tanah' => $s($ref->kategoriGunaTanah()),
            'tahap_risiko' => $s(Lalai::TAHAP_RISIKO), 'status_jadual' => $s(Lalai::STATUS_JADUAL), 'status_lawatan' => $s(Lalai::STATUS_LAWATAN),
            'jenis_pemeriksaan' => $s(Lalai::JENIS_PEMERIKSAAN), 'status_item' => $s(Lalai::STATUS_ITEM), 'kategori_item' => $s(Lalai::KATEGORI_ITEM),
            'keadaan_tapak' => $s(Lalai::KEADAAN_TAPAK), 'kategori_isu' => $s(Lalai::KATEGORI_ISU), 'tahap_keterukan' => $s(Lalai::TAHAP_KETERUKAN),
            'status_isu' => $s(Lalai::STATUS_ISU), 'status_tindakan' => $s(Lalai::STATUS_TINDAKAN), 'jenis_penyelenggaraan' => $s(Lalai::JENIS_PENYELENGGARAAN),
            'medan_cadangan' => $s(Lalai::MEDAN_CADANGAN),
            'kategori_risiko' => array_map(fn ($k, $v) => ['kod' => $k, 'nama' => $v[0]], array_keys(Lalai::KATEGORI_RISIKO), Lalai::KATEGORI_RISIKO),
            'warna' => array_map(fn ($k) => KodWarna::papar($k), array_keys(Lalai::WARNA)),
            'ambang_lawatan' => ['kuning' => $warna->ambang('lawatan')?->tempoh_kelewatan_kuning, 'merah' => $warna->ambang('lawatan')?->tempoh_kelewatan_merah],
        ]]);
    }
}
