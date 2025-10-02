<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
     /**
     * Create a new conversation for professor
     */
    public function newConversation()
    {
        $conversation = Conversation::create([
            'professor_id' => Auth::id(),
        ]);

        return response()->json([
            'response_code' => 200,
            'status'        => 'success',
            'message'       => 'Conversation created successfully',
            'content'       => $conversation,
        ]);
    }

    /**
     * Get all conversations of authenticated professor
     */
    public function getConversations()
    {
        $professorId = Auth::id();

        $conversations = Conversation::where('professor_id', $professorId)
            ->with(['messages' => function ($query) {
                $query->latest()->limit(1); // preview: last message only
            }])
            ->latest()
            ->get();

        return response()->json([
            'response_code' => 200,
            'status'        => 'success',
            'message'       => 'Conversations retrieved successfully',
            'content'       => $conversations,
        ]);
    }

    /**
     * Get all messages from a conversation
     */
    public function getMessages($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('professor_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'response_code' => 200,
            'status'        => 'success',
            'message'       => 'Messages retrieved successfully',
            'content'       => $conversation->messages()->get(),
        ]);
    }

    /**
     * Update conversation title
     */
    public function updateConversation(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation = Conversation::where('id', $id)
            ->where('professor_id', Auth::id())
            ->firstOrFail();

        $conversation->update([
            'title' => $request->title,
        ]);

        return response()->json([
            'response_code' => 200,
            'status'        => 'success',
            'message'       => 'Conversation updated successfully',
            'content'       => $conversation,
        ]);
    }

    /**
     * Delete conversation + messages
     */
    public function deleteConversation($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('professor_id', Auth::id())
            ->firstOrFail();

        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json([
            'response_code' => 200,
            'status'        => 'success',
            'message'       => 'Conversation deleted successfully',
            'content'       => null,
        ]);
    }
}
