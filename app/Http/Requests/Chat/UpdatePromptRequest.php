<?php

namespace App\Http\Requests\Chat;

use App\Rules\Chat\MessageContentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('conversation')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', new MessageContentRule],
            'reasoning' => ['sometimes', 'nullable', 'string', 'max:'.StorePromptRequest::MAX_TEXT_CHARS],
            'stats' => ['sometimes', 'nullable', 'array'],
            'error' => ['sometimes', 'nullable', 'string', 'max:'.StorePromptRequest::MAX_ERROR_CHARS],
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

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectOversizedJson($validator, 'stats');
                $this->rejectOversizedJson($validator, 'request_payload');
            },
        ];
    }

    private function rejectOversizedJson(Validator $validator, string $field): void
    {
        if (! $this->exists($field) || $this->input($field) === null) {
            return;
        }

        $encoded = json_encode($this->input($field));

        if ($encoded !== false && strlen($encoded) > StorePromptRequest::MAX_JSON_BYTES) {
            $validator->errors()->add($field, "The {$field} field must not exceed ".StorePromptRequest::MAX_JSON_BYTES.' bytes when encoded as JSON.');
        }
    }
}
