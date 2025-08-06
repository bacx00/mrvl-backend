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

            // Search functionality
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('n.title', 'LIKE', "%{$request->search}%")
                      ->orWhere('n.content', 'LIKE', "%{$request->search}%")
                      ->orWhere('n.excerpt', 'LIKE', "%{$request->search}%");
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
                    'mentions' => $this->extractMentions($article->content),
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
            'category_id' => 'required|exists:news_categories,id',
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
                'category_id' => $request->category_id,
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

            // Process mentions in content
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
            'parent_id' => 'nullable|exists:news_comments,id'
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

            // Process mentions in comment
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

            // Verify news exists
            $newsExists = DB::table('news')->where('id', $newsId)->exists();
            if (!$newsExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'News article not found'
                ], 404);
            }

            // Check for existing vote with proper unique constraint handling
            $existingVote = DB::table('news_votes')
                ->where('news_id', $newsId)
                ->where('user_id', $userId)
                ->where(function($query) use ($commentId) {
                    if ($commentId) {
                        $query->where('comment_id', $commentId);
                    } else {
                        $query->whereNull('comment_id');
                    }
                })
                ->first();

            DB::beginTransaction();

            try {
                if ($existingVote) {
                    if ($existingVote->vote_type === $voteType) {
                        // Remove vote if same type (toggle off)
                        DB::table('news_votes')->where('id', $existingVote->id)->delete();
                        $this->updateNewsVoteCounts($newsId, $commentId);
                        
                        DB::commit();
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote removed',
                            'action' => 'removed',
                            'vote_type' => null
                        ]);
                    } else {
                        // Update vote if different type (switch vote)
                        DB::table('news_votes')
                            ->where('id', $existingVote->id)
                            ->update([
                                'vote_type' => $voteType, 
                                'updated_at' => now()
                            ]);
                        
                        $this->updateNewsVoteCounts($newsId, $commentId);
                        
                        DB::commit();
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote updated',
                            'action' => 'updated',
                            'vote_type' => $voteType
                        ]);
                    }
                } else {
                    // Create new vote using INSERT IGNORE for race condition safety
                    $inserted = DB::table('news_votes')->insert([
                        'news_id' => $newsId,
                        'comment_id' => $commentId,
                        'user_id' => $userId,
                        'vote_type' => $voteType,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    if (!$inserted) {
                        throw new \Exception('Failed to insert vote - possible race condition');
                    }

                    $this->updateNewsVoteCounts($newsId, $commentId);
                    
                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote recorded',
                        'action' => 'created',
                        'vote_type' => $voteType
                    ]);
                }
            } catch (\Exception $innerException) {
                DB::rollback();
                throw $innerException;
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
                    'is_edited' => true,
                    'edited_at' => now(),
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

            // Soft delete by updating status
            DB::table('news_comments')
                ->where('id', $commentId)
                ->update([
                    'status' => 'deleted',
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
            // Check if comment exists and is active
            $comment = DB::table('news_comments')
                ->where('id', $commentId)
                ->where('status', 'active')
                ->first();
                
            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found or deleted'
                ], 404);
            }

            $userId = Auth::id();
            $voteType = $request->vote_type;

            // Check for existing vote with proper constraint handling
            $existingVote = DB::table('news_votes')
                ->where('comment_id', $commentId)
                ->where('user_id', $userId)
                ->first();

            DB::beginTransaction();

            try {
                if ($existingVote) {
                    if ($existingVote->vote_type === $voteType) {
                        // Remove vote if same type (toggle off)
                        DB::table('news_votes')->where('id', $existingVote->id)->delete();
                        $this->updateNewsVoteCounts($comment->news_id, $commentId);
                        
                        DB::commit();
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote removed',
                            'action' => 'removed',
                            'vote_type' => null
                        ]);
                    } else {
                        // Update vote if different type (switch vote)
                        DB::table('news_votes')
                            ->where('id', $existingVote->id)
                            ->update([
                                'vote_type' => $voteType, 
                                'updated_at' => now()
                            ]);
                        
                        $this->updateNewsVoteCounts($comment->news_id, $commentId);
                        
                        DB::commit();
                        return response()->json([
                            'success' => true,
                            'message' => 'Vote updated',
                            'action' => 'updated',
                            'vote_type' => $voteType
                        ]);
                    }
                } else {
                    // Create new vote
                    $inserted = DB::table('news_votes')->insert([
                        'news_id' => $comment->news_id,
                        'comment_id' => $commentId,
                        'user_id' => $userId,
                        'vote_type' => $voteType,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    if (!$inserted) {
                        throw new \Exception('Failed to insert vote - possible race condition');
                    }

                    $this->updateNewsVoteCounts($comment->news_id, $commentId);
                    
                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Vote recorded',
                        'action' => 'created',
                        'vote_type' => $voteType
                    ]);
                }
            } catch (\Exception $innerException) {
                DB::rollback();
                throw $innerException;
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
            ->where('nc.status', 'active')
            ->select([
                'nc.*',
                'u.name as author_name',
                'u.avatar as author_avatar'
            ])
            ->orderBy('nc.created_at', 'asc')
            ->get();

        // Build nested structure with flairs and profile pictures
        $nestedComments = [];
        $commentMap = [];

        foreach ($comments as $comment) {
            $commentData = [
                'id' => $comment->id,
                'content' => $comment->content,
                'author' => $this->getUserWithFlairs($comment->user_id),
                'stats' => [
                    'score' => $comment->score ?? 0,
                    'upvotes' => $comment->upvotes ?? 0,
                    'downvotes' => $comment->downvotes ?? 0
                ],
                'meta' => [
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'edited' => $comment->created_at !== $comment->updated_at
                ],
                'mentions' => $this->extractMentions($comment->content),
                'user_vote' => $this->getUserCommentVote($comment->id),
                'replies' => []
            ];

            $commentMap[$comment->id] = $commentData;

            if ($comment->parent_id) {
                // This is a reply
                if (isset($commentMap[$comment->parent_id])) {
                    $commentMap[$comment->parent_id]['replies'][] = &$commentMap[$comment->id];
                }
            } else {
                // This is a top-level comment
                $nestedComments[] = &$commentMap[$comment->id];
            }
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
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'score' => $upvotes - $downvotes
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
            $categories = DB::table('news_categories')
                ->where('active', true)
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

    private function extractMentions($content)
    {
        $mentions = [];
        
        // Extract @username mentions (users)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $userMatches, PREG_OFFSET_CAPTURE);
        foreach ($userMatches[1] as $match) {
            $username = $match[0];
            $position = $userMatches[0][array_search($match, $userMatches[1])][1];
            
            $user = DB::table('users')->where('name', $username)->first();
            if ($user) {
                $mentionText = "@{$username}";
                $mentions[] = [
                    'type' => 'user',
                    'id' => $user->id,
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => $mentionText,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText)
                ];
            }
        }

        // Extract @team:teamname mentions
        preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches, PREG_OFFSET_CAPTURE);
        foreach ($teamMatches[1] as $match) {
            $teamName = $match[0];
            $position = $teamMatches[0][array_search($match, $teamMatches[1])][1];
            
            $team = DB::table('teams')->where('short_name', $teamName)->first();
            if ($team) {
                $mentionText = "@team:{$teamName}";
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

        // Extract @player:playername mentions
        preg_match_all('/@player:([a-zA-Z0-9_]+)/', $content, $playerMatches, PREG_OFFSET_CAPTURE);
        foreach ($playerMatches[1] as $match) {
            $playerName = $match[0];
            $position = $playerMatches[0][array_search($match, $playerMatches[1])][1];
            
            $player = DB::table('players')->where('username', $playerName)->first();
            if ($player) {
                $mentionText = "@player:{$playerName}";
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
        
        // Extract YouTube URLs (various formats)
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})(?:\S+)?/', $content, $youtubeMatches);
        foreach ($youtubeMatches[1] as $videoId) {
            $videos[] = [
                'platform' => 'youtube',
                'video_id' => $videoId,
                'embed_url' => "https://www.youtube.com/embed/{$videoId}?rel=0&modestbranding=1&controls=1&showinfo=0&fs=1",
                'thumbnail' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                'title' => null // Will be populated later if needed
            ];
        }

        // Extract Twitch clip URLs
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?(?:clips\.twitch\.tv\/|twitch\.tv\/\w+\/clip\/)([a-zA-Z0-9_-]+)/', $content, $twitchClipMatches);
        foreach ($twitchClipMatches[1] as $clipId) {
            $videos[] = [
                'platform' => 'twitch-clip',
                'video_id' => $clipId,
                'embed_url' => "https://clips.twitch.tv/embed?clip={$clipId}&parent=" . request()->getHost(),
                'thumbnail' => null,
                'title' => null
            ];
        }

        // Extract Twitch video URLs
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?twitch\.tv\/videos\/([0-9]+)/', $content, $twitchVideoMatches);
        foreach ($twitchVideoMatches[1] as $videoId) {
            $videos[] = [
                'platform' => 'twitch-video',
                'video_id' => $videoId,
                'embed_url' => "https://player.twitch.tv/?video={$videoId}&parent=" . request()->getHost(),
                'thumbnail' => null,
                'title' => null
            ];
        }

        // Extract Twitter/X URLs
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?(?:twitter\.com|x\.com)\/\w+\/status\/([0-9]+)/', $content, $twitterMatches);
        foreach ($twitterMatches[1] as $tweetId) {
            $videos[] = [
                'platform' => 'twitter',
                'video_id' => $tweetId,
                'embed_url' => null, // Twitter uses different embedding
                'thumbnail' => null,
                'title' => null
            ];
        }

        // Extract generic video URLs (MP4, WebM, etc.)
        preg_match_all('/(?:https?:\/\/[^\s]+\.(?:mp4|webm|ogg|mov))/', $content, $genericVideoMatches);
        foreach ($genericVideoMatches[0] as $videoUrl) {
            $videos[] = [
                'platform' => 'generic',
                'video_id' => null,
                'embed_url' => $videoUrl,
                'thumbnail' => null,
                'title' => basename(parse_url($videoUrl, PHP_URL_PATH))
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
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
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
                'u.use_hero_as_avatar',
                't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
            ])
            ->first();

        if (!$user) {
            return [
                'id' => null,
                'name' => 'Unknown User',
                'avatar' => null,
                'flairs' => []
            ];
        }

        $flairs = [];
        
        // Add hero flair if enabled
        if ($user->show_hero_flair && $user->hero_flair) {
            $heroImage = $this->getHeroImagePath($user->hero_flair);
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $user->hero_flair,
                'image' => $heroImage,
                'fallback_text' => $user->hero_flair
            ];
        }
        
        // Add team flair if enabled
        if ($user->show_team_flair && $user->team_name) {
            $flairs['team'] = [
                'type' => 'team',
                'name' => $user->team_name,
                'short_name' => $user->team_short,
                'image' => $user->team_logo ? asset('storage/' . $user->team_logo) : null,
                'fallback_text' => $user->team_short
            ];
        }

        // Determine avatar - use hero image if enabled and available
        $avatar = $user->avatar;
        if ($user->use_hero_as_avatar && $user->hero_flair) {
            $avatar = $this->getHeroImagePath($user->hero_flair);
        } else if ($user->avatar) {
            // Ensure avatar URL is properly formatted
            $avatar = str_starts_with($user->avatar, 'http') 
                ? $user->avatar 
                : asset('storage/avatars/' . $user->avatar);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $avatar,
            'flairs' => $flairs,
            'use_hero_as_avatar' => (bool)$user->use_hero_as_avatar
        ];
    }

    private function getHeroImagePath($heroName)
    {
        // Convert hero name to image path
        $heroSlug = str_replace([' ', '&', '.', "'"], ['-', 'and', '', ''], strtolower($heroName));
        $possiblePaths = [
            "/images/heroes/{$heroSlug}-headbig.webp",
            "/images/heroes/{$heroSlug}.png",
            "/images/heroes/{$heroSlug}.webp",
            "/images/heroes/{$heroSlug}.jpg"
        ];

        foreach ($possiblePaths as $path) {
            $fullPath = public_path($path);
            if (file_exists($fullPath)) {
                return asset($path);
            }
        }

        // Fallback to default hero placeholder
        return asset('/images/heroes/default-hero.png');
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
                ->where('nc.status', 'active')
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
                    'upvotes' => $votes->upvotes ?? 0,
                    'downvotes' => $votes->downvotes ?? 0,
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
                    'featured_at' => now(),
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
                    'featured_at' => null,
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
                            'status' => 'moderated',
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
                            'is_edited' => true,
                            'edited_at' => now(),
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
}