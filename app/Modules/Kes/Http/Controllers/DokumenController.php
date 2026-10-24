<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Modules\Kes\Http\Present;
use App\Modules\Kes\Models\DokumenKes;
use App\Modules\Kes\Services\DokumenService;
use App\Modules\Kes\Support\Akses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-KES-12/13: legal documents through MOD-DMS; downloads logged with IP and time. */
class DokumenController extends KesBaseController
{
    public function __construct(Akses $akses, private DokumenService $service, private Present $present)
    {
        parent::__construct($akses);
    }

    public function index(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);

        return response()->json(['data' => DokumenKes::query()->where('kes_mahkamah_id', $k->id)->orderByDesc('tarikh_muat_naik')->get()->map(fn ($d) => $this->present->dokumen($d))->values()]);
    }

    public function store(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['fail' => ['required', 'file'], 'kategori_dokumen' => ['required', Rule::in(DokumenKes::KATEGORI)]]);
        $doc = $this->service->muatNaik($k, $request->file('fail'), $d['kategori_dokumen'], $user);

        return response()->json(['data' => $this->present->dokumen($doc), 'message' => __('kes::kes.mesej.dokumen')], 201);
    }

    public function muatTurun(Request $request, string $kes, int $dokumen): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $doc = DokumenKes::query()->where('kes_mahkamah_id', $k->id)->findOrFail($dokumen);
        $url = $this->service->muatTurun($k, $doc, $this->user($request));
        abort_if($url === null, 403, (string) __('kes::kes.ralat.dokumen_tiada_akses'));

        return response()->json(['data' => ['url' => $url]]);
    }
}
