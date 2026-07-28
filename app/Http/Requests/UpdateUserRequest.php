<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Accepts the id of the user being updated so the email uniqueness
     * check can ignore that record. Also called directly from the
     * UserManagement Livewire component (outside the HTTP lifecycle).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(?int $userId = null): array
    {
        $userId ??= $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'is_active' => ['boolean'],
        ];
    }
}
