<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'short_name', 'slug', 'logo', 'region', 'platform', 'game', 'division',
        'country', 'flag', 'country_code', 'country_flag', 'rating', 'rank', 'win_rate', 
        'map_win_rate', 'recent_performance', 'longest_win_streak', 'current_streak_count',
        'current_streak_type', 'points', 'record', 'wins', 'losses', 'matches_played',
        'maps_won', 'maps_lost', 'tournaments_won', 'peak', 'streak', 'last_match', 
        'founded', 'founded_date', 'captain', 'coach', 'manager', 'coach_picture', 
        'coach_image', 'coach_name', 'coach_nationality', 'coach_social_media',
        'description', 'website', 'liquipedia_url', 'twitter', 'instagram',
        'youtube', 'twitch', 'tiktok', 'discord', 'facebook', 'social_media', 
        'social_links', 'achievements', 'recent_form', 'player_count', 'status',
        'earnings', 'owner', 'elo_rating', 'peak_elo', 'elo_changes', 'last_elo_update',
        'ranking'
    ];

    protected $casts = [
        'rating' => 'integer',
        'rank' => 'integer',
        'win_rate' => 'float',
        'points' => 'integer',
        'peak' => 'integer',
        'player_count' => 'integer',
        'social_media' => 'array',
        'coach_social_media' => 'array',
        'achievements' => 'array',
        'recent_form' => 'array'
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
