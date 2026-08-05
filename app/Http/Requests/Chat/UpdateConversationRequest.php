<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'params' => ['sometimes', 'array'],
            'params.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'params.max_tokens' => ['nullable', 'integer', 'min:1'],
            'params.top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
