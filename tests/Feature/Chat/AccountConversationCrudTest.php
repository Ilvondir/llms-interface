<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountConversationCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_create_rename_and_delete_conversations(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->post(route('conversations.store'));
        $create->assertRedirect();

        $conversation = Conversation::query()->whereBelongsTo($user)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('New chat', $conversation->title);

        $createWithFields = $this->actingAs($user)->postJson(route('conversations.store'), [
            'title' => 'From prompt',
            'system_prompt' => 'Be brief.',
            'model' => 'demo',
            'params' => [
                'temperature' => 0.2,
                'max_tokens' => 128,
                'top_p' => 0.9,
            ],
        ]);
        $createWithFields->assertOk();
        $seeded = Conversation::query()->whereBelongsTo($user)->where('title', 'From prompt')->first();
        $this->assertNotNull($seeded);
        $this->assertSame('Be brief.', $seeded->system_prompt);
        $this->assertSame('demo', $seeded->model);
        $this->assertSame(0.2, $seeded->params['temperature']);

        $rename = $this->actingAs($user)->patchJson(route('conversations.update', $conversation), [
            'title' => 'Renamed',
        ]);
        $rename->assertOk();
        $this->assertSame('Renamed', $conversation->fresh()->title);

        $delete = $this->actingAs($user)->delete(route('conversations.destroy', $conversation));
        $delete->assertRedirect(route('conversations.show', $seeded));
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    #[Test]
    public function guest_cannot_create_conversations(): void
    {
        $response = $this->post(route('conversations.store'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('conversations', 0);
    }
}
