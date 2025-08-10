<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserWarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'moderator_id',
        'reason',
        'severity',
        'duration_days',  // Changed from expires_at
        'acknowledged',
        'acknowledged_at'
    ];

    protected $casts = [
        'duration_days' => 'integer',  // Changed from expires_at
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function scopeActive($query)
    {
        // Check if expires_at column exists
        if (\Schema::hasColumn('user_warnings', 'expires_at')) {
            return $query->where(function ($q) {
                $q->where('expires_at', '>', now())
                  ->orWhereNull('expires_at');
            });
        }
        // If expires_at doesn't exist, return all warnings as active
        return $query;
    }

    public function scopeExpired($query)
    {
        // Check if expires_at column exists
        if (\Schema::hasColumn('user_warnings', 'expires_at')) {
            return $query->where('expires_at', '<=', now());
        }
        // If expires_at doesn't exist, return empty query
        return $query->whereRaw('1 = 0');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeRecentFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function isActive()
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function isExpired()
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function acknowledge()
    {
        $this->update([
            'acknowledged' => true,
            'acknowledged_at' => now()
        ]);
    }

    public function getDaysUntilExpirationAttribute()
    {
        if ($this->expires_at === null) {
            return null; // Never expires
        }

        return $this->expires_at->diffInDays(now(), false);
    }

    public function getSeverityColorAttribute()
    {
        switch ($this->severity) {
            case 'low':
                return '#10B981'; // green
            case 'medium':
                return '#F59E0B'; // yellow
            case 'high':
                return '#EF4444'; // red
            case 'critical':
                return '#7C2D12'; // dark red
            default:
                return '#6B7280'; // gray
        }
    }

    public function getSeverityDisplayNameAttribute()
    {
        return ucfirst($this->severity);
    }

    public function getStatusAttribute()
    {
        if (!$this->acknowledged) {
            return 'unacknowledged';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isActive()) {
            return 'active';
        }

        return 'inactive';
    }
}