<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'sometimes|string|in:admin,editor,reader',
            'permissions' => 'sometimes|array',
        ];
    }
}
