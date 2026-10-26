<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Rbc\Services\PanduanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-GEN-08: the signed-in user's own interactive guide preferences. There
 * is deliberately no user id in the route — nobody can read or change
 * another user's preferences.
 */
class PanduanController extends Controller
{
    public function __construct(private PanduanService $panduan) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->panduan->forUser($this->user($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'aktif' => ['sometimes', 'boolean'],
            'selesai' => ['sometimes', 'array:kunci,versi'],
            'selesai.kunci' => ['required_with:selesai', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'selesai.versi' => ['required_with:selesai', 'string', 'max:20'],
            'set_semula' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->panduan->update($this->user($request), $validated)]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
