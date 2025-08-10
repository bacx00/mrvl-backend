<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumThread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'content', 'user_id', 'category_id', 'replies', 'views',
        'pinned', 'locked', 'sticky', 'is_flagged', 'last_reply_at',
        'moderation_note', 'last_moderated_at', 'last_moderated_by'
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'locked' => 'boolean',
        'sticky' => 'boolean',
        'is_flagged' => 'boolean',
        'last_reply_at' => 'datetime',
        'last_moderated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'thread_id');
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function votes()
    {
        return $this->morphMany(Vote::class, 'voteable');
    }

    public function lastModerator()
    {
        return $this->belongsTo(User::class, 'last_moderated_by');
    }

    public function scopePinned($query)
    {
        return $query->where('pinned', true);
    }

    public function scopeSticky($query)
    {
        return $query->where('sticky', true);
    }

    public function scopeLocked($query)
    {
        return $query->where('locked', true);
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopePopular($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
                    ->orderByDesc('views')
                    ->orderByDesc('replies');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function incrementViews()
    {
        $this->increment('views');
    }

    public function incrementReplies()
    {
        $this->increment('replies');
        $this->update(['last_reply_at' => now()]);
    }

    public function getStatusAttribute()
    {
        if ($this->is_flagged) return 'flagged';
        if ($this->locked) return 'locked';
        if ($this->sticky) return 'sticky';
        if ($this->pinned) return 'pinned';
        return 'normal';
    }

    public function canBeModerated()
    {
        return !$this->trashed();
    }

    public function hasReports()
    {
        return $this->reports()->where('status', 'pending')->exists();
    }
}