<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Vote extends Model
{
    protected $fillable = [
        'user_id',
        'voteable_type',
        'voteable_id',
        'vote'
    ];

    protected $casts = [
        'vote' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user who cast the vote
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the voteable model (news, comment, etc.)
     */
    public function voteable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the vote type as string
     */
    public function getVoteTypeAttribute(): string
    {
        return match($this->vote) {
            1 => 'upvote',
            -1 => 'downvote',
            default => 'neutral'
        };
    }

    /**
     * Check if this is an upvote
     */
    public function isUpvote(): bool
    {
        return $this->vote === 1;
    }

    /**
     * Check if this is a downvote
     */
    public function isDownvote(): bool
    {
        return $this->vote === -1;
    }

    /**
     * Scope to get votes for specific content
     */
    public function scopeForContent($query, string $type, int $id)
    {
        return $query->where('voteable_type', $type)
                    ->where('voteable_id', $id);
    }

    /**
     * Scope to get user's votes
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get upvotes only
     */
    public function scopeUpvotes($query)
    {
        return $query->where('vote', 1);
    }

    /**
     * Scope to get downvotes only
     */
    public function scopeDownvotes($query)
    {
        return $query->where('vote', -1);
    }
}