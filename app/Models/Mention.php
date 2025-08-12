<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Mention extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentionable_type',
        'mentionable_id',
        'mentioned_type',
        'mentioned_id',
        'context',
        'mention_text',
        'position_start',
        'position_end',
        'mentioned_by',
        'mentioned_at',
        'is_active',
        'metadata'
    ];

    protected $casts = [
        'mentioned_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array'
    ];

    // Polymorphic relationship to the content that contains the mention
    public function mentionable()
    {
        return $this->morphTo();
    }

    // Relationship to the user who made the mention
    public function mentionedBy()
    {
        return $this->belongsTo(User::class, 'mentioned_by');
    }

    // Polymorphic relationship to what was mentioned (player, team, user)
    public function mentioned()
    {
        return $this->morphTo('mentioned', 'mentioned_type', 'mentioned_id');
    }

    // Scopes for filtering mentions
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlayer($query, $playerId)
    {
        return $query->where('mentioned_type', 'player')
                    ->where('mentioned_id', $playerId);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('mentioned_type', 'team')
                    ->where('mentioned_id', $teamId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('mentioned_type', 'user')
                    ->where('mentioned_id', $userId);
    }

    public function scopeInContent($query, $contentType, $contentId)
    {
        return $query->where('mentionable_type', $contentType)
                    ->where('mentionable_id', $contentId);
    }

    // Helper method to get the display name of what was mentioned
    public function getMentionedDisplayName()
    {
        switch ($this->mentioned_type) {
            case 'player':
                $player = \App\Models\Player::find($this->mentioned_id);
                return $player ? ($player->real_name ?: $player->username) : 'Unknown Player';
            
            case 'team':
                $team = \App\Models\Team::find($this->mentioned_id);
                return $team ? $team->name : 'Unknown Team';
            
            case 'user':
                $user = \App\Models\User::find($this->mentioned_id);
                return $user ? $user->name : 'Unknown User';
            
            default:
                return 'Unknown';
        }
    }

    // Helper method to get the URL for the mentioned entity
    public function getMentionedUrl()
    {
        switch ($this->mentioned_type) {
            case 'player':
                return "/players/{$this->mentioned_id}";
            
            case 'team':
                return "/teams/{$this->mentioned_id}";
            
            case 'user':
                return "/users/{$this->mentioned_id}";
            
            default:
                return '#';
        }
    }

    // Helper method to get the content context
    public function getContentContext()
    {
        switch ($this->mentionable_type) {
            case 'news':
                $news = \App\Models\News::find($this->mentionable_id);
                return $news ? [
                    'type' => 'news',
                    'title' => $news->title,
                    'url' => "/news/{$news->slug}"
                ] : null;
            
            case 'news_comment':
                $comment = \App\Models\NewsComment::find($this->mentionable_id);
                if ($comment && $comment->news) {
                    return [
                        'type' => 'news_comment',
                        'title' => "Comment on: {$comment->news->title}",
                        'url' => "/news/{$comment->news->slug}#comment-{$comment->id}"
                    ];
                }
                return null;
            
            case 'match':
                $match = \App\Models\Match::find($this->mentionable_id);
                if ($match) {
                    return [
                        'type' => 'match',
                        'title' => "{$match->team1_name} vs {$match->team2_name}",
                        'url' => "/matches/{$match->id}"
                    ];
                }
                return null;
            
            case 'forum_thread':
                $thread = \App\Models\ForumThread::find($this->mentionable_id);
                return $thread ? [
                    'type' => 'forum_thread',
                    'title' => $thread->title,
                    'url' => "/forums/threads/{$thread->id}"
                ] : null;
            
            case 'forum_post':
                $post = \App\Models\ForumPost::find($this->mentionable_id);
                if ($post && $post->thread) {
                    return [
                        'type' => 'forum_post',
                        'title' => "Reply in: {$post->thread->title}",
                        'url' => "/forums/threads/{$post->thread->id}#post-{$post->id}"
                    ];
                }
                return null;
            
            default:
                return null;
        }
    }
}