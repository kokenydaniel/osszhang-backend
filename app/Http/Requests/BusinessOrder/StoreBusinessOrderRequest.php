<?php

namespace App\Http\Requests\BusinessOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerName' => 'required',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'channel' => 'nullable|string',
            'paymentMethod' => 'nullable|string',
            'provider' => 'nullable|string',
            'destination' => 'nullable|string',
            'paidDate' => 'nullable|date',
            'invoiceId' => 'nullable|string',
            'orderStatus' => 'nullable|string|max:100',
            'order_status' => 'nullable|string|max:100',
        ];
    }
}
