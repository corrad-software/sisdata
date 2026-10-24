<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hkm\Http\Controllers\LaporanController as Laporan;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Hkm\Support\StatusTanah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Code lists for HKM forms and filters (MOD-REF codes + HKM's own lists). Non-sensitive. */
class RujukanController extends Controller
{
    public function __construct(private RefLookup $ref, private StatusTanah $statuses) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(collect(['hkm.hak_milik.lihat', 'hkm.peti_besi.lihat', 'hkm.permohonan.lihat', 'hkm.cadangan.lihat', 'hkm.pemantauan.lihat'])->contains(fn ($p) => $user->can($p)), 403);

        return response()->json(['data' => [
            'jenis_hak_milik' => array_values(array_map(fn ($j) => ['kod' => $j['kod'], 'nama' => $j['nama'], 'singkatan' => $j['singkatan'], 'sementara' => $j['sementara'], 'hak_milik' => $j['hak_milik']], array_filter($this->ref->jenisHakMilik(), fn ($j) => $j['aktif']))),
            'kategori_tanah' => array_values(array_map(fn ($k) => ['kod' => $k['kod'], 'nama' => $k['nama']], array_filter($this->ref->kategoriGunaTanah(), fn ($k) => $k['aktif']))),
            'daerah' => array_values($this->ref->daerah()),
            'mukim' => array_values($this->ref->mukim()),
            'status_tanah' => $this->statuses->all(),
            'status_dokumen' => Kod::options(Kod::STATUS_DOKUMEN),
            'jenis_dokumen_asal' => Kod::options(Kod::JENIS_DOKUMEN_ASAL),
            'keadaan_pulang' => Kod::options(Kod::KEADAAN_PULANG),
            'jenis_sekatan' => Kod::options(Kod::JENIS_SEKATAN),
            'status_sekatan' => Kod::options(Kod::STATUS_SEKATAN),
            'hubungan' => Kod::options(Kod::HUBUNGAN),
            'sumber_pengesahan' => Kod::options(Kod::SUMBER_PENGESAHAN),
            'status_pengesahan_waris' => Kod::options(Kod::STATUS_PENGESAHAN_WARIS),
            'jenis_urusan' => Kod::options(Kod::JENIS_URUSAN),
            'status_urusan' => Kod::options(Kod::STATUS_URUSAN),
            'peringkat_hmk' => Kod::options(Kod::PERINGKAT_HMK),
            'status_hmk' => Kod::options(Kod::STATUS_HMK),
            'jenis_perubahan' => Kod::options(Kod::JENIS_PERUBAHAN),
            'status_perubahan' => Kod::options(Kod::STATUS_PERUBAHAN),
            'sumber_cadangan' => Kod::options(Kod::SUMBER_CADANGAN),
            'status_peti_besi' => Kod::options(Kod::STATUS_PETI_BESI),
            'laporan' => Kod::options(Laporan::JENIS),
            'peti_besi' => $user->can('hkm.peti_besi.lihat')
                ? PetiBesi::query()->where('status_peti_besi', '!=', 'tidak_aktif')->orderBy('kod_peti_besi')->get(['id', 'kod_peti_besi', 'nama'])->toArray()
                : [],
        ]]);
    }
}
