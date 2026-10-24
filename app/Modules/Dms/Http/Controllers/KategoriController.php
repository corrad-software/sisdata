<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Models\DokumenMedanMetadata;
use App\Modules\Dms\Services\Upload\FileTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SRS-DMS-05/11/21: categories (allowed types, size limit, retention) and
 * their extra metadata fields — added, changed or deactivated by the
 * Application Administrator, no code change. Changes are model changes,
 * audited by MOD-AUD.
 */
class KategoriController extends DmsController
{
    public function index(Request $request): JsonResponse
    {
        $all = $request->boolean('semua') && $this->access->allows($this->user($request), 'dms.konfigurasi.lihat');

        $kategori = DokumenKategori::query()
            ->when(! $all, fn ($q) => $q->where('aktif', true))
            ->withCount('medan')
            ->orderBy('nama')
            ->get();

        $fields = DokumenMedanMetadata::query()->when(! $all, fn ($q) => $q->where('aktif', true))->orderBy('susunan')->orderBy('id')->get();

        return response()->json(['data' => $kategori->map(fn (DokumenKategori $k) => [
            ...$this->present($k),
            'medan' => $fields->filter(fn (DokumenMedanMetadata $f) => $f->kategori_id === null || $f->kategori_id === $k->id)
                ->map(fn (DokumenMedanMetadata $f) => $this->presentField($f))->values(),
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $kategori = DokumenKategori::query()->create($data);

        return response()->json(['data' => $this->present($kategori)], 201);
    }

    public function update(Request $request, DokumenKategori $kategori): JsonResponse
    {
        $kategori->fill($this->validated($request, $kategori))->save();

        return response()->json(['data' => $this->present($kategori)]);
    }

    public function fields(Request $request): JsonResponse
    {
        $request->validate(['kategori_id' => ['nullable', 'integer']]);
        $fields = $request->filled('kategori_id')
            ? DokumenMedanMetadata::forKategori((int) $request->integer('kategori_id'))
            : DokumenMedanMetadata::query()->orderBy('susunan')->orderBy('id')->get();

        return response()->json(['data' => $fields->map(fn (DokumenMedanMetadata $f) => $this->presentField($f))->values()]);
    }

    public function storeField(Request $request): JsonResponse
    {
        $field = DokumenMedanMetadata::query()->create($this->validatedField($request, null));

        return response()->json(['data' => $this->presentField($field)], 201);
    }

    public function updateField(Request $request, DokumenMedanMetadata $medan): JsonResponse
    {
        $medan->fill($this->validatedField($request, $medan))->save();

        return response()->json(['data' => $this->presentField($medan)]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?DokumenKategori $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';

        return $request->validate([
            'kod' => [$required, 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('dokumen_kategori', 'kod')->ignore($existing?->id)],
            'nama' => [$required, 'string', 'max:255'],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'jenis_fail_dibenarkan' => ['sometimes', 'nullable', 'array'],
            'jenis_fail_dibenarkan.*' => ['string', Rule::in(array_values(array_unique(array_column(FileTypes::TYPES, 0))))],
            'had_saiz_mb' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5120'],
            'tempoh_retensi_bulan' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1200'],
            'tempoh_arkib_bulan' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1200'],
            'tindakan_selepas_arkib' => ['sometimes', Rule::in(DokumenKategori::TINDAKAN)],
            'tempoh_amaran_luput_hari' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'klasifikasi_lalai' => ['sometimes', Rule::in(Dokumen::KLASIFIKASI)],
            'aktif' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedField(Request $request, ?DokumenMedanMetadata $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';
        $kategoriId = $request->input('kategori_id', $existing?->kategori_id);

        $data = $request->validate([
            'kategori_id' => ['sometimes', 'nullable', 'integer', 'exists:dokumen_kategori,id'],
            'kod' => [$required, 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('dokumen_medan_metadata', 'kod')->where(fn ($q) => $kategoriId === null ? $q->whereNull('kategori_id') : $q->where('kategori_id', $kategoriId))->ignore($existing?->id),
                Rule::notIn(['metadata', 'tajuk', 'kata_kunci', 'tarikh_dokumen', 'tarikh_luput', 'tahun', 'kod_daerah', 'kod_mukim', 'pemilik_proses_id', 'modul_sumber', 'pautan_induk'])],
            'label' => [$required, 'string', 'max:255'],
            'jenis' => [$required, Rule::in(DokumenMedanMetadata::JENIS)],
            'pilihan' => ['nullable', 'array', 'required_if:jenis,senarai'],
            'pilihan.*' => ['string', 'max:255'],
            'wajib' => ['sometimes', 'boolean'],
            'aktif' => ['sometimes', 'boolean'],
            'susunan' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        return $data;
    }

    /** @return array<string, mixed> */
    private function present(DokumenKategori $k): array
    {
        return [
            'id' => $k->id,
            'kod' => $k->kod,
            'nama' => $k->nama,
            'keterangan' => $k->keterangan,
            'jenis_fail_dibenarkan' => $k->jenis_fail_dibenarkan,
            'had_saiz_mb' => $k->had_saiz_mb,
            'tempoh_retensi_bulan' => $k->tempoh_retensi_bulan,
            'tempoh_arkib_bulan' => $k->tempoh_arkib_bulan,
            'tindakan_selepas_arkib' => $k->tindakan_selepas_arkib,
            'tempoh_amaran_luput_hari' => $k->tempoh_amaran_luput_hari,
            'klasifikasi_lalai' => $k->klasifikasi_lalai,
            'aktif' => $k->aktif,
        ];
    }

    /** @return array<string, mixed> */
    private function presentField(DokumenMedanMetadata $f): array
    {
        return [
            'id' => $f->id,
            'kategori_id' => $f->kategori_id,
            'kod' => $f->kod,
            'label' => $f->label,
            'jenis' => $f->jenis,
            'pilihan' => $f->pilihan,
            'wajib' => $f->wajib,
            'aktif' => $f->aktif,
            'susunan' => $f->susunan,
        ];
    }
}
