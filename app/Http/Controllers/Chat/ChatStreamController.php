<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatStreamRequest;
use App\Services\Llm\ChatCompletionProxy;
use App\Services\Llm\ChatHistoryComposer;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatStreamController extends Controller
{
    public function __invoke(
        ChatStreamRequest $request,
        ChatHistoryComposer $composer,
        ChatCompletionProxy $proxy,
    ): StreamedResponse|JsonResponse {
        $validated = $request->validated();

        $messages = $composer->compose(
            $validated['system_prompt'] ?? null,
            $validated['messages'],
        );

        if ($messages === []) {
            return response()->json([
                'message' => 'No valid messages to send to the model.',
            ], 422);
        }

        $payload = [
            'model' => $validated['model'],
            'messages' => $messages,
        ];

        if (array_key_exists('temperature', $validated) && $validated['temperature'] !== null) {
            $payload['temperature'] = (float) $validated['temperature'];
        }

        if (array_key_exists('top_p', $validated) && $validated['top_p'] !== null) {
            $payload['top_p'] = (float) $validated['top_p'];
        }

        if (array_key_exists('max_tokens', $validated) && $validated['max_tokens'] !== null) {
            $payload['max_tokens'] = (int) $validated['max_tokens'];
        }

        // Release the session lock before the long-lived upstream stream.
        $request->session()->save();

        try {
            return $proxy->streamChatCompletions($validated['api_base_url'], $payload);
        } catch (RuntimeException $exception) {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600
                ? (int) $exception->getCode()
                : 502;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unexpected error while proxying chat completions.',
            ], 500);
        }
    }
}
