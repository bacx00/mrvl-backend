<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\UserActivity;
use App\Models\Vote;
use App\Models\News;
use App\Models\NewsComment;
use Exception;

class VoteController extends Controller
{
    /**
     * Vote on content (news, forum posts, etc.)
     */
    public function vote(Request $request)
    {
        try {
            Log::info('Vote request received', $request->all());

            $request->validate([
                'votable_type' => 'required|string|in:news,news_comment,forum_thread,forum_post,match_comment',
                'votable_id' => 'required|integer',
                'vote_type' => 'required|string|in:upvote,downvote'
            ]);

            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $votableType = $request->votable_type;  // Frontend sends votable_type
            $votableId = $request->votable_id;      // Frontend sends votable_id
            $voteType = $request->vote_type;
            
            // Map to database column names (voteable with 'ea')
            $voteableType = $votableType;
            $voteableId = $votableId;
            
            // Convert vote type to integer for database storage
            $voteValue = $this->convertVoteTypeToValue($voteType);

            // Verify the target content exists
            if (!$this->contentExists($voteableType, $voteableId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found'
                ], 404);
            }

            // Check if user already voted
            $existingVote = DB::table('votes')
                ->where('user_id', $user->id)
                ->where('voteable_type', $voteableType)
                ->where('voteable_id', $voteableId)
                ->first();

            $action = null;
            $oldVoteValue = $existingVote ? $existingVote->vote : null;
            $oldVoteType = $oldVoteValue ? $this->convertValueToVoteType($oldVoteValue) : null;

            if ($existingVote) {
                if ($existingVote->vote === $voteValue) {
                    // Remove vote (toggle off)
                    DB::table('votes')
                        ->where('id', $existingVote->id)
                        ->delete();
                    
                    $action = 'removed';
                    
                    // Track activity
                    UserActivity::track(
                        $user->id,
                        'vote_removed',
                        "Removed {$voteType} on {$voteableType}",
                        $voteableType,
                        $voteableId,
                        ['vote_type' => $voteType]
                    );
                } else {
                    // Change vote type
                    DB::table('votes')
                        ->where('id', $existingVote->id)
                        ->update([
                            'vote' => $voteValue,
                            'updated_at' => now()
                        ]);
                    
                    $action = 'changed';
                    
                    // Track activity
                    UserActivity::track(
                        $user->id,
                        'vote_changed',
                        "Changed vote from {$oldVoteType} to {$voteType} on {$voteableType}",
                        $voteableType,
                        $voteableId,
                        ['old_vote_type' => $oldVoteType, 'new_vote_type' => $voteType]
                    );
                }
            } else {
                // Create new vote
                DB::table('votes')->insert([
                    'user_id' => $user->id,
                    'voteable_type' => $voteableType,
                    'voteable_id' => $voteableId,
                    'vote' => $voteValue,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $action = 'added';
                
                // Track activity
                UserActivity::track(
                    $user->id,
                    'vote_added',
                    "Added {$voteType} on {$voteableType}",
                    $voteableType,
                    $voteableId,
                    ['vote_type' => $voteType]
                );
            }

            // Get updated vote counts
            $voteCounts = $this->getVoteCounts($voteableType, $voteableId);
            
            // Update the parent content's vote count fields
            $this->updateContentVoteCounts($voteableType, $voteableId, $voteCounts);
            
            // Get user's current vote
            $userVoteValue = DB::table('votes')
                ->where('user_id', $user->id)
                ->where('voteable_type', $voteableType)
                ->where('voteable_id', $voteableId)
                ->value('vote');
            
            $userVote = $userVoteValue ? $this->convertValueToVoteType($userVoteValue) : null;

            return response()->json([
                'success' => true,
                'action' => $action,
                'vote_counts' => $voteCounts,
                'user_vote' => $userVote,
                'message' => $this->getVoteMessage($action, $voteType)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in vote request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error processing vote: ' . $e->getMessage(), [
                'user_id' => auth('api')->id(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process vote'
            ], 500);
        }
    }

    /**
     * Get votes for specific content
     */
    public function getVotes(Request $request)
    {
        try {
            $request->validate([
                'votable_type' => 'required|string',
                'votable_id' => 'required|integer'
            ]);

            $votableType = $request->votable_type;  // Frontend sends votable_type
            $votableId = $request->votable_id;      // Frontend sends votable_id
            $user = auth('api')->user();
            
            // Map to database column names (voteable with 'ea')
            $voteableType = $votableType;
            $voteableId = $votableId;

            $voteCounts = $this->getVoteCounts($voteableType, $voteableId);
            
            $userVote = null;
            if ($user) {
                $userVoteValue = DB::table('votes')
                    ->where('user_id', $user->id)
                    ->where('voteable_type', $voteableType)
                    ->where('voteable_id', $voteableId)
                    ->value('vote');
                
                $userVote = $userVoteValue ? $this->convertValueToVoteType($userVoteValue) : null;
            }

            return response()->json([
                'success' => true,
                'vote_counts' => $voteCounts,
                'user_vote' => $userVote
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching votes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch votes'
            ], 500);
        }
    }

    /**
     * Get user's voting history
     */
    public function getUserVotes(Request $request)
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            
            $perPage = min($request->get('per_page', 20), 50);
            
            $votes = Vote::byUser($user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Enrich votes with content information
            $enrichedVotes = [];
            foreach ($votes->items() as $vote) {
                $contentInfo = $this->getContentInfo($vote->voteable_type, $vote->voteable_id);
                $enrichedVotes[] = [
                    'id' => $vote->id,
                    'vote_type' => $vote->vote_type,
                    'voteable_type' => $vote->voteable_type,
                    'voteable_id' => $vote->voteable_id,
                    'created_at' => $vote->created_at,
                    'content' => $contentInfo
                ];
            }

            return response()->json([
                'success' => true,
                'votes' => $enrichedVotes,
                'pagination' => [
                    'current_page' => $votes->currentPage(),
                    'per_page' => $votes->perPage(),
                    'total' => $votes->total(),
                    'last_page' => $votes->lastPage()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching user votes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user votes'
            ], 500);
        }
    }

    /**
     * Get vote statistics for user
     */
    public function getVoteStats()
    {
        try {
            $user = auth('api')->user();
            
            $stats = [
                'votes_given' => DB::table('votes')->where('user_id', $user->id)->count(),
                'upvotes_given' => DB::table('votes')->where('user_id', $user->id)->where('vote', 1)->count(),
                'downvotes_given' => DB::table('votes')->where('user_id', $user->id)->where('vote', -1)->count(),
                'votes_received' => 0,
                'upvotes_received' => 0,
                'downvotes_received' => 0,
                'net_score' => 0
            ];

            // Calculate votes received on user's content
            $userContent = [
                'news' => DB::table('news')->where('author_id', $user->id)->pluck('id'),
                'forum_threads' => DB::table('forum_threads')->where('user_id', $user->id)->pluck('id'),
                'forum_posts' => DB::table('posts')->where('user_id', $user->id)->pluck('id'),
            ];

            foreach ($userContent as $type => $ids) {
                if ($ids->isNotEmpty()) {
                    $typeStats = DB::table('votes')
                        ->where('voteable_type', $type)
                        ->whereIn('voteable_id', $ids)
                        ->selectRaw('vote, COUNT(*) as count')
                        ->groupBy('vote')
                        ->get();

                    foreach ($typeStats as $stat) {
                        $stats['votes_received'] += $stat->count;
                        if ($stat->vote === 1) {
                            $stats['upvotes_received'] += $stat->count;
                        } else if ($stat->vote === -1) {
                            $stats['downvotes_received'] += $stat->count;
                        }
                    }
                }
            }

            $stats['net_score'] = $stats['upvotes_received'] - $stats['downvotes_received'];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching vote stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vote statistics'
            ], 500);
        }
    }

    /**
     * Get vote counts for content
     */
    private function getVoteCounts($voteableType, $voteableId)
    {
        $counts = DB::table('votes')
            ->where('voteable_type', $voteableType)
            ->where('voteable_id', $voteableId)
            ->selectRaw('vote, COUNT(*) as count')
            ->groupBy('vote')
            ->get()
            ->keyBy('vote');

        // Convert numeric vote values to counts (1 = upvote, -1 = downvote)
        $upvotes = $counts->get(1) ? $counts->get(1)->count : 0;
        $downvotes = $counts->get(-1) ? $counts->get(-1)->count : 0;

        return [
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'total' => $upvotes + $downvotes,
            'score' => $upvotes - $downvotes
        ];
    }

    /**
     * Get content information for votes
     */
    private function getContentInfo($votableType, $votableId)
    {
        switch ($voteableType) {
            case 'news':
                $news = DB::table('news')->where('id', $voteableId)->first(['title', 'slug']);
                return $news ? [
                    'type' => 'news',
                    'title' => $news->title,
                    'url' => "/news/{$news->slug}"
                ] : null;

            case 'news_comment':
                $comment = DB::table('news_comments as nc')
                    ->join('news as n', 'nc.news_id', '=', 'n.id')
                    ->where('nc.id', $votableId)
                    ->first(['n.title', 'n.slug', 'n.id as news_id']);
                return $comment ? [
                    'type' => 'news_comment',
                    'title' => "Comment on: {$comment->title}",
                    'url' => "/news/{$comment->slug}#comment-{$votableId}"
                ] : null;

            case 'forum_thread':
                $thread = DB::table('forum_threads')->where('id', $voteableId)->first(['title', 'slug']);
                return $thread ? [
                    'type' => 'forum_thread',
                    'title' => $thread->title,
                    'url' => "/forums/threads/{$votableId}"
                ] : null;

            case 'forum_post':
                $post = DB::table('forum_posts as p')
                    ->join('forum_threads as t', 'p.thread_id', '=', 't.id')
                    ->where('p.id', $votableId)
                    ->first(['p.content', 't.title', 't.id as thread_id']);
                return $post ? [
                    'type' => 'forum_post',
                    'title' => "Reply in: {$post->title}",
                    'url' => "/forums/threads/{$post->thread_id}#post-{$votableId}"
                ] : null;

            case 'match_comment':
                $comment = DB::table('match_comments as mc')
                    ->join('matches as m', 'mc.match_id', '=', 'm.id')
                    ->where('mc.id', $votableId)
                    ->first(['m.title', 'm.id as match_id']);
                return $comment ? [
                    'type' => 'match_comment',
                    'title' => "Comment on match: {$comment->title}",
                    'url' => "/matches/{$comment->match_id}#comment-{$votableId}"
                ] : null;

            default:
                return null;
        }
    }

    /**
     * Vote on news article
     */
    public function voteNews(Request $request, $newsId)
    {
        $request->merge([
            'votable_type' => 'news',
            'votable_id' => $newsId
        ]);

        return $this->vote($request);
    }

    /**
     * Vote on news comment
     */
    public function voteNewsComment(Request $request, $newsId, $commentId)
    {
        $request->merge([
            'votable_type' => 'news_comment',
            'votable_id' => $commentId
        ]);

        return $this->vote($request);
    }

    /**
     * Vote on forum thread
     */
    public function voteThread(Request $request, $threadId)
    {
        $request->merge([
            'votable_type' => 'forum_thread',
            'votable_id' => $threadId
        ]);

        return $this->vote($request);
    }

    /**
     * Vote on forum post
     */
    public function votePost(Request $request, $postId)
    {
        $request->merge([
            'votable_type' => 'forum_post',
            'votable_id' => $postId
        ]);

        return $this->vote($request);
    }

    /**
     * Update content vote count fields in their respective tables
     */
    private function updateContentVoteCounts($voteableType, $voteableId, $voteCounts)
    {
        switch ($voteableType) {
            case 'news':
                DB::table('news')->where('id', $voteableId)->update([
                    'upvotes' => $voteCounts['upvotes'],
                    'downvotes' => $voteCounts['downvotes'],
                    'score' => $voteCounts['score'],
                    'updated_at' => now()
                ]);
                break;
                
            case 'news_comment':
                // Check if news_comments table has vote count fields
                if (Schema::hasColumn('news_comments', 'upvotes')) {
                    DB::table('news_comments')->where('id', $voteableId)->update([
                        'upvotes' => $voteCounts['upvotes'],
                        'downvotes' => $voteCounts['downvotes'],
                        'score' => $voteCounts['score'],
                        'updated_at' => now()
                    ]);
                }
                break;
                
            case 'forum_thread':
                // Check if forum_threads table has vote count fields
                if (Schema::hasColumn('forum_threads', 'upvotes')) {
                    DB::table('forum_threads')->where('id', $voteableId)->update([
                        'upvotes' => $voteCounts['upvotes'],
                        'downvotes' => $voteCounts['downvotes'],
                        'score' => $voteCounts['score'],
                        'updated_at' => now()
                    ]);
                }
                break;
                
            case 'forum_post':
                // Check if forum_posts table has vote count fields
                if (Schema::hasColumn('forum_posts', 'upvotes')) {
                    DB::table('forum_posts')->where('id', $voteableId)->update([
                        'upvotes' => $voteCounts['upvotes'],
                        'downvotes' => $voteCounts['downvotes'],
                        'score' => $voteCounts['score'],
                        'updated_at' => now()
                    ]);
                }
                break;
                
            // Add other content types as needed
        }
    }

    /**
     * Convert vote type string to database value
     */
    private function convertVoteTypeToValue($voteType)
    {
        switch ($voteType) {
            case 'upvote':
                return 1;
            case 'downvote':
                return -1;
            default:
                throw new \InvalidArgumentException("Invalid vote type: {$voteType}");
        }
    }

    /**
     * Convert database value to vote type string
     */
    private function convertValueToVoteType($value)
    {
        switch ($value) {
            case 1:
                return 'upvote';
            case -1:
                return 'downvote';
            default:
                return null;
        }
    }

    /**
     * Check if content exists
     */
    private function contentExists($voteableType, $voteableId)
    {
        switch ($voteableType) {
            case 'news':
                return DB::table('news')->where('id', $voteableId)->exists();
            case 'news_comment':
                return DB::table('news_comments')->where('id', $voteableId)->exists();
            case 'forum_thread':
                return DB::table('forum_threads')->where('id', $voteableId)->exists();
            case 'forum_post':
                return DB::table('forum_posts')->where('id', $voteableId)->exists();
            case 'match_comment':
                return DB::table('match_comments')->where('id', $voteableId)->exists();
            default:
                return false;
        }
    }

    /**
     * Get vote message based on action
     */
    private function getVoteMessage($action, $voteType)
    {
        switch ($action) {
            case 'added':
                return $voteType === 'upvote' ? 'Upvoted successfully!' : 'Downvoted successfully!';
            case 'changed':
                return 'Vote updated successfully!';
            case 'removed':
                return 'Vote removed successfully!';
            default:
                return 'Vote processed successfully!';
        }
    }
}