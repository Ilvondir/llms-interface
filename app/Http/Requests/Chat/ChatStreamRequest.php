<?php

namespace App\Http\Requests\Chat;

use App\Rules\Chat\MessageContentRule;
use App\Support\Chat\ChatContentLimits;
use App\Support\Chat\MessageContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'messages' => ['required', 'array', 'min:1', 'max:'.ChatContentLimits::MAX_MESSAGES_PER_REQUEST],
            'messages.*.role' => ['required', 'string', Rule::in(['system', 'user', 'assistant'])],
            'messages.*.content' => ['required', new MessageContentRule],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $messages = $this->input('messages');

                if (! is_array($messages)) {
                    return;
                }

                if ($this->user() === null) {
                    foreach ($messages as $index => $message) {
                        if (! is_array($message)) {
                            continue;
                        }

                        if (MessageContent::containsImage($message['content'] ?? null)) {
                            $validator->errors()->add(
                                "messages.{$index}.content",
                                'Sign in to send images.',
                            );
                        }
                    }
                }

                $encoded = json_encode($messages);

                if ($encoded !== false && strlen($encoded) > ChatContentLimits::MAX_STREAM_MESSAGES_BYTES) {
                    $validator->errors()->add(
                        'messages',
                        'The messages payload must not exceed '.ChatContentLimits::MAX_STREAM_MESSAGES_BYTES.' bytes when encoded as JSON.',
                    );
                }
            },
        ];
    }
}
