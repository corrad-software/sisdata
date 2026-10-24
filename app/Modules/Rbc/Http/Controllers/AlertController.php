<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Models\AmaranKeselamatan;
use App\Modules\Rbc\Support\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-RBC-22: suspicious-activity alerts for system administrators. */
class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = AmaranKeselamatan::query()->with('user:id,name,email')
            ->when($request->boolean('belum_disemak'), fn ($q) => $q->whereNull('disemak_pada'))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->string('jenis')))
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (AmaranKeselamatan $a) => Presenters::alert($a)),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage(),
                'belum_disemak' => AmaranKeselamatan::query()->whereNull('disemak_pada')->count(),
            ],
        ]);
    }

    public function acknowledge(Request $request, AmaranKeselamatan $amaran): JsonResponse
    {
        $amaran->forceFill(['disemak_pada' => now(), 'disemak_oleh' => $request->user()->id])->save();

        return response()->json(['data' => Presenters::alert($amaran->load('user:id,name,email'))]);
    }
}
