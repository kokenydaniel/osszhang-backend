<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalletManualBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->household_id !== null;
    }

    public function rules(): array
    {
        return [
            'manual_balance' => 'required|numeric',
        ];
    }
}
