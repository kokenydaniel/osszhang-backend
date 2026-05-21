<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class QueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => 'required|string|max:1000',
            'include_context' => 'boolean',
        ];
    }
}
