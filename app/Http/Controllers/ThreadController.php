<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function index()
    {
        // List all threads with author information
        $threads = Thread::with('user')
                         ->orderBy('created_at', 'desc')->get();
        return response()->json($threads);
    }

    public function show(Thread $thread)
    {
        // Load thread with author information
        $thread->load('user');
        return response()->json($thread);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string'
        ]);
        $user = $request->user();
        // Create the thread with content
        $thread = Thread::create([
            'title'   => $data['title'],
            'content' => $data['content'],
            'user_id' => $user->id
        ]);
        return response()->json([
            'message'   => 'Thread created',
            'thread_id' => $thread->id
        ], 201);
    }

    public function destroy(Thread $thread)
    {
        $this->authorize('delete', $thread);
        $thread->delete();
        return response()->json(['message' => 'Thread deleted']);
    }
}