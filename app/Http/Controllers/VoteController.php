<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Thread;
use App\Models\Post;
use App\Models\News;
use App\Models\Comment;

class VoteController extends Controller
{
    public function voteThread(Request $request, Thread $thread)
    {
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        $user = $request->user();
        $voteType = $request->vote_type === 'upvote' ? 1 : -1;

        // Check if user already voted
        $existingVote = DB::table('votes')
            ->where('user_id', $user->id)
            ->where('voteable_type', Thread::class)
            ->where('voteable_id', $thread->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->vote === $voteType) {
                // Remove vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->delete();
                
                return response()->json([
                    'success' => true,
                    'action' => 'removed'
                ]);
            } else {
                // Change vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->update(['vote' => $voteType]);
                
                return response()->json([
                    'success' => true,
                    'action' => 'changed'
                ]);
            }
        }

        // Create new vote
        DB::table('votes')->insert([
            'user_id' => $user->id,
            'voteable_type' => Thread::class,
            'voteable_id' => $thread->id,
            'vote' => $voteType,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'action' => 'voted'
        ]);
    }

    public function votePost(Request $request, Post $post)
    {
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        $user = $request->user();
        $voteType = $request->vote_type === 'upvote' ? 1 : -1;

        // Check if user already voted
        $existingVote = DB::table('votes')
            ->where('user_id', $user->id)
            ->where('voteable_type', Post::class)
            ->where('voteable_id', $post->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->vote === $voteType) {
                // Remove vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->delete();
                
                return response()->json([
                    'success' => true,
                    'action' => 'removed'
                ]);
            } else {
                // Change vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->update(['vote' => $voteType]);
                
                return response()->json([
                    'success' => true,
                    'action' => 'changed'
                ]);
            }
        }

        // Create new vote
        DB::table('votes')->insert([
            'user_id' => $user->id,
            'voteable_type' => Post::class,
            'voteable_id' => $post->id,
            'vote' => $voteType,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'action' => 'voted'
        ]);
    }

    public function voteNewsComment(Request $request, $newsId, $commentId)
    {
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        $user = $request->user();
        $voteType = $request->vote_type === 'upvote' ? 1 : -1;

        // Check if comment exists
        $comment = DB::table('comments')
            ->where('id', $commentId)
            ->where('commentable_type', News::class)
            ->where('commentable_id', $newsId)
            ->first();

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        // Check if user already voted
        $existingVote = DB::table('votes')
            ->where('user_id', $user->id)
            ->where('voteable_type', 'App\\Models\\Comment')
            ->where('voteable_id', $commentId)
            ->first();

        if ($existingVote) {
            if ($existingVote->vote === $voteType) {
                // Remove vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->delete();
                
                return response()->json([
                    'success' => true,
                    'action' => 'removed'
                ]);
            } else {
                // Change vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->update(['vote' => $voteType]);
                
                return response()->json([
                    'success' => true,
                    'action' => 'changed'
                ]);
            }
        }

        // Create new vote
        DB::table('votes')->insert([
            'user_id' => $user->id,
            'voteable_type' => 'App\\Models\\Comment',
            'voteable_id' => $commentId,
            'vote' => $voteType,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'action' => 'voted'
        ]);
    }

    public function voteMatchComment(Request $request, $commentId)
    {
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        $user = $request->user();
        $voteType = $request->vote_type === 'upvote' ? 1 : -1;

        // Check if comment exists
        $comment = DB::table('comments')
            ->where('id', $commentId)
            ->where('commentable_type', 'App\\Models\\Match')
            ->first();

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        // Check if user already voted
        $existingVote = DB::table('votes')
            ->where('user_id', $user->id)
            ->where('voteable_type', 'App\\Models\\Comment')
            ->where('voteable_id', $commentId)
            ->first();

        if ($existingVote) {
            if ($existingVote->vote === $voteType) {
                // Remove vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->delete();
                
                return response()->json([
                    'success' => true,
                    'action' => 'removed'
                ]);
            } else {
                // Change vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->update(['vote' => $voteType]);
                
                return response()->json([
                    'success' => true,
                    'action' => 'changed'
                ]);
            }
        }

        // Create new vote
        DB::table('votes')->insert([
            'user_id' => $user->id,
            'voteable_type' => 'App\\Models\\Comment',
            'voteable_id' => $commentId,
            'vote' => $voteType,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'action' => 'voted'
        ]);
    }
    
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'vote_type' => 'required|in:up,down',
                'voteable_type' => 'required|in:news,forum_post,forum_thread,match_comment,news_comment',
                'voteable_id' => 'required|integer'
            ]);
            
            $user = $request->user();
            
            // Convert vote type to numeric
            $voteValue = $validated['vote_type'] === 'up' ? 1 : -1;
            
            // Check if user already voted on this item
            $existingVote = DB::table('votes')
                ->where('user_id', $user->id)
                ->where('voteable_type', $validated['voteable_type'])
                ->where('voteable_id', $validated['voteable_id'])
                ->first();
                
            if ($existingVote) {
                // Update existing vote
                DB::table('votes')
                    ->where('id', $existingVote->id)
                    ->update([
                        'vote' => $voteValue,
                        'updated_at' => now()
                    ]);
                    
                return response()->json([
                    'success' => true,
                    'message' => 'Vote updated successfully',
                    'vote_type' => $validated['vote_type']
                ]);
            } else {
                // Create new vote
                $voteId = DB::table('votes')->insertGetId([
                    'user_id' => $user->id,
                    'voteable_type' => $validated['voteable_type'],
                    'voteable_id' => $validated['voteable_id'],
                    'vote' => $voteValue,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Vote created successfully',
                    'vote_id' => $voteId,
                    'vote_type' => $validated['vote_type']
                ], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vote'
            ], 500);
        }
    }
    
    public function destroy(Request $request)
    {
        try {
            $validated = $request->validate([
                'voteable_type' => 'required|in:news,forum_post,forum_thread,match_comment,news_comment',
                'voteable_id' => 'required|integer'
            ]);
            
            $user = $request->user();
            
            $deleted = DB::table('votes')
                ->where('user_id', $user->id)
                ->where('voteable_type', $validated['voteable_type'])
                ->where('voteable_id', $validated['voteable_id'])
                ->delete();
                
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Vote removed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vote not found'
                ], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vote'
            ], 500);
        }
    }
}