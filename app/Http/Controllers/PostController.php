<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Thread;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request, Thread $thread)
    {
        $data = $request->validate([
            'content' => 'required|string'
        ]);
        $user = $request->user();
        $post = Post::create([
            'thread_id' => $thread->id,
            'user_id'   => $user->id,
            'content'   => $data['content']
        ]);
        return response()->json(['message' => 'Post added', 'post_id' => $post->id], 201);
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);
        $post->delete();
        return response()->json(['message' => 'Post deleted']);
    }
}