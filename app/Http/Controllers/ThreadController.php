<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use App\Models\Post;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function index()
    {
        // List all threads with author and post count
        $threads = Thread::with('user')->withCount('posts')
                         ->orderBy('created_at', 'desc')->get();
        return response()->json($threads);
    }

    public function show(Thread $thread)
    {
        // Load thread author and all posts (with each post's author)
        $thread->load(['user', 'posts.user']);
        return response()->json($thread);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string'
        ]);
        $user = $request->user();
        // Create the thread and its first post atomically
        $thread = Thread::create([
            'title'   => $data['title'],
            'user_id' => $user->id
        ]);
        Post::create([
            'thread_id' => $thread->id,
            'user_id'   => $user->id,
            'content'   => $data['content']
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