<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Http\Middleware\TokenPentadbir;
use App\Modules\Adm\Services\Siem\LogKeselamatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-ADM-05: admin-scoped API tokens for automation (bulk code import,
 * rescheduling an integration…). A token carries only the `adm:pentadbir`
 * ability and the holder's own permissions; it can be minted only from an
 * interactive session, never with another token.
 */
class TokenApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $user->tokens()->orderByDesc('id')->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])]);
    }

    public function store(Request $request, LogKeselamatan $security): JsonResponse
    {
        $user = $request->user();
        if ($request->bearerToken() !== null) {
            throw new AuthorizationException(__('adm::adm.token.session_only'));
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'tamat_hari' => ['nullable', 'integer', 'min:1', 'max:365'],
        ], [], (array) __('adm::adm.attributes'));

        $token = $user->createToken($data['nama'], [TokenPentadbir::ABILITY], now()->addDays((int) ($data['tamat_hari'] ?? 90)));
        $security->rekod('token_api_dicipta', 'success', $user->id, 'Token API pentadbiran dicipta', ['id_token' => $token->accessToken->getKey()]);

        return response()->json([
            'message' => __('adm::adm.token.created'),
            'data' => ['id' => $token->accessToken->getKey(), 'token' => $token->plainTextToken, 'tamat_pada' => $token->accessToken->getAttribute('expires_at')?->toIso8601String()],
        ], 201);
    }

    public function destroy(Request $request, int $id, LogKeselamatan $security): JsonResponse
    {
        $user = $request->user();
        $deleted = $user->tokens()->whereKey($id)->delete();
        abort_if($deleted === 0, 404);
        $security->rekod('token_api_dibatalkan', 'success', $user->id, 'Token API pentadbiran dibatalkan', ['id_token' => $id]);

        return response()->json(null, 204);
    }
}
