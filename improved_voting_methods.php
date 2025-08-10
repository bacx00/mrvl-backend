<?php

// Improved voting methods for ForumController

public function voteThread(Request $request, $threadId)
{
    // Check if user is authenticated
    if (!auth('api')->check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }
    
    $request->validate([
        'vote_type' => 'required|in:upvote,downvote'
    ]);

    try {
        $userId = auth('api')->id();
        $voteType = $request->vote_type;
        
        // Generate unique vote key for thread votes
        $voteKey = "user:{$userId}:thread:{$threadId}";

        // Check if thread exists
        $thread = DB::table('forum_threads')->where('id', $threadId)->first();
        if (!$thread) {
            return response()->json(['success' => false, 'message' => 'Thread not found'], 404);
        }

        // Use DB transaction for thread voting with vote_key approach
        return DB::transaction(function() use ($threadId, $userId, $voteType, $voteKey) {
            // Check for existing thread vote using vote_key
            $existingVote = DB::table('forum_votes')
                ->where('vote_key', $voteKey)
                ->lockForUpdate()
                ->first();

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type (toggle off)
                    DB::table('forum_votes')->where('id', $existingVote->id)->delete();
                    $this->updateVoteCounts($threadId);
                    
                    // Get updated vote counts for immediate response
                    $updatedThread = DB::table('forum_threads')
                        ->where('id', $threadId)
                        ->select(['upvotes', 'downvotes', 'score'])
                        ->first();
                        
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed',
                        'data' => [
                            'thread_id' => $threadId,
                            'upvotes' => (int)($updatedThread->upvotes ?? 0),
                            'downvotes' => (int)($updatedThread->downvotes ?? 0),
                            'score' => (int)($updatedThread->score ?? 0)
                        ],
                        'user_vote' => null,
                    ]);
                } else {
                    // Update vote if different type (upvote to downvote or vice versa)
                    DB::table('forum_votes')
                        ->where('id', $existingVote->id)
                        ->update([
                            'vote_type' => $voteType, 
                            'updated_at' => now()
                        ]);

                    $this->updateVoteCounts($threadId);
                    
                    // Get updated vote counts for immediate response
                    $updatedThread = DB::table('forum_threads')
                        ->where('id', $threadId)
                        ->select(['upvotes', 'downvotes', 'score'])
                        ->first();
                        
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote changed successfully',
                        'action' => 'updated',
                        'data' => [
                            'thread_id' => $threadId,
                            'upvotes' => (int)($updatedThread->upvotes ?? 0),
                            'downvotes' => (int)($updatedThread->downvotes ?? 0),
                            'score' => (int)($updatedThread->score ?? 0)
                        ],
                        'user_vote' => $voteType,
                    ]);
                }
            } else {
                // Create new thread vote using vote_key to prevent duplicates
                DB::table('forum_votes')->insert([
                    'thread_id' => $threadId,
                    'user_id' => $userId,
                    'vote_type' => $voteType,
                    'post_id' => null,
                    'vote_key' => $voteKey,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $this->updateVoteCounts($threadId);
                
                // Get updated vote counts for immediate response
                $updatedThread = DB::table('forum_threads')
                    ->where('id', $threadId)
                    ->select(['upvotes', 'downvotes', 'score'])
                    ->first();
                    
                return response()->json([
                    'success' => true,
                    'message' => 'Vote recorded successfully',
                    'action' => 'voted',
                    'data' => [
                        'thread_id' => $threadId,
                        'upvotes' => (int)($updatedThread->upvotes ?? 0),
                        'downvotes' => (int)($updatedThread->downvotes ?? 0),
                        'score' => (int)($updatedThread->score ?? 0)
                    ],
                    'user_vote' => $voteType,
                ]);
            }
        });
    } catch (\Exception $e) {
        Log::error('Thread voting error', [
            'thread_id' => $threadId,
            'user_id' => auth('api')->id(),
            'vote_type' => $request->vote_type ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error processing vote. Please try again.',
            'action' => 'error'
        ], 500);
    }
}

public function votePost(Request $request, $postId)
{
    // Check if user is authenticated
    if (!auth('api')->check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }
    
    $request->validate([
        'vote_type' => 'required|in:upvote,downvote'
    ]);

    try {
        $userId = auth('api')->id();
        $voteType = $request->vote_type;

        // Check if post exists
        $post = DB::table('forum_posts')->where('id', $postId)->first();
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }
        
        // Generate unique vote key for post votes
        $voteKey = "user:{$userId}:post:{$postId}";

        // Use DB transaction for post voting with vote_key approach
        return DB::transaction(function() use ($postId, $post, $userId, $voteType, $voteKey) {
            // Check for existing post vote using vote_key
            $existingVote = DB::table('forum_votes')
                ->where('vote_key', $voteKey)
                ->lockForUpdate()
                ->first();

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type (toggle off)
                    DB::table('forum_votes')->where('id', $existingVote->id)->delete();
                    $this->updateVoteCounts($post->thread_id, $postId);
                    
                    // Get updated vote counts for immediate response
                    $updatedPost = DB::table('forum_posts')
                        ->where('id', $postId)
                        ->select(['upvotes', 'downvotes', 'score'])
                        ->first();
                        
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed',
                        'data' => [
                            'post_id' => $postId,
                            'upvotes' => (int)($updatedPost->upvotes ?? 0),
                            'downvotes' => (int)($updatedPost->downvotes ?? 0),
                            'score' => (int)($updatedPost->score ?? 0)
                        ],
                        'user_vote' => null,
                    ]);
                } else {
                    // Update vote if different type (upvote to downvote or vice versa)
                    DB::table('forum_votes')
                        ->where('id', $existingVote->id)
                        ->update([
                            'vote_type' => $voteType, 
                            'updated_at' => now()
                        ]);

                    $this->updateVoteCounts($post->thread_id, $postId);
                    
                    // Get updated vote counts for immediate response
                    $updatedPost = DB::table('forum_posts')
                        ->where('id', $postId)
                        ->select(['upvotes', 'downvotes', 'score'])
                        ->first();
                        
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote changed successfully',
                        'action' => 'updated',
                        'data' => [
                            'post_id' => $postId,
                            'upvotes' => (int)($updatedPost->upvotes ?? 0),
                            'downvotes' => (int)($updatedPost->downvotes ?? 0),
                            'score' => (int)($updatedPost->score ?? 0)
                        ],
                        'user_vote' => $voteType,
                    ]);
                }
            } else {
                // Create new post vote using vote_key to prevent duplicates
                DB::table('forum_votes')->insert([
                    'thread_id' => $post->thread_id,
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'vote_type' => $voteType,
                    'vote_key' => $voteKey,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $this->updateVoteCounts($post->thread_id, $postId);
                
                // Get updated vote counts for immediate response
                $updatedPost = DB::table('forum_posts')
                    ->where('id', $postId)
                    ->select(['upvotes', 'downvotes', 'score'])
                    ->first();
                    
                return response()->json([
                    'success' => true,
                    'message' => 'Vote recorded successfully',
                    'action' => 'voted',
                    'data' => [
                        'post_id' => $postId,
                        'upvotes' => (int)($updatedPost->upvotes ?? 0),
                        'downvotes' => (int)($updatedPost->downvotes ?? 0),
                        'score' => (int)($updatedPost->score ?? 0)
                    ],
                    'user_vote' => $voteType,
                ]);
            }
        });
    } catch (\Exception $e) {
        Log::error('Post voting error', [
            'post_id' => $postId,
            'user_id' => auth('api')->id(),
            'vote_type' => $request->vote_type ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error processing vote. Please try again.',
            'action' => 'error'
        ], 500);
    }
}