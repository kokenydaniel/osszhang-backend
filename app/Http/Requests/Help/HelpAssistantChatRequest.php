<?php

namespace App\Http\Requests\Help;

use Illuminate\Foundation\Http\FormRequest;

class HelpAssistantChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|min:2|max:2000',
            'history' => 'sometimes|array|max:20',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:4000',
        ];
    }
}
