<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('conversation')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'content' => ['required', 'string'],
            'reasoning' => ['nullable', 'string'],
            'stats' => ['nullable', 'array'],
            'error' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'params' => ['nullable', 'array'],
            'params.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'params.max_tokens' => ['nullable', 'integer', 'min:1'],
            'params.top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'sent_at' => ['nullable', 'integer', 'min:0'],
            'received_at' => ['nullable', 'integer', 'min:0'],
            'request_payload' => ['nullable', 'array'],
        ];
    }
}
