<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTitle extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'color',
        'achievement_id',
        'is_active',
        'earned_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'earned_at' => 'datetime'
    ];

    /**
     * Get the user that owns this title
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the achievement that granted this title (if any)
     */
    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    /**
     * Activate this title (deactivates others for the user)
     */
    public function activate(): void
    {
        // Deactivate all other titles for this user
        UserTitle::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);
            
        // Activate this title
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate this title
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Get formatted title with color
     */
    public function getFormattedTitle(): array
    {
        return [
            'title' => $this->title,
            'color' => $this->color,
            'earned_at' => $this->earned_at
        ];
    }

    /**
     * Scope for active titles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for user titles
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Boot method to ensure only one active title per user
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($userTitle) {
            if ($userTitle->is_active) {
                // Deactivate other titles for this user
                UserTitle::where('user_id', $userTitle->user_id)
                    ->update(['is_active' => false]);
            }
        });
        
        static::updating(function ($userTitle) {
            if ($userTitle->is_active && $userTitle->isDirty('is_active')) {
                // Deactivate other titles for this user
                UserTitle::where('user_id', $userTitle->user_id)
                    ->where('id', '!=', $userTitle->id)
                    ->update(['is_active' => false]);
            }
        });
    }
}