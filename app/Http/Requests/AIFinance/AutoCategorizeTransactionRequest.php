<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;

class AutoCategorizeTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:255',
            'type' => 'nullable|in:income,expense',
            'amount' => 'nullable|numeric',
            'candidate_categories' => 'required|array|min:1',
            'candidate_categories.*' => 'string',
        ];
    }
}
