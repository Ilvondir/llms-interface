<?php

namespace App\Http\Requests\Chat;

use App\Support\Chat\McpServerConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'mcp_servers' => ['sometimes', 'array', 'max:'.McpServerConfig::MAX_SERVERS],
            'mcp_servers.*.id' => ['required', 'string', 'max:64', 'regex:/'.McpServerConfig::ID_REGEX.'/'],
            'mcp_servers.*.name' => ['required', 'string', 'max:255'],
            'mcp_servers.*.url' => ['required', 'string', 'url', 'max:2048'],
            'mcp_servers.*.token' => ['nullable', 'string', 'max:'.McpServerConfig::MAX_TOKEN_LENGTH],
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

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $servers = $this->input('mcp_servers');

                if (! is_array($servers)) {
                    return;
                }

                $ids = [];

                foreach ($servers as $index => $server) {
                    if (! is_array($server) || ! is_string($server['id'] ?? null)) {
                        continue;
                    }

                    $id = $server['id'];

                    if (isset($ids[$id])) {
                        $validator->errors()->add(
                            "mcp_servers.{$index}.id",
                            'Each MCP server id must be unique.',
                        );
                    }

                    $ids[$id] = true;
                }
            },
        ];
    }
}
