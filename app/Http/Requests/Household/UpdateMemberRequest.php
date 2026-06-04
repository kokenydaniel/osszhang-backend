<?php

namespace App\Http\Requests\Household;

use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $role = $this->input('role');
        if ($role === 'viewer') {
            $this->merge(['role' => 'reader']);
        }
    }

    public function rules(): array
    {
        return [
            'role' => 'sometimes|string|in:admin,editor,reader',
            'permissions' => 'sometimes|array',
            'permissions.*' => ['string', Rule::in(AccessControl::MODULES)],
        ];
    }
}
