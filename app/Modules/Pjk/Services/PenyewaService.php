<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Support\Kod;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** SRS-PJK-01: tenant register (Sulit). */
class PenyewaService
{
    /** @param array<string, mixed> $input */
    public function create(array $input, User $user): Penyewa
    {
        return Penyewa::query()->create($this->validate($input, null) + ['id_pencipta' => $user->id, 'klasifikasi' => 'sulit']);
    }

    /** @param array<string, mixed> $input */
    public function update(Penyewa $penyewa, array $input): Penyewa
    {
        $penyewa->fill($this->validate($input + $penyewa->only(['jenis_entiti', 'nama']), $penyewa))->save();

        return $penyewa;
    }

    /**
     * Reuse the tenant with the same identity number, or register a new one
     * (portal / counter applications).
     *
     * @param  array<string, mixed>  $input
     */
    public function findOrCreate(array $input, User $user): Penyewa
    {
        $data = $this->validate($input, null, false);
        $existing = Penyewa::query()
            ->when(! empty($data['no_pengenalan']), fn ($q) => $q->where('no_pengenalan', $data['no_pengenalan']))
            ->when(empty($data['no_pengenalan']) && ! empty($data['no_pendaftaran_syarikat']), fn ($q) => $q->where('no_pendaftaran_syarikat', $data['no_pendaftaran_syarikat']))
            ->when(empty($data['no_pengenalan']) && empty($data['no_pendaftaran_syarikat']), fn ($q) => $q->whereRaw('1 = 0'))
            ->first();

        return $existing ?? Penyewa::query()->create($data + ['id_pencipta' => $user->id, 'klasifikasi' => 'sulit']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input, ?Penyewa $penyewa, bool $unique = true): array
    {
        if (isset($input['no_pengenalan']) && is_string($input['no_pengenalan'])) {
            $digits = preg_replace('/\D/', '', $input['no_pengenalan']) ?? '';
            if (strlen($digits) === 12) {
                $input['no_pengenalan'] = substr($digits, 0, 6).'-'.substr($digits, 6, 2).'-'.substr($digits, 8);
            }
        }
        $uniq = fn (string $col) => $unique ? [Rule::unique('penyewa', $col)->ignore($penyewa?->id)->whereNull('deleted_at')] : [];

        return Validator::make($input, [
            'jenis_entiti' => ['required', Rule::in(array_keys(Kod::JENIS_ENTITI))],
            'nama' => ['required', 'string', 'max:200'],
            'no_pengenalan' => ['nullable', 'required_if:jenis_entiti,individu', 'regex:/^\d{6}-\d{2}-\d{4}$/', ...$uniq('no_pengenalan')],
            'no_pendaftaran_syarikat' => ['nullable', 'required_if:jenis_entiti,syarikat', 'string', 'max:30', 'regex:/^[0-9A-Z\-]+$/', ...$uniq('no_pendaftaran_syarikat')],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'poskod' => ['nullable', 'regex:/^\d{5}$/'],
            'telefon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'emel' => ['nullable', 'email', 'max:150'],
        ], [
            'no_pengenalan.regex' => __('pjk::pjk.ralat.format_kp'),
        ], [
            'jenis_entiti' => 'jenis entiti', 'nama' => 'nama', 'no_pengenalan' => 'no. kad pengenalan',
            'no_pendaftaran_syarikat' => 'no. pendaftaran syarikat', 'telefon' => 'telefon', 'emel' => 'e-mel', 'poskod' => 'poskod',
        ])->validate();
    }
}
