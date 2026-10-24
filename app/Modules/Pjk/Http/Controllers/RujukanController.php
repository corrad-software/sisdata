<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Integration\MockPaymentGateway;
use App\Modules\Pjk\Services\Laporan;
use App\Modules\Pjk\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Code lists for the MOD-PJK screens (Malay labels). */
class RujukanController
{
    public function index(PaymentGateway $gateway): JsonResponse
    {
        return response()->json(['data' => [
            'status_kontrak' => Kod::options(Kod::STATUS_KONTRAK),
            'status_premis' => Kod::options(Kod::STATUS_PREMIS),
            'kategori_hartanah' => Kod::options(Kod::KATEGORI_HARTANAH),
            'jenis_aset' => Kod::options(Kod::JENIS_ASET),
            'jenis_entiti' => Kod::options(Kod::JENIS_ENTITI),
            'jenis_perjanjian' => Kod::options(Kod::JENIS_PERJANJIAN),
            'kitaran_caj' => Kod::options(Kod::KITARAN_CAJ),
            'kod_caj' => Kod::options(Kod::KOD_CAJ),
            'saluran_bayaran' => Kod::options(Kod::SALURAN_BAYARAN),
            'saluran_luar_talian' => Kod::options(array_intersect_key(Kod::SALURAN_BAYARAN, array_flip(Kod::SALURAN_LUAR_TALIAN))),
            'jenis_pindaan' => Kod::options(Kod::JENIS_PINDAAN),
            'jenis_pelarasan' => Kod::options(Kod::JENIS_PELARASAN),
            'status_deposit' => Kod::options(Kod::STATUS_DEPOSIT),
            'status_permohonan' => Kod::options(Kod::STATUS_PERMOHONAN),
            'jenis_surat' => Kod::options(Kod::JENIS_SURAT),
            'status_perolehan' => Kod::options(Kod::STATUS_PEROLEHAN),
            'bahagian' => Kod::options(Kod::BAHAGIAN),
            'kumpulan_umur' => Kod::options(Kod::KUMPULAN_UMUR),
            'jenis_sisihan' => Kod::options(Kod::JENIS_SISIHAN),
            'jenis_laporan' => Kod::options(Laporan::JENIS),
            'daerah' => DB::table('ref_daerah')->where('aktif', true)->orderBy('susunan')->get(['kod', 'nama'])->map(fn ($r) => ['kod' => $r->kod, 'nama' => $r->nama])->all(),
            'mukim' => DB::table('ref_mukim')->where('aktif', true)->orderBy('nama')->get(['kod', 'nama', 'daerah_kod'])->map(fn ($r) => ['kod' => $r->kod, 'nama' => $r->nama, 'daerah_kod' => $r->daerah_kod])->all(),
            'gateway' => ['kod' => $gateway->code(), 'saluran' => $gateway->channels(), 'ujian' => $gateway instanceof MockPaymentGateway],
        ]]);
    }
}
