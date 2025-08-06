<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BracketStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'name', 'type', 'stage_order', 'status', 'settings'
    ];

    protected $casts = [
        'settings' => 'array'
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches()
    {
        return $this->hasMany(BracketMatch::class)->orderBy('round_number')->orderBy('match_number');
    }

    public function positions()
    {
        return $this->hasMany(BracketPosition::class);
    }

    public function getMatchesByRoundAttribute()
    {
        return $this->matches->groupBy('round_number');
    }

    public function getCurrentRoundAttribute()
    {
        return $this->matches()
                    ->where('status', '!=', 'completed')
                    ->orderBy('round_number')
                    ->first()?->round_number ?? 1;
    }
}