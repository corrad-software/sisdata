<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_title' => ['required', 'string', 'max:100'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
