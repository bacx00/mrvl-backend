<?php
namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsComment;
use App\Models\NewsVote;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Mention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'n.category as category_name'
                ]);

            // Filter by category
            if ($request->category && $request->category !== 'all') {
                $query->where('n.category', $request->category);
            }

            // Enhanced search functionality including mentions
            if ($request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    // Search in title, content, and excerpt
                    $q->where('n.title', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('n.content', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('n.excerpt', 'LIKE', "%{$searchTerm}%");
                    
                    // Search for mentioned users by name (support both @username and partial username)
                    $q->orWhereExists(function($subQuery) use ($searchTerm) {
                        $subQuery->select(DB::raw(1))
                            ->from('mentions as m')
                            ->join('users as u', 'm.mentioned_id', '=', 'u.id')
                            ->where('m.mentionable_type', 'news')
                            ->whereColumn('m.mentionable_id', 'n.id')
                            ->where('m.mentioned_type', 'user')
                            ->where(function($userQuery) use ($searchTerm) {
                                $cleanSearch = str_replace('@', '', $searchTerm);
                                $userQuery->where('u.name', 'LIKE', "%{$cleanSearch}%")
                                    ->orWhere('m.mention_text', 'LIKE', "%{$searchTerm}%");
                            });
                    });
                    
                    // Search for mentioned teams
                    $q->orWhereExists(function($subQuery) use ($searchTerm) {
                        $subQuery->select(DB::raw(1))
                            ->from('mentions as m')
                            ->join('teams as t', 'm.mentioned_id', '=', 't.id')
                            ->where('m.mentionable_type', 'news')
                            ->whereColumn('m.mentionable_id', 'n.id')
                            ->where('m.mentioned_type', 'team')
                            ->where(function($teamQuery) use ($searchTerm) {
                                $cleanSearch = str_replace(['@team:', '@'], '', $searchTerm);
                                $teamQuery->where('t.name', 'LIKE', "%{$cleanSearch}%")
                                    ->orWhere('t.short_name', 'LIKE', "%{$cleanSearch}%")
                                    ->orWhere('m.mention_text', 'LIKE', "%{$searchTerm}%");
                            });
                    });
                    
                    // Search for mentioned players
                    $q->orWhereExists(function($subQuery) use ($searchTerm) {
                        $subQuery->select(DB::raw(1))
                            ->from('mentions as m')
                            ->join('players as p', 'm.mentioned_id', '=', 'p.id')
                            ->where('m.mentionable_type', 'news')
                            ->whereColumn('m.mentionable_id', 'n.id')
                            ->where('m.mentioned_type', 'player')
                            ->where(function($playerQuery) use ($searchTerm) {
                                $cleanSearch = str_replace(['@player:', '@'], '', $searchTerm);
                                $playerQuery->where('p.username', 'LIKE', "%{$cleanSearch}%")
                                    ->orWhere('p.real_name', 'LIKE', "%{$cleanSearch}%")
                                    ->orWhere('m.mention_text', 'LIKE', "%{$searchTerm}%");
                            });
                    });
                });
            }

            // Sort options
            $sortBy = $request->get('sort', 'latest');
            switch ($sortBy) {
                case 'popular':
                    $query->orderBy('n.score', 'desc');
                    break;
                case 'hot':
                    $query->orderBy('n.comments_count', 'desc');
                    break;
                case 'trending':
                    $query->where('n.created_at', '>=', now()->subDays(7))
                          ->orderBy('n.views', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('n.created_at', 'asc');
                    break;
                default: // latest
                    $query->orderBy('n.featured', 'desc')
                          ->orderBy('n.published_at', 'desc');
            }

            // Only show published news
            $query->where('n.status', 'published');

            $news = $query->paginate(15);

            // Add additional data for each news article with VLR.gg-style formatting
            $newsData = collect($news->items())->map(function($article) {
                $author = $this->getUserWithFlairs($article->author_id);
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'featured_image' => $article->featured_image,
                    'author' => $author,
                    'category' => [
                        'name' => $article->category_name,
                        'slug' => Str::slug($article->category_name ?? 'general'),
                        'color' => '#6b7280'
                    ],
                    'stats' => [
                        'views' => $article->views ?? 0,
                        'comments' => $article->comments_count ?? 0,
                        'score' => $article->score ?? 0,
                        'upvotes' => $article->upvotes ?? 0,
                        'downvotes' => $article->downvotes ?? 0
                    ],
                    'meta' => [
                        'featured' => (bool)$article->featured,
                        'breaking' => (bool)$article->breaking,
                        'published_at' => $article->published_at,
                        'created_at' => $article->created_at,
                        'updated_at' => $article->updated_at,
                        'read_time' => $this->calculateReadTime($article->content)
                    ],
                    'mentions' => array_merge(
                        $this->extractMentions($article->title),
                        $this->extractMentions($article->excerpt ?? ''),
                        $this->extractMentions($article->content)
                    ),
                    'tags' => $article->tags ? json_decode($article->tags, true) : []
                ];
            });

            return response()->json([
                'data' => $newsData,
                'pagination' => [
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                    'per_page' => $news->perPage(),
                    'total' => $news->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($identifier)
    {
        try {
            // Get news article by slug or ID
            $query = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->where('n.status', 'published');
            
            // Check if identifier is numeric (ID) or string (slug)
            if (is_numeric($identifier)) {
                $query->where('n.id', $identifier);
            } else {
                $query->where('n.slug', $identifier);
            }
            
            $article = $query
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'n.category as category_name'
                ])
                ->first();

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found'
                ], 404);
            }

            // Get all comments for this article with nested structure (VLR.gg style)
            $comments = $this->getNewsComments($article->id);

            // Increment view count
            DB::table('news')->where('id', $article->id)->increment('views');

            // Get user's vote on article (if authenticated)
            $userVote = null;
            if (Auth::check()) {
                $userVote = DB::table('news_votes')
                    ->where('news_id', $article->id)
                    ->where('user_id', Auth::id())
                    ->where('comment_id', null)
                    ->value('vote_type');
            }

            // Get related articles
            $relatedArticles = $this->getRelatedArticles($article->id, $article->category);

            $articleData = [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'content' => $article->content,
                'excerpt' => $article->excerpt,
                'featured_image' => $article->featured_image,
                'author' => $this->getUserWithFlairs($article->author_id),
                'category' => [
                    'name' => $article->category_name,
                    'slug' => Str::slug($article->category_name ?? 'general'),
                    'color' => '#6b7280'
                ],
                'stats' => [
                    'views' => $article->views ?? 0,
                    'comments' => $article->comments_count ?? 0,
                    'score' => $article->score ?? 0,
                    'upvotes' => $article->upvotes ?? 0,
                    'downvotes' => $article->downvotes ?? 0
                ],
                'meta' => [
                    'featured' => (bool)$article->featured,
                    'breaking' => (bool)$article->breaking,
                    'published_at' => $article->published_at,
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at,
                    'read_time' => $this->calculateReadTime($article->content)
                ],
                'mentions' => $this->extractMentions($article->content),
                'tags' => $article->tags ? json_decode($article->tags, true) : [],
                'videos' => $this->extractVideoEmbeds($article->content),
                'user_vote' => $userVote,
                'comments' => $comments,
                'related_articles' => $relatedArticles
            ];

            return response()->json([
                'data' => $articleData,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching article: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Check if user is authenticated and has admin role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }
        
        // Convert empty strings to null for nullable fields
        if ($request->has('featured_image') && $request->featured_image === '') {
            $request->merge(['featured_image' => null]);
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:50',
            'excerpt' => 'nullable|string|max:500',
            'category' => 'required|string',
            'featured_image' => 'nullable|url',
            'tags' => 'nullable|array',
            'featured' => 'boolean',
            'breaking' => 'boolean',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date'
        ]);

        try {
            $slug = Str::slug($request->title);
            
            // Ensure unique slug
            $counter = 1;
            $originalSlug = $slug;
            while (DB::table('news')->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $newsId = DB::table('news')->insertGetId([
                'title' => $request->title,
                'slug' => $slug,
                'content' => $request->content,
                'excerpt' => $request->excerpt,
                'category' => $request->category,
                'author_id' => Auth::id(),
                'featured_image' => $request->featured_image,
                'tags' => $request->tags ? json_encode($request->tags) : null,
                'featured' => $request->featured ?? false,
                'breaking' => $request->breaking ?? false,
                'status' => $request->status,
                'published_at' => $request->status === 'published' ? ($request->published_at ?? now()) : $request->published_at,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Process mentions from title, excerpt and content
            $this->processMentions($request->title, $newsId);
            if ($request->excerpt) {
                $this->processMentions($request->excerpt, $newsId);
            }
            $this->processMentions($request->content, $newsId);

            return response()->json([
                'data' => ['id' => $newsId, 'slug' => $slug],
                'success' => true,
                'message' => 'News article created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating article: ' . $e->getMessage()
            ], 500);
        }
    }

    public function comment(Request $request, $newsId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'content' => 'required|string|min:1',
            'parent_id' => 'nullable|exists:news_comments,id',
            'mentions' => 'nullable|array'
        ]);

        try {
            // Check if news exists
            $news = DB::table('news')->where('id', $newsId)->first();
            if (!$news) {
                return response()->json(['success' => false, 'message' => 'News article not found'], 404);
            }

            $commentId = DB::table('news_comments')->insertGetId([
                'news_id' => $newsId,
                'user_id' => Auth::id(),
                'content' => $request->content,
                'parent_id' => $request->parent_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update news comment count
            DB::table('news')
                ->where('id', $newsId)
                ->increment('comments_count');

            // Process mentions from frontend data
            if ($request->has('mentions') && is_array($request->mentions)) {
                $this->processMentionsFromData($request->mentions, $newsId, $commentId);
            }
            // Also extract mentions from content as fallback
            $this->processMentions($request->content, $newsId, $commentId);

            return response()->json([
                'data' => ['id' => $commentId],
                'success' => true,
                'message' => 'Comment posted successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error posting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function vote(Request $request, $newsId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote',
            'comment_id' => 'nullable|exists:news_comments,id'
        ]);

        try {
            $userId = Auth::id();
            $voteType = $request->vote_type;
            $commentId = $request->comment_id;

            // Check for existing vote
            if ($commentId) {
                // Voting on a comment - check by comment_id only
                $existingVote = DB::table('news_votes')
                    ->where('user_id', $userId)
                    ->where('comment_id', $commentId)
                    ->whereNull('news_id')
                    ->first();
            } else {
                // Voting on article - check by news_id only  
                $existingVote = DB::table('news_votes')
                    ->where('news_id', $newsId)
                    ->where('user_id', $userId)
                    ->whereNull('comment_id')
                    ->first();
            }

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type
                    DB::table('news_votes')->where('id', $existingVote->id)->delete();
                    $this->updateNewsVoteCounts($newsId, $commentId);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed'
                    ]);
                } else {
                    // Update vote if different type
                    DB::table('news_votes')
                        ->where('id', $existingVote->id)
                        ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                    
                    // Update vote counts
                    $this->updateNewsVoteCounts($newsId, $commentId);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote changed',
                        'action' => 'changed'
                    ]);
                }
            } else {
                // Create new vote with duplicate protection
                try {
                    if ($commentId) {
                        // Voting on a comment - only set comment_id
                        DB::table('news_votes')->insert([
                            'news_id' => null,
                            'comment_id' => $commentId,
                            'user_id' => $userId,
                            'vote_type' => $voteType,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } else {
                        // Voting on article - only set news_id
                        DB::table('news_votes')->insert([
                            'news_id' => $newsId,
                            'comment_id' => null,
                            'user_id' => $userId,
                            'vote_type' => $voteType,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    
                    // Update vote counts
                    $this->updateNewsVoteCounts($newsId, $commentId);

                    return response()->json([
                        'success' => true,
                        'message' => 'Vote recorded',
                        'action' => 'voted'
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle duplicate key constraint
                    if ($e->getCode() === '23000') {
                        // Re-check for existing vote and update it
                        if ($commentId) {
                            $existingVote = DB::table('news_votes')
                                ->where('user_id', $userId)
                                ->where('comment_id', $commentId)
                                ->whereNull('news_id')
                                ->first();
                        } else {
                            $existingVote = DB::table('news_votes')
                                ->where('news_id', $newsId)
                                ->where('user_id', $userId)
                                ->whereNull('comment_id')
                                ->first();
                        }
                        
                        if ($existingVote) {
                            DB::table('news_votes')
                                ->where('id', $existingVote->id)
                                ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                            
                            $this->updateNewsVoteCounts($newsId, $commentId);
                            
                            return response()->json([
                                'success' => true,
                                'message' => 'Vote updated',
                                'action' => 'changed'
                            ]);
                        }
                    }
                    throw $e; // Re-throw if not a duplicate key error
                }
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording vote: ' . $e->getMessage()
            ], 500);
        }
    }


    // Missing CRUD methods for comments
    public function updateComment(Request $request, $commentId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'content' => 'required|string|min:1'
        ]);

        try {
            // Check if comment exists and user owns it
            $comment = DB::table('news_comments')
                ->where('id', $commentId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found or unauthorized'
                ], 404);
            }

            DB::table('news_comments')
                ->where('id', $commentId)
                ->update([
                    'content' => $request->content,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyComment($commentId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            // Check if comment exists and user owns it or is admin/moderator
            $comment = DB::table('news_comments')->where('id', $commentId)->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            $user = Auth::user();
            if ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'moderator'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this comment'
                ], 403);
            }

            // Soft delete by updating deleted_at
            DB::table('news_comments')
                ->where('id', $commentId)
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now()
                ]);

            // Update comment count
            DB::table('news')
                ->where('id', $comment->news_id)
                ->decrement('comments_count');

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function voteComment(Request $request, $commentId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $request->validate([
            'vote_type' => 'required|in:upvote,downvote'
        ]);

        try {
            // Check if comment exists
            $comment = DB::table('news_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            $userId = Auth::id();
            $voteType = $request->vote_type;

            // Check for existing vote on this specific comment
            $existingVote = DB::table('news_votes')
                ->where('user_id', $userId)
                ->where('comment_id', $commentId)
                ->whereNull('news_id') // Only comment votes, not article votes
                ->first();

            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Remove vote if same type
                    DB::table('news_votes')->where('id', $existingVote->id)->delete();
                    $this->updateNewsVoteCounts($comment->news_id, $commentId);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote removed',
                        'action' => 'removed'
                    ]);
                } else {
                    // Update vote if different type
                    DB::table('news_votes')
                        ->where('id', $existingVote->id)
                        ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                    
                    // Update vote counts
                    $this->updateNewsVoteCounts($comment->news_id, $commentId);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote changed',
                        'action' => 'changed'
                    ]);
                }
            } else {
                // Create new vote with duplicate protection
                try {
                    DB::table('news_votes')->insert([
                        'news_id' => null, // Don't include news_id for comment votes to avoid constraint conflicts
                        'comment_id' => $commentId,
                        'user_id' => $userId,
                        'vote_type' => $voteType,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    // Update vote counts
                    $this->updateNewsVoteCounts($comment->news_id, $commentId);

                    return response()->json([
                        'success' => true,
                        'message' => 'Vote recorded',
                        'action' => 'voted'
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle duplicate key constraint
                    if ($e->getCode() === '23000') {
                        // Re-check for existing vote and update it
                        $existingVote = DB::table('news_votes')
                            ->where('user_id', $userId)
                            ->where('comment_id', $commentId)
                            ->whereNull('news_id') // Only comment votes
                            ->first();
                        
                        if ($existingVote) {
                            DB::table('news_votes')
                                ->where('id', $existingVote->id)
                                ->update(['vote_type' => $voteType, 'updated_at' => now()]);
                            
                            $this->updateNewsVoteCounts($comment->news_id, $commentId);
                            
                            return response()->json([
                                'success' => true,
                                'message' => 'Vote updated',
                                'action' => 'changed'
                            ]);
                        }
                    }
                    throw $e; // Re-throw if not a duplicate key error
                }
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording vote: ' . $e->getMessage()
            ], 500);
        }
    }

    // VLR.gg-style helper methods

    private function getNewsComments($newsId)
    {
        $comments = DB::table('news_comments as nc')
            ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
            ->where('nc.news_id', $newsId)
            ->whereNull('nc.deleted_at')
            ->select([
                'nc.*',
                'u.name as author_name',
                'u.avatar as author_avatar'
            ])
            ->orderBy('nc.created_at', 'desc')
            ->get();

        // Build nested structure with flairs and profile pictures
        $nestedComments = [];
        $commentMap = [];

        // First pass: Build all comment objects
        foreach ($comments as $comment) {
            $commentData = [
                'id' => $comment->id,
                'content' => $comment->content,
                'author' => $this->getUserWithFlairs($comment->user_id),
                'stats' => [
                    'score' => ($comment->likes ?? 0) - ($comment->dislikes ?? 0),
                    'upvotes' => $comment->likes ?? 0,
                    'downvotes' => $comment->dislikes ?? 0
                ],
                'meta' => [
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'edited' => false
                ],
                'mentions' => $this->extractMentions($comment->content),
                'user_vote' => $this->getUserCommentVote($comment->id),
                'replies' => [],
                'parent_id' => $comment->parent_id
            ];

            $commentMap[$comment->id] = $commentData;
        }

        // Second pass: Build relationships and collect top-level comments
        foreach ($commentMap as $commentId => &$commentData) {
            if ($commentData['parent_id']) {
                // This is a reply - add to parent's replies
                if (isset($commentMap[$commentData['parent_id']])) {
                    $commentMap[$commentData['parent_id']]['replies'][] = &$commentData;
                }
            } else {
                // This is a top-level comment
                $nestedComments[] = &$commentData;
            }
        }
        
        // Remove parent_id from final output as it's not needed on frontend
        foreach ($commentMap as &$commentData) {
            unset($commentData['parent_id']);
        }

        return $nestedComments;
    }

    private function getUserCommentVote($commentId)
    {
        if (!Auth::check()) {
            return null;
        }

        return DB::table('news_votes')
            ->where('comment_id', $commentId)
            ->where('user_id', Auth::id())
            ->value('vote_type');
    }

    private function updateNewsVoteCounts($newsId, $commentId = null)
    {
        if ($commentId) {
            // Update comment vote counts
            $upvotes = DB::table('news_votes')
                ->where('comment_id', $commentId)
                ->where('vote_type', 'upvote')
                ->count();

            $downvotes = DB::table('news_votes')
                ->where('comment_id', $commentId)
                ->where('vote_type', 'downvote')
                ->count();

            DB::table('news_comments')->where('id', $commentId)->update([
                'likes' => $upvotes,
                'dislikes' => $downvotes
            ]);
        } else {
            // Update news vote counts
            $upvotes = DB::table('news_votes')
                ->where('news_id', $newsId)
                ->where('comment_id', null)
                ->where('vote_type', 'upvote')
                ->count();

            $downvotes = DB::table('news_votes')
                ->where('news_id', $newsId)
                ->where('comment_id', null)
                ->where('vote_type', 'downvote')
                ->count();

            DB::table('news')->where('id', $newsId)->update([
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'score' => $upvotes - $downvotes
            ]);
        }
    }

    public function getCategories()
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('news_categories')) {
                // Return default categories
                $categories = collect([
                    ['id' => 1, 'name' => 'General', 'slug' => 'general', 'icon' => 'newspaper', 'color' => '#3B82F6', 'sort_order' => 1],
                    ['id' => 2, 'name' => 'Patch Notes', 'slug' => 'patch-notes', 'icon' => 'wrench', 'color' => '#10B981', 'sort_order' => 2],
                    ['id' => 3, 'name' => 'Esports', 'slug' => 'esports', 'icon' => 'trophy', 'color' => '#F59E0B', 'sort_order' => 3],
                    ['id' => 4, 'name' => 'Community', 'slug' => 'community', 'icon' => 'users', 'color' => '#8B5CF6', 'sort_order' => 4],
                    ['id' => 5, 'name' => 'Updates', 'slug' => 'updates', 'icon' => 'sparkles', 'color' => '#EF4444', 'sort_order' => 5],
                ]);
            } else {
                $categories = DB::table('news_categories')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
            }

            return response()->json([
                'data' => $categories,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching categories: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractMentions($content)
    {
        $mentions = [];
        
        // Extract @username mentions with positions (handles both @username and @display name formats)
        preg_match_all('/@([a-zA-Z0-9_\s]+)(?=\s|$|[,.!?\'"])/u', $content, $userMatches, PREG_OFFSET_CAPTURE);
        foreach ($userMatches[0] as $index => $match) {
            $mentionText = $match[0];
            $position = $match[1];
            $username = trim($userMatches[1][$index][0]);
            
            // First, try to find a player by username or real_name
            $player = DB::table('players')
                ->where('real_name', $username)
                ->orWhere('username', $username)
                ->first();
                
            if ($player) {
                // Found a player mention
                $mentions[] = [
                    'type' => 'player',
                    'id' => $player->id,
                    'name' => $player->username,
                    'display_name' => $player->real_name ?? $player->username,
                    'avatar' => $player->avatar,
                    'avatar_url' => $player->avatar,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            } else {
                // Try to find a user by name
                $user = DB::table('users')
                    ->where('name', $username)
                    ->first();
                
                if ($user) {
                    // Found a user mention
                    $mentions[] = [
                        'type' => 'user',
                        'id' => $user->id,
                        'name' => $user->name,
                        'display_name' => $user->name,
                        'avatar' => $user->avatar,
                        'avatar_url' => $user->avatar,
                        'mention_text' => $mentionText,
                        'position_start' => $position,
                        'position_end' => $position + strlen($mentionText)
                    ];
                }
            }
        }

        // Extract @team:teamname mentions with positions
        preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches, PREG_OFFSET_CAPTURE);
        foreach ($teamMatches[0] as $index => $match) {
            $mentionText = $match[0];
            $position = $match[1];
            $teamName = $teamMatches[1][$index][0];
            
            $team = DB::table('teams')->where('short_name', $teamName)->first();
            if ($team) {
                $mentions[] = [
                    'type' => 'team',
                    'id' => $team->id,
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            }
        }

        // Extract @player:playername mentions with positions
        preg_match_all('/@player:([a-zA-Z0-9_]+)/', $content, $playerMatches, PREG_OFFSET_CAPTURE);
        foreach ($playerMatches[0] as $index => $match) {
            $mentionText = $match[0];
            $position = $match[1];
            $playerName = $playerMatches[1][$index][0];
            
            $player = DB::table('players')->where('username', $playerName)->first();
            if ($player) {
                $mentions[] = [
                    'type' => 'player',
                    'id' => $player->id,
                    'name' => $player->username,
                    'display_name' => $player->real_name ?? $player->username,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            }
        }

        return $mentions;
    }

    private function extractVideoEmbeds($content)
    {
        $videos = [];
        
        // Extract YouTube URLs
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $content, $youtubeMatches);
        foreach ($youtubeMatches[1] as $videoId) {
            $videos[] = [
                'platform' => 'youtube',
                'video_id' => $videoId,
                'embed_url' => "https://www.youtube.com/embed/{$videoId}",
                'thumbnail' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg"
            ];
        }

        // Extract Twitch URLs
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?twitch\.tv\/videos\/([0-9]+)/', $content, $twitchMatches);
        foreach ($twitchMatches[1] as $videoId) {
            $videos[] = [
                'platform' => 'twitch',
                'video_id' => $videoId,
                'embed_url' => "https://player.twitch.tv/?video={$videoId}&parent=yourdomain.com",
                'thumbnail' => null
            ];
        }

        return $videos;
    }

    private function processMentions($content, $newsId, $commentId = null)
    {
        $mentions = $this->extractMentions($content);
        
        foreach ($mentions as $mention) {
            try {
                // Extract context for the mention
                $context = $this->extractMentionContext($content, $mention['mention_text']);
                
                // Store mention in database
                // Check if mention already exists to avoid duplicate constraint violation
                $existingMention = DB::table('mentions')
                    ->where('mentionable_type', $commentId ? 'news_comment' : 'news')
                    ->where('mentionable_id', $commentId ?: $newsId)
                    ->where('mentioned_type', $mention['type'])
                    ->where('mentioned_id', $mention['id'])
                    ->where('mention_text', $mention['mention_text'])
                    ->first();

                if (!$existingMention) {
                    DB::table('mentions')->insert([
                        'mentionable_type' => $commentId ? 'news_comment' : 'news',
                        'mentionable_id' => $commentId ?: $newsId,
                        'mentioned_type' => $mention['type'],
                        'mentioned_id' => $mention['id'],
                        'mention_text' => $mention['mention_text'],
                        'position_start' => $mention['position_start'] ?? null,
                        'position_end' => $mention['position_end'] ?? null,
                        'context' => $context,
                        'mentioned_by' => Auth::id(),
                        'mentioned_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Error processing mention: ' . $e->getMessage());
            }
        }
    }

    private function extractMentionContext($content, $mentionText)
    {
        $position = strpos($content, $mentionText);
        if ($position === false) {
            return null;
        }

        // Extract 50 characters before and after the mention for context
        $contextLength = 50;
        $start = max(0, $position - $contextLength);
        $end = min(strlen($content), $position + strlen($mentionText) + $contextLength);
        
        $context = substr($content, $start, $end - $start);
        
        // Clean up context (remove excessive whitespace, etc.)
        $context = preg_replace('/\s+/', ' ', trim($context));
        
        return $context;
    }

    private function calculateReadTime($content)
    {
        $wordCount = str_word_count(strip_tags($content));
        $readingSpeed = 200; // words per minute
        return max(1, round($wordCount / $readingSpeed));
    }

    private function getRelatedArticles($currentId, $categoryName)
    {
        return DB::table('news as n')
            ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
            ->where('n.id', '!=', $currentId)
            ->where('n.category', $categoryName)
            ->where('n.status', 'published')
            ->select([
                'n.id', 'n.title', 'n.slug', 'n.excerpt', 'n.featured_image',
                'n.published_at', 'n.views', 'u.name as author_name'
            ])
            ->orderBy('n.published_at', 'desc')
            ->limit(5)
            ->get();
    }

    // Helper method to get user with flairs (VLR.gg style) - reused from ForumController
    private function getUserWithFlairs($userId)
    {
        $user = DB::table('users as u')
            ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
            ->where('u.id', $userId)
            ->select([
                'u.id', 'u.name', 'u.avatar', 'u.hero_flair', 'u.show_hero_flair', 'u.show_team_flair',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->first();

        if (!$user) {
            return null;
        }

        $flairs = [];
        
        // Add hero flair if enabled
        if ($user->show_hero_flair && $user->hero_flair) {
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $user->hero_flair,
                'image' => "/images/heroes/" . str_replace([' ', '&'], ['-', 'and'], strtolower($user->hero_flair)) . ".png",
                'fallback_text' => $user->hero_flair
            ];
        }
        
        // Add team flair if enabled
        if ($user->show_team_flair && $user->team_name) {
            $flairs['team'] = [
                'type' => 'team',
                'name' => $user->team_name,
                'short_name' => $user->team_short,
                'image' => $user->team_logo,
                'fallback_text' => $user->team_short
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'flairs' => $flairs
        ];
    }


    // Admin Routes
    public function adminIndex(Request $request)
    {
        try {
            $query = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'n.category as category_name'
                ]);

            // Filters
            if ($request->status && $request->status !== 'all') {
                $query->where('n.status', $request->status);
            }

            if ($request->category && $request->category !== 'all') {
                $query->where('n.category', $request->category);
            }

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('n.title', 'LIKE', "%{$request->search}%")
                      ->orWhere('n.content', 'LIKE', "%{$request->search}%");
                });
            }

            $news = $query->orderBy('n.created_at', 'desc')->paginate(20);

            return response()->json([
                'data' => $news->items(),
                'pagination' => [
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                    'per_page' => $news->perPage(),
                    'total' => $news->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching admin news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getNewsAdmin($newsId)
    {
        try {
            $news = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->where('n.id', $newsId)
                ->select([
                    'n.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar'
                ])
                ->first();

            if (!$news) {
                return response()->json([
                    'success' => false,
                    'message' => 'News not found'
                ], 404);
            }

            return response()->json([
                'data' => $news,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $newsId)
    {
        try {
            // Convert empty strings to null for nullable fields
            if ($request->has('featured_image') && $request->featured_image === '') {
                $request->merge(['featured_image' => null]);
            }
            
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'excerpt' => 'required|string|max:500',
                'content' => 'required|string',
                'category' => 'required|string',
                'status' => 'required|string|in:draft,published,archived',
                'featured_image' => 'nullable|string',
                'tags' => 'nullable|string',
                'featured' => 'boolean',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:255'
            ]);

            // Generate slug from title if not provided
            if (!isset($data['slug']) || empty($data['slug'])) {
                $data['slug'] = Str::slug($data['title']);
                
                // Ensure unique slug
                $originalSlug = $data['slug'];
                $counter = 1;
                while (DB::table('news')->where('slug', $data['slug'])->where('id', '!=', $newsId)->exists()) {
                    $data['slug'] = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            $data['updated_at'] = now();

            $updated = DB::table('news')
                ->where('id', $newsId)
                ->update($data);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'News not found or no changes made'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'News updated successfully',
                'data' => DB::table('news')->where('id', $newsId)->first()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($newsId)
    {
        try {
            $deleted = DB::table('news')->where('id', $newsId)->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'News not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'News deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCommentsWithNesting($newsId, Request $request)
    {
        try {
            $sort = $request->get('sort', 'newest');
            
            // Get all comments for this news item
            $allComments = DB::table('news_comments as nc')
                ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
                ->leftJoin('teams as t', 'u.team_flair_id', '=', 't.id')
                ->where('nc.news_id', $newsId)
                ->whereNull('nc.deleted_at')
                ->select([
                    'nc.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'u.hero_flair',
                    'u.show_hero_flair',
                    'u.show_team_flair',
                    't.name as team_flair_name',
                    't.short_name as team_flair_short',
                    't.logo as team_flair_logo'
                ])
                ->get();

            // Get vote counts and user votes
            $userId = auth()->id();
            $voteData = [];
            
            if ($userId) {
                $userVotes = DB::table('news_votes')
                    ->where('news_id', $newsId)
                    ->where('user_id', $userId)
                    ->get()
                    ->keyBy('comment_id');
                    
                foreach ($userVotes as $commentId => $vote) {
                    $voteData[$commentId] = $vote->vote_type;
                }
            }

            // Get aggregated vote counts
            $voteCounts = DB::table('news_votes')
                ->where('news_id', $newsId)
                ->select([
                    'comment_id',
                    DB::raw('SUM(CASE WHEN vote_type = "upvote" THEN 1 ELSE 0 END) as upvotes'),
                    DB::raw('SUM(CASE WHEN vote_type = "downvote" THEN 1 ELSE 0 END) as downvotes')
                ])
                ->groupBy('comment_id')
                ->get()
                ->keyBy('comment_id');

            // Transform comments
            $comments = $allComments->map(function($comment) use ($voteData, $voteCounts) {
                $commentId = $comment->id;
                $votes = $voteCounts->get($commentId);
                
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'parent_id' => $comment->parent_id,
                    'upvotes' => $votes->upvotes ?? ($comment->likes ?? 0),
                    'downvotes' => $votes->downvotes ?? ($comment->dislikes ?? 0),
                    'user_vote' => $voteData[$commentId] ?? null,
                    'author' => [
                        'id' => $comment->user_id,
                        'name' => $comment->author_name,
                        'avatar' => $comment->author_avatar,
                        'hero_flair' => $comment->hero_flair,
                        'show_hero_flair' => $comment->show_hero_flair,
                        'show_team_flair' => $comment->show_team_flair,
                        'team_flair' => $comment->team_flair_name ? [
                            'name' => $comment->team_flair_name,
                            'short_name' => $comment->team_flair_short,
                            'logo' => $comment->team_flair_logo
                        ] : null
                    ]
                ];
            });

            // Build nested structure
            $nestedComments = $this->buildNestedComments($comments->toArray(), $sort);

            return response()->json($nestedComments);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching comments: ' . $e->getMessage()
            ], 500);
        }
    }

    private function buildNestedComments($comments, $sort = 'newest')
    {
        // Group by parent_id
        $grouped = [];
        foreach ($comments as $comment) {
            $parentId = $comment['parent_id'] ?? 0;
            $grouped[$parentId][] = $comment;
        }

        // Sort each group
        foreach ($grouped as &$group) {
            usort($group, function($a, $b) use ($sort) {
                switch ($sort) {
                    case 'oldest':
                        return strtotime($a['created_at']) - strtotime($b['created_at']);
                    case 'best':
                        $scoreA = $a['upvotes'] - $a['downvotes'];
                        $scoreB = $b['upvotes'] - $b['downvotes'];
                        return $scoreB - $scoreA;
                    case 'newest':
                    default:
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                }
            });
        }

        // Build tree recursively
        $buildTree = function($parentId = 0) use (&$buildTree, $grouped) {
            $result = [];
            if (isset($grouped[$parentId])) {
                foreach ($grouped[$parentId] as $comment) {
                    $comment['replies'] = $buildTree($comment['id']);
                    $comment['replies_count'] = count($comment['replies']);
                    $result[] = $comment;
                }
            }
            return $result;
        };

        return $buildTree(0);
    }

    // Admin/Moderator methods for featuring news
    public function featureNews($newsId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            $updated = DB::table('news')
                ->where('id', $newsId)
                ->update([
                    'featured' => true,
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'News article featured successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'News article not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error featuring news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unfeatureNews($newsId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            $updated = DB::table('news')
                ->where('id', $newsId)
                ->update([
                    'featured' => false,
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'News article unfeatured successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'News article not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unfeaturing news: ' . $e->getMessage()
            ], 500);
        }
    }

    // Additional moderation methods referenced in routes
    public function reportComment(Request $request, $commentId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            // Check if comment exists
            $comment = DB::table('news_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            // Create report
            DB::table('reports')->insert([
                'reportable_type' => 'news_comment',
                'reportable_id' => $commentId,
                'reporter_id' => Auth::id(),
                'reason' => $request->reason,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment reported successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReportedComments(Request $request)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            $reportedComments = DB::table('reports as r')
                ->join('news_comments as nc', function($join) {
                    $join->on('r.reportable_id', '=', 'nc.id')
                         ->where('r.reportable_type', '=', 'news_comment');
                })
                ->leftJoin('users as u', 'nc.user_id', '=', 'u.id')
                ->leftJoin('users as reporter', 'r.reporter_id', '=', 'reporter.id')
                ->leftJoin('news as n', 'nc.news_id', '=', 'n.id')
                ->where('r.status', 'pending')
                ->select([
                    'r.*',
                    'nc.content as comment_content',
                    'nc.created_at as comment_created_at',
                    'u.name as author_name',
                    'reporter.name as reporter_name',
                    'n.title as news_title',
                    'n.id as news_id'
                ])
                ->orderBy('r.created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'data' => $reportedComments,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching reported comments: ' . $e->getMessage()
            ], 500);
        }
    }

    public function moderateComment(Request $request, $commentId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        $request->validate([
            'action' => 'required|in:approve,delete,edit',
            'content' => 'required_if:action,edit|string',
            'reason' => 'nullable|string'
        ]);

        try {
            $comment = DB::table('news_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            switch ($request->action) {
                case 'approve':
                    // Mark any reports as resolved
                    DB::table('reports')
                        ->where('reportable_type', 'news_comment')
                        ->where('reportable_id', $commentId)
                        ->update(['status' => 'resolved', 'updated_at' => now()]);
                    break;

                case 'delete':
                    // Soft delete the comment
                    DB::table('news_comments')
                        ->where('id', $commentId)
                        ->update([
                            'deleted_at' => now(),
                            'updated_at' => now()
                        ]);

                    // Mark reports as resolved
                    DB::table('reports')
                        ->where('reportable_type', 'news_comment')
                        ->where('reportable_id', $commentId)
                        ->update(['status' => 'resolved', 'updated_at' => now()]);

                    // Update comment count
                    DB::table('news')
                        ->where('id', $comment->news_id)
                        ->decrement('comments_count');
                    break;

                case 'edit':
                    // Edit the comment content
                    DB::table('news_comments')
                        ->where('id', $commentId)
                        ->update([
                            'content' => $request->content,
                            'updated_at' => now()
                        ]);

                    // Mark reports as resolved
                    DB::table('reports')
                        ->where('reportable_type', 'news_comment')
                        ->where('reportable_id', $commentId)
                        ->update(['status' => 'resolved', 'updated_at' => now()]);
                    break;
            }

            // Log moderation action
            DB::table('moderation_logs')->insert([
                'moderator_id' => Auth::id(),
                'action' => $request->action,
                'target_type' => 'news_comment',
                'target_id' => $commentId,
                'reason' => $request->reason,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment moderated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error moderating comment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDeleteComment($commentId)
    {
        // Check if user is authenticated and has admin role
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        try {
            $comment = DB::table('news_comments')->where('id', $commentId)->first();
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found'
                ], 404);
            }

            // Hard delete the comment
            DB::table('news_comments')->where('id', $commentId)->delete();

            // Update comment count
            DB::table('news')
                ->where('id', $comment->news_id)
                ->decrement('comments_count');

            return response()->json([
                'success' => true,
                'message' => 'Comment permanently deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    // Missing admin methods
    public function getPendingNews(Request $request)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            $pendingNews = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->where('n.status', 'draft')
                ->select([
                    'n.*',
                    'u.name as author_name'
                ])
                ->orderBy('n.created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'data' => $pendingNews,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching pending news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveNews($newsId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        try {
            $updated = DB::table('news')
                ->where('id', $newsId)
                ->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'News approved and published successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'News not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectNews(Request $request, $newsId)
    {
        // Check if user is authenticated and has admin/moderator role
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $updated = DB::table('news')
                ->where('id', $newsId)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now()
                ]);

            if ($updated) {
                // Log rejection reason if provided
                if ($request->reason) {
                    DB::table('moderation_logs')->insert([
                        'moderator_id' => Auth::id(),
                        'action' => 'reject_news',
                        'target_type' => 'news',
                        'target_id' => $newsId,
                        'reason' => $request->reason,
                        'created_at' => now()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'News rejected successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'News not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function forceDeleteNews($newsId)
    {
        // Check if user is authenticated and has admin role
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        try {
            // Delete all related data
            DB::table('news_comments')->where('news_id', $newsId)->delete();
            DB::table('news_votes')->where('news_id', $newsId)->delete();
            
            // Delete the news article
            $deleted = DB::table('news')->where('id', $newsId)->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'News permanently deleted'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'News not found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting news: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllNews(Request $request)
    {
        return $this->adminIndex($request);
    }

    // ========================================
    // NEWS CATEGORIES MANAGEMENT
    // ========================================

    public function getCategoriesAdmin(Request $request)
    {
        try {
            $categories = DB::table('news_categories')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $categories,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching categories: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeCategory(Request $request)
    {
        // Check if user has admin/moderator permissions
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:news_categories,name',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        try {
            $slug = Str::slug($request->name);
            $counter = 1;
            $originalSlug = $slug;
            
            // Ensure unique slug
            while (DB::table('news_categories')->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $categoryId = DB::table('news_categories')->insertGetId([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'sort_order' => $request->sort_order ?? 999,
                'is_default' => 0,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'data' => ['id' => $categoryId, 'slug' => $slug],
                'success' => true,
                'message' => 'Category created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(Request $request, $categoryId)
    {
        // Check if user has admin/moderator permissions
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:news_categories,name,' . $categoryId,
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        try {
            $category = DB::table('news_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            // Generate new slug if name changed
            $slug = $category->slug;
            if ($request->name !== $category->name) {
                $slug = Str::slug($request->name);
                $counter = 1;
                $originalSlug = $slug;
                
                while (DB::table('news_categories')->where('slug', $slug)->where('id', '!=', $categoryId)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            DB::table('news_categories')->where('id', $categoryId)->update([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'sort_order' => $request->sort_order ?? $category->sort_order,
                'updated_at' => now()
            ]);

            return response()->json([
                'data' => ['id' => $categoryId, 'slug' => $slug],
                'success' => true,
                'message' => 'Category updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyCategory($categoryId)
    {
        // Check if user has admin permissions (only admin can delete categories)
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required'
            ], 403);
        }

        try {
            $category = DB::table('news_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            // Check if category is a default category
            if ($category->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default categories'
                ], 400);
            }

            // Check if category is in use
            $newsCount = DB::table('news')->where('category', $category->name)->count();
            if ($newsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete category: {$newsCount} news articles are using this category. Please reassign them first."
                ], 400);
            }

            DB::table('news_categories')->where('id', $categoryId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reorderCategories(Request $request)
    {
        // Check if user has admin/moderator permissions
        if (!Auth::check() || !in_array(Auth::user()->role, ['admin', 'moderator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin or Moderator access required'
            ], 403);
        }

        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|integer|exists:news_categories,id',
            'categories.*.sort_order' => 'required|integer|min:0'
        ]);

        try {
            foreach ($request->categories as $categoryData) {
                DB::table('news_categories')
                    ->where('id', $categoryData['id'])
                    ->update([
                        'sort_order' => $categoryData['sort_order'],
                        'updated_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reordering categories: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processMentionsFromData($mentions, $newsId, $commentId = null)
    {
        foreach ($mentions as $mention) {
            try {
                // Get the full content to find the mention position
                if ($commentId) {
                    $content = DB::table('news_comments')->where('id', $commentId)->value('content');
                } else {
                    $article = DB::table('news')->where('id', $newsId)->first();
                    $content = $article->title . ' ' . $article->content;
                }
                
                // Find the mention text in content
                $mentionText = '@' . ($mention['display_name'] ?? $mention['name']);
                $position = strpos($content, $mentionText);
                
                if ($position === false) {
                    // Try alternative formats
                    $mentionText = '@' . $mention['name'];
                    $position = strpos($content, $mentionText);
                }
                
                // Extract context for the mention
                $context = $this->extractMentionContext($content, $mentionText);
                
                // Check if mention already exists to avoid duplicate constraint violation
                $existingMention = DB::table('mentions')
                    ->where('mentionable_type', $commentId ? 'news_comment' : 'news')
                    ->where('mentionable_id', $commentId ?: $newsId)
                    ->where('mentioned_type', $mention['type'])
                    ->where('mentioned_id', $mention['id'])
                    ->where('mention_text', $mentionText)
                    ->first();

                if (!$existingMention) {
                    DB::table('mentions')->insert([
                        'mentionable_type' => $commentId ? 'news_comment' : 'news',
                        'mentionable_id' => $commentId ?: $newsId,
                        'mentioned_type' => $mention['type'],
                        'mentioned_id' => $mention['id'],
                        'mention_text' => $mentionText,
                        'position_start' => $position !== false ? $position : null,
                        'position_end' => $position !== false ? $position + strlen($mentionText) : null,
                        'context' => $context,
                        'mentioned_by' => Auth::id(),
                        'mentioned_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but continue processing other mentions
                \Log::error('Error processing mention: ' . $e->getMessage());
            }
        }
    }
}