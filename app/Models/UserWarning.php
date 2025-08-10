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
        'expires_at',
        'acknowledged',
        'acknowledged_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
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
        return $query->where(function ($q) {
            $q->where('expires_at', '>', now())
              ->orWhereNull('expires_at');
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
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