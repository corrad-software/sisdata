<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Models\User;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenKebenaran;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-DMS-19: explicit per-user / per-role grants on a Sulit or Rahsia
 * document. Managed by holders of dms.kebenaran.kemas_kini or the uploader.
 * Grant changes are model changes, so MOD-AUD records them.
 */
class KebenaranController extends DmsController
{
    public function index(Request $request, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->manageable($dokumen, $user);

        return response()->json([
            'data' => $doc->kebenaran()->orderBy('id')->get()->map(fn (DokumenKebenaran $k) => $this->present($k))->values(),
        ]);
    }

    public function store(Request $request, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->manageable($dokumen, $user);
        if (! $doc->isTerhad()) {
            throw new HttpException(422, __('dms::mesej.tidak_terhad'));
        }

        $data = $request->validate([
            'jenis_penerima' => ['required', Rule::in(['pengguna', 'peranan'])],
            'penerima' => ['required', 'string', 'max:64'],
            'tamat_pada' => ['nullable', 'date', 'after:now'],
        ]);
        if ($data['jenis_penerima'] === 'pengguna') {
            $request->validate(['penerima' => ['integer', 'exists:users,id']]);
        }

        $grant = DokumenKebenaran::query()->updateOrCreate(
            ['dokumen_id' => $doc->id, 'jenis_penerima' => $data['jenis_penerima'], 'penerima' => (string) $data['penerima']],
            ['diberi_oleh' => $user->id, 'tamat_pada' => $data['tamat_pada'] ?? null],
        );

        return response()->json(['message' => __('dms::mesej.kebenaran_ditambah'), 'data' => $this->present($grant)], 201);
    }

    public function destroy(Request $request, string $dokumen, int $kebenaran): JsonResponse
    {
        $doc = $this->manageable($dokumen, $this->user($request));
        $doc->kebenaran()->whereKey($kebenaran)->firstOrFail()->delete();

        return response()->json(['message' => __('dms::mesej.kebenaran_dibuang')]);
    }

    private function manageable(string $id, User $user): Dokumen
    {
        $doc = $this->visible($id, $user);
        if ($doc->id_pencipta !== $user->id && ! $this->access->allows($user, 'dms.kebenaran.kemas_kini')) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }

        return $doc;
    }

    /** @return array<string, mixed> */
    private function present(DokumenKebenaran $grant): array
    {
        $label = $grant->penerima;
        if ($grant->jenis_penerima === 'pengguna') {
            $label = User::query()->whereKey((int) $grant->penerima)->value('name') ?? $grant->penerima;
        }

        return [
            'id' => $grant->id,
            'jenis_penerima' => $grant->jenis_penerima,
            'penerima' => $grant->penerima,
            'label' => $label,
            'tamat_pada' => $grant->tamat_pada?->toIso8601String(),
            'diberi_pada' => $grant->created_at->toIso8601String(),
        ];
    }
}
