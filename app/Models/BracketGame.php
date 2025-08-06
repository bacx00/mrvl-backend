<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BracketGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_match_id', 'game_number', 'map_name',
        'team1_id', 'team2_id', 'team1_score', 'team2_score', 'winner_id',
        'status', 'duration_minutes', 'started_at', 'completed_at',
        'stats', 'vod_link'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'stats' => 'array'
    ];

    public function bracketMatch()
    {
        return $this->belongsTo(BracketMatch::class);
    }

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function getScoreDisplayAttribute()
    {
        if ($this->status === 'pending') return 'TBD';
        if ($this->status === 'ongoing') return 'LIVE';
        return "{$this->team1_score} - {$this->team2_score}";
    }

    public function getDurationDisplayAttribute()
    {
        if (!$this->duration_minutes) return null;
        $hours = intval($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }
}