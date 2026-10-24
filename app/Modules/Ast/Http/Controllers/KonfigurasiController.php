<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-AST-03 configurable categories (cycle, revaluation threshold, inspection period, depreciation). */
class KonfigurasiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => AsetKategori::query()->orderBy('susunan')->orderBy('id')->get()->map(fn (AsetKategori $k) => [
            'id' => $k->id, 'kod' => $k->kod, 'nama_kategori' => $k->nama_kategori, 'jenis_lalai' => $k->jenis_lalai,
            'kitaran_penyelenggaraan_bulan' => $k->kitaran_penyelenggaraan_bulan, 'tempoh_ambang_penilaian_bulan' => $k->tempoh_ambang_penilaian_bulan,
            'tempoh_pemeriksaan_bulan' => $k->tempoh_pemeriksaan_bulan, 'hayat_guna_tahun' => $k->hayat_guna_tahun, 'kadar_nilai_sisa' => $k->kadar_nilai_sisa,
            'jenis_penyelenggaraan_lalai' => $k->jenis_penyelenggaraan_lalai, 'status_aktif' => $k->status_aktif, 'susunan' => $k->susunan,
            'bilangan_aset' => $k->newQuery()->getConnection()->table('aset_fizikal')->where('id_kategori', $k->id)->count(),
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $k = AsetKategori::query()->create($this->rules($request, null));

        return response()->json(['data' => ['id' => $k->id], 'message' => __('ast::ast.kategori_disimpan')], 201);
    }

    public function update(Request $request, int $kategori): JsonResponse
    {
        $k = AsetKategori::query()->findOrFail($kategori);
        $k->fill($this->rules($request, $k))->save();

        return response()->json(['data' => ['id' => $k->id], 'message' => __('ast::ast.kategori_disimpan')]);
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?AsetKategori $k): array
    {
        $req = $k ? 'sometimes' : 'required';

        return $request->validate([
            'kod' => [$k ? 'prohibited' : 'required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('aset_kategori', 'kod')],
            'nama_kategori' => [$req, 'string', 'max:120'],
            'jenis_lalai' => [$req, Rule::in(array_keys(Lalai::JENIS_ASET))],
            'kitaran_penyelenggaraan_bulan' => [$req, 'integer', 'between:1,240'],
            'tempoh_ambang_penilaian_bulan' => [$req, 'integer', 'between:1,600'],
            'tempoh_pemeriksaan_bulan' => [$req, 'integer', 'between:1,120'],
            'hayat_guna_tahun' => [$req, 'integer', 'between:1,200'],
            'kadar_nilai_sisa' => [$req, 'numeric', 'between:0,100'],
            'jenis_penyelenggaraan_lalai' => ['sometimes', 'array', 'max:20'],
            'jenis_penyelenggaraan_lalai.*' => [Rule::in(array_keys(Lalai::JENIS_PENYELENGGARAAN))],
            'status_aktif' => ['sometimes', 'boolean'],
            'susunan' => ['sometimes', 'integer', 'between:0,999'],
        ]);
    }
}
