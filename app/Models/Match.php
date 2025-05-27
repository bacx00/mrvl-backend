<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Match extends Model
{
    use HasFactory;

    protected $fillable = [
        'team1_id', 'team2_id', 'event_id', 'scheduled_at', 'status',
        'team1_score', 'team2_score', 'format', 'current_map', 'viewers',
        'stream_url', 'maps_data', 'prize_pool'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'team1_score' => 'integer',
        'team2_score' => 'integer',
        'viewers' => 'integer',
        'maps_data' => 'array'
    ];

    protected $appends = ['series', 'maps'];

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

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_player')
                   ->withPivot(['kills', 'deaths', 'assists', 'damage', 'healing']);
    }

    public function getSeriesAttribute()
    {
        return [
            'format' => $this->format,
            'score' => [$this->team1_score, $this->team2_score]
        ];
    }

    public function getMapsAttribute()
    {
        if ($this->maps_data) {
            return $this->maps_data;
        }

        return [
            ['name' => 'Asgard Throne Room', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
            ['name' => 'Helicarrier Command', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
            ['name' => 'Sanctum Sanctorum', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming']
        ];
    }
}
