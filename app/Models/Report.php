<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'description',
        'status',
        'moderator_id',
        'resolution',
        'resolution_reason',
        'resolved_at'
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('reportable_type', 'like', '%' . $type . '%');
    }

    public function scopeRecentFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function markAsResolved($moderatorId, $resolution, $reason = null)
    {
        $this->update([
            'status' => 'resolved',
            'moderator_id' => $moderatorId,
            'resolution' => $resolution,
            'resolution_reason' => $reason,
            'resolved_at' => now()
        ]);
    }

    public function markAsDismissed($moderatorId, $reason = null)
    {
        $this->update([
            'status' => 'dismissed',
            'moderator_id' => $moderatorId,
            'resolution' => 'dismissed',
            'resolution_reason' => $reason,
            'resolved_at' => now()
        ]);
    }

    public function getTypeDisplayNameAttribute()
    {
        $type = class_basename($this->reportable_type);
        
        switch ($type) {
            case 'ForumThread':
                return 'Forum Thread';
            case 'Post':
                return 'Forum Post';
            case 'User':
                return 'User';
            case 'NewsComment':
                return 'News Comment';
            default:
                return ucfirst(strtolower($type));
        }
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isResolved()
    {
        return $this->status === 'resolved';
    }

    public function isDismissed()
    {
        return $this->status === 'dismissed';
    }
}