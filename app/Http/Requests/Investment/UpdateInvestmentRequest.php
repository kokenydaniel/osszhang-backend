<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'principalAmount' => 'sometimes|numeric|min:0',
            'annualInterestRate' => 'sometimes|numeric|min:0|max:100',
            'purchaseDate' => 'sometimes|date',
            'maturityDate' => 'nullable|date',
            'owner' => 'nullable|string',
            'countInSavings' => 'sometimes|boolean',
            'currentValue' => 'nullable|numeric|min:0',
            'maturityAmount' => 'nullable|numeric|min:0',
            'nextPayoutAmount' => 'nullable|numeric|min:0',
            'nextPayoutDate' => 'nullable|date',
        ];
    }
}
