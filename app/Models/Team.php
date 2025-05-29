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

    protected $appends = ['recent_matches', 'win_percentage', 'total_matches'];

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

    public function getRecentMatchesAttribute()
    {
        return GameMatch::where('team1_id', $this->id)
                       ->orWhere('team2_id', $this->id)
                       ->with(['team1', 'team2', 'event'])
                       ->orderBy('scheduled_at', 'desc')
                       ->limit(5)
                       ->get();
    }

    public function getWinPercentageAttribute()
    {
        $totalMatches = GameMatch::where(function($query) {
            $query->where('team1_id', $this->id)
                  ->orWhere('team2_id', $this->id);
        })->where('status', 'completed')->count();

        if ($totalMatches === 0) return 0;

        $wins = GameMatch::where(function($query) {
            $query->where(function($q) {
                $q->where('team1_id', $this->id)
                  ->whereRaw('team1_score > team2_score');
            })->orWhere(function($q) {
                $q->where('team2_id', $this->id)
                  ->whereRaw('team2_score > team1_score');
            });
        })->where('status', 'completed')->count();

        return round(($wins / $totalMatches) * 100, 1);
    }

    public function getTotalMatchesAttribute()
    {
        return GameMatch::where('team1_id', $this->id)
                       ->orWhere('team2_id', $this->id)
                       ->count();
    }
}
