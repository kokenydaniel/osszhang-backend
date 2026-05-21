<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;

class MonthYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ];
    }
}
