<?php

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Mention;
use App\Notifications\MentionNotification;
use App\Events\MentionCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class MentionService
{
    /**
     * Process mentions in content and return rendered HTML with clickable links
     * 
     * @param string $content
     * @param array $existingMentions
     * @return string
     */
    public function processMentionsForDisplay($content, $existingMentions = [])
    {
        // Convert existing mentions to clickable format
        foreach ($existingMentions as $mention) {
            $mentionText = $mention['mention_text'] ?? '';
            $url = $this->getMentionUrl($mention);
            $displayName = $mention['display_name'] ?? $mention['name'] ?? '';
            $className = 'mention mention-' . ($mention['type'] ?? 'user');
            
            if ($mentionText && $url) {
                $clickableLink = "<a href=\"{$url}\" class=\"{$className}\" data-mention-id=\"{$mention['id']}\" data-mention-type=\"{$mention['type']}\">{$mentionText}</a>";
                $content = str_replace($mentionText, $clickableLink, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Extract mentions from content for API response
     * 
     * @param string $content
     * @return array
     */
    public function extractMentions($content)
    {
        $mentions = [];
        
        // Extract @username mentions (users) - but not @team: or @player: or emails
        preg_match_all('/(?<![a-zA-Z0-9.])@([a-zA-Z0-9_]+)(?![:@])(?!\w)/', $content, $userMatches, PREG_OFFSET_CAPTURE);
        foreach ($userMatches[1] as $match) {
            $username = trim($match[0]);
            $position = $match[1] - 1; // Account for @ symbol
            
            $user = User::where('name', $username)->where('status', 'active')->first();
            if ($user) {
                $mentionText = "@{$username}";
                $mentions[] = [
                    'type' => 'user',
                    'id' => $user->id,
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => $mentionText,
                    'url' => "/users/{$user->id}",
                    'avatar' => $user->avatar,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText),
                    'clickable' => true
                ];
            }
        }

        // Extract @team:teamname mentions
        preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches, PREG_OFFSET_CAPTURE);
        foreach ($teamMatches[1] as $match) {
            $teamName = $match[0];
            $position = $match[1] - 6; // Account for @team: prefix
            
            $team = Team::where('short_name', $teamName)->orWhere('name', $teamName)->first();
            if ($team) {
                $mentionText = "@team:{$teamName}";
                $mentions[] = [
                    'type' => 'team',
                    'id' => $team->id,
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => $mentionText,
                    'url' => "/teams/{$team->id}",
                    'avatar' => $team->logo,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText),
                    'clickable' => true
                ];
            }
        }

        // Extract @player:playername mentions
        preg_match_all('/@player:([a-zA-Z0-9_]+)/', $content, $playerMatches, PREG_OFFSET_CAPTURE);
        foreach ($playerMatches[1] as $match) {
            $playerName = $match[0];
            $position = $match[1] - 8; // Account for @player: prefix
            
            $player = Player::where('username', $playerName)->orWhere('real_name', $playerName)->first();
            if ($player) {
                $mentionText = "@player:{$playerName}";
                $mentions[] = [
                    'type' => 'player',
                    'id' => $player->id,
                    'name' => $player->username,
                    'display_name' => $player->real_name ?: $player->username,
                    'mention_text' => $mentionText,
                    'url' => "/players/{$player->id}",
                    'avatar' => $player->avatar,
                    'position_start' => $position,
                    'position_end' => $position + strlen($mentionText),
                    'clickable' => true
                ];
            }
        }

        return $mentions;
    }

    /**
     * Store mentions in database
     * 
     * @param string $content
     * @param string $contentType
     * @param int $contentId
     * @param int|null $parentId
     * @return int
     */
    public function storeMentions($content, $contentType, $contentId, $parentId = null)
    {
        $mentions = $this->extractMentions($content);
        $mentionCount = 0;
        
        foreach ($mentions as $mention) {
            try {
                // Extract context for the mention
                $context = $this->extractMentionContext($content, $mention['mention_text']);
                
                // Convert mention type to full class name for checking duplicates
                $mentionedType = $this->getMentionedTypeClass($mention['type']);
                
                // Check for duplicates
                $existingMention = Mention::where([
                    'mentionable_type' => $contentType,
                    'mentionable_id' => $contentId,
                    'mentioned_type' => $mentionedType,
                    'mentioned_id' => $mention['id'],
                    'mention_text' => $mention['mention_text']
                ])->first();
                
                if (!$existingMention) {
                    $createdMention = Mention::create([
                        'mentionable_type' => $contentType,
                        'mentionable_id' => $contentId,
                        'mentioned_type' => $mentionedType,
                        'mentioned_id' => $mention['id'],
                        'mention_text' => $mention['mention_text'],
                        'position_start' => $mention['position_start'] ?? null,
                        'position_end' => $mention['position_end'] ?? null,
                        'context' => $context,
                        'mentioned_by' => Auth::id(),
                        'mentioned_at' => now(),
                        'is_active' => true
                    ]);
                    
                    // Trigger notifications and events for user mentions
                    if ($mention['type'] === 'user') {
                        $this->triggerMentionNotification($createdMention, $mention, $contentType, $contentId);
                    }
                    
                    $mentionCount++;
                }
            } catch (\Exception $e) {
                \Log::error('Error storing mention: ' . $e->getMessage());
            }
        }
        
        return $mentionCount;
    }

    /**
     * Get mention URL based on type
     * 
     * @param array $mention
     * @return string
     */
    private function getMentionUrl($mention)
    {
        switch ($mention['type']) {
            case 'user':
                return "/users/{$mention['id']}";
            case 'team':
                return "/teams/{$mention['id']}";
            case 'player':
                return "/players/{$mention['id']}";
            default:
                return '#';
        }
    }

    /**
     * Extract context around mention
     * 
     * @param string $content
     * @param string $mentionText
     * @return string|null
     */
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
        
        return substr($content, $start, $end - $start);
    }

    /**
     * Convert mention type to full class name for polymorphic relationship
     * 
     * @param string $type
     * @return string
     */
    private function getMentionedTypeClass($type)
    {
        switch ($type) {
            case 'user':
                return 'App\Models\User';
            case 'team':
                return 'App\Models\Team';
            case 'player':
                return 'App\Models\Player';
            default:
                return $type;
        }
    }

    /**
     * Trigger notification and events for a mention
     * 
     * @param Mention $mention
     * @param array $mentionData
     * @param string $contentType
     * @param int $contentId
     */
    private function triggerMentionNotification(Mention $mention, array $mentionData, string $contentType, int $contentId)
    {
        try {
            // Get the mentioned user
            $mentionedUser = User::find($mentionData['id']);
            
            if (!$mentionedUser) {
                return;
            }
            
            // Generate content context
            $contentContext = $this->generateContentContext($contentType, $contentId);
            
            // Send notification to the mentioned user
            $mentionedUser->notify(new MentionNotification($mention, $contentContext));
            
            // Broadcast real-time event
            event(new MentionCreated($mention, $mentionedUser, $contentContext));
            
        } catch (\Exception $e) {
            \Log::error('Error triggering mention notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate content context for notifications
     * 
     * @param string $contentType
     * @param int $contentId
     * @return array
     */
    private function generateContentContext(string $contentType, int $contentId): array
    {
        switch ($contentType) {
            case 'news':
                $news = \App\Models\News::find($contentId);
                return [
                    'type' => 'news',
                    'title' => $news ? $news->title : 'News Article',
                    'url' => $news && $news->slug ? "/news/{$news->slug}" : "/news/$contentId"
                ];
                
            case 'news_comment':
                $comment = \App\Models\NewsComment::find($contentId);
                if ($comment && $comment->news) {
                    return [
                        'type' => 'news_comment',
                        'title' => "Comment on: {$comment->news->title}",
                        'url' => "/news/{$comment->news->slug}#comment-{$comment->id}"
                    ];
                }
                return ['type' => 'news_comment', 'title' => 'News Comment', 'url' => '#'];
                
            case 'forum_thread':
                $thread = \App\Models\ForumThread::find($contentId);
                return [
                    'type' => 'forum_thread',
                    'title' => $thread ? $thread->title : 'Forum Thread',
                    'url' => "/forums/threads/$contentId"
                ];
                
            case 'forum_post':
                $post = \App\Models\Post::find($contentId);
                if ($post && $post->thread) {
                    return [
                        'type' => 'forum_post',
                        'title' => "Reply in: {$post->thread->title}",
                        'url' => "/forums/threads/{$post->thread->id}#post-{$post->id}"
                    ];
                }
                return ['type' => 'forum_post', 'title' => 'Forum Reply', 'url' => '#'];
                
            case 'match':
                $match = \App\Models\MatchModel::find($contentId);
                if ($match) {
                    return [
                        'type' => 'match',
                        'title' => "{$match->team1_name} vs {$match->team2_name}",
                        'url' => "/matches/$contentId"
                    ];
                }
                return ['type' => 'match', 'title' => 'Match', 'url' => "#"];
                
            case 'match_comment':
                $comment = \App\Models\MatchComment::find($contentId);
                if ($comment && $comment->match) {
                    return [
                        'type' => 'match_comment',
                        'title' => "Comment on match: {$comment->match->team1_name} vs {$comment->match->team2_name}",
                        'url' => "/matches/{$comment->match->id}#comment-{$comment->id}"
                    ];
                }
                return ['type' => 'match_comment', 'title' => 'Match Comment', 'url' => '#'];
                
            default:
                return [
                    'type' => $contentType,
                    'title' => ucfirst($contentType),
                    'url' => '#'
                ];
        }
    }

    /**
     * Get mentions for a specific content
     * 
     * @param string $contentType
     * @param int $contentId
     * @return array
     */
    public function getMentionsForContent($contentType, $contentId)
    {
        return Mention::where('mentionable_type', $contentType)
            ->where('mentionable_id', $contentId)
            ->where('is_active', true)
            ->with('mentioned')
            ->get()
            ->map(function ($mention) {
                return [
                    'type' => $mention->mentioned_type,
                    'id' => $mention->mentioned_id,
                    'name' => $mention->getMentionedDisplayName(),
                    'display_name' => $mention->getMentionedDisplayName(),
                    'mention_text' => $mention->mention_text,
                    'url' => $mention->getMentionedUrl(),
                    'clickable' => true
                ];
            })
            ->toArray();
    }

    /**
     * Delete all mentions for a specific content
     * 
     * @param string $contentType
     * @param int $contentId
     * @return int Number of mentions deleted
     */
    public function deleteMentions($contentType, $contentId)
    {
        try {
            $count = Mention::where('mentionable_type', $contentType)
                ->where('mentionable_id', $contentId)
                ->delete();
                
            return $count;
        } catch (\Exception $e) {
            \Log::error('Error deleting mentions: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get mentions for a user (used in profiles)
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getMentionsForUser($userId, $limit = 20)
    {
        return Mention::where('mentioned_type', 'App\\Models\\User')
            ->where('mentioned_id', $userId)
            ->where('is_active', true)
            ->with(['mentionable', 'mentionedBy'])
            ->orderBy('mentioned_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($mention) {
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
            })
            ->toArray();
    }

    /**
     * Get mentions for a team (used in profiles)
     * 
     * @param int $teamId
     * @param int $limit
     * @return array
     */
    public function getMentionsForTeam($teamId, $limit = 20)
    {
        return Mention::where('mentioned_type', 'App\\Models\\Team')
            ->where('mentioned_id', $teamId)
            ->where('is_active', true)
            ->with(['mentionable', 'mentionedBy'])
            ->orderBy('mentioned_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($mention) {
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
            })
            ->toArray();
    }

    /**
     * Get mentions for a player (used in profiles)
     * 
     * @param int $playerId
     * @param int $limit
     * @return array
     */
    public function getMentionsForPlayer($playerId, $limit = 20)
    {
        return Mention::where('mentioned_type', 'App\\Models\\Player')
            ->where('mentioned_id', $playerId)
            ->where('is_active', true)
            ->with(['mentionable', 'mentionedBy'])
            ->orderBy('mentioned_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($mention) {
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
            })
            ->toArray();
    }
}