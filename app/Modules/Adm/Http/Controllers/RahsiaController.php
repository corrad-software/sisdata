<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\Rahsia;
use App\Modules\Adm\Services\KelulusanKonfigurasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-ADM-28 secret store: list keys (never values) and request a new value.
 * Setting a secret is a security setting → second-admin approval; the value
 * waits encrypted in the approval row and is never shown again.
 */
class RahsiaController extends Controller
{
    public function __construct(private KelulusanKonfigurasi $approvals) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => Rahsia::query()->orderBy('kunci')->get(['id', 'kunci', 'keterangan', 'updated_at'])]);
    }

    public function update(Request $request, string $kunci): JsonResponse
    {
        abort_unless((bool) preg_match('/^[a-z0-9_.-]{2,120}$/', $kunci), 404);

        $data = $request->validate([
            'nilai' => ['required', 'string', 'max:10000'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'justifikasi' => ['required', 'string', 'max:1000'],
        ], [], (array) __('adm::adm.attributes'));

        $exists = Rahsia::query()->where('kunci', $kunci)->exists();
        $approval = $this->approvals->request(
            'rahsia',
            $kunci,
            $exists ? ['nilai' => KelulusanKonfigurasi::SECRET_MASK] : null,
            ['keterangan' => $data['keterangan'] ?? null],
            $data['justifikasi'],
            $request->user(),
            $data['nilai'],
        );

        return response()->json(['message' => __('adm::adm.kelulusan.submitted'), 'data' => KelulusanController::present($approval)], 202);
    }
}
