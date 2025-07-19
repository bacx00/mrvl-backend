<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'map_id',
        'event_type',
        'game_time_seconds',
        'event_data',
        'player_id',
        'target_player_id'
    ];

    protected $casts = [
        'event_data' => 'array'
    ];

    /**
     * Relationships
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(Match::class);
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(MatchMap::class, 'map_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function targetPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'target_player_id');
    }

    /**
     * Event Type Constants
     */
    const TYPE_KILL = 'kill';
    const TYPE_DEATH = 'death';
    const TYPE_ASSIST = 'assist';
    const TYPE_OBJECTIVE_CAPTURE = 'objective_capture';
    const TYPE_OBJECTIVE_LOST = 'objective_lost';
    const TYPE_HERO_SWAP = 'hero_swap';
    const TYPE_ULTIMATE_USED = 'ultimate_used';
    const TYPE_ROUND_START = 'round_start';
    const TYPE_ROUND_END = 'round_end';
    const TYPE_MAP_START = 'map_start';
    const TYPE_MAP_END = 'map_end';
    const TYPE_MATCH_START = 'match_start';
    const TYPE_MATCH_END = 'match_end';
    const TYPE_PAUSE = 'pause';
    const TYPE_RESUME = 'resume';
    const TYPE_CHECKPOINT_REACHED = 'checkpoint_reached';
    const TYPE_PAYLOAD_PROGRESS = 'payload_progress';
    const TYPE_CAPTURE_PROGRESS = 'capture_progress';

    /**
     * Scopes
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForMap($query, int $mapId)
    {
        return $query->where('map_id', $mapId);
    }

    public function scopeByPlayer($query, int $playerId)
    {
        return $query->where('player_id', $playerId);
    }

    /**
     * Get formatted game time
     */
    public function getGameTimeFormattedAttribute(): string
    {
        $minutes = floor($this->game_time_seconds / 60);
        $seconds = $this->game_time_seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Create event helpers
     */
    public static function createKillEvent(int $matchId, ?int $mapId, int $gameTime, array $data): self
    {
        return self::create([
            'match_id' => $matchId,
            'map_id' => $mapId,
            'event_type' => self::TYPE_KILL,
            'game_time_seconds' => $gameTime,
            'event_data' => $data,
            'player_id' => $data['killer_id'] ?? null,
            'target_player_id' => $data['victim_id'] ?? null
        ]);
    }

    public static function createObjectiveEvent(int $matchId, ?int $mapId, int $gameTime, string $type, array $data): self
    {
        return self::create([
            'match_id' => $matchId,
            'map_id' => $mapId,
            'event_type' => $type,
            'game_time_seconds' => $gameTime,
            'event_data' => $data,
            'player_id' => $data['player_id'] ?? null
        ]);
    }

    public static function createHeroSwapEvent(int $matchId, ?int $mapId, int $gameTime, int $playerId, string $fromHero, string $toHero): self
    {
        return self::create([
            'match_id' => $matchId,
            'map_id' => $mapId,
            'event_type' => self::TYPE_HERO_SWAP,
            'game_time_seconds' => $gameTime,
            'event_data' => [
                'from_hero' => $fromHero,
                'to_hero' => $toHero
            ],
            'player_id' => $playerId
        ]);
    }

    /**
     * Get event description for display
     */
    public function getDescriptionAttribute(): string
    {
        switch ($this->event_type) {
            case self::TYPE_KILL:
                return "{$this->player->username} eliminated {$this->targetPlayer->username}";
            
            case self::TYPE_HERO_SWAP:
                return "{$this->player->username} switched from {$this->event_data['from_hero']} to {$this->event_data['to_hero']}";
            
            case self::TYPE_OBJECTIVE_CAPTURE:
                return "Objective captured" . ($this->player ? " by {$this->player->username}" : "");
            
            case self::TYPE_CHECKPOINT_REACHED:
                return "Checkpoint {$this->event_data['checkpoint_number']} reached";
            
            case self::TYPE_ROUND_END:
                return "Round {$this->event_data['round_number']} ended";
            
            default:
                return ucfirst(str_replace('_', ' ', $this->event_type));
        }
    }
}