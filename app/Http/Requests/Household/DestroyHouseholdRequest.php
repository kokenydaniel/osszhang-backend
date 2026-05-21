<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class DestroyHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_name' => 'required|string',
        ];
    }
}
