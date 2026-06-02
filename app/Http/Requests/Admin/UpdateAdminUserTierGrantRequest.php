<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserTierGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grant_tier' => ['nullable', 'in:pro,premium'],
            'permanent' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'grant_tier.in' => 'A grant csak Pro vagy Premium lehet.',
            'expires_at.after' => 'A lejárat csak jövőbeli dátum lehet.',
        ];
    }
}
