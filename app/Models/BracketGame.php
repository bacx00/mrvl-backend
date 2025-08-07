<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_match_id', 'game_number', 'map_name', 'map_type',
        'team1_score', 'team2_score', 'winner_id', 
        'duration_seconds', 'started_at', 'ended_at',
        'game_data', 'vod_url'
    ];

    protected $casts = [
        'game_data' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'team1_score' => 'integer',
        'team2_score' => 'integer',
        'game_number' => 'integer',
        'duration_seconds' => 'integer'
    ];

    // Relationships
    public function bracketMatch(): BelongsTo
    {
        return $this->belongsTo(BracketMatch::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    // Computed attributes
    public function getDurationDisplayAttribute(): string
    {
        if (!$this->duration_seconds) return 'N/A';
        
        $minutes = intval($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getGameDisplayNameAttribute(): string
    {
        $mapText = $this->map_name ? " - {$this->map_name}" : '';
        return "Game {$this->game_number}{$mapText}";
    }

    public function getScoreDisplayAttribute(): string
    {
        return "{$this->team1_score} - {$this->team2_score}";
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->winner_id !== null && $this->ended_at !== null;
    }

    public function getMapTypeDisplayAttribute(): ?string
    {
        return match($this->map_type) {
            'control' => 'Control',
            'escort' => 'Escort',
            'hybrid' => 'Hybrid',
            'flashpoint' => 'Flashpoint',
            'push' => 'Push',
            default => $this->map_type
        };
    }

    // Scopes
    public function scopeByMatch($query, $matchId)
    {
        return $query->where('bracket_match_id', $matchId);
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('winner_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('game_number');
    }

    // Helper methods
    public function complete(int $winnerId, array $gameData = []): bool
    {
        $this->update([
            'winner_id' => $winnerId,
            'ended_at' => now(),
            'game_data' => array_merge($this->game_data ?? [], $gameData)
        ]);

        // Update parent match score
        $this->updateMatchScore();
        
        return true;
    }

    public function start(): bool
    {
        $this->update(['started_at' => now()]);
        return true;
    }

    private function updateMatchScore(): void
    {
        $match = $this->bracketMatch;
        $completedGames = $match->games()->completed()->get();
        
        $team1Wins = $completedGames->where('winner_id', $match->team1_id)->count();
        $team2Wins = $completedGames->where('winner_id', $match->team2_id)->count();
        
        $match->updateScore($team1Wins, $team2Wins);
    }

    // Constants
    public const MAP_TYPES = [
        'control' => 'Control',
        'escort' => 'Escort', 
        'hybrid' => 'Hybrid',
        'flashpoint' => 'Flashpoint',
        'push' => 'Push'
    ];

    // Marvel Rivals specific maps
    public const MARVEL_RIVALS_MAPS = [
        // Control Maps
        'birnin-zana' => 'Birnin Zana',
        'stark-tower' => 'Stark Tower',
        'sanctum-sanctorum' => 'Sanctum Sanctorum',
        
        // Escort Maps
        'new-tokyo' => 'New Tokyo',
        'klyntar' => 'Klyntar',
        'intergalactic-empire-of-wakanda' => 'Intergalactic Empire of Wakanda',
        
        // Other map types as they're added to the game
    ];
}