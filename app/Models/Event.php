<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'status', 'start_date', 'end_date', 'prize_pool',
        'team_count', 'location', 'organizer', 'format', 'description',
        'image', 'registration_open', 'stream_viewers'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'team_count' => 'integer',
        'registration_open' => 'boolean',
        'stream_viewers' => 'integer'
    ];

    public function matches()
    {
        return $this->hasMany(Match::class);
    }

    public function getTeamsAttribute()
    {
        return $this->team_count ?? 32;
    }
}
