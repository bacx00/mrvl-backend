<?php
namespace App\Http\Controllers;

use App\Models\ForumThread;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        $query = ForumThread::with(['user']);

        if ($request->category && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $threads = $query->orderBy('pinned', 'desc')
                        ->orderBy('last_reply_at', 'desc')
                        ->get();

        return response()->json([
            'data' => $threads,
            'total' => $threads->count(),
            'success' => true
        ]);
    }

    public function show(ForumThread $thread)
    {
        $thread->load(['user']);
        $thread->increment('views');

        return response()->json([
            'data' => $thread,
            'success' => true
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string'
        ]);

        $thread = ForumThread::create([
            'title' => $request->title,
            'content' => $request->content,
            'category' => $request->category,
            'user_id' => $request->user()->id,
            'last_reply_at' => now()
        ]);

        return response()->json([
            'data' => $thread->load('user'),
            'success' => true,
            'message' => 'Thread created successfully'
        ], 201);
    }
}
