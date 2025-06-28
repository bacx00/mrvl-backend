<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'team1_id', 'team2_id', 'event_id', 'scheduled_at', 'status',
        'team1_score', 'team2_score', 'format', 'match_format', 'current_map', 
        'current_round', 'current_mode', 'series_completed', 'series_winner_id',
        'viewers', 'stream_url', 'maps_data', 'timer_data', 'prize_pool',
        'competitive_settings', 'preparation_phase', 'overtime_data'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'team1_score' => 'integer',
        'team2_score' => 'integer',
        'current_round' => 'integer',
        'viewers' => 'integer',
        'series_completed' => 'boolean',
        'maps_data' => 'array',
        'timer_data' => 'array',
        'competitive_settings' => 'array',
        'preparation_phase' => 'array',
        'overtime_data' => 'array'
    ];

    protected $appends = []; // Removed problematic accessors

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function seriesWinner()
    {
        return $this->belongsTo(Team::class, 'series_winner_id');
    }

    public function rounds()
    {
        return $this->hasMany(MatchRound::class, 'match_id');
    }

    public function currentRoundData()
    {
        return $this->hasOne(MatchRound::class, 'match_id')->where('round_number', $this->current_round);
    }

    public function timers()
    {
        return $this->hasMany(CompetitiveTimer::class, 'match_id');
    }

    public function activeTimers()
    {
        return $this->hasMany(CompetitiveTimer::class, 'match_id')->whereIn('status', ['running', 'paused']);
    }

    public function playerStats()
    {
        return $this->hasMany(PlayerMatchStats::class, 'match_id');
    }

    public function liveEvents()
    {
        return $this->hasMany(LiveEvent::class, 'match_id')->orderBy('event_timestamp', 'desc');
    }

    public function matchHistory()
    {
        return $this->hasMany(MatchHistory::class, 'match_id');
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_player')
                   ->withPivot(['kills', 'deaths', 'assists', 'damage', 'healing']);
    }

    // Enhanced accessors and methods
    public function getSeriesAttribute()
    {
        return [
            'format' => $this->match_format ?? $this->format,
            'score' => [$this->team1_score, $this->team2_score],
            'completed' => $this->series_completed,
            'winner_id' => $this->series_winner_id
        ];
    }

    public function getMapsAttribute()
    {
        if ($this->maps_data) {
            return $this->maps_data;
        }

        // Default maps for competitive play
        return [
            ['name' => 'Yggsgard: Royal Palace', 'mode' => 'Domination', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
            ['name' => 'Tokyo 2099: Spider-Islands', 'mode' => 'Convoy', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
            ['name' => 'Wakanda: Birnin T\'Challa', 'mode' => 'Domination', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming']
        ];
    }

    public function getCompetitiveStatusAttribute()
    {
        return [
            'status' => $this->status,
            'current_round' => $this->current_round,
            'current_map' => $this->current_map,
            'current_mode' => $this->current_mode,
            'series_score' => [$this->team1_score, $this->team2_score],
            'format' => $this->match_format ?? $this->format,
            'completed' => $this->series_completed,
            'live_viewers' => $this->viewers ?? 0
        ];
    }

    public function getWinnerTeamAttribute()
    {
        if (!$this->series_completed) {
            return null;
        }

        if ($this->series_winner_id) {
            return $this->seriesWinner;
        }

        // Fallback to score comparison
        if ($this->team1_score > $this->team2_score) {
            return $this->team1;
        } elseif ($this->team2_score > $this->team1_score) {
            return $this->team2;
        }

        return null; // Draw
    }

    // Helper methods for live scoring
    public function isLive()
    {
        return $this->status === 'live';
    }

    public function isPaused()
    {
        return $this->status === 'paused';
    }

    public function isCompleted()
    {
        return $this->status === 'completed' || $this->series_completed;
    }

    public function canAdvanceRound()
    {
        $maxRounds = $this->match_format === 'BO1' ? 1 : ($this->match_format === 'BO3' ? 3 : 5);
        return $this->current_round < $maxRounds;
    }

    public function getRequiredWinsToWinSeries()
    {
        return match($this->match_format ?? $this->format) {
            'BO1' => 1,
            'BO3' => 2,
            'BO5' => 3,
            default => 2
        };
    }

    public function hasSeriesWinner()
    {
        $requiredWins = $this->getRequiredWinsToWinSeries();
        return $this->team1_score >= $requiredWins || $this->team2_score >= $requiredWins;
    }
}
