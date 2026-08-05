<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Support\Chat\AccountChatPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatPageController extends Controller
{
    public function __construct(private AccountChatPresenter $presenter) {}

    public function home(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Inertia::render('Chat/Index');
        }

        return Inertia::render('Chat/Index', $this->presenter->props($user));
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $settings = $this->presenter->settingsFor($user);
        $settings->forceFill(['active_conversation_id' => $conversation->id])->save();

        $conversation->load(['prompts' => fn ($query) => $query->orderBy('position')]);

        return Inertia::render('Chat/Index', $this->presenter->props($user, $conversation));
    }
}
