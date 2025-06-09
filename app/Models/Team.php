<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'short_name', 'logo', 'region', 'country', 'flag',
        'rating', 'rank', 'win_rate', 'points', 'record', 'peak',
        'streak', 'last_match', 'founded', 'captain', 'coach', 
        'website', 'earnings', 'social_media', 'achievements'
    ];

    protected $casts = [
        'rating' => 'integer',
        'rank' => 'integer',
        'win_rate' => 'float',
        'points' => 'integer',
        'peak' => 'integer',
        'social_media' => 'array',
        'achievements' => 'array'
    ];

    protected $appends = []; // Removed problematic accessors

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function homeMatches()
    {
        return $this->hasMany(GameMatch::class, 'team1_id');
    }

    public function awayMatches()
    {
        return $this->hasMany(GameMatch::class, 'team2_id');
    }

    // Remove problematic accessor methods that cause infinite loops
    // These will be handled by raw queries in controllers
}
