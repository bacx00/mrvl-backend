<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Events\ChatMessageCreated;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $messages = ChatMessage::with('user')
                    ->orderBy('created_at', 'asc')
                    ->limit(50)->get();
        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['message' => 'required|string']);
        $user = $request->user();

        $chatMessage = ChatMessage::create([
            'user_id' => $user->id,
            'message' => $data['message']
        ]);
        broadcast(new ChatMessageCreated($user, $chatMessage));

        return response()->json(['message' => 'Message sent']);
    }
}