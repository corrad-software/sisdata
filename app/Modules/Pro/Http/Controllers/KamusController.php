<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Modules\Pro\Models\KamusData;
use App\Modules\Pro\Models\KlasifikasiData;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Services\KamusDataService;
use App\Modules\Pro\Services\Klasifikasi;
use App\Modules\Pro\Services\Pengeksport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-PRO-05 data dictionary (shown + exportable), SRS-PRO-04 physical model, SRS-PRO-14 classification register. */
class KamusController extends ProController
{
    public function __construct(private KamusDataService $dictionary) {}

    public function tables(): JsonResponse
    {
        return response()->json(['data' => $this->dictionary->tables()]);
    }

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'jadual' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:100'],
            'klasifikasi' => ['nullable', Rule::in(LotTanah::KLASIFIKASI)],
        ]);

        return response()->json(['data' => $this->dictionary->search($f['q'] ?? null, $f['jadual'] ?? null, $f['klasifikasi'] ?? null)]);
    }

    public function export(Request $request, Pengeksport $exporter): Response
    {
        $f = $request->validate([
            'format' => ['required', Rule::in(Pengeksport::FORMATS)],
            'jadual' => ['nullable', 'string', 'max:64'],
        ]);

        return $exporter->download($this->dictionary->columns($f['jadual'] ?? null), [
            'nama_jadual' => 'Jadual', 'nama_medan' => 'Nama Teknikal', 'nama_perniagaan' => 'Nama Perniagaan', 'definisi' => 'Definisi',
            'jenis_data' => 'Jenis Data', 'saiz' => 'Saiz', 'format' => 'Format', 'nilai_dibenarkan' => 'Nilai Dibenarkan', 'nilai_lalai' => 'Nilai Lalai',
            'wajib' => 'Wajib', 'kunci' => 'Kunci', 'data_owner' => 'Data Owner', 'data_steward' => 'Data Steward', 'klasifikasi' => 'Klasifikasi',
        ], $f['format'], 'kamus-data', (string) __('pro::pro.eksport.tajuk_kamus'));
    }

    public function upsert(Request $request, Klasifikasi $klasifikasi): JsonResponse
    {
        $data = $request->validate([
            'nama_jadual' => ['required', 'string', 'max:64'],
            'nama_medan' => ['nullable', 'string', 'max:64'],
            'nama_perniagaan' => ['required', 'string', 'max:150'],
            'definisi' => ['nullable', 'string', 'max:2000'],
            'format' => ['nullable', 'string', 'max:150'],
            'nilai_dibenarkan' => ['nullable', 'string', 'max:500'],
            'wajib' => ['nullable', 'boolean'],
            'data_owner' => ['nullable', 'string', 'max:150'],
            'data_steward' => ['nullable', 'string', 'max:150'],
            'klasifikasi' => ['required', Rule::in(LotTanah::KLASIFIKASI)],
        ]);
        $exists = collect($this->dictionary->columns($data['nama_jadual'], true))->contains(fn ($c) => $data['nama_medan'] === null || $c['nama_medan'] === $data['nama_medan']);
        abort_unless($exists, 422, __('pro::pro.kamus.medan_tiada'));

        $row = KamusData::query()->updateOrCreate(
            ['nama_jadual' => $data['nama_jadual'], 'nama_medan' => $data['nama_medan'] ?? null],
            $data,
        );
        $klasifikasi->flush();

        return response()->json(['message' => __('pro::pro.kamus.disimpan'), 'data' => $row]);
    }

    public function physical(): JsonResponse
    {
        return response()->json(['data' => $this->dictionary->physicalModel()]);
    }

    public function classification(): JsonResponse
    {
        return response()->json(['data' => KlasifikasiData::query()->orderBy('id')->get()]);
    }
}
