<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'New chat',
            'system_prompt' => '',
            'model' => '',
            'params' => [
                'temperature' => 0.7,
                'max_tokens' => null,
                'top_p' => 1,
            ],
            'enabled_mcp_server_ids' => [],
        ];
    }
}
