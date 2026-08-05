<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptRequest extends FormRequest
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
            'content' => ['sometimes', 'string'],
            'reasoning' => ['sometimes', 'nullable', 'string'],
            'stats' => ['sometimes', 'nullable', 'array'],
            'error' => ['sometimes', 'nullable', 'string'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'params' => ['sometimes', 'nullable', 'array'],
            'params.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'params.max_tokens' => ['nullable', 'integer', 'min:1'],
            'params.top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'sent_at' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'received_at' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'request_payload' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
