<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Match extends Model
{
    use HasFactory;

    protected $fillable = ['team1_id', 'team2_id', 'score1', 'score2', 'date'];

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_player')
                    ->withPivot('kills', 'deaths', 'assists')
                    ->withTimestamps();
    }
}