<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Mention;
use App\Models\User;
use App\Models\Player;
use App\Models\Team;
use App\Events\MentionCreated;
use App\Events\MentionDeleted;

class MentionController extends Controller
{
    public function searchMentions(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'q' => 'nullable|string|max:100',
                'type' => 'nullable|in:all,user,team,player',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $query = $validated['q'] ?? '';
            $type = $validated['type'] ?? 'all';
            $limit = min($validated['limit'] ?? 10, 20); // Max 20 results

            if (strlen($query) < 1) {
                // Return popular suggestions when no query provided
                return $this->getPopularMentions($request);
            }
        
        // For very short queries, be more restrictive
        if (strlen($query) === 1 && $type === 'all') {
            $limit = min($limit, 5); // Limit single character searches
        }

        $results = [];

        // Search users if type is 'all' or 'user'
        if ($type === 'all' || $type === 'user') {
            $users = DB::table('users')
                ->where('name', 'LIKE', "%{$query}%")
                ->where('status', 'active')
                ->select(['id', 'name', 'avatar'])
                ->limit($limit)
                ->get();

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => "@{$user->name}",
                    'avatar' => $user->avatar,
                    'subtitle' => 'User',
                    'icon' => 'user'
                ];
            }
        }

        // Search teams if type is 'all' or 'team'
        if ($type === 'all' || $type === 'team') {
            $teams = DB::table('teams')
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('short_name', 'LIKE', "%{$query}%");
                })
                ->select(['id', 'name', 'short_name', 'logo', 'region'])
                ->limit($limit)
                ->get();

            foreach ($teams as $team) {
                $results[] = [
                    'id' => $team->id,
                    'type' => 'team',
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => "@team:{$team->short_name}",
                    'avatar' => $team->logo,
                    'subtitle' => "Team • {$team->region}",
                    'icon' => 'team'
                ];
            }
        }

        // Search players if type is 'all' or 'player'
        if ($type === 'all' || $type === 'player') {
            $players = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->where(function($q) use ($query) {
                    $q->where('p.username', 'LIKE', "%{$query}%")
                      ->orWhere('p.real_name', 'LIKE', "%{$query}%");
                })
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role',
                    't.name as team_name', 't.short_name as team_short'
                ])
                ->limit($limit)
                ->get();

            foreach ($players as $player) {
                $subtitle = $player->role;
                if ($player->team_name) {
                    $subtitle .= " • {$player->team_name}";
                }

                $results[] = [
                    'id' => $player->id,
                    'type' => 'player',
                    'name' => $player->username,
                    'display_name' => $player->real_name ?: $player->username,
                    'mention_text' => "@player:{$player->username}",
                    'avatar' => $player->avatar,
                    'subtitle' => $subtitle,
                    'icon' => 'player'
                ];
            }
        }

        // Sort results by relevance (exact matches first, then by name length)
        usort($results, function($a, $b) use ($query) {
            $aExact = stripos($a['name'], $query) === 0 ? 0 : 1;
            $bExact = stripos($b['name'], $query) === 0 ? 0 : 1;
            
            if ($aExact !== $bExact) {
                return $aExact - $bExact;
            }
            
            return strlen($a['name']) - strlen($b['name']);
        });

        // Limit total results
        $results = array_slice($results, 0, $limit);

        return response()->json([
            'data' => $results,
            'success' => true,
            'query' => $query,
            'type' => $type,
            'total_results' => count($results)
        ]);
        
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('MentionController@searchMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPopularMentions(Request $request)
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50'
            ]);
            
            $limit = min($validated['limit'] ?? 10, 20);
        
        // Get most mentioned entities from the last 30 days
        $popularMentions = DB::table('mentions as m')
            ->select([
                'm.mentioned_type',
                'm.mentioned_id',
                DB::raw('COUNT(*) as mention_count')
            ])
            ->where('m.mentioned_at', '>=', now()->subDays(30))
            ->groupBy(['m.mentioned_type', 'm.mentioned_id'])
            ->orderBy('mention_count', 'desc')
            ->limit($limit)
            ->get();

        $results = [];

        foreach ($popularMentions as $mention) {
            $entityData = $this->getEntityData($mention->mentioned_type, $mention->mentioned_id);
            if ($entityData) {
                $entityData['mention_count'] = $mention->mention_count;
                $results[] = $entityData;
            }
        }

        // If no popular mentions, show some default suggestions
        if (empty($results)) {
            // Get some recent users
            $users = DB::table('users')
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
            
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => "@{$user->name}",
                    'avatar' => $user->avatar,
                    'subtitle' => 'User',
                    'icon' => 'user'
                ];
            }

            // Get some teams
            $teams = DB::table('teams')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
            
            foreach ($teams as $team) {
                $results[] = [
                    'id' => $team->id,
                    'type' => 'team',
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => "@team:{$team->short_name}",
                    'avatar' => $team->logo,
                    'subtitle' => "Team • {$team->region}",
                    'icon' => 'team'
                ];
            }
        }

        return response()->json([
            'data' => $results,
            'success' => true,
            'total_results' => count($results)
        ]);
        
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('MentionController@getPopularMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching popular mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getEntityData($type, $id)
    {
        switch ($type) {
            case 'user':
                $user = DB::table('users')
                    ->where('id', $id)
                    ->where('status', 'active')
                    ->select(['id', 'name', 'avatar'])
                    ->first();
                
                if (!$user) return null;
                
                return [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => "@{$user->name}",
                    'avatar' => $user->avatar,
                    'subtitle' => 'User',
                    'icon' => 'user'
                ];

            case 'team':
                $team = DB::table('teams')
                    ->where('id', $id)
                    ->select(['id', 'name', 'short_name', 'logo', 'region'])
                    ->first();
                
                if (!$team) return null;
                
                return [
                    'id' => $team->id,
                    'type' => 'team',
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => "@team:{$team->short_name}",
                    'avatar' => $team->logo,
                    'subtitle' => "Team • {$team->region}",
                    'icon' => 'team'
                ];

            case 'player':
                $player = DB::table('players as p')
                    ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                    ->where('p.id', $id)
                    ->select([
                        'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role',
                        't.name as team_name'
                    ])
                    ->first();
                
                if (!$player) return null;
                
                $subtitle = $player->role;
                if ($player->team_name) {
                    $subtitle .= " • {$player->team_name}";
                }
                
                return [
                    'id' => $player->id,
                    'type' => 'player',
                    'name' => $player->username,
                    'display_name' => $player->real_name ?: $player->username,
                    'mention_text' => "@player:{$player->username}",
                    'avatar' => $player->avatar,
                    'subtitle' => $subtitle,
                    'icon' => 'player'
                ];

            default:
                return null;
        }
    }

    /**
     * Get mention counts for an entity (user, team, or player)
     */
    public function getMentionCounts(Request $request, $type, $id)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|in:all,week,month,year'
            ]);

            $period = $validated['period'] ?? 'all';

            // Check cache first
            $cacheKey = "mention_count_{$type}_{$id}_{$period}";
            $cachedCount = Cache::get($cacheKey);

            if ($cachedCount !== null) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'entity_type' => $type,
                        'entity_id' => $id,
                        'mention_count' => $cachedCount,
                        'period' => $period,
                        'cached' => true
                    ]
                ]);
            }

            // Build query based on period
            $query = Mention::where('mentioned_type', $type)
                ->where('mentioned_id', $id)
                ->where('is_active', true);

            switch ($period) {
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
                case 'year':
                    $query->where('created_at', '>=', now()->subYear());
                    break;
                // 'all' - no additional filter
            }

            $count = $query->count();

            // Cache the result
            $cacheDuration = $period === 'all' ? now()->addHours(6) : now()->addMinutes(30);
            Cache::put($cacheKey, $count, $cacheDuration);

            return response()->json([
                'success' => true,
                'data' => [
                    'entity_type' => $type,
                    'entity_id' => $id,
                    'mention_count' => $count,
                    'period' => $period,
                    'cached' => false
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@getMentionCounts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching mention counts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent mentions for an entity
     */
    public function getRecentMentions(Request $request, $type, $id)
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $limit = min($validated['limit'] ?? 10, 20);

            // Check cache first
            $cacheKey = "recent_mentions_{$type}_{$id}";
            $cachedMentions = Cache::get($cacheKey);

            if ($cachedMentions !== null) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedMentions->take($limit),
                    'cached' => true
                ]);
            }

            $mentions = Mention::where('mentioned_type', $type)
                ->where('mentioned_id', $id)
                ->where('is_active', true)
                ->with(['mentionedBy', 'mentionable'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $formattedMentions = $mentions->map(function ($mention) {
                return [
                    'id' => $mention->id,
                    'mention_text' => $mention->mention_text,
                    'context' => $mention->context,
                    'mentioned_at' => $mention->mentioned_at->toISOString(),
                    'mentioned_by' => $mention->mentionedBy ? [
                        'id' => $mention->mentionedBy->id,
                        'name' => $mention->mentionedBy->name,
                        'avatar' => $mention->mentionedBy->avatar,
                    ] : null,
                    'content_context' => $mention->getContentContext(),
                ];
            });

            // Cache the result
            Cache::put($cacheKey, $formattedMentions, now()->addHour());

            return response()->json([
                'success' => true,
                'data' => $formattedMentions,
                'cached' => false
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@getRecentMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recent mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create mentions from content (called when content is created/updated)
     */
    public function createMentionsFromContent(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'mentionable_type' => 'required|string',
                'mentionable_id' => 'required|integer',
                'mentioned_by' => 'required|integer|exists:users,id'
            ]);

            $content = $validated['content'];
            $mentionableType = $validated['mentionable_type'];
            $mentionableId = $validated['mentionable_id'];
            $mentionedBy = $validated['mentioned_by'];

            $mentions = $this->parseMentionsFromContent($content, $mentionableType, $mentionableId, $mentionedBy);

            return response()->json([
                'success' => true,
                'data' => [
                    'mentions_created' => count($mentions),
                    'mentions' => $mentions
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@createMentionsFromContent error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete mentions when content is deleted
     */
    public function deleteMentionsFromContent(Request $request)
    {
        try {
            $validated = $request->validate([
                'mentionable_type' => 'required|string',
                'mentionable_id' => 'required|integer'
            ]);

            $mentionableType = $validated['mentionable_type'];
            $mentionableId = $validated['mentionable_id'];

            $mentions = Mention::where('mentionable_type', $mentionableType)
                ->where('mentionable_id', $mentionableId)
                ->where('is_active', true)
                ->get();

            foreach ($mentions as $mention) {
                $mentionedUser = null;
                if ($mention->mentioned_type === 'user') {
                    $mentionedUser = User::find($mention->mentioned_id);
                }

                // Fire deletion event
                if ($mentionedUser) {
                    event(new MentionDeleted(
                        $mention->id,
                        $mentionedUser,
                        $mention->mentionable_type,
                        $mention->mentionable_id,
                        $mention->mentioned_type,
                        $mention->mentioned_id,
                        $mention->getContentContext() ?: []
                    ));
                }

                // Mark mention as inactive instead of deleting
                $mention->update(['is_active' => false]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'mentions_deleted' => count($mentions)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@deleteMentionsFromContent error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse mentions from content and create mention records
     */
    private function parseMentionsFromContent($content, $mentionableType, $mentionableId, $mentionedBy)
    {
        $mentions = [];
        
        // Match @username, @team:teamname, @player:playername patterns
        $patterns = [
            '/@([a-zA-Z0-9_-]+)(?!\w)/' => 'user',
            '/@team:([a-zA-Z0-9_-]+)(?!\w)/' => 'team',
            '/@player:([a-zA-Z0-9_-]+)(?!\w)/' => 'player'
        ];

        foreach ($patterns as $pattern => $type) {
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[1] as $index => $match) {
                $identifier = $match[0];
                $position = $match[1];
                $fullMatch = $matches[0][$index][0];
                
                $entity = $this->findEntity($type, $identifier);
                
                if ($entity) {
                    $mention = Mention::create([
                        'mentionable_type' => $mentionableType,
                        'mentionable_id' => $mentionableId,
                        'mentioned_type' => $type,
                        'mentioned_id' => $entity->id,
                        'context' => $this->extractContext($content, $position, strlen($fullMatch)),
                        'mention_text' => $fullMatch,
                        'position_start' => $position,
                        'position_end' => $position + strlen($fullMatch),
                        'mentioned_by' => $mentionedBy,
                        'mentioned_at' => now(),
                        'is_active' => true
                    ]);

                    // Fire created event for users
                    if ($type === 'user') {
                        event(new MentionCreated($mention, $entity, $this->getContentContextForEvent($mentionableType, $mentionableId)));
                    }

                    $mentions[] = [
                        'id' => $mention->id,
                        'type' => $type,
                        'entity' => [
                            'id' => $entity->id,
                            'name' => $this->getEntityName($entity, $type)
                        ],
                        'mention_text' => $fullMatch
                    ];
                }
            }
        }

        return $mentions;
    }

    /**
     * Find entity by type and identifier
     */
    private function findEntity($type, $identifier)
    {
        switch ($type) {
            case 'user':
                return User::where('name', $identifier)->where('status', 'active')->first();
            case 'team':
                return Team::where('short_name', $identifier)->first();
            case 'player':
                return Player::where('username', $identifier)->first();
            default:
                return null;
        }
    }

    /**
     * Extract context around the mention
     */
    private function extractContext($content, $position, $length, $contextLength = 100)
    {
        $start = max(0, $position - $contextLength);
        $end = min(strlen($content), $position + $length + $contextLength);
        
        return substr($content, $start, $end - $start);
    }

    /**
     * Get entity name for display
     */
    private function getEntityName($entity, $type)
    {
        switch ($type) {
            case 'user':
                return $entity->name;
            case 'team':
                return $entity->name;
            case 'player':
                return $entity->real_name ?: $entity->username;
            default:
                return 'Unknown';
        }
    }

    /**
     * Get content context for event broadcasting
     */
    private function getContentContextForEvent($mentionableType, $mentionableId)
    {
        switch ($mentionableType) {
            case 'news':
                $news = \App\Models\News::find($mentionableId);
                return $news ? [
                    'title' => $news->title,
                    'url' => "/news/{$news->slug}"
                ] : [];
            
            case 'forum_thread':
                $thread = \App\Models\ForumThread::find($mentionableId);
                return $thread ? [
                    'title' => $thread->title,
                    'url' => "/forums/threads/{$thread->id}"
                ] : [];
            
            case 'match':
                $match = \App\Models\Match::find($mentionableId);
                return $match ? [
                    'title' => "{$match->team1_name} vs {$match->team2_name}",
                    'url' => "/matches/{$match->id}"
                ] : [];
            
            default:
                return [];
        }
    }

    /**
     * Get mentions for a specific user (for profile pages)
     */
    public function getUserMentions(Request $request, $userId)
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50',
                'page' => 'nullable|integer|min:1'
            ]);

            $limit = min($validated['limit'] ?? 20, 50);
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $mentions = Mention::where('mentioned_type', 'App\\Models\\User')
                ->where('mentioned_id', $userId)
                ->where('is_active', true)
                ->with(['mentionedBy', 'mentionable'])
                ->orderBy('mentioned_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            $total = Mention::where('mentioned_type', 'App\\Models\\User')
                ->where('mentioned_id', $userId)
                ->where('is_active', true)
                ->count();

            $formattedMentions = $mentions->map(function ($mention) {
                $context = $mention->getContentContext();
                return [
                    'id' => $mention->id,
                    'mention_text' => $mention->mention_text,
                    'context' => $mention->context,
                    'mentioned_at' => $mention->mentioned_at->toISOString(),
                    'mentioned_by' => [
                        'id' => $mention->mentionedBy ? $mention->mentionedBy->id : null,
                        'name' => $mention->mentionedBy ? $mention->mentionedBy->name : 'Unknown'
                    ],
                    'content' => $context
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedMentions,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@getUserMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mentions for a specific team (for profile pages)
     */
    public function getTeamMentions(Request $request, $teamId)
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50',
                'page' => 'nullable|integer|min:1'
            ]);

            $limit = min($validated['limit'] ?? 20, 50);
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            // Check if team exists
            $team = Team::find($teamId);
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $mentions = Mention::where('mentioned_type', 'App\\Models\\Team')
                ->where('mentioned_id', $teamId)
                ->where('is_active', true)
                ->with(['mentionedBy', 'mentionable'])
                ->orderBy('mentioned_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            $total = Mention::where('mentioned_type', 'App\\Models\\Team')
                ->where('mentioned_id', $teamId)
                ->where('is_active', true)
                ->count();

            $formattedMentions = $mentions->map(function ($mention) {
                $context = $mention->getContentContext();
                return [
                    'id' => $mention->id,
                    'mention_text' => $mention->mention_text,
                    'context' => $mention->context,
                    'mentioned_at' => $mention->mentioned_at->toISOString(),
                    'mentioned_by' => [
                        'id' => $mention->mentionedBy ? $mention->mentionedBy->id : null,
                        'name' => $mention->mentionedBy ? $mention->mentionedBy->name : 'Unknown'
                    ],
                    'content' => $context
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedMentions,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@getTeamMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mentions for a specific player (for profile pages)
     */
    public function getPlayerMentions(Request $request, $playerId)
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50',
                'page' => 'nullable|integer|min:1'
            ]);

            $limit = min($validated['limit'] ?? 20, 50);
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            // Check if player exists
            $player = Player::find($playerId);
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            $mentions = Mention::where('mentioned_type', 'App\\Models\\Player')
                ->where('mentioned_id', $playerId)
                ->where('is_active', true)
                ->with(['mentionedBy', 'mentionable'])
                ->orderBy('mentioned_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            $total = Mention::where('mentioned_type', 'App\\Models\\Player')
                ->where('mentioned_id', $playerId)
                ->where('is_active', true)
                ->count();

            $formattedMentions = $mentions->map(function ($mention) {
                $context = $mention->getContentContext();
                return [
                    'id' => $mention->id,
                    'mention_text' => $mention->mention_text,
                    'context' => $mention->context,
                    'mentioned_at' => $mention->mentioned_at->toISOString(),
                    'mentioned_by' => [
                        'id' => $mention->mentionedBy ? $mention->mentionedBy->id : null,
                        'name' => $mention->mentionedBy ? $mention->mentionedBy->name : 'Unknown'
                    ],
                    'content' => $context
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedMentions,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('MentionController@getPlayerMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player mentions: ' . $e->getMessage()
            ], 500);
        }
    }
}