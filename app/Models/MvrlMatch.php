<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MvrlMatch extends Model
{
    use HasFactory;

    /**************************************************************
     * Table & fill-ables
     *************************************************************/
    protected $table = 'matches';   // ← if your table is literally “matches”
    protected $fillable = [
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'scheduled_at',
        'format',
        'status',
        'event_id',
        'maps_data',
        'hero_data',
        'live_data',
        'player_stats',
        'match_timer',
        'series_score_team1',
        'series_score_team2',
        'current_map_number',
        'viewers',
        'stream_urls',
        'betting_urls',
        'vod_urls',
        'round',
        'bracket_position',
        'overtime',
        'created_by',
        'allow_past_date',
        'winner_id',
        'started_at',
        'ended_at'
    ];

    /**************************************************************
     * Casts
     *************************************************************/
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'overtime' => 'boolean',
        'allow_past_date' => 'boolean',
        'maps_data' => 'array',
        'hero_data' => 'array',
        'live_data' => 'array',
        'player_stats' => 'array'
    ];

    /**************************************************************
     * Relationships
     *************************************************************/
    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    /**
     * The event this match belongs to
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**  
     *  Players that actually played in the match  
     *  match_player pivot has KDA columns.  
     */
    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_player')
                    ->withPivot(['kills', 'deaths', 'assists'])
                    ->withTimestamps();
    }

    /**
     * Winner team relationship
     */
    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    /**
     * Creator of the match
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
