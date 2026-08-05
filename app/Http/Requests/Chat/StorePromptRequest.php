<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePromptRequest extends FormRequest
{
    public const MAX_TEXT_CHARS = 100_000;

    public const MAX_ERROR_CHARS = 10_000;

    public const MAX_JSON_BYTES = 100_000;

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
            'role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'content' => ['required', 'string', 'max:'.self::MAX_TEXT_CHARS],
            'reasoning' => ['nullable', 'string', 'max:'.self::MAX_TEXT_CHARS],
            'stats' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:'.self::MAX_ERROR_CHARS],
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

        if ($encoded !== false && strlen($encoded) > self::MAX_JSON_BYTES) {
            $validator->errors()->add($field, "The {$field} field must not exceed ".self::MAX_JSON_BYTES.' bytes when encoded as JSON.');
        }
    }
}
