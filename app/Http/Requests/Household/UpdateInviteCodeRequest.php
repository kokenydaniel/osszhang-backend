<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInviteCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invite_code' => 'required|string|min:4|unique:households,invite_code',
        ];
    }
}
