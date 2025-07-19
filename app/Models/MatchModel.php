<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MatchModel extends Model
{
    use HasFactory;
    
    protected $table = 'matches';

    protected $fillable = [
        'team1_id',
        'team2_id',
        'event_id',
        'format',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'team1_score',
        'team2_score',
        'winner_id',
        'round',
        'bracket_position',
        'match_number',
        'stream_urls',
        'vod_urls',
        'betting_urls',
        'viewers',
        'peak_viewers',
        'hero_bans_enabled',
        'banned_heroes',
        'is_remake',
        'remake_of',
        'map_pool',
        'notes',
        'featured',
        'sponsors'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'stream_urls' => 'array',
        'vod_urls' => 'array',
        'betting_urls' => 'array',
        'banned_heroes' => 'array',
        'map_pool' => 'array',
        'notes' => 'array',
        'sponsors' => 'array',
        'hero_bans_enabled' => 'boolean',
        'is_remake' => 'boolean',
        'featured' => 'boolean'
    ];

    /**
     * Relationships
     */
    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function maps(): HasMany
    {
        return $this->hasMany(MatchMap::class);
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    public function remakeOriginal(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'remake_of');
    }

    public function remakes(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'remake_of');
    }

    /**
     * Scopes
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
                    ->where('scheduled_at', '>', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Accessors
     */
    public function getIsLiveAttribute(): bool
    {
        return $this->status === 'live';
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->status === 'upcoming' && $this->scheduled_at > now();
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->ended_at) {
            return $this->ended_at->diffInSeconds($this->started_at);
        }
        return null;
    }

    public function getCurrentMapAttribute()
    {
        return $this->maps()
            ->where('status', 'live')
            ->orWhere('status', 'upcoming')
            ->orderBy('map_number')
            ->first();
    }

    /**
     * Methods
     */
    public function startMatch(): void
    {
        $this->update([
            'status' => 'live',
            'started_at' => now()
        ]);
    }

    public function endMatch(): void
    {
        // Determine winner based on score
        if ($this->team1_score > $this->team2_score) {
            $winnerId = $this->team1_id;
        } elseif ($this->team2_score > $this->team1_score) {
            $winnerId = $this->team2_id;
        } else {
            $winnerId = null; // Draw
        }

        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'winner_id' => $winnerId
        ]);
    }

    public function updateScore(int $team1Score, int $team2Score): void
    {
        $this->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score
        ]);
    }

    public function getPlayerStatsForMap($mapId)
    {
        return $this->playerStats()
            ->where('map_id', $mapId)
            ->with(['player', 'team'])
            ->get()
            ->groupBy('team_id');
    }

    public function getOverallPlayerStats()
    {
        return $this->playerStats()
            ->with(['player', 'team'])
            ->selectRaw('
                player_id,
                team_id,
                SUM(eliminations) as total_eliminations,
                SUM(assists) as total_assists,
                SUM(deaths) as total_deaths,
                AVG(kda) as avg_kda,
                SUM(damage_dealt) as total_damage,
                SUM(healing_done) as total_healing,
                SUM(damage_blocked) as total_blocked
            ')
            ->groupBy('player_id', 'team_id')
            ->get();
    }

    /**
     * Get match format details
     */
    public function getFormatDetailsAttribute()
    {
        $formats = [
            'BO1' => ['maps' => 1, 'win_condition' => 1],
            'BO3' => ['maps' => 3, 'win_condition' => 2],
            'BO5' => ['maps' => 5, 'win_condition' => 3],
            'BO7' => ['maps' => 7, 'win_condition' => 4],
            'BO9' => ['maps' => 9, 'win_condition' => 5]
        ];

        return $formats[$this->format] ?? ['maps' => 3, 'win_condition' => 2];
    }

    /**
     * Check if match can be started
     */
    public function canStart(): bool
    {
        return $this->status === 'upcoming' && 
               $this->team1_id && 
               $this->team2_id &&
               $this->maps()->count() > 0;
    }

    /**
     * Get live data for real-time updates
     */
    public function getLiveData()
    {
        return [
            'match_id' => $this->id,
            'status' => $this->status,
            'team1_score' => $this->team1_score,
            'team2_score' => $this->team2_score,
            'current_map' => $this->current_map,
            'viewers' => $this->viewers,
            'duration' => $this->duration,
            'maps' => $this->maps()->with(['playerStats'])->get()
        ];
    }
}