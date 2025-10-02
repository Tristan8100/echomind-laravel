<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ConversationController;
use App\Http\Controllers\API\MessageController;

Route::middleware('auth:professor-api')->group(function () {
    Route::post('/conversations', [ConversationController::class, 'newConversation']); //done
    Route::get('/conversations', [ConversationController::class, 'getConversations']); //done
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'getMessages']); //done
    //Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
    Route::put('/conversations/{id}', [ConversationController::class, 'updateConversation']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'deleteConversation']);

    Route::post('/messages/{id}', [MessageController::class, 'sendMessage']);
});