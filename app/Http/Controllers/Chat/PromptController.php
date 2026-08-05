<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StorePromptRequest;
use App\Http\Requests\Chat\UpdatePromptRequest;
use App\Models\Conversation;
use App\Models\Prompt;
use App\Support\Chat\AccountChatPresenter;
use App\Support\Chat\MessageContent;
use App\Support\Chat\RequestPayloadSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PromptController extends Controller
{
    public function __construct(private AccountChatPresenter $presenter) {}

    public function store(StorePromptRequest $request, Conversation $conversation): Response|JsonResponse
    {
        $validated = $request->validated();

        $position = ((int) $conversation->prompts()->max('position')) + 1;

        $conversation->prompts()->create([
            'role' => $validated['role'],
            'content' => MessageContent::encodeForStorage($validated['content']),
            'reasoning' => $validated['reasoning'] ?? null,
            'stats' => $validated['stats'] ?? null,
            'error' => $validated['error'] ?? null,
            'model' => $validated['model'] ?? null,
            'params' => $validated['params'] ?? null,
            'sent_at' => isset($validated['sent_at']) ? Carbon::createFromTimestampMs($validated['sent_at']) : null,
            'received_at' => isset($validated['received_at']) ? Carbon::createFromTimestampMs($validated['received_at']) : null,
            'request_payload' => RequestPayloadSanitizer::sanitize($validated['request_payload'] ?? null),
            'position' => $position,
        ]);

        $conversation->touch();
        $conversation->load(['prompts' => fn ($query) => $query->orderBy('position')]);

        $props = $this->presenter->props($request->user(), $conversation);

        if ($request->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('Chat/Index', $props);
    }

    public function update(UpdatePromptRequest $request, Conversation $conversation, Prompt $prompt): Response|JsonResponse
    {
        abort_unless($prompt->conversation_id === $conversation->id, 404);

        $validated = $request->validated();

        $prompt->fill([
            'content' => array_key_exists('content', $validated)
                ? MessageContent::encodeForStorage($validated['content'])
                : $prompt->content,
            'reasoning' => array_key_exists('reasoning', $validated) ? $validated['reasoning'] : $prompt->reasoning,
            'stats' => array_key_exists('stats', $validated) ? $validated['stats'] : $prompt->stats,
            'error' => array_key_exists('error', $validated) ? $validated['error'] : $prompt->error,
            'model' => array_key_exists('model', $validated) ? $validated['model'] : $prompt->model,
            'params' => array_key_exists('params', $validated) ? $validated['params'] : $prompt->params,
            'sent_at' => array_key_exists('sent_at', $validated)
                ? ($validated['sent_at'] !== null ? Carbon::createFromTimestampMs($validated['sent_at']) : null)
                : $prompt->sent_at,
            'received_at' => array_key_exists('received_at', $validated)
                ? ($validated['received_at'] !== null ? Carbon::createFromTimestampMs($validated['received_at']) : null)
                : $prompt->received_at,
            'request_payload' => array_key_exists('request_payload', $validated)
                ? RequestPayloadSanitizer::sanitize($validated['request_payload'])
                : $prompt->request_payload,
        ])->save();

        $conversation->touch();
        $conversation->load(['prompts' => fn ($query) => $query->orderBy('position')]);

        $props = $this->presenter->props($request->user(), $conversation);

        if ($request->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('Chat/Index', $props);
    }
}
