<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventStanding extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'team_id', 'position', 'wins', 'losses',
        'maps_won', 'maps_lost', 'prize_won', 'status', 'match_history'
    ];

    protected $casts = [
        'position' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'maps_won' => 'integer',
        'maps_lost' => 'integer',
        'prize_won' => 'decimal:2',
        'match_history' => 'array'
    ];

    // Relationships
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Accessors
    public function getWinRateAttribute()
    {
        $totalMatches = $this->wins + $this->losses;
        return $totalMatches > 0 ? round(($this->wins / $totalMatches) * 100, 1) : 0;
    }

    public function getMapDifferentialAttribute()
    {
        return $this->maps_won - $this->maps_lost;
    }

    public function getFormattedPrizeAttribute()
    {
        return $this->prize_won ? number_format($this->prize_won, 0) : null;
    }

    // Scopes
    public function scopeByPosition($query)
    {
        return $query->orderBy('position');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEliminated($query)
    {
        return $query->where('status', 'eliminated');
    }

    public function scopeQualified($query)
    {
        return $query->where('status', 'qualified');
    }

    // Helper Methods
    public function updateStats($won, $mapsWon = 0, $mapsLost = 0)
    {
        if ($won) {
            $this->wins++;
        } else {
            $this->losses++;
        }
        
        $this->maps_won += $mapsWon;
        $this->maps_lost += $mapsLost;
        $this->save();
    }

    public function addMatchToHistory($matchData)
    {
        $history = $this->match_history ?? [];
        $history[] = $matchData;
        $this->match_history = $history;
        $this->save();
    }

    // Constants
    public const STATUSES = [
        'active' => 'Active',
        'eliminated' => 'Eliminated',
        'qualified' => 'Qualified',
        'champion' => 'Champion'
    ];
}