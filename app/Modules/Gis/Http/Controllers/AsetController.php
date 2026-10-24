<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Gis\Models\AsetTitik;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Physical asset points (mandatory layer 10) — placed from the map or the
 * field (GPS), until MOD-AST owns the asset register. Scoped by daerah via
 * ScopedByAccess; audited by MOD-AUD (geometry changes included, GIS-31).
 */
class AsetController extends GisController
{
    /** @return array<string, mixed> */
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'nama' => [$req, 'string', 'max:150'],
            'kategori' => [$req, Rule::in(AsetTitik::KATEGORI)],
            'status_keadaan' => ['sometimes', Rule::in(AsetTitik::KEADAAN)],
            'id_lot' => ['nullable', 'uuid'],
            'lng' => [$req, 'numeric', 'between:99,120'],
            'lat' => [$req, 'numeric', 'between:0.5,7.6'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function store(Request $request, LotRegistry $lots): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $user = $this->user($request);
        $place = $this->place((float) $data['lng'], (float) $data['lat']);
        if (! empty($data['id_lot'])) {
            $lot = $lots->find($data['id_lot'], $user) ?? throw ValidationException::withMessages(['id_lot' => [__('gis::gis.aset.lot_tidak_wujud')]]);
            $place = ['kod_daerah' => $lot['kod_daerah'], 'kod_mukim' => $lot['kod_mukim']];
        }
        $asset = DB::transaction(function () use ($data, $user, $place) {
            $asset = AsetTitik::query()->create([
                'nama' => $data['nama'], 'kategori' => $data['kategori'], 'status_keadaan' => $data['status_keadaan'] ?? 'baik',
                'id_lot' => $data['id_lot'] ?? null, 'catatan' => $data['catatan'] ?? null, 'id_pengguna' => $user->id,
                'geom' => $this->point((float) $data['lng'], (float) $data['lat']),
            ] + $place);

            return $asset;
        });

        return response()->json(['data' => ['id' => $asset->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asset = AsetTitik::query()->findOrFail($id);
        $data = $request->validate($this->rules(false));
        $changes = array_intersect_key($data, array_flip(['nama', 'kategori', 'status_keadaan', 'id_lot', 'catatan']));
        if (isset($data['lng'], $data['lat'])) {
            $changes['geom'] = $this->point((float) $data['lng'], (float) $data['lat']);
            $changes += $this->place((float) $data['lng'], (float) $data['lat']);
        }
        $asset->update($changes);

        return response()->json(['data' => ['id' => $asset->id]]);
    }

    public function destroy(int $id): JsonResponse
    {
        AsetTitik::query()->findOrFail($id)->delete();

        return response()->json(['message' => __('gis::gis.aset.dipadam')]);
    }

    private function point(float $lng, float $lat): string
    {
        return (string) DB::selectOne('SELECT ST_AsHEXEWKB(ST_SetSRID(ST_MakePoint(?, ?), 4326)) AS g', [$lng, $lat])->g;
    }

    /** @return array{kod_daerah: string|null, kod_mukim: string|null} */
    private function place(float $lng, float $lat): array
    {
        $row = DB::selectOne(
            "SELECT (SELECT kod FROM ref_sempadan WHERE jenis = 'daerah' AND ST_Contains(geometri, p) LIMIT 1) AS d,
                    (SELECT kod FROM ref_sempadan WHERE jenis = 'mukim' AND ST_Contains(geometri, p) LIMIT 1) AS m
             FROM (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS p) x",
            [$lng, $lat],
        );

        return ['kod_daerah' => $row?->d ? substr((string) $row->d, 0, 4) : null, 'kod_mukim' => $row?->m ? substr((string) $row->m, 0, 6) : null];
    }
}
