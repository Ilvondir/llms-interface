<?php

use App\Http\Controllers\Chat\ChatModelsController;
use App\Http\Controllers\Chat\ChatStreamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Chat/Index');
})->name('home');

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
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
