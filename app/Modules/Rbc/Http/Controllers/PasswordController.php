<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Rbc\Services\LoginService;
use App\Modules\Rbc\Services\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-RBC-12: an internal account changes its own password. */
class PasswordController extends Controller
{
    public function update(Request $request, PasswordPolicy $passwords): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->jenis_pengesahan !== 'dalaman', 422, __('rbc::rbc.kata_laluan.akaun_sso'));

        $data = $request->validate([
            'kata_laluan_semasa' => ['required', 'string'],
            'kata_laluan' => ['required', 'string', 'confirmed', 'max:255'],
        ]);

        $passwords->change($user, $data['kata_laluan_semasa'], $data['kata_laluan']);

        if ($request->hasSession()) {
            $request->session()->forget(LoginService::MUST_CHANGE_KEY);
        }

        return response()->json(['message' => __('rbc::rbc.kata_laluan.berjaya')]);
    }
}
