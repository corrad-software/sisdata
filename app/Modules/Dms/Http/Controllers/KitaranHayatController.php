<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Dms\Models\DokumenPelupusan;
use App\Modules\Dms\Services\Lifecycle\DisposalService;
use App\Modules\Dms\Services\Lifecycle\RetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** SRS-DMS-22 manual archive and SRS-DMS-23 disposal requests. */
class KitaranHayatController extends DmsController
{
    public function archive(Request $request, RetentionService $retention, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->visible($dokumen, $user);
        if (! in_array($doc->status, ['aktif', 'tamat'], true)) {
            throw new HttpException(409, __('dms::mesej.sudah_diarkib'));
        }

        $retention->archive($doc, $user, 'manual');

        return response()->json(['message' => __('dms::mesej.diarkib')]);
    }

    public function requestDisposal(Request $request, DisposalService $disposal, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->visible($dokumen, $user);
        $data = $request->validate(['justifikasi' => ['required', 'string', 'min:10', 'max:2000']]);

        $item = $disposal->request($doc, $user, $data['justifikasi']);

        return response()->json(['message' => __('dms::mesej.pelupusan_dimohon'), 'data' => [
            'id' => $item->id, 'status' => $item->status, 'id_aliran_kerja' => $item->id_aliran_kerja,
        ]], 201);
    }

    public function disposals(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $items = DokumenPelupusan::query()->with(['dokumen.kategori', 'pemohon:id,name'])->latest()->limit(200)->get()
            ->filter(fn (DokumenPelupusan $p) => $this->access->canView($user, $p->dokumen))
            ->map(fn (DokumenPelupusan $p) => [
                'id' => $p->id,
                'dokumen' => ['id' => $p->dokumen->id, 'no_rujukan' => $p->dokumen->no_rujukan, 'tajuk' => $p->dokumen->tajuk, 'status' => $p->dokumen->status],
                'justifikasi' => $p->justifikasi,
                'status' => $p->status,
                'id_aliran_kerja' => $p->id_aliran_kerja,
                'pemohon' => $p->pemohon?->name,
                'dimohon_pada' => $p->created_at->toIso8601String(),
                'tarikh_keputusan' => $p->tarikh_keputusan?->toIso8601String(),
                'catatan_keputusan' => $p->catatan_keputusan,
            ])
            ->values();

        return response()->json(['data' => $items]);
    }
}
