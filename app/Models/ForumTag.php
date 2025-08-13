<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ForumTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug', 
        'description',
        'color',
        'usage_count',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'usage_count' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Get threads associated with this tag
     */
    public function threads()
    {
        return $this->belongsToMany(ForumThread::class, 'forum_thread_tags', 'tag_id', 'thread_id')
                    ->withTimestamps();
    }

    /**
     * Get the user who created this tag
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active tags
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for popular tags
     */
    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    /**
     * Scope for trending tags (recently used)
     */
    public function scopeTrending($query, $days = 7)
    {
        return $query->whereHas('threads', function($q) use ($days) {
            $q->where('forum_threads.created_at', '>=', now()->subDays($days));
        })
        ->withCount(['threads' => function($q) use ($days) {
            $q->where('forum_threads.created_at', '>=', now()->subDays($days));
        }])
        ->orderBy('threads_count', 'desc');
    }

    /**
     * Get tag display style
     */
    public function getDisplayStyleAttribute()
    {
        return [
            'background-color' => $this->color,
            'color' => $this->getContrastColor(),
            'border-radius' => '12px',
            'padding' => '4px 8px',
            'font-size' => '12px',
            'font-weight' => '500'
        ];
    }

    /**
     * Get contrasting text color for the tag background
     */
    public function getContrastColor()
    {
        $hex = ltrim($this->color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        
        return $brightness > 155 ? '#000000' : '#FFFFFF';
    }

    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement usage count
     */
    public function decrementUsage()
    {
        $this->decrement('usage_count');
    }

    /**
     * Get URL for tag page
     */
    public function getUrlAttribute()
    {
        return "/forums/tags/{$this->slug}";
    }

    /**
     * Check if tag is trending
     */
    public function getIsTrendingAttribute()
    {
        $recentUsage = $this->threads()
            ->where('forum_threads.created_at', '>=', now()->subDays(7))
            ->count();
            
        return $recentUsage >= 3; // Consider trending if used 3+ times in last week
    }
}