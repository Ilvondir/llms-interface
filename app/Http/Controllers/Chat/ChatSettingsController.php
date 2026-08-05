<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\UpdateChatSettingsRequest;
use App\Models\Conversation;
use App\Support\Chat\AccountChatPresenter;
use Illuminate\Http\RedirectResponse;

class ChatSettingsController extends Controller
{
    public function __construct(private AccountChatPresenter $presenter) {}

    public function update(UpdateChatSettingsRequest $request): RedirectResponse
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

        if ($settings->active_conversation_id) {
            return redirect()->route('conversations.show', $settings->active_conversation_id);
        }

        return redirect()->route('home');
    }
}
