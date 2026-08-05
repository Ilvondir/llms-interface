<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prompt>
 */
class PromptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => 'user',
            'content' => fake()->sentence(),
            'reasoning' => null,
            'stats' => null,
            'error' => null,
            'model' => null,
            'params' => null,
            'sent_at' => null,
            'received_at' => null,
            'request_payload' => null,
            'position' => 1,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
            'params' => [
                'temperature' => 0.7,
                'max_tokens' => null,
                'top_p' => 1,
            ],
            'model' => 'test-model',
        ]);
    }
}
