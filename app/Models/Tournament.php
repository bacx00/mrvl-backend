<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'type', 'status', 'description', 'region',
        'prize_pool', 'team_count', 'start_date', 'end_date', 'settings'
    ];

    protected $casts = [
        'prize_pool' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'settings' => 'array'
    ];

    public function bracketStages()
    {
        return $this->hasMany(BracketStage::class)->orderBy('stage_order');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'tournament_teams')
                    ->withPivot(['seed', 'swiss_wins', 'swiss_losses', 'swiss_score', 'status', 'registered_at'])
                    ->withTimestamps();
    }

    public function matches()
    {
        return $this->hasMany(BracketMatch::class);
    }

    public function getSwissStandingsAttribute()
    {
        return $this->teams()
                    ->orderByDesc('pivot_swiss_score')
                    ->orderByDesc('pivot_swiss_wins')
                    ->orderBy('pivot_swiss_losses')
                    ->get();
    }

    public function getUpperBracketTeamsAttribute()
    {
        return $this->teams()
                    ->wherePivot('swiss_score', '>=', 2.0)
                    ->orderByDesc('pivot_swiss_score')
                    ->limit(4)
                    ->get();
    }

    public function getLowerBracketTeamsAttribute()
    {
        return $this->teams()
                    ->wherePivot('swiss_score', '<', 2.0)
                    ->orderByDesc('pivot_swiss_score')
                    ->limit(4)
                    ->get();
    }
}