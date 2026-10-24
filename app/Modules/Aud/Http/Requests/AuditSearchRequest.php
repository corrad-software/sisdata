<?php

namespace App\Modules\Aud\Http\Requests;

use App\Modules\Aud\Services\AuditExporter;
use App\Modules\Aud\Services\AuditSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware does the permission check
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_pengguna' => ['nullable', 'integer', 'min:1'],
            'pengguna' => ['nullable', 'string', 'max:255'],
            'peranan' => ['nullable', 'string', 'max:100'],
            'modul' => ['nullable', 'string', 'max:20'],
            'nama_entiti' => ['nullable', 'string', 'max:100'],
            'id_rekod' => ['nullable', 'string', 'max:100'],
            'jenis_tindakan' => ['nullable', 'string', 'max:40'],
            'alamat_ip' => ['nullable', 'ip'],
            'status_transaksi' => ['nullable', 'string', 'max:20'],
            'saluran_capaian' => ['nullable', 'string', 'max:20'],
            'klasifikasi' => ['nullable', 'string', 'max:20'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'hingga' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'sumber' => ['nullable', Rule::in([AuditSearch::SUMBER_DALAM_TALIAN, AuditSearch::SUMBER_ARKIB, AuditSearch::SUMBER_SEMUA])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', Rule::in(AuditExporter::FORMATS)],
        ];
    }

    /**
     * Only the search criteria, without empty values.
     *
     * @return array<string, mixed>
     */
    public function criteria(): array
    {
        return array_filter(
            $this->safe()->only(AuditSearch::FILTERS),
            fn ($v) => $v !== null && $v !== '',
        );
    }

    public function wantsArchive(): bool
    {
        return in_array($this->input('sumber'), [AuditSearch::SUMBER_ARKIB, AuditSearch::SUMBER_SEMUA], true);
    }
}
