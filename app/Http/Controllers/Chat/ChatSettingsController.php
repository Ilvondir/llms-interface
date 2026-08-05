<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\UpdateChatSettingsRequest;
use App\Models\Conversation;
use App\Support\Chat\AccountChatPresenter;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChatSettingsController extends Controller
{
    public function __construct(private AccountChatPresenter $presenter) {}

    public function update(UpdateChatSettingsRequest $request): Response|JsonResponse
    {
        $user = $request->user();
        $settings = $this->presenter->settingsFor($user);
        $validated = $request->validated();

        if (array_key_exists('active_conversation_id', $validated) && $validated['active_conversation_id'] !== null) {
            Conversation::query()
                ->whereKey($validated['active_conversation_id'])
                ->whereBelongsTo($user)
                ->firstOrFail();
        }

        $settings->fill([
            'api_base_url' => $validated['api_base_url'] ?? $settings->api_base_url,
            'default_params' => array_key_exists('default_params', $validated)
                ? array_merge(AccountChatPresenter::defaultParams(), $validated['default_params'] ?? [])
                : $settings->default_params,
            'active_conversation_id' => array_key_exists('active_conversation_id', $validated)
                ? $validated['active_conversation_id']
                : $settings->active_conversation_id,
        ])->save();

        $active = null;

        if ($settings->active_conversation_id) {
            $active = $user->conversations()->find($settings->active_conversation_id);
        }

        if ($request->wantsJson()) {
            return response()->json($this->presenter->fieldMutationProps($user, $active));
        }

        if ($active !== null) {
            $active->load(['prompts' => fn ($query) => $query->orderBy('position')]);
        }

        return Inertia::render('Chat/Index', $this->presenter->props($user, $active));
    }
}
