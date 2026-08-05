<?php

namespace App\Support\Chat;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserChatSettings;
use Illuminate\Support\Collection;

class AccountChatPresenter
{
    /**
     * Default params mirrored from guest store.
     *
     * @return array{temperature: float, max_tokens: int|null, top_p: float}
     */
    public static function defaultParams(): array
    {
        return [
            'temperature' => 0.7,
            'max_tokens' => null,
            'top_p' => 1.0,
        ];
    }

    public function settingsFor(User $user): UserChatSettings
    {
        return $user->chatSettings()->firstOrCreate(
            [],
            [
                'api_base_url' => '',
                'default_params' => self::defaultParams(),
                'active_conversation_id' => null,
            ],
        );
    }

    /**
     * @return array{
     *     chatSettings: array<string, mixed>,
     *     conversations: list<array<string, mixed>>,
     *     activeConversation: array<string, mixed>|null
     * }
     */
    public function props(User $user, ?Conversation $active = null): array
    {
        $settings = $this->settingsFor($user);

        $conversations = $user->conversations()
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at', 'created_at', 'model']);

        if ($active === null && $settings->active_conversation_id) {
            $active = $user->conversations()
                ->with(['prompts' => fn ($query) => $query->orderBy('position')])
                ->find($settings->active_conversation_id);
        }

        if ($active === null) {
            $active = $user->conversations()
                ->with(['prompts' => fn ($query) => $query->orderBy('position')])
                ->orderByDesc('updated_at')
                ->first();
        }

        if ($active !== null && $active->relationLoaded('prompts') === false) {
            $active->load(['prompts' => fn ($query) => $query->orderBy('position')]);
        }

        if ($active !== null && $settings->active_conversation_id !== $active->id) {
            $settings->forceFill(['active_conversation_id' => $active->id])->save();
        }

        return [
            'chatSettings' => $this->presentSettings($settings),
            'conversations' => $conversations
                ->map(fn (Conversation $conversation) => $this->presentConversationSummary($conversation))
                ->values()
                ->all(),
            'activeConversation' => $active ? $this->presentConversation($active) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSettings(UserChatSettings $settings): array
    {
        return [
            'apiBaseUrl' => $settings->api_base_url ?? '',
            'defaultParams' => array_merge(self::defaultParams(), $settings->default_params ?? []),
            'activeConversationId' => $settings->active_conversation_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentConversationSummary(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'model' => $conversation->model,
            'createdAt' => optional($conversation->created_at)->getTimestamp() * 1000,
            'updatedAt' => optional($conversation->updated_at)->getTimestamp() * 1000,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentConversation(Conversation $conversation): array
    {
        /** @var Collection<int, Prompt> $prompts */
        $prompts = $conversation->relationLoaded('prompts')
            ? $conversation->prompts
            : $conversation->prompts()->orderBy('position')->get();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'systemPrompt' => $conversation->system_prompt ?? '',
            'model' => $conversation->model ?? '',
            'params' => array_merge(self::defaultParams(), $conversation->params ?? []),
            'createdAt' => optional($conversation->created_at)->getTimestamp() * 1000,
            'updatedAt' => optional($conversation->updated_at)->getTimestamp() * 1000,
            'messages' => $prompts
                ->map(fn (Prompt $prompt) => $this->presentPrompt($prompt))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPrompt(Prompt $prompt): array
    {
        $message = [
            'id' => $prompt->id,
            'role' => $prompt->role,
            'content' => $prompt->content,
            'createdAt' => optional($prompt->created_at)->getTimestamp() * 1000,
            'position' => $prompt->position,
        ];

        if ($prompt->reasoning !== null) {
            $message['reasoning'] = $prompt->reasoning;
        }

        if ($prompt->stats !== null) {
            $message['stats'] = $prompt->stats;
        }

        if ($prompt->error !== null) {
            $message['error'] = $prompt->error;
        }

        if ($prompt->model !== null && $prompt->model !== '') {
            $message['model'] = $prompt->model;
        }

        if ($prompt->params !== null) {
            $message['params'] = $prompt->params;
        }

        if ($prompt->sent_at !== null) {
            $message['sentAt'] = $prompt->sent_at->getTimestamp() * 1000;
        }

        if ($prompt->received_at !== null) {
            $message['receivedAt'] = $prompt->received_at->getTimestamp() * 1000;
        }

        if ($prompt->request_payload !== null) {
            $message['requestPayload'] = $prompt->request_payload;
        }

        return $message;
    }
}
