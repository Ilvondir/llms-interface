<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_base_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'default_params' => ['sometimes', 'array'],
            'default_params.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'default_params.max_tokens' => ['nullable', 'integer', 'min:1'],
            'default_params.top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'active_conversation_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('conversations', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
        ];
    }
}
