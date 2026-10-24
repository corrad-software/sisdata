<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Gis\Contracts\MapLayer;
use App\Modules\Gis\Models\Lapisan;
use App\Modules\Gis\Models\MetadataLapisan;
use App\Modules\Gis\Services\LayerRegistry;
use App\Modules\Gis\Services\LayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Layer administration: classification, publication, order, opacity (GIS-12/32) and metadata (GIS-29). */
class LapisanController extends GisController
{
    public function __construct(private LayerRegistry $registry, private LayerService $layers) {}

    public function index(): JsonResponse
    {
        $rows = array_map(function (MapLayer $layer) {
            $config = $this->layers->config($layer);
            $meta = MetadataLapisan::query()->where('kod_lapisan', $layer->key())->first();

            return [
                'kod' => $layer->key(), 'nama_asal' => $layer->label(), 'kebenaran' => $layer->permission(),
                'klasifikasi_minimum' => $this->layers->declaredLevel($layer), 'klasifikasi_berkesan' => $this->layers->level($layer),
                'konfigurasi' => $config->only(['nama', 'kumpulan', 'klasifikasi_keselamatan', 'status_terbit', 'urutan_paparan', 'ketelusan_lalai', 'papar_lalai', 'terbit_ogc', 'lapisan_awam']),
                'metadata' => $meta?->only(MetadataLapisan::FIELDS),
                'dikemas_kini_pada' => $config->updated_at?->toIso8601String(),
            ];
        }, array_values($this->registry->all()));
        usort($rows, fn ($a, $b) => $a['konfigurasi']['urutan_paparan'] <=> $b['konfigurasi']['urutan_paparan']);

        return response()->json(['data' => $rows]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $layer = $this->registry->get($key) ?? throw new NotFoundHttpException(__('gis::gis.lapisan.tidak_wujud'));
        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:150'],
            'klasifikasi_keselamatan' => ['sometimes', Rule::in(Lapisan::KLASIFIKASI)],
            'status_terbit' => ['sometimes', Rule::in(Lapisan::STATUS)],
            'urutan_paparan' => ['sometimes', 'integer', 'between:0,1000'],
            'ketelusan_lalai' => ['sometimes', 'numeric', 'between:0,1'],
            'papar_lalai' => ['sometimes', 'boolean'],
            'terbit_ogc' => ['sometimes', 'boolean'],
            'lapisan_awam' => ['sometimes', 'boolean'],
        ]);
        $declared = $this->layers->declaredLevel($layer);
        if (isset($data['klasifikasi_keselamatan']) && LayerService::LEVELS[$data['klasifikasi_keselamatan']] < LayerService::LEVELS[$declared]) {
            throw ValidationException::withMessages(['klasifikasi_keselamatan' => [__('gis::gis.lapisan.tidak_boleh_rendahkan', ['minimum' => $declared])]]);
        }
        $effective = $data['klasifikasi_keselamatan'] ?? $this->layers->level($layer);
        if (($data['lapisan_awam'] ?? false) && $effective !== 'awam') {
            throw ValidationException::withMessages(['lapisan_awam' => [__('gis::gis.lapisan.awam_mesti_awam')]]);
        }
        if (($data['terbit_ogc'] ?? false) && ! array_key_exists($key, (array) config('gis.ogc_layers'))) {
            throw ValidationException::withMessages(['terbit_ogc' => [__('gis::gis.lapisan.ogc_tidak_dikonfigurasi')]]);
        }
        $config = $this->layers->config($layer);
        $config->update($data + ['dikemas_kini_oleh' => $this->user($request)->id]);
        $this->layers->forget();

        return response()->json(['data' => $this->layers->describe($layer)]);
    }

    public function metadata(Request $request, string $key): JsonResponse
    {
        $this->registry->get($key) ?? throw new NotFoundHttpException(__('gis::gis.lapisan.tidak_wujud'));
        $data = $request->validate([
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'pemilik_data' => ['nullable', 'string', 'max:150'],
            'tarikh_kemas_kini' => ['nullable', 'date'],
            'status_pengesahan' => ['required', Rule::in(MetadataLapisan::STATUS)],
            'sistem_unjuran' => ['required', 'string', 'max:60'],
            'tahap_ketepatan' => ['nullable', 'string', 'max:100'],
            'had_penggunaan' => ['nullable', 'string', 'max:2000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $meta = MetadataLapisan::query()->updateOrCreate(['kod_lapisan' => $key], $data + ['dikemas_kini_oleh' => $this->user($request)->id]);

        return response()->json(['data' => $meta->only(MetadataLapisan::FIELDS)]);
    }

    /** SRS-GIS-29 report: metadata of every layer and accepted dataset. */
    public function metadataReport(): JsonResponse
    {
        $layers = MetadataLapisan::query()->whereNotNull('kod_lapisan')->get()->map(fn (MetadataLapisan $m) => ['jenis' => 'lapisan', 'kod' => $m->kod_lapisan] + $m->only(MetadataLapisan::FIELDS));
        $datasets = MetadataLapisan::query()->whereNotNull('id_import')
            ->join('gis_log_import', 'gis_log_import.id', '=', 'gis_metadata_lapisan.id_import')
            ->get(['gis_metadata_lapisan.*', 'gis_log_import.nama_set_data'])
            ->map(fn (MetadataLapisan $m) => ['jenis' => 'set_data', 'kod' => $m->id_import, 'nama' => $m->getAttribute('nama_set_data')] + $m->only(MetadataLapisan::FIELDS));

        return response()->json(['data' => [...$layers, ...$datasets]]);
    }
}
