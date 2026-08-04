<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatModelsRequest;
use App\Services\Llm\ChatCompletionProxy;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class ChatModelsController extends Controller
{
    public function __invoke(
        ChatModelsRequest $request,
        ChatCompletionProxy $proxy,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            return response()->json(
                $proxy->listModels($validated['api_base_url']),
            );
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
                'message' => 'Unexpected error while proxying models.',
            ], 500);
        }
    }
}
