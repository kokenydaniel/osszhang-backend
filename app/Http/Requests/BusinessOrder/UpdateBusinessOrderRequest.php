<?php

namespace App\Http\Requests\BusinessOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerName' => 'sometimes|required',
            'amount' => 'sometimes|required|numeric',
            'date' => 'sometimes|required|date',
        ];
    }
}
