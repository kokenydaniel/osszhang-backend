<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string',
            'principalAmount' => 'required|numeric|min:0',
            'annualInterestRate' => 'required|numeric|min:0|max:100',
            'purchaseDate' => 'required|date',
            'maturityDate' => 'nullable|date',
            'owner' => 'nullable|string',
            'countInSavings' => 'nullable|boolean',
            'currentValue' => 'nullable|numeric|min:0',
            'maturityAmount' => 'nullable|numeric|min:0',
            'nextPayoutAmount' => 'nullable|numeric|min:0',
            'nextPayoutDate' => 'nullable|date',
        ];
    }
}
