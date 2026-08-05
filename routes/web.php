<?php

use App\Http\Controllers\Chat\ChatModelsController;
use App\Http\Controllers\Chat\ChatPageController;
use App\Http\Controllers\Chat\ChatSettingsController;
use App\Http\Controllers\Chat\ChatStreamController;
use App\Http\Controllers\Chat\ConversationController;
use App\Http\Controllers\Chat\PromptController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatPageController::class, 'home'])->name('home');

Route::post('/chat/stream', ChatStreamController::class)
    ->middleware('throttle:llms-chat')
    ->name('chat.stream');

Route::post('/chat/models', ChatModelsController::class)
    ->middleware('throttle:llms-chat')
    ->name('chat.models');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/conversations/{conversation}', [ChatPageController::class, 'show'])
        ->name('conversations.show');

    Route::post('/conversations', [ConversationController::class, 'store'])
        ->name('conversations.store');

    Route::patch('/conversations/{conversation}', [ConversationController::class, 'update'])
        ->name('conversations.update');

    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])
        ->name('conversations.destroy');

    Route::patch('/chat-settings', [ChatSettingsController::class, 'update'])
        ->name('chat-settings.update');

    Route::post('/conversations/{conversation}/prompts', [PromptController::class, 'store'])
        ->name('conversations.prompts.store');

    Route::patch('/conversations/{conversation}/prompts/{prompt}', [PromptController::class, 'update'])
        ->scopeBindings()
        ->name('conversations.prompts.update');
});
