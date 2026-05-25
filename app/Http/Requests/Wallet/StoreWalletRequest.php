<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->household_id !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'isShared' => 'sometimes|boolean',
            'ownerId' => 'nullable|integer|exists:users,id',
        ];
    }
}
