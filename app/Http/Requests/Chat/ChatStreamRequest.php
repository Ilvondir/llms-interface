<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_base_url' => ['required', 'url', 'max:2048'],
            'model' => ['required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:100000'],
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', Rule::in(['system', 'user', 'assistant'])],
            'messages.*.content' => ['required', 'string'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
