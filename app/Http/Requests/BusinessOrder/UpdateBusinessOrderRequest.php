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
            'channel' => 'sometimes|nullable|string',
            'paymentMethod' => 'sometimes|nullable|string',
            'provider' => 'sometimes|nullable|string',
            'destination' => 'sometimes|nullable|string',
            'paidDate' => 'sometimes|nullable|date',
            'invoiceId' => 'sometimes|nullable|string',
            'orderStatus' => 'sometimes|nullable|string|max:100',
            'order_status' => 'sometimes|nullable|string|max:100',
        ];
    }
}
