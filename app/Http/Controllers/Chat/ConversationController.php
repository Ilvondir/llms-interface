<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreConversationRequest;
use App\Http\Requests\Chat\UpdateConversationRequest;
use App\Models\Conversation;
use App\Support\Chat\AccountChatPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(private AccountChatPresenter $presenter) {}

    public function store(StoreConversationRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $settings = $this->presenter->settingsFor($user);

        $conversation = $user->conversations()->create([
            'title' => 'New chat',
            'system_prompt' => '',
            'model' => '',
            'params' => $settings->default_params ?? AccountChatPresenter::defaultParams(),
        ]);

        $settings->forceFill(['active_conversation_id' => $conversation->id])->save();

        if ($request->wantsJson()) {
            $conversation->load(['prompts' => fn ($query) => $query->orderBy('position')]);

            return response()->json($this->presenter->props($user, $conversation));
        }

        return redirect()->route('conversations.show', $conversation);
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): Response|JsonResponse
    {
        $validated = $request->validated();

        $conversation->fill([
            'title' => array_key_exists('title', $validated)
                ? (trim((string) $validated['title']) ?: 'Untitled')
                : $conversation->title,
            'system_prompt' => $validated['system_prompt'] ?? $conversation->system_prompt,
            'model' => $validated['model'] ?? $conversation->model,
            'params' => array_key_exists('params', $validated)
                ? array_merge(AccountChatPresenter::defaultParams(), $validated['params'] ?? [])
                : $conversation->params,
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(
                $this->presenter->fieldMutationProps($request->user(), $conversation),
            );
        }

        $conversation->load(['prompts' => fn ($query) => $query->orderBy('position')]);

        return Inertia::render('Chat/Index', $this->presenter->props($request->user(), $conversation));
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $user = $conversation->user;
        $wasActive = $user->chatSettings?->active_conversation_id === $conversation->id;

        $conversation->delete();

        if ($wasActive) {
            $next = $user->conversations()->orderByDesc('updated_at')->first();
            $settings = $this->presenter->settingsFor($user);
            $settings->forceFill(['active_conversation_id' => $next?->id])->save();

            if ($next) {
                return redirect()->route('conversations.show', $next);
            }
        }

        return redirect()->route('home');
    }
}
