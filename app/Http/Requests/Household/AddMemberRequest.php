<?php

namespace App\Http\Requests\Household;

use App\Support\Username;
use Illuminate\Foundation\Http\FormRequest;

class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Username::normalize($this->input('username', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,editor,reader',
            'permissions' => 'required|array',
        ];
    }
}
