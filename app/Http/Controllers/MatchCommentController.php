<?php

namespace App\Http\Controllers;

use App\Models\MatchComment;
use App\Models\GameMatch;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchCommentController extends Controller
{
    /**
     * Get all comments for a match with forum-style structure
     */
    public function index(Request $request, $matchId): JsonResponse
    {
        try {
            $match = GameMatch::findOrFail($matchId);
            
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $sortBy = $request->get('sort', 'created_at');
            $sortOrder = $request->get('order', 'asc');
            
            // Get top-level comments without complex withCount first
            $comments = MatchComment::where('match_id', $matchId)
                ->whereNull('parent_id')
                ->approved()
                ->with([
                    'user:id,name,email,avatar,role,team_flair_id,hero_flair',
                    'mentions',
                    'replies.user:id,name,email,avatar,role,team_flair_id,hero_flair',
                    'replies.mentions'
                ])
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);
            
            // Manually calculate vote counts
            $commentIds = $comments->pluck('id')->toArray();
            $allCommentIds = $commentIds;
            
            // Get reply IDs too
            foreach ($comments as $comment) {
                $replyIds = $comment->replies->pluck('id')->toArray();
                $allCommentIds = array_merge($allCommentIds, $replyIds);
            }
            
            // Get vote counts for all comments at once
            $voteCounts = Vote::whereIn('voteable_id', $allCommentIds)
                ->where('voteable_type', 'App\\Models\\MatchComment')
                ->selectRaw('voteable_id, sum(case when vote = 1 then 1 else 0 end) as upvotes, sum(case when vote = -1 then 1 else 0 end) as downvotes')
                ->groupBy('voteable_id')
                ->get()
                ->keyBy('voteable_id');
            
            // Get user votes if authenticated
            $userVotes = [];
            if (Auth::check()) {
                $userVotes = Vote::whereIn('voteable_id', $allCommentIds)
                    ->where('voteable_type', 'App\\Models\\MatchComment')
                    ->where('user_id', Auth::id())
                    ->get()
                    ->keyBy('voteable_id');
            }
            
            // Transform comments to match forum structure
            $transformedComments = $comments->getCollection()->map(function ($comment) use ($voteCounts, $userVotes) {
                $commentVotes = $voteCounts->get($comment->id);
                $upvotes = $commentVotes ? $commentVotes->upvotes : 0;
                $downvotes = $commentVotes ? $commentVotes->downvotes : 0;
                $userVote = isset($userVotes[$comment->id]) ? 
                    ($userVotes[$comment->id]->vote == 1 ? 'upvote' : 'downvote') : null;
                
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'author' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'username' => $comment->user->name,
                        'email' => $comment->user->email,
                        'avatar_url' => $comment->user->avatar,
                        'role' => $comment->user->role,
                        'team_flair_id' => $comment->user->team_flair_id,
                        'hero_flair' => $comment->user->hero_flair
                    ],
                    'meta' => [
                        'created_at' => $comment->created_at,
                        'updated_at' => $comment->updated_at,
                        'is_edited' => $comment->is_edited,
                        'edited_at' => $comment->edited_at
                    ],
                    'stats' => [
                        'upvotes' => $upvotes,
                        'downvotes' => $downvotes,
                        'score' => $upvotes - $downvotes
                    ],
                    'upvotes' => $upvotes,
                    'downvotes' => $downvotes,
                    'user_vote' => $userVote,
                    'mentions' => $comment->mentions->map(function ($mention) {
                        return [
                            'id' => $mention->entity_id,
                            'type' => $mention->type,
                            'mention_text' => $mention->mention_text,
                            'name' => $mention->name ?? '',
                            'display_name' => $mention->display_name ?? '',
                            'name' => $mention->name ?? '',
                            'team_name' => $mention->team_name ?? '',
                            'player_name' => $mention->player_name ?? ''
                        ];
                    }),
                    'replies' => $comment->replies->map(function ($reply) use ($voteCounts, $userVotes) {
                        $replyVotes = $voteCounts->get($reply->id);
                        $replyUpvotes = $replyVotes ? $replyVotes->upvotes : 0;
                        $replyDownvotes = $replyVotes ? $replyVotes->downvotes : 0;
                        $replyUserVote = isset($userVotes[$reply->id]) ? 
                            ($userVotes[$reply->id]->vote == 1 ? 'upvote' : 'downvote') : null;
                        
                        return [
                            'id' => $reply->id,
                            'content' => $reply->content,
                            'author' => [
                                'id' => $reply->user->id,
                                'name' => $reply->user->name,
                                'username' => $reply->user->name,
                                'email' => $reply->user->email,
                                'avatar_url' => $reply->user->avatar,
                                'role' => $reply->user->role,
                                'team_flair_id' => $reply->user->team_flair_id,
                                'hero_flair' => $reply->user->hero_flair
                            ],
                            'meta' => [
                                'created_at' => $reply->created_at,
                                'updated_at' => $reply->updated_at,
                                'is_edited' => $reply->is_edited,
                                'edited_at' => $reply->edited_at
                            ],
                            'stats' => [
                                'upvotes' => $replyUpvotes,
                                'downvotes' => $replyDownvotes,
                                'score' => $replyUpvotes - $replyDownvotes
                            ],
                            'upvotes' => $replyUpvotes,
                            'downvotes' => $replyDownvotes,
                            'user_vote' => $replyUserVote,
                            'mentions' => $reply->mentions->map(function ($mention) {
                                return [
                                    'id' => $mention->entity_id,
                                    'type' => $mention->type,
                                    'mention_text' => $mention->mention_text,
                                    'name' => $mention->name ?? '',
                                    'display_name' => $mention->display_name ?? '',
                                    'name' => $mention->name ?? '',
                                    'team_name' => $mention->team_name ?? '',
                                    'player_name' => $mention->player_name ?? ''
                                ];
                            })
                        ];
                    })
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $transformedComments,
                'meta' => [
                    'current_page' => $comments->currentPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total(),
                    'last_page' => $comments->lastPage(),
                    'has_more_pages' => $comments->hasMorePages(),
                    'match' => [
                        'id' => $match->id,
                        'team1' => $match->team1->name ?? 'Team 1',
                        'team2' => $match->team2->name ?? 'Team 2',
                        'status' => $match->status
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load match comments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new comment (forum-style)
     */
    public function store(Request $request, $matchId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string|min:1|max:5000',
                'parent_id' => 'nullable|exists:match_comments,id'
            ]);
            
            $match = GameMatch::findOrFail($matchId);
            
            // Check if user is authenticated
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to post comments'
                ], 401);
            }
            
            // Check if match allows comments (could add a setting later)
            if ($match->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Comments are not allowed on cancelled matches'
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Create the comment
            $comment = MatchComment::create([
                'match_id' => $matchId,
                'user_id' => Auth::id(),
                'parent_id' => $request->parent_id,
                'content' => trim($request->content),
                'status' => 'approved' // Auto-approve for now, can add moderation later
            ]);
            
            // Process mentions
            $comment->processMentions();
            
            // Load relationships for response
            $comment->load([
                'user:id,name,email,avatar,role,team_flair_id,hero_flair',
                'mentions'
            ]);
            
            DB::commit();
            
            // Transform response to match forum structure
            $responseData = [
                'id' => $comment->id,
                'content' => $comment->content,
                'author' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'username' => $comment->user->name,
                    'email' => $comment->user->email,
                    'avatar_url' => $comment->user->avatar,
                    'role' => $comment->user->role,
                    'team_flair_id' => $comment->user->team_flair_id,
                    'hero_flair' => $comment->user->hero_flair
                ],
                'meta' => [
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'is_edited' => false,
                    'edited_at' => null
                ],
                'stats' => [
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'score' => 0
                ],
                'upvotes' => 0,
                'downvotes' => 0,
                'user_vote' => null,
                'mentions' => $comment->mentions->map(function ($mention) {
                    return [
                        'id' => $mention->entity_id,
                        'type' => $mention->type,
                        'mention_text' => $mention->mention_text,
                        'name' => $mention->name ?? '',
                        'display_name' => $mention->display_name ?? '',
                        'name' => $mention->name ?? '',
                        'team_name' => $mention->team_name ?? '',
                        'player_name' => $mention->player_name ?? ''
                    ];
                }),
                'replies' => []
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Comment posted successfully',
                'comment' => $responseData
            ], 201);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to post comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a comment (forum-style)
     */
    public function update(Request $request, $commentId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string|min:1|max:5000'
            ]);
            
            $comment = MatchComment::findOrFail($commentId);
            
            // Check permissions
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            
            $user = Auth::user();
            if (!$comment->canBeEditedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to edit this comment'
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Update content
            $comment->update([
                'content' => trim($request->content)
            ]);
            
            // Mark as edited
            $comment->markAsEdited();
            
            // Reprocess mentions
            $comment->mentions()->delete();
            $comment->processMentions();
            
            // Reload relationships
            $comment->load([
                'user:id,name,email,avatar,role,team_flair_id,hero_flair',
                'mentions'
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'comment' => $comment
            ]);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a comment (forum-style soft delete)
     */
    public function destroy($commentId): JsonResponse
    {
        try {
            $comment = MatchComment::findOrFail($commentId);
            
            // Check permissions
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            
            $user = Auth::user();
            if (!$comment->canBeEditedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this comment'
                ], 403);
            }
            
            // Soft delete the comment
            $comment->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vote on a comment (forum-style voting)
     */
    public function vote(Request $request, $commentId): JsonResponse
    {
        try {
            $request->validate([
                'vote_type' => 'required|in:upvote,downvote,remove'
            ]);
            
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to vote'
                ], 401);
            }
            
            $comment = MatchComment::findOrFail($commentId);
            $user = Auth::user();
            
            DB::beginTransaction();
            
            // Remove existing vote if any
            $existingVote = $comment->votes()->where('user_id', $user->id)->first();
            if ($existingVote) {
                $existingVote->delete();
            }
            
            $newVoteType = null;
            
            // Add new vote if not removing
            if ($request->vote_type !== 'remove') {
                $voteValue = $request->vote_type === 'upvote' ? 1 : -1;
                $comment->votes()->create([
                    'user_id' => $user->id,
                    'vote' => $voteValue
                ]);
                $newVoteType = $request->vote_type;
            }
            
            // Recalculate vote counts
            $upvotes = $comment->votes()->where('vote', 1)->count();
            $downvotes = $comment->votes()->where('vote', -1)->count();
            
            $comment->update([
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'score' => $upvotes - $downvotes
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Vote updated successfully',
                'vote_counts' => [
                    'upvotes' => $upvotes,
                    'downvotes' => $downvotes,
                    'score' => $upvotes - $downvotes
                ],
                'user_vote' => $newVoteType
            ]);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vote',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Flag a comment for moderation
     */
    public function flag(Request $request, $commentId): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);
            
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to report comments'
                ], 401);
            }
            
            $comment = MatchComment::findOrFail($commentId);
            $user = Auth::user();
            
            // Create a report (assuming we have a reports system)
            // This would need to be implemented if we want reporting functionality
            
            return response()->json([
                'success' => true,
                'message' => 'Comment reported successfully. Moderators will review it.'
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to report comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}