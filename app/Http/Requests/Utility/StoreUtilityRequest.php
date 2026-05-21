<?php

namespace App\Http\Requests\Utility;

use Illuminate\Foundation\Http\FormRequest;

class StoreUtilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|max:100',
            'total' => 'required|numeric|min:0',
            'dueDate' => 'required|date',
            'splitRule' => 'nullable|in:shared,dani-private,ildi-private',
            'paidBy' => 'nullable|in:Mi,Ildi',
            'paidDate' => 'nullable|date',
        ];
    }
}
