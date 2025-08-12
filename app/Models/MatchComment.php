<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'match_id',
        'user_id',
        'parent_id',
        'content',
        'status',
        'is_edited',
        'edited_at',
        'upvotes',
        'downvotes',
        'score',
        'likes',
        'dislikes',
        'is_flagged',
        'moderation_note',
        'last_moderated_at',
        'last_moderated_by'
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'is_flagged' => 'boolean',
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'last_moderated_at' => 'datetime'
    ];

    /**
     * Get the match this comment belongs to
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /**
     * Get the user who made this comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for replies)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MatchComment::class, 'parent_id');
    }

    /**
     * Get child comments (replies)
     */
    public function children()
    {
        return $this->hasMany(MatchComment::class, 'parent_id');
    }

    /**
     * Get replies with proper sorting
     */
    public function replies()
    {
        return $this->hasMany(MatchComment::class, 'parent_id')
                    ->with(['user', 'votes', 'mentions'])
                    ->withCount(['votes as upvotes' => function($query) {
                        $query->where('vote', 1);
                    }, 'votes as downvotes' => function($query) {
                        $query->where('vote', -1);
                    }])
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Get votes for this comment
     */
    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'voteable');
    }

    /**
     * Get mentions for this comment
     */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /**
     * Get reports for this comment
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Get last moderator who acted on this comment
     */
    public function lastModerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_moderated_by');
    }

    /**
     * Check if this comment is a reply
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Check if user can edit this comment
     */
    public function canBeEditedBy(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('moderator') || $user->id === $this->user_id;
    }

    /**
     * Mark comment as edited
     */
    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now()
        ]);
    }

    /**
     * Get comment depth for nested replies
     */
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        
        return $depth;
    }

    /**
     * Calculate comment score
     */
    public function calculateScore(): int
    {
        return $this->upvotes - $this->downvotes;
    }

    /**
     * Scope to get approved comments
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get top-level comments (not replies)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get replies to a specific comment
     */
    public function scopeRepliesTo($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Scope to get flagged comments
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Scope to get comments by match
     */
    public function scopeByMatch($query, int $matchId)
    {
        return $query->where('match_id', $matchId);
    }

    /**
     * Scope to get comments by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if comment has pending reports
     */
    public function hasReports(): bool
    {
        return $this->reports()->where('status', 'pending')->exists();
    }

    /**
     * Check if comment can be moderated
     */
    public function canBeModerated(): bool
    {
        return !$this->trashed();
    }

    /**
     * Get comment status badge
     */
    public function getStatusBadge(): array
    {
        if ($this->is_flagged) {
            return ['text' => 'FLAGGED', 'color' => 'red'];
        }
        
        if ($this->status === 'pending') {
            return ['text' => 'PENDING', 'color' => 'yellow'];
        }
        
        if ($this->status === 'approved') {
            return ['text' => 'APPROVED', 'color' => 'green'];
        }
        
        return ['text' => 'NORMAL', 'color' => 'gray'];
    }

    /**
     * Process mentions in comment content
     */
    public function processMentions(): void
    {
        // Extract mentions from content
        preg_match_all('/@([a-zA-Z0-9_]+)|@team:([a-zA-Z0-9_]+)|@player:([a-zA-Z0-9_]+)/', $this->content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $mentionText = $match[0];
            $type = 'user';
            $entityId = null;
            
            if (isset($match[2]) && !empty($match[2])) {
                // Team mention
                $type = 'team';
                $team = Team::where('name', 'LIKE', '%' . $match[2] . '%')
                           ->orWhere('short_name', 'LIKE', '%' . $match[2] . '%')
                           ->first();
                $entityId = $team?->id;
            } elseif (isset($match[3]) && !empty($match[3])) {
                // Player mention
                $type = 'player';
                $player = Player::where('name', 'LIKE', '%' . $match[3] . '%')
                               ->orWhere('username', 'LIKE', '%' . $match[3] . '%')
                               ->first();
                $entityId = $player?->id;
            } else {
                // User mention
                $user = User::where('username', 'LIKE', '%' . $match[1] . '%')
                           ->orWhere('name', 'LIKE', '%' . $match[1] . '%')
                           ->first();
                $entityId = $user?->id;
            }
            
            if ($entityId) {
                $this->mentions()->updateOrCreate([
                    'mention_text' => $mentionText,
                    'type' => $type,
                    'entity_id' => $entityId
                ]);
            }
        }
    }
}