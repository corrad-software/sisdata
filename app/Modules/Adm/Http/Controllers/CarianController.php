<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Jobs\IndeksSemulaCarian;
use App\Modules\Adm\Models\IndeksCarian;
use App\Modules\Adm\Models\SinonimCarian;
use App\Modules\Adm\Services\Carian\PendaftaranCarian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-ADM-21 global search API (any signed-in user; providers filter by the
 * user's access), SRS-ADM-22 deep-link resolver, SRS-ADM-20 settings.
 */
class CarianController extends Controller
{
    public function __construct(private PendaftaranCarian $registry) {}

    public function search(Request $request, Parameters $parameters): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'entiti' => ['nullable', 'array'],
            'entiti.*' => ['string', 'max:60'],
            'tapis' => ['nullable', 'array'],
            'tapis.*' => ['nullable', 'string', 'max:200'],
            'had' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($data['had'] ?? $parameters->get('adm.carian_had_hasil', 10));
        $filters = array_filter($data['tapis'] ?? [], fn ($v) => $v !== null && $v !== '');
        $groups = $this->registry->search((string) ($data['q'] ?? ''), $request->user(), $filters, $data['entiti'] ?? null, $limit);

        return response()->json([
            'data' => $groups,
            'jumlah' => array_sum(array_map(fn ($g) => count($g['hasil']), $groups)),
            'istilah' => ($data['q'] ?? '') !== '' ? $this->registry->expand((string) $data['q']) : [],
        ]);
    }

    public function resolve(Request $request, string $entiti, string $id): JsonResponse
    {
        $link = $this->registry->resolve($entiti, $id, $request->user());

        // 404 for "no such record" and "no access" alike — don't leak existence.
        abort_if($link === null, 404, __('Record not found.'));

        return response()->json(['data' => ['entiti' => $entiti, 'id' => $id, 'pautan' => $link]]);
    }

    public function settings(): JsonResponse
    {
        $this->registry->syncSettings();
        $registered = array_keys($this->registry->providers());

        return response()->json([
            'data' => IndeksCarian::query()->orderBy('entiti')->get()->map(fn (IndeksCarian $i) => [
                ...$i->toArray(),
                'berdaftar' => in_array($i->entiti, $registered, true),
            ]),
            'sinonim' => SinonimCarian::query()->orderBy('istilah')->get(),
        ]);
    }

    public function updateSetting(Request $request, IndeksCarian $indeks): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'medan' => ['sometimes', 'array', 'max:50'],
            'medan.*' => ['string', 'max:60', 'regex:/^[a-z0-9_.]+$/'],
            'pemberat' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'aktif' => ['sometimes', 'boolean'],
            'justifikasi' => ['nullable', 'string', 'max:1000'],
        ]);
        $indeks->update([...$data, 'dikemas_kini_oleh' => $request->user()->id]);

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $indeks]);
    }

    public function reindex(IndeksCarian $indeks): JsonResponse
    {
        $indeks->forceFill(['status_indeks' => 'beratur'])->saveQuietly();
        IndeksSemulaCarian::dispatch($indeks->entiti);

        return response()->json(['message' => __('adm::adm.carian.reindex_queued'), 'data' => $indeks->fresh()], 202);
    }

    public function storeSynonym(Request $request): JsonResponse
    {
        $data = $request->validate([
            'istilah' => ['required', 'string', 'max:120'],
            'sinonim' => ['required', 'array', 'min:1', 'max:20'],
            'sinonim.*' => ['string', 'max:120'],
        ], [], (array) __('adm::adm.attributes'));

        $row = SinonimCarian::query()->updateOrCreate(
            ['istilah' => mb_strtolower(trim($data['istilah']))],
            ['sinonim' => array_values(array_unique(array_map(fn ($s) => mb_strtolower(trim($s)), $data['sinonim']))), 'dikemas_kini_oleh' => $request->user()->id],
        );

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $row], 201);
    }

    public function destroySynonym(SinonimCarian $sinonim): JsonResponse
    {
        $sinonim->delete();

        return response()->json(null, 204);
    }
}
