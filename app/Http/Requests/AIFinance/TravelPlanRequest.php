<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;

class TravelPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination' => 'required|string|min:2|max:200',
            'duration_days' => 'required|integer|min:1|max:90',
            'total_budget' => 'required|numeric|min:1000|max:999999999',
        ];
    }
}
