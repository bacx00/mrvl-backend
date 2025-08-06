<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserActivity;
use Exception;

class VoteController extends Controller
{
    /**
     * Vote on content (news, forum posts, etc.)
     */
    public function vote(Request $request)
    {
        try {
            $request->validate([
                'votable_type' => 'required|string|in:news,news_comment,forum_thread,forum_post,match_comment',
                'votable_id' => 'required|integer',
                'vote_type' => 'required|string|in:upvote,downvote'
            ]);

            $user = Auth::user();
            $votableType = $request->votable_type;
            $votableId = $request->votable_id;
            $voteType = $request->vote_type;

            // Check if user already voted
            $existingVote = DB::table('votes')
                ->where('user_id', $user->id)
                ->where('votable_type', $votableType)
                ->where('votable_id', $votableId)
                ->first();

            $action = null;
            $oldVoteType = $existingVote ? $existingVote->vote_type : null;

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote (toggle off)
                    DB::table('votes')
                        ->where('id', $existingVote->id)
                        ->delete();
                    
                    $action = 'removed';
                    
                    // Track activity
                    UserActivity::track(
                        $user->id,
                        'vote_removed',
                        "Removed {$voteType} on {$votableType}",
                        $votableType,
                        $votableId,
                        ['vote_type' => $voteType]
                    );
                } else {
                    // Change vote type
                    DB::table('votes')
                        ->where('id', $existingVote->id)
                        ->update([
                            'vote_type' => $voteType,
                            'updated_at' => now()
                        ]);
                    
                    $action = 'changed';
                    
                    // Track activity
                    UserActivity::track(
                        $user->id,
                        'vote_changed',
                        "Changed vote from {$oldVoteType} to {$voteType} on {$votableType}",
                        $votableType,
                        $votableId,
                        ['old_vote_type' => $oldVoteType, 'new_vote_type' => $voteType]
                    );
                }
            } else {
                // Create new vote
                DB::table('votes')->insert([
                    'user_id' => $user->id,
                    'votable_type' => $votableType,
                    'votable_id' => $votableId,
                    'vote_type' => $voteType,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $action = 'added';
                
                // Track activity
                UserActivity::track(
                    $user->id,
                    'vote_added',
                    "Added {$voteType} on {$votableType}",
                    $votableType,
                    $votableId,
                    ['vote_type' => $voteType]
                );
            }

            // Get updated vote counts
            $voteCounts = $this->getVoteCounts($votableType, $votableId);
            
            // Get user's current vote
            $userVote = DB::table('votes')
                ->where('user_id', $user->id)
                ->where('votable_type', $votableType)
                ->where('votable_id', $votableId)
                ->value('vote_type');

            return response()->json([
                'success' => true,
                'action' => $action,
                'vote_counts' => $voteCounts,
                'user_vote' => $userVote,
                'message' => $this->getVoteMessage($action, $voteType)
            ]);

        } catch (Exception $e) {
            Log::error('Error processing vote: ' . $e->getMessage());
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

            $votableType = $request->votable_type;
            $votableId = $request->votable_id;
            $user = Auth::user();

            $voteCounts = $this->getVoteCounts($votableType, $votableId);
            
            $userVote = null;
            if ($user) {
                $userVote = DB::table('votes')
                    ->where('user_id', $user->id)
                    ->where('votable_type', $votableType)
                    ->where('votable_id', $votableId)
                    ->value('vote_type');
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
            $user = Auth::user();
            $perPage = min($request->get('per_page', 20), 50);
            
            $votes = DB::table('votes')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Enrich votes with content information
            $enrichedVotes = [];
            foreach ($votes->items() as $vote) {
                $contentInfo = $this->getContentInfo($vote->votable_type, $vote->votable_id);
                $enrichedVotes[] = [
                    'id' => $vote->id,
                    'vote_type' => $vote->vote_type,
                    'votable_type' => $vote->votable_type,
                    'votable_id' => $vote->votable_id,
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
            $user = Auth::user();
            
            $stats = [
                'votes_given' => DB::table('votes')->where('user_id', $user->id)->count(),
                'upvotes_given' => DB::table('votes')->where('user_id', $user->id)->where('vote_type', 'upvote')->count(),
                'downvotes_given' => DB::table('votes')->where('user_id', $user->id)->where('vote_type', 'downvote')->count(),
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
                        ->where('votable_type', $type)
                        ->whereIn('votable_id', $ids)
                        ->selectRaw('vote_type, COUNT(*) as count')
                        ->groupBy('vote_type')
                        ->get();

                    foreach ($typeStats as $stat) {
                        $stats['votes_received'] += $stat->count;
                        if ($stat->vote_type === 'upvote') {
                            $stats['upvotes_received'] += $stat->count;
                        } else {
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
    private function getVoteCounts($votableType, $votableId)
    {
        $counts = DB::table('votes')
            ->where('votable_type', $votableType)
            ->where('votable_id', $votableId)
            ->selectRaw('vote_type, COUNT(*) as count')
            ->groupBy('vote_type')
            ->get()
            ->keyBy('vote_type');

        return [
            'upvotes' => $counts->get('upvote')->count ?? 0,
            'downvotes' => $counts->get('downvote')->count ?? 0,
            'total' => ($counts->get('upvote')->count ?? 0) + ($counts->get('downvote')->count ?? 0),
            'score' => ($counts->get('upvote')->count ?? 0) - ($counts->get('downvote')->count ?? 0)
        ];
    }

    /**
     * Get content information for votes
     */
    private function getContentInfo($votableType, $votableId)
    {
        switch ($votableType) {
            case 'news':
                $news = DB::table('news')->where('id', $votableId)->first(['title', 'slug']);
                return $news ? [
                    'type' => 'news',
                    'title' => $news->title,
                    'url' => "/news/{$news->slug}"
                ] : null;

            case 'forum_thread':
                $thread = DB::table('forum_threads')->where('id', $votableId)->first(['title', 'slug']);
                return $thread ? [
                    'type' => 'forum_thread',
                    'title' => $thread->title,
                    'url' => "/forums/threads/{$votableId}"
                ] : null;

            case 'forum_post':
                $post = DB::table('posts as p')
                    ->join('forum_threads as t', 'p.thread_id', '=', 't.id')
                    ->where('p.id', $votableId)
                    ->first(['p.content', 't.title', 't.id as thread_id']);
                return $post ? [
                    'type' => 'forum_post',
                    'title' => "Reply in: {$post->title}",
                    'url' => "/forums/threads/{$post->thread_id}#post-{$votableId}"
                ] : null;

            default:
                return null;
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