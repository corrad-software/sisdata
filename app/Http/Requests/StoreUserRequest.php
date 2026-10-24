<?php

namespace App\Http\Requests;

use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Services\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /** Authorised by the route's `can:rbc.pengguna.cipta` middleware. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['password.required_unless' => __('validation.required', ['attribute' => __('validation.attributes.password')])];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            // SRS-RBC-11: SSO accounts get no password; internal ones must meet SRS-RBC-12.
            'jenis_pengesahan' => ['nullable', Rule::in(['dalaman', 'sso'])],
            'password' => ['nullable', 'required_unless:jenis_pengesahan,sso', 'prohibited_if:jenis_pengesahan,sso', 'string', 'max:255', app(PasswordPolicy::class)->rule()],
            'peranan' => ['nullable', 'array'],
            'peranan.*' => ['integer', Rule::exists('peranan', 'id')],
            // Deprecated: 'admin' maps to the Pentadbir Sistem role.
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'is_active' => ['boolean'],
            'status_akaun' => ['nullable', Rule::in(AccountStatus::ALL)],
            'no_pekerja' => ['nullable', 'string', 'max:50'],
            'bahagian' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'jawatan' => ['nullable', 'string', 'max:150'],
            'tarikh_mula_capaian' => ['nullable', 'date'],
            'tarikh_tamat_capaian' => ['nullable', 'date', 'after_or_equal:tarikh_mula_capaian'],
        ];
    }
}
