<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'username', 'alternate_ids', 'real_name', 'romanized_name', 'avatar', 
        'team_id', 'past_teams', 'role', 'team_position', 'position_order', 'jersey_number',
        'hero_preferences', 'skill_rating', 'main_hero', 'alt_heroes', 'region', 'country', 
        'flag', 'country_flag', 'country_code', 'nationality', 'team_country', 'rank', 
        'rating', 'elo_rating', 'peak_elo', 'elo_changes', 'last_elo_update', 'peak_rating',
        'age', 'birth_date', 'earnings', 'earnings_amount', 'earnings_currency', 
        'total_matches', 'tournaments_played', 'social_media', 'twitter', 'instagram', 
        'twitch', 'tiktok', 'youtube', 'facebook', 'discord', 'liquipedia_url', 
        'biography', 'event_placements', 'hero_pool', 'status', 'total_earnings',
        'total_eliminations', 'total_deaths', 'total_assists', 'overall_kda',
        'average_damage_per_match', 'average_healing_per_match', 'average_damage_blocked_per_match',
        'hero_statistics', 'most_played_hero', 'best_winrate_hero', 'longest_win_streak',
        'current_win_streak', 'achievements'
    ];

    protected $casts = [
        'rating' => 'float',
        'age' => 'integer',
        'alt_heroes' => 'array',
        'social_media' => 'array',
        'past_teams' => 'array',
        'total_matches' => 'integer',
        'total_wins' => 'integer',
        'total_maps_played' => 'integer',
        'avg_rating' => 'decimal:2',
        'avg_combat_score' => 'decimal:2',
        'avg_kda' => 'decimal:2',
        'avg_damage_per_round' => 'decimal:2',
        'avg_kast' => 'decimal:2',
        'avg_kills_per_round' => 'decimal:2',
        'avg_assists_per_round' => 'decimal:2',
        'avg_first_kills_per_round' => 'decimal:2',
        'avg_first_deaths_per_round' => 'decimal:2',
        'hero_pool' => 'array',
        'career_stats' => 'array',
        'achievements' => 'array'
    ];

    protected $appends = []; // Removed problematic accessors

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function matches()
    {
        return $this->belongsToMany(GameMatch::class, 'match_player', 'player_id', 'match_id')
                   ->withPivot(['kills', 'deaths', 'assists', 'damage', 'healing']);
    }

    public function matchStats()
    {
        return $this->hasMany(MatchPlayerStat::class);
    }

    public function recentMatches($limit = 10)
    {
        return $this->matchStats()
            ->with(['match', 'match.team1', 'match.team2', 'match.event'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function teamHistory()
    {
        return $this->hasMany(PlayerTeamHistory::class)->orderBy('change_date', 'desc');
    }

    // Boot method to track team changes
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($player) {
            if ($player->isDirty('team_id')) {
                $originalTeamId = $player->getOriginal('team_id');
                $newTeamId = $player->team_id;

                // Create team history record
                PlayerTeamHistory::create([
                    'player_id' => $player->id,
                    'from_team_id' => $originalTeamId,
                    'to_team_id' => $newTeamId,
                    'change_date' => now(),
                    'change_type' => $player->determineChangeType($originalTeamId, $newTeamId),
                    'is_official' => true,
                    'announced_by' => auth()->id()
                ]);
            }
        });
    }

    private function determineChangeType($fromTeamId, $toTeamId)
    {
        if (!$fromTeamId && $toTeamId) {
            return 'joined';
        } elseif ($fromTeamId && !$toTeamId) {
            return 'left';
        } elseif ($fromTeamId && $toTeamId) {
            return 'transferred';
        }
        return 'transferred';
    }

    public function getCurrentTeamTenure()
    {
        $lastChange = $this->teamHistory()
            ->where('to_team_id', $this->team_id)
            ->latest('change_date')
            ->first();

        if ($lastChange) {
            return $lastChange->change_date->diffForHumans();
        }

        return 'Unknown';
    }

    // Remove problematic accessor - handled in frontend

    /**
     * Get player stats by hero (VLR.gg style)
     */
    public function getStatsByHero()
    {
        return $this->matchStats()
            ->select('hero', 
                DB::raw('COUNT(*) as matches_played'),
                DB::raw('AVG(performance_rating) as avg_rating'),
                DB::raw('AVG(combat_score) as avg_acs'),
                DB::raw('AVG(kda) as avg_kd'),
                DB::raw('AVG(damage_per_round) as avg_adr'),
                DB::raw('AVG(kast_percentage) as avg_kast'),
                DB::raw('AVG(eliminations_per_round) as avg_kpr'),
                DB::raw('AVG(assists_per_round) as avg_apr'),
                DB::raw('SUM(eliminations) as total_kills'),
                DB::raw('SUM(deaths) as total_deaths'),
                DB::raw('SUM(assists) as total_assists')
            )
            ->groupBy('hero')
            ->orderBy('matches_played', 'desc')
            ->get();
    }

    /**
     * Update career averages (called after each match)
     */
    public function updateCareerStats()
    {
        $stats = $this->matchStats;
        
        if ($stats->isEmpty()) return;
        
        $this->update([
            'total_matches' => $stats->pluck('match_id')->unique()->count(),
            'total_maps_played' => $stats->count(),
            'total_wins' => $stats->whereHas('match', function($q) {
                $q->where(function($query) {
                    $query->where('winner_id', $this->team_id)
                          ->orWhere(function($q2) {
                              $q2->where('team1_id', $this->team_id)
                                 ->whereColumn('team1_score', '>', 'team2_score');
                          })
                          ->orWhere(function($q2) {
                              $q2->where('team2_id', $this->team_id)
                                 ->whereColumn('team2_score', '>', 'team1_score');
                          });
                });
            })->count(),
            'avg_rating' => round($stats->avg('performance_rating'), 2),
            'avg_combat_score' => round($stats->avg('combat_score'), 2),
            'avg_kda' => round($stats->avg('kda'), 2),
            'avg_damage_per_round' => round($stats->avg('damage_per_round'), 2),
            'avg_kast' => round($stats->avg('kast_percentage'), 2),
            'avg_kills_per_round' => round($stats->avg('eliminations_per_round'), 2),
            'avg_assists_per_round' => round($stats->avg('assists_per_round'), 2),
            'avg_first_kills_per_round' => round($stats->avg('first_kills') / max($stats->avg('map.total_rounds'), 1), 2),
            'avg_first_deaths_per_round' => round($stats->avg('first_deaths') / max($stats->avg('map.total_rounds'), 1), 2),
            'hero_pool' => $stats->pluck('hero')->unique()->values()->toArray()
        ]);
    }

    /**
     * Get win rate
     */
    public function getWinRateAttribute()
    {
        if ($this->total_matches == 0) return 0;
        return round(($this->total_wins / $this->total_matches) * 100, 1);
    }
}
