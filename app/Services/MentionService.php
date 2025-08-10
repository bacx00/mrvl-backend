<?php

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Mention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
        
        // Extract @username mentions (users)
        preg_match_all('/@([a-zA-Z0-9_\s]+)(?!\w)/', $content, $userMatches, PREG_OFFSET_CAPTURE);
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
                
                // Check for duplicates
                $existingMention = Mention::where([
                    'mentionable_type' => $contentType,
                    'mentionable_id' => $contentId,
                    'mentioned_type' => $mention['type'],
                    'mentioned_id' => $mention['id'],
                    'mention_text' => $mention['mention_text']
                ])->first();
                
                if (!$existingMention) {
                    Mention::create([
                        'mentionable_type' => $contentType,
                        'mentionable_id' => $contentId,
                        'mentioned_type' => $mention['type'],
                        'mentioned_id' => $mention['id'],
                        'mention_text' => $mention['mention_text'],
                        'position_start' => $mention['position_start'] ?? null,
                        'position_end' => $mention['position_end'] ?? null,
                        'context' => $context,
                        'mentioned_by' => Auth::id(),
                        'mentioned_at' => now(),
                        'is_active' => true
                    ]);
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
}