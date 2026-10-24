<?php

namespace App\Modules\Per\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Per\Services\WakafIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/per/portal-wakaf/permohonan — push endpoint for Portal
 * Hartanah Wakaf Selangor (SRS-PER-25). Called with the portal's service
 * account token (permission per.portal_wakaf.cipta). Creates a Wakaf case in
 * "permohonan awam menunggu semakan"; never touches the master lot record.
 */
class PortalWakafController extends Controller
{
    public function store(Request $request, WakafIntake $intake): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $case = $intake->receive($request->all(), $user);

        return response()->json(['data' => [
            'no_kes' => $case->no_kes,
            'status' => $case->status_pemerolehan,
            'rujukan_portal' => $case->rujukan_luaran,
        ], 'message' => __('per::per.portal_diterima')], $case->wasRecentlyCreated ? 201 : 200);
    }
}
