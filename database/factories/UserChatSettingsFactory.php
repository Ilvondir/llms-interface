<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserChatSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChatSettings>
 */
class UserChatSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'api_base_url' => '',
            'default_params' => [
                'temperature' => 0.7,
                'max_tokens' => null,
                'top_p' => 1,
            ],
            'mcp_servers' => [],
            'active_conversation_id' => null,
        ];
    }
}
