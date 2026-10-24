<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wfe\Services\Reporting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** SRS-WFE-13 (dashboard) and SRS-WFE-23 (performance report). */
class PemantauanController extends Controller
{
    public function __construct(private Reporting $reporting) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->reporting->dashboard()]);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'hingga' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'kod_aliran' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $this->reporting->performance(
            isset($data['dari']) ? Carbon::parse($data['dari'], 'Asia/Kuala_Lumpur')->startOfDay()->utc() : null,
            isset($data['hingga']) ? Carbon::parse($data['hingga'], 'Asia/Kuala_Lumpur')->endOfDay()->utc() : null,
            $data['kod_aliran'] ?? null,
        )]);
    }
}
