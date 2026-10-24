<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Services\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /** Authorised by the route's `can:rbc.pengguna.kemas_kini` middleware. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'max:255', app(PasswordPolicy::class)->rule($user instanceof User ? $user : null)],
            'peranan' => ['nullable', 'array'],
            'peranan.*' => ['integer', Rule::exists('peranan', 'id')],
            // Deprecated: 'admin' maps to the Pentadbir Sistem role.
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'is_active' => ['boolean'],
            'status_akaun' => ['nullable', Rule::in(AccountStatus::ALL)],
            'sebab' => ['nullable', 'string', 'max:255'],
            'no_pekerja' => ['nullable', 'string', 'max:50'],
            'bahagian' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'jawatan' => ['nullable', 'string', 'max:150'],
            'tarikh_mula_capaian' => ['nullable', 'date'],
            'tarikh_tamat_capaian' => ['nullable', 'date', 'after_or_equal:tarikh_mula_capaian'],
        ];
    }
}
