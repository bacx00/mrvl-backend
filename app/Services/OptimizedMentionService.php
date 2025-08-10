<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class OptimizedMentionService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const BATCH_SIZE = 100;

    /**
     * Extract mentions from content with optimized database queries
     */
    public function extractMentionsOptimized($content)
    {
        if (empty($content)) {
            return [];
        }

        $mentions = [];
        $allMatches = [];

        // Extract all mention patterns in one pass
        $patterns = [
            'user' => '/@([a-zA-Z0-9_\s]+)(?!\w)/',
            'team' => '/@team:([a-zA-Z0-9_]+)/',
            'player' => '/@player:([a-zA-Z0-9_]+)/'
        ];

        foreach ($patterns as $type => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as $match) {
                $allMatches[] = [
                    'type' => $type,
                    'name' => trim($match[0]),
                    'position' => $match[1] - strlen($this->getMentionPrefix($type)),
                    'full_match' => $this->buildMentionText($type, trim($match[0]))
                ];
            }
        }

        if (empty($allMatches)) {
            return [];
        }

        // Group by type for batch queries
        $groupedMatches = [];
        foreach ($allMatches as $match) {
            $groupedMatches[$match['type']][] = $match;
        }

        // Batch query for each type
        foreach ($groupedMatches as $type => $matches) {
            $names = array_unique(array_column($matches, 'name'));
            $entities = $this->batchQueryEntities($type, $names);

            foreach ($matches as $match) {
                $entity = $entities[$match['name']] ?? null;
                if ($entity) {
                    $mentions[] = [
                        'type' => $type,
                        'id' => $entity->id,
                        'name' => $entity->name,
                        'display_name' => $entity->display_name,
                        'mention_text' => $match['full_match'],
                        'url' => $this->getMentionUrl($type, $entity->id),
                        'avatar' => $entity->avatar,
                        'position_start' => $match['position'],
                        'position_end' => $match['position'] + strlen($match['full_match']),
                        'clickable' => true
                    ];
                }
            }
        }

        return $mentions;
    }

    /**
     * Store mentions in database with batch operations
     */
    public function storeMentionsOptimized($content, $contentType, $contentId, $userId = null)
    {
        $mentions = $this->extractMentionsOptimized($content);
        if (empty($mentions)) {
            return 0;
        }

        $userId = $userId ?? Auth::id();
        $mentionsToInsert = [];
        $existingMentions = [];

        // Check for existing mentions in batch
        if (!empty($mentions)) {
            $mentionKeys = array_map(function($mention) use ($contentType, $contentId) {
                return [
                    'mentionable_type' => $contentType,
                    'mentionable_id' => $contentId,
                    'mentioned_type' => $mention['type'],
                    'mentioned_id' => $mention['id']
                ];
            }, $mentions);

            // Build query to check existing mentions
            $query = DB::table('mentions')->where('mentionable_type', $contentType)
                                         ->where('mentionable_id', $contentId);
            
            $conditions = [];
            foreach ($mentionKeys as $key) {
                $conditions[] = "(mentioned_type = '{$key['mentioned_type']}' AND mentioned_id = {$key['mentioned_id']})";
            }
            
            if (!empty($conditions)) {
                $query->whereRaw('(' . implode(' OR ', $conditions) . ')');
                $existingMentions = $query->get()->keyBy(function($mention) {
                    return $mention->mentioned_type . '_' . $mention->mentioned_id;
                });
            }
        }

        // Prepare batch insert data
        foreach ($mentions as $mention) {
            $key = $mention['type'] . '_' . $mention['id'];
            
            if (!isset($existingMentions[$key])) {
                $context = $this->extractMentionContext($content, $mention['mention_text']);
                
                $mentionsToInsert[] = [
                    'mentionable_type' => $contentType,
                    'mentionable_id' => $contentId,
                    'mentioned_type' => $mention['type'],
                    'mentioned_id' => $mention['id'],
                    'user_id' => $userId,
                    'mention_text' => $mention['mention_text'],
                    'position_start' => $mention['position_start'],
                    'position_end' => $mention['position_end'],
                    'context' => $context,
                    'is_read' => false,
                    'mentioned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // Batch insert new mentions
        if (!empty($mentionsToInsert)) {
            // Insert in chunks to avoid query size limits
            $chunks = array_chunk($mentionsToInsert, self::BATCH_SIZE);
            foreach ($chunks as $chunk) {
                DB::table('mentions')->insert($chunk);
            }
        }

        return count($mentionsToInsert);
    }

    /**
     * Get mentions for content with optimized query
     */
    public function getMentionsForContentOptimized($contentType, $contentId)
    {
        $cacheKey = "mentions:{$contentType}:{$contentId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($contentType, $contentId) {
            // Single query with joins to get all mention data
            return DB::table('mentions as m')
                ->where('m.mentionable_type', $contentType)
                ->where('m.mentionable_id', $contentId)
                ->where('m.is_read', false)
                ->leftJoin('users as u', function($join) {
                    $join->on('m.mentioned_id', '=', 'u.id')
                         ->where('m.mentioned_type', '=', 'user');
                })
                ->leftJoin('teams as t', function($join) {
                    $join->on('m.mentioned_id', '=', 't.id')
                         ->where('m.mentioned_type', '=', 'team');
                })
                ->leftJoin('players as p', function($join) {
                    $join->on('m.mentioned_id', '=', 'p.id')
                         ->where('m.mentioned_type', '=', 'player');
                })
                ->select([
                    'm.mentioned_type as type',
                    'm.mentioned_id as id',
                    'm.mention_text',
                    'm.position_start',
                    'm.position_end',
                    // User data
                    'u.name as user_name',
                    'u.avatar as user_avatar',
                    // Team data
                    't.name as team_name',
                    't.short_name as team_short_name',
                    't.logo as team_logo',
                    // Player data
                    'p.username as player_username',
                    'p.real_name as player_real_name',
                    'p.avatar as player_avatar'
                ])
                ->get()
                ->map(function($mention) {
                    return [
                        'type' => $mention->type,
                        'id' => $mention->id,
                        'name' => $this->getEntityName($mention),
                        'display_name' => $this->getEntityDisplayName($mention),
                        'mention_text' => $mention->mention_text,
                        'url' => $this->getMentionUrl($mention->type, $mention->id),
                        'avatar' => $this->getEntityAvatar($mention),
                        'position_start' => $mention->position_start,
                        'position_end' => $mention->position_end,
                        'clickable' => true
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Batch query entities by type
     */
    private function batchQueryEntities($type, $names)
    {
        $cacheKey = "mention_entities:{$type}:" . md5(implode(',', $names));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($type, $names) {
            $entities = collect();
            
            switch ($type) {
                case 'user':
                    $users = DB::table('users')
                        ->whereIn('name', $names)
                        ->where('status', 'active')
                        ->select(['id', 'name', 'name as display_name', 'avatar'])
                        ->get()
                        ->keyBy('name');
                    $entities = $entities->merge($users);
                    break;
                    
                case 'team':
                    $teams = DB::table('teams')
                        ->where(function($query) use ($names) {
                            $query->whereIn('short_name', $names)
                                  ->orWhereIn('name', $names);
                        })
                        ->select(['id', 'short_name as name', 'name as display_name', 'logo as avatar'])
                        ->get();
                    
                    // Key by both short_name and name for flexible matching
                    foreach ($teams as $team) {
                        $entities[$team->name] = $team;
                        if ($team->display_name !== $team->name) {
                            $entities[$team->display_name] = $team;
                        }
                    }
                    break;
                    
                case 'player':
                    $players = DB::table('players')
                        ->where(function($query) use ($names) {
                            $query->whereIn('username', $names)
                                  ->orWhereIn('real_name', $names);
                        })
                        ->select(['id', 'username as name', 'real_name', 'avatar'])
                        ->get();
                    
                    // Key by both username and real_name for flexible matching
                    foreach ($players as $player) {
                        $player->display_name = $player->real_name ?: $player->name;
                        $entities[$player->name] = $player;
                        if ($player->real_name && $player->real_name !== $player->name) {
                            $entities[$player->real_name] = $player;
                        }
                    }
                    break;
            }
            
            return $entities;
        });
    }

    /**
     * Process mentions for display with cached data
     */
    public function processMentionsForDisplayOptimized($content, $contentType = null, $contentId = null)
    {
        if (empty($content)) {
            return $content;
        }

        // Get mentions from cache or extract from content
        if ($contentType && $contentId) {
            $mentions = $this->getMentionsForContentOptimized($contentType, $contentId);
        } else {
            $mentions = $this->extractMentionsOptimized($content);
        }

        // Sort mentions by position (descending) to avoid position shifts during replacement
        usort($mentions, function($a, $b) {
            return ($b['position_start'] ?? 0) - ($a['position_start'] ?? 0);
        });

        // Replace mentions with clickable links
        foreach ($mentions as $mention) {
            $mentionText = $mention['mention_text'];
            $url = $mention['url'];
            $displayName = $mention['display_name'] ?? $mention['name'] ?? '';
            $className = 'mention mention-' . $mention['type'];
            
            if ($mentionText && $url) {
                $clickableLink = "<a href=\"{$url}\" class=\"{$className}\" data-mention-id=\"{$mention['id']}\" data-mention-type=\"{$mention['type']}\" title=\"{$displayName}\">{$mentionText}</a>";
                $content = str_replace($mentionText, $clickableLink, $content);
            }
        }
        
        return $content;
    }

    /**
     * Mark mentions as read in batch
     */
    public function markMentionsAsReadBatch($mentionedType, $mentionedId, $contentTypes = [])
    {
        $query = DB::table('mentions')
            ->where('mentioned_type', $mentionedType)
            ->where('mentioned_id', $mentionedId)
            ->where('is_read', false);

        if (!empty($contentTypes)) {
            $query->whereIn('mentionable_type', $contentTypes);
        }

        return $query->update([
            'is_read' => true,
            'updated_at' => now()
        ]);
    }

    /**
     * Get unread mentions count efficiently
     */
    public function getUnreadMentionsCount($mentionedType, $mentionedId)
    {
        $cacheKey = "unread_mentions_count:{$mentionedType}:{$mentionedId}";
        
        return Cache::remember($cacheKey, 60, function() use ($mentionedType, $mentionedId) {
            return DB::table('mentions')
                ->where('mentioned_type', $mentionedType)
                ->where('mentioned_id', $mentionedId)
                ->where('is_read', false)
                ->count();
        });
    }

    /**
     * Get recent mentions with content preview
     */
    public function getRecentMentionsOptimized($mentionedType, $mentionedId, $limit = 10)
    {
        $cacheKey = "recent_mentions:{$mentionedType}:{$mentionedId}:{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function() use ($mentionedType, $mentionedId, $limit) {
            return DB::table('mentions as m')
                ->where('m.mentioned_type', $mentionedType)
                ->where('m.mentioned_id', $mentionedId)
                ->leftJoin('users as u', 'm.user_id', '=', 'u.id')
                ->leftJoin('forum_threads as ft', function($join) {
                    $join->on('m.mentionable_id', '=', 'ft.id')
                         ->where('m.mentionable_type', '=', 'forum_thread');
                })
                ->leftJoin('forum_posts as fp', function($join) {
                    $join->on('m.mentionable_id', '=', 'fp.id')
                         ->where('m.mentionable_type', '=', 'forum_post');
                })
                ->leftJoin('news as n', function($join) {
                    $join->on('m.mentionable_id', '=', 'n.id')
                         ->where('m.mentionable_type', '=', 'news');
                })
                ->leftJoin('news_comments as nc', function($join) {
                    $join->on('m.mentionable_id', '=', 'nc.id')
                         ->where('m.mentionable_type', '=', 'news_comment');
                })
                ->select([
                    'm.*',
                    'u.name as author_name',
                    'u.avatar as author_avatar',
                    'ft.title as thread_title',
                    'fp.content as post_content',
                    'n.title as news_title',
                    'nc.content as news_comment_content'
                ])
                ->orderBy('m.mentioned_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($mention) {
                    return [
                        'id' => $mention->id,
                        'type' => $mention->mentionable_type,
                        'content_id' => $mention->mentionable_id,
                        'content_preview' => $this->getContentPreview($mention),
                        'context' => $mention->context,
                        'author' => [
                            'name' => $mention->author_name,
                            'avatar' => $mention->author_avatar
                        ],
                        'mentioned_at' => $mention->mentioned_at,
                        'is_read' => (bool)$mention->is_read,
                        'url' => $this->getContentUrl($mention)
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Helper methods
     */
    private function getMentionPrefix($type)
    {
        return match($type) {
            'user' => '@',
            'team' => '@team:',
            'player' => '@player:',
            default => '@'
        };
    }

    private function buildMentionText($type, $name)
    {
        return $this->getMentionPrefix($type) . $name;
    }

    private function getMentionUrl($type, $id)
    {
        return match($type) {
            'user' => "/users/{$id}",
            'team' => "/teams/{$id}",
            'player' => "/players/{$id}",
            default => '#'
        };
    }

    private function getEntityName($mention)
    {
        return match($mention->type) {
            'user' => $mention->user_name,
            'team' => $mention->team_short_name ?: $mention->team_name,
            'player' => $mention->player_username,
            default => 'Unknown'
        };
    }

    private function getEntityDisplayName($mention)
    {
        return match($mention->type) {
            'user' => $mention->user_name,
            'team' => $mention->team_name,
            'player' => $mention->player_real_name ?: $mention->player_username,
            default => 'Unknown'
        };
    }

    private function getEntityAvatar($mention)
    {
        return match($mention->type) {
            'user' => $mention->user_avatar,
            'team' => $mention->team_logo,
            'player' => $mention->player_avatar,
            default => null
        };
    }

    private function extractMentionContext($content, $mentionText)
    {
        $position = strpos($content, $mentionText);
        if ($position === false) {
            return null;
        }

        $contextLength = 50;
        $start = max(0, $position - $contextLength);
        $end = min(strlen($content), $position + strlen($mentionText) + $contextLength);
        
        return substr($content, $start, $end - $start);
    }

    private function getContentPreview($mention)
    {
        $content = match($mention->mentionable_type) {
            'forum_thread' => $mention->thread_title,
            'forum_post' => $mention->post_content,
            'news' => $mention->news_title,
            'news_comment' => $mention->news_comment_content,
            default => $mention->context
        };

        return $content ? Str::limit(strip_tags($content), 100) : 'No preview available';
    }

    private function getContentUrl($mention)
    {
        return match($mention->mentionable_type) {
            'forum_thread' => "/forum/threads/{$mention->mentionable_id}",
            'forum_post' => "/forum/posts/{$mention->mentionable_id}",
            'news' => "/news/{$mention->mentionable_id}",
            'news_comment' => "/news/{$mention->mentionable_id}#comment-{$mention->mentionable_id}",
            default => '#'
        };
    }

    /**
     * Clear mention caches for specific content
     */
    public function clearMentionCaches($contentType, $contentId)
    {
        $patterns = [
            "mentions:{$contentType}:{$contentId}",
            "mention_entities:*",
            "unread_mentions_count:*",
            "recent_mentions:*"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need cache tags or manual cleanup
                // This is a simplified version
                Cache::forget($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }
}