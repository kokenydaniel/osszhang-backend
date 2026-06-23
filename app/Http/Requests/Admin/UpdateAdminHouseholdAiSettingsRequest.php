<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminHouseholdAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usage_blocked' => ['sometimes', 'boolean'],
            'monthly_token_limit' => ['nullable', 'integer', 'min:1000', 'max:10000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'monthly_token_limit.min' => 'A havi limit legalább 1000 token lehet.',
            'monthly_token_limit.max' => 'A havi limit legfeljebb 10 000 000 token lehet.',
        ];
    }
}
