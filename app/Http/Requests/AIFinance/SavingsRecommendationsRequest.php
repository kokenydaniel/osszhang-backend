<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;

class SavingsRecommendationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goals' => 'required|array|min:1',
            'goals.*.name' => 'required|string',
            'goals.*.target_amount' => 'required|numeric|min:1',
            'goals.*.target_date' => 'required|date',
            'goals.*.priority' => 'nullable|integer|min:1|max:5',
            'constraints.min_buffer' => 'nullable|numeric|min:0',
            'wallet_id' => 'sometimes|integer|exists:wallets,id',
        ];
    }
}
