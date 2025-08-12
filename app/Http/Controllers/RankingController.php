<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Services\EloRatingService;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Create cache key based on request parameters
            $cacheKey = 'player_rankings_' . md5(serialize([
                'rank' => $request->rank,
                'region' => $request->region,
                'role' => $request->role,
                'search' => $request->search,
                'page' => $request->page ?? 1
            ]));
            
            // Check cache first (cache for 15 minutes)
            if ($cachedData = Cache::get($cacheKey)) {
                return response()->json($cachedData);
            }
            
            $query = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role', 
                    'p.main_hero', 'p.rating', 'p.peak_rating', 'p.country', 'p.region',
                    't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
                ]);

            // Filter by rank/division
            if ($request->rank && $request->rank !== 'all') {
                $rankRange = $this->getRankRange($request->rank);
                $query->whereBetween('p.rating', $rankRange);
            }

            // Filter by region
            if ($request->region && $request->region !== 'all') {
                $query->where('p.region', $request->region);
            }

            // Filter by role
            if ($request->role && $request->role !== 'all') {
                $query->where('p.role', $request->role);
            }

            // Search functionality
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('p.username', 'LIKE', "%{$request->search}%")
                      ->orWhere('p.real_name', 'LIKE', "%{$request->search}%");
                });
            }

            // Sort by rating (highest first)
            $query->orderBy('p.rating', 'desc');

            // Handle pagination - allow limit parameter or use default of 50
            $perPage = min($request->get('limit', 50), 100); // Max 100 per page
            $players = $query->paginate($perPage);

            // Add ranking position and format data
            $playersData = collect($players->items())->map(function($player, $index) use ($players) {
                $globalRank = ($players->currentPage() - 1) * $players->perPage() + $index + 1;
                $rank = $this->getRankByRating($player->rating);
                $division = $this->getDivisionByRating($player->rating);
                
                // Ensure peak rating is at least current rating
                $actualPeakRating = max($player->peak_rating ?? 0, $player->rating ?? 0);
                
                return [
                    'id' => $player->id,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'avatar' => $player->avatar,
                    'role' => $player->role,
                    'main_hero' => $player->main_hero,
                    'country' => $player->country,
                    'region' => $player->region,
                    'team' => $player->team_name ? [
                        'name' => $player->team_name,
                        'short_name' => $player->team_short,
                        'logo' => $player->team_logo
                    ] : null,
                    'ranking' => [
                        'global_rank' => $globalRank,
                        'rating' => $player->rating,
                        'peak_rating' => $actualPeakRating,
                        'rank' => $rank,
                        'division' => $division,
                        'full_rank' => $this->getFullRankName($rank, $division),
                        'rank_image' => $this->getRankImagePath($rank, $division),
                        'points_in_division' => $this->getPointsInDivision($player->rating),
                        'points_to_next' => $this->getPointsToNext($player->rating),
                        'is_decay_eligible' => $this->isDecayEligible($rank),
                        'hero_bans_unlocked' => $this->hasHeroBansUnlocked($player->rating),
                        'chrono_shield_available' => $this->hasChronoShield($player->rating)
                    ]
                ];
            });

            // Get leaderboard statistics
            $stats = $this->getLeaderboardStats();

            $responseData = [
                'data' => $playersData,
                'pagination' => [
                    'current_page' => $players->currentPage(),
                    'last_page' => $players->lastPage(),
                    'per_page' => $players->perPage(),
                    'total' => $players->total()
                ],
                'stats' => $stats,
                'success' => true
            ];
            
            // Cache the response for 15 minutes
            Cache::put($cacheKey, $responseData, 900);

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching rankings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($playerId)
    {
        try {
            $player = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->where('p.id', $playerId)
                ->select([
                    'p.*',
                    't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
                ])
                ->first();

            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Calculate player's rank and position
            $globalRank = $this->getPlayerGlobalRank($playerId);
            $regionRank = $this->getPlayerRegionRank($playerId, $player->region);
            $rank = $this->getRankByRating($player->rating);
            $division = $this->getDivisionByRating($player->rating);

            // Get ranking history (last 30 days)
            $rankingHistory = $this->getPlayerRankingHistory($playerId);

            // Get competitive stats
            $competitiveStats = $this->getPlayerCompetitiveStats($playerId);

            // Ensure peak rating is at least current rating
            $actualPeakRating = max($player->peak_rating ?? 0, $player->rating ?? 0);

            $playerData = [
                'id' => $player->id,
                'username' => $player->username,
                'real_name' => $player->real_name,
                'avatar' => $player->avatar,
                'role' => $player->role,
                'main_hero' => $player->main_hero,
                'alt_heroes' => $player->alt_heroes ? json_decode($player->alt_heroes, true) : [],
                'country' => $player->country,
                'region' => $player->region,
                'team' => $player->team_name ? [
                    'name' => $player->team_name,
                    'short_name' => $player->team_short,
                    'logo' => $player->team_logo
                ] : null,
                'ranking' => [
                    'global_rank' => $globalRank,
                    'region_rank' => $regionRank,
                    'rating' => $player->rating,
                    'peak_rating' => $actualPeakRating,
                    'rank' => $rank,
                    'division' => $division,
                    'full_rank' => $this->getFullRankName($rank, $division),
                    'rank_image' => $this->getRankImagePath($rank, $division),
                    'points_in_division' => $this->getPointsInDivision($player->rating),
                    'points_to_next' => $this->getPointsToNext($player->rating),
                    'is_decay_eligible' => $this->isDecayEligible($rank),
                    'hero_bans_unlocked' => $this->hasHeroBansUnlocked($player->rating),
                    'chrono_shield_available' => $this->hasChronoShield($player->rating)
                ],
                'competitive_stats' => $competitiveStats,
                'ranking_history' => $rankingHistory,
                'achievements' => $this->getPlayerAchievements($playerId, $rank, $actualPeakRating),
                'marvel_rivals_features' => [
                    'hero_bans_unlocked' => $this->hasHeroBansUnlocked($player->rating),
                    'chrono_shield_available' => $this->hasChronoShield($player->rating),
                    'rank_decay_eligible' => $this->isDecayEligible($rank),
                    'team_restrictions' => $this->getTeamRestrictions($rank, $player->rating)
                ]
            ];

            return response()->json([
                'data' => $playerData,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player ranking: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRankDistribution()
    {
        try {
            $distribution = [];
            $ranks = $this->getAllRanks();
            
            foreach ($ranks as $rank) {
                $rankRange = $this->getRankRange($rank['key']);
                $count = DB::table('players')
                    ->whereBetween('rating', $rankRange)
                    ->count();
                    
                $distribution[] = [
                    'rank' => $rank['key'],
                    'name' => $rank['name'],
                    'count' => $count,
                    'percentage' => 0,
                    'rating_range' => $rankRange,
                    'divisions' => $this->getRankDivisions($rank['key'])
                ];
            }
            
            $totalPlayers = array_sum(array_column($distribution, 'count'));
            
            // Calculate percentages
            foreach ($distribution as &$rank) {
                $rank['percentage'] = $totalPlayers > 0 ? round(($rank['count'] / $totalPlayers) * 100, 2) : 0;
            }

            return response()->json([
                'data' => $distribution,
                'total_players' => $totalPlayers,
                'marvel_rivals_info' => [
                    'total_ranks' => 23,
                    'points_per_division' => 100,
                    'starting_rank' => 'Bronze III',
                    'minimum_level' => 15,
                    'season_reset' => '9 divisions down'
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching rank distribution: ' . $e->getMessage()
            ], 500);
        }
    }

    // Marvel Rivals ranking system helper methods

    private function getRankByRating($rating)
    {
        if ($rating >= 5000) return 'one_above_all';  // Top 500 only
        if ($rating >= 4600) return 'eternity';       // Point-based, no divisions
        if ($rating >= 3700) return 'celestial';      // 3 divisions (4600-3700)
        if ($rating >= 2800) return 'grandmaster';    // 3 divisions (3700-2800)
        if ($rating >= 1900) return 'diamond';        // 3 divisions (2800-1900)
        if ($rating >= 1000) return 'platinum';       // 3 divisions (1900-1000)
        if ($rating >= 700) return 'gold';            // 3 divisions (1000-700)
        if ($rating >= 400) return 'silver';          // 3 divisions (700-400)
        return 'bronze';                               // 3 divisions (400-0)
    }

    private function getDivisionByRating($rating)
    {
        $rank = $this->getRankByRating($rating);
        
        // Special ranks with no divisions
        if ($rank === 'one_above_all' || $rank === 'eternity') {
            return null;
        }
        
        // Calculate division based on rating within rank range
        $rankRanges = [
            'celestial' => [3700, 4600],
            'grandmaster' => [2800, 3700],
            'diamond' => [1900, 2800],
            'platinum' => [1000, 1900],
            'gold' => [700, 1000],
            'silver' => [400, 700],
            'bronze' => [0, 400]
        ];
        
        if (isset($rankRanges[$rank])) {
            $min = $rankRanges[$rank][0];
            $max = $rankRanges[$rank][1];
            $range = $max - $min;
            $divisionSize = $range / 3; // 3 divisions per rank
            
            $position = $rating - $min;
            
            if ($position < $divisionSize) return 'III';
            if ($position < $divisionSize * 2) return 'II';
            return 'I';
        }
        
        return 'III'; // Default to lowest division
    }

    private function getFullRankName($rank, $division)
    {
        $rankNames = [
            'bronze' => 'Bronze',
            'silver' => 'Silver',
            'gold' => 'Gold',
            'platinum' => 'Platinum',
            'diamond' => 'Diamond',
            'grandmaster' => 'Grandmaster',
            'celestial' => 'Celestial',
            'eternity' => 'Eternity',
            'one_above_all' => 'One Above All'
        ];
        
        $rankName = $rankNames[$rank] ?? 'Unranked';
        
        if ($division) {
            return $rankName . ' ' . $division;
        }
        
        return $rankName;
    }

    private function getRankImagePath($rank, $division)
    {
        if ($division) {
            return "/images/ranks/{$rank}_{$division}.png";
        }
        return "/images/ranks/{$rank}.png";
    }

    private function getPointsInDivision($rating)
    {
        $rank = $this->getRankByRating($rating);
        
        if ($rank === 'one_above_all' || $rank === 'eternity') {
            return $rating; // Point-based system
        }
        
        $rankRanges = [
            'celestial' => [3700, 4600],
            'grandmaster' => [2800, 3700],
            'diamond' => [1900, 2800],
            'platinum' => [1000, 1900],
            'gold' => [700, 1000],
            'silver' => [400, 700],
            'bronze' => [0, 400]
        ];
        
        if (isset($rankRanges[$rank])) {
            $min = $rankRanges[$rank][0];
            $divisionSize = ($rankRanges[$rank][1] - $min) / 3;
            $positionInRank = $rating - $min;
            
            return floor($positionInRank % $divisionSize);
        }
        
        return 0;
    }

    private function getPointsToNext($rating)
    {
        $rank = $this->getRankByRating($rating);
        $division = $this->getDivisionByRating($rating);
        
        if ($rank === 'one_above_all') {
            return 0; // Highest rank
        }
        
        if ($rank === 'eternity') {
            return 5000 - $rating; // Points to One Above All
        }
        
        $rankRanges = [
            'celestial' => [3700, 4600],
            'grandmaster' => [2800, 3700],
            'diamond' => [1900, 2800],
            'platinum' => [1000, 1900],
            'gold' => [700, 1000],
            'silver' => [400, 700],
            'bronze' => [0, 400]
        ];
        
        if (isset($rankRanges[$rank])) {
            $min = $rankRanges[$rank][0];
            $max = $rankRanges[$rank][1];
            $divisionSize = ($max - $min) / 3;
            $positionInRank = $rating - $min;
            
            if ($division === 'I') {
                // Next is new rank
                return $max - $rating;
            } else {
                // Next division in same rank
                $currentDivision = $division === 'III' ? 0 : 1;
                $nextDivisionStart = ($currentDivision + 1) * $divisionSize;
                return ceil($nextDivisionStart - $positionInRank);
            }
        }
        
        return 100; // Default division size
    }

    private function getRankRange($rankKey)
    {
        $ranges = [
            'bronze' => [0, 400],
            'silver' => [400, 700],
            'gold' => [700, 1000],
            'platinum' => [1000, 1900],
            'diamond' => [1900, 2800],
            'grandmaster' => [2800, 3700],
            'celestial' => [3700, 4600],
            'eternity' => [4600, 5000],
            'one_above_all' => [5000, 10000]
        ];
        
        return $ranges[$rankKey] ?? [0, 10000];
    }

    private function getAllRanks()
    {
        return [
            ['key' => 'bronze', 'name' => 'Bronze'],
            ['key' => 'silver', 'name' => 'Silver'],
            ['key' => 'gold', 'name' => 'Gold'],
            ['key' => 'platinum', 'name' => 'Platinum'],
            ['key' => 'diamond', 'name' => 'Diamond'],
            ['key' => 'grandmaster', 'name' => 'Grandmaster'],
            ['key' => 'celestial', 'name' => 'Celestial'],
            ['key' => 'eternity', 'name' => 'Eternity'],
            ['key' => 'one_above_all', 'name' => 'One Above All']
        ];
    }

    private function getRankDivisions($rankKey)
    {
        if (in_array($rankKey, ['eternity', 'one_above_all'])) {
            return null; // No divisions
        }
        
        return ['III', 'II', 'I'];
    }

    private function isDecayEligible($rank)
    {
        return in_array($rank, ['eternity', 'one_above_all']);
    }

    private function hasHeroBansUnlocked($rating)
    {
        return $rating >= 700; // Gold III+
    }

    private function hasChronoShield($rating)
    {
        return $rating <= 1000; // Gold rank and below
    }

    private function getTeamRestrictions($rank, $rating)
    {
        if ($rating <= 1000) { // Gold and below
            return 'Can team with anyone';
        }
        
        if ($rating >= 1000 && $rating < 4600) { // Gold I to Celestial
            return 'Within 3 divisions';
        }
        
        if ($rating >= 4600) { // Eternity/One Above All
            return 'Solo/Duo only, Celestial II+ within 200 points';
        }
        
        return 'Standard restrictions';
    }

    private function getPlayerGlobalRank($playerId)
    {
        return DB::table('players')
            ->where('rating', '>', function($query) use ($playerId) {
                $query->select('rating')
                      ->from('players')
                      ->where('id', $playerId);
            })
            ->count() + 1;
    }

    private function getPlayerRegionRank($playerId, $region)
    {
        return DB::table('players')
            ->where('region', $region)
            ->where('rating', '>', function($query) use ($playerId) {
                $query->select('rating')
                      ->from('players')
                      ->where('id', $playerId);
            })
            ->count() + 1;
    }

    private function getLeaderboardStats()
    {
        return [
            'total_players' => DB::table('players')->count(),
            'average_rating' => round(DB::table('players')->avg('rating'), 0),
            'highest_rating' => DB::table('players')->max('rating'),
            'one_above_all_count' => DB::table('players')->where('rating', '>=', 5000)->count(),
            'eternity_count' => DB::table('players')->whereBetween('rating', [4600, 5000])->count(),
            'celestial_plus_count' => DB::table('players')->where('rating', '>=', 3700)->count(),
            'hero_bans_unlocked' => DB::table('players')->where('rating', '>=', 700)->count(),
            'chrono_shield_eligible' => DB::table('players')->where('rating', '<=', 1000)->count()
        ];
    }

    private function getPlayerRankingHistory($playerId)
    {
        try {
            $rankingService = app(RankingService::class);
            return $rankingService->getPlayerRankingHistory($playerId, 30);
        } catch (\Exception $e) {
            Log::error('Failed to get player ranking history', [
                'player_id' => $playerId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function getPlayerCompetitiveStats($playerId)
    {
        // Get match statistics from player_match_stats table if available
        try {
            $stats = DB::table('player_match_stats as pms')
                ->join('matches as m', 'pms.match_id', '=', 'm.id')
                ->where('pms.player_id', $playerId)
                ->where('m.status', 'completed')
                ->selectRaw('
                    COUNT(*) as matches_played,
                    SUM(CASE WHEN pms.won = 1 THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN pms.won = 0 THEN 1 ELSE 0 END) as losses
                ')
                ->first();

            if ($stats && $stats->matches_played > 0) {
                $winRate = round(($stats->wins / $stats->matches_played) * 100, 1);
                
                // Calculate current win streak
                $currentStreak = $this->calculateCurrentWinStreak($playerId);
                
                return [
                    'matches_played' => $stats->matches_played,
                    'wins' => $stats->wins,
                    'losses' => $stats->losses,
                    'win_rate' => $winRate,
                    'current_win_streak' => $currentStreak,
                    'best_win_streak' => $currentStreak, // Simplified for now
                    'season_high' => 0, // Would need rating history
                    'season_low' => 0   // Would need rating history
                ];
            }
        } catch (\Exception $e) {
            // Fall back to basic data if match stats not available
        }

        return [
            'matches_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'win_rate' => 0,
            'current_win_streak' => 0,
            'best_win_streak' => 0,
            'season_high' => 0,
            'season_low' => 0
        ];
    }

    private function getPlayerAchievements($playerId, $rank, $peakRating)
    {
        $achievements = [];
        
        // Marvel Rivals rank-based achievements
        if ($rank === 'one_above_all') {
            $achievements[] = [
                'name' => 'One Above All',
                'description' => 'Reached the highest rank (Top 500)',
                'type' => 'rank',
                'icon' => '/images/achievements/one_above_all.png'
            ];
        }
        
        if ($rank === 'eternity' || $rank === 'one_above_all') {
            $achievements[] = [
                'name' => 'Eternity Reached',
                'description' => 'Achieved Eternity rank',
                'type' => 'rank',
                'icon' => '/images/achievements/eternity.png'
            ];
        }
        
        if ($peakRating >= 3700) {
            $achievements[] = [
                'name' => 'Celestial Being',
                'description' => 'Reached Celestial rank',
                'type' => 'rank',
                'icon' => '/images/achievements/celestial.png'
            ];
        }
        
        if ($peakRating >= 2800) {
            $achievements[] = [
                'name' => 'Grandmaster Status',
                'description' => 'Achieved Grandmaster rank',
                'type' => 'rank',
                'icon' => '/images/achievements/grandmaster.png'
            ];
        }
        
        return $achievements;
    }

    public function getMarvelRivalsInfo()
    {
        return response()->json([
            'data' => [
                'ranking_system' => [
                    'total_ranks' => 23,
                    'points_per_division' => 100,
                    'total_points_per_rank' => 300,
                    'starting_rank' => 'Bronze III',
                    'minimum_level' => 15,
                    'season_reset' => '9 divisions down'
                ],
                'features' => [
                    'hero_bans' => [
                        'unlock_rank' => 'Gold III',
                        'format' => '2 heroes banned per team simultaneously'
                    ],
                    'chrono_shield' => [
                        'availability' => 'Gold rank and below',
                        'function' => 'Prevents immediate rank demotion'
                    ],
                    'rank_decay' => [
                        'applies_to' => ['Eternity', 'One Above All'],
                        'description' => 'Performance-based ranking decay'
                    ]
                ],
                'team_restrictions' => [
                    'gold_and_below' => 'Can team with anyone',
                    'gold_to_celestial' => 'Within 3 divisions',
                    'eternity_oaa' => 'Solo/Duo only, Celestial II+ within 200 points'
                ],
                'ranks' => $this->getAllRanks()
            ],
            'success' => true
        ]);
    }

    // Admin methods for ranking management
    public function recalculateRankings()
    {
        try {
            // Recalculate all player rankings based on match results
            $players = DB::table('players')->get();
            $updatedCount = 0;
            
            foreach ($players as $player) {
                $newRating = $this->calculatePlayerELO($player->id);
                if ($newRating !== $player->rating) {
                    DB::table('players')
                        ->where('id', $player->id)
                        ->update([
                            'rating' => $newRating,
                            'updated_at' => now()
                        ]);
                    $updatedCount++;
                }
            }
            
            // Also recalculate team rankings
            $teams = DB::table('teams')->get();
            $updatedTeams = 0;
            
            foreach ($teams as $team) {
                $newRating = $this->calculateTeamELO($team->id);
                if ($newRating !== $team->rating) {
                    DB::table('teams')
                        ->where('id', $team->id)
                        ->update([
                            'rating' => $newRating,
                            'updated_at' => now()
                        ]);
                    $updatedTeams++;
                }
            }
            
            // Clear all ranking caches after recalculation
            $this->clearRankingCaches();
            
            return response()->json([
                'success' => true,
                'message' => 'Rankings recalculated successfully',
                'data' => [
                    'players_updated' => $updatedCount,
                    'teams_updated' => $updatedTeams,
                    'total_players' => $players->count(),
                    'total_teams' => $teams->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recalculating rankings: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function performSeasonReset()
    {
        try {
            // Marvel Rivals season reset: 9 divisions down
            $players = DB::table('players')->where('rating', '>', 0)->get();
            $resetCount = 0;
            
            foreach ($players as $player) {
                $currentRating = $player->rating;
                $resetRating = $this->applySeasonReset($currentRating);
                
                if ($resetRating !== $currentRating) {
                    DB::table('players')
                        ->where('id', $player->id)
                        ->update([
                            'rating' => $resetRating,
                            'peak_rating' => $player->rating, // Save current as peak if higher
                            'updated_at' => now()
                        ]);
                    $resetCount++;
                }
            }
            
            // Reset team ratings too
            $teams = DB::table('teams')->where('rating', '>', 0)->get();
            $resetTeams = 0;
            
            foreach ($teams as $team) {
                $currentRating = $team->rating;
                $resetRating = $this->applySeasonReset($currentRating);
                
                if ($resetRating !== $currentRating) {
                    DB::table('teams')
                        ->where('id', $team->id)
                        ->update([
                            'rating' => $resetRating,
                            'updated_at' => now()
                        ]);
                    $resetTeams++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Season reset completed successfully',
                'data' => [
                    'players_reset' => $resetCount,
                    'teams_reset' => $resetTeams,
                    'reset_formula' => '9 divisions down (Marvel Rivals standard)'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error performing season reset: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function updatePlayerRating(Request $request, $playerId)
    {
        try {
            $request->validate([
                'rating' => 'required|integer|min:0|max:10000',
                'reason' => 'required|string|max:255'
            ]);
            
            $player = DB::table('players')->where('id', $playerId)->first();
            
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }
            
            $oldRating = $player->rating;
            $newRating = $request->rating;
            
            DB::table('players')
                ->where('id', $playerId)
                ->update([
                    'rating' => $newRating,
                    'peak_rating' => max($player->peak_rating, $newRating),
                    'updated_at' => now()
                ]);
            
            // Log the rating change
            DB::table('rating_changes')->insert([
                'player_id' => $playerId,
                'old_rating' => $oldRating,
                'new_rating' => $newRating,
                'change_reason' => $request->reason,
                'changed_by' => Auth::id(),
                'created_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Player rating updated successfully',
                'data' => [
                    'player_id' => $playerId,
                    'old_rating' => $oldRating,
                    'new_rating' => $newRating,
                    'change' => $newRating - $oldRating,
                    'reason' => $request->reason
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating player rating: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function calculatePlayerELO($playerId)
    {
        // Basic ELO calculation based on match results
        // This would integrate with match results to calculate proper ELO
        $baseRating = 1000;
        
        // Get player's match results
        $matches = DB::table('matches')
            ->whereRaw('JSON_CONTAINS(team1_players, ?)', [json_encode(['id' => $playerId])])
            ->orWhereRaw('JSON_CONTAINS(team2_players, ?)', [json_encode(['id' => $playerId])])
            ->orderBy('date', 'desc')
            ->limit(100)
            ->get();
        
        $currentRating = $baseRating;
        
        foreach ($matches as $match) {
            // Simplified ELO calculation
            $isWin = $this->didPlayerWin($playerId, $match);
            $opponent_rating = $this->getOpponentAverageRating($playerId, $match);
            
            $expected = 1 / (1 + pow(10, ($opponent_rating - $currentRating) / 400));
            $actual = $isWin ? 1 : 0;
            $k_factor = 32; // Standard K-factor
            
            $currentRating = $currentRating + $k_factor * ($actual - $expected);
        }
        
        return max(0, round($currentRating));
    }
    
    /**
     * Clear all ranking caches
     */
    public function clearRankingCaches()
    {
        try {
            // Clear all player ranking caches
            $cacheKeys = [];
            
            // Get all possible cache keys (this is simplified - in production you might want to use cache tags)
            foreach (['all', 'na', 'eu', 'asia', 'china', 'oce'] as $region) {
                foreach (['all', 'vanguard', 'duelist', 'strategist'] as $role) {
                    for ($page = 1; $page <= 10; $page++) { // Assume max 10 pages
                        $cacheKey = 'player_rankings_' . md5(serialize([
                            'rank' => 'all',
                            'region' => $region,
                            'role' => $role,
                            'search' => '',
                            'page' => $page
                        ]));
                        Cache::forget($cacheKey);
                    }
                }
            }
            
            // Clear team ranking caches
            foreach (['all', 'na', 'eu', 'asia', 'china', 'oce'] as $region) {
                foreach (['rating', 'earnings', 'wins', 'winrate'] as $sort) {
                    for ($page = 1; $page <= 10; $page++) {
                        $cacheKey = 'team_rankings_' . md5(serialize([
                            'region' => $region,
                            'sort' => $sort,
                            'search' => '',
                            'page' => $page
                        ]));
                        Cache::forget($cacheKey);
                    }
                }
            }
            
            // Clear related caches
            Cache::forget('ranking_stats');
            Cache::forget('leaderboard_stats');
            Cache::forget('rank_distribution');
            
            return response()->json([
                'success' => true,
                'message' => 'All ranking caches cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing caches: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function calculateTeamELO($teamId)
    {
        // Calculate team ELO based on average player ratings and team performance
        $teamPlayers = DB::table('players')
            ->where('team_id', $teamId)
            ->where('status', 'active')
            ->get();
        
        if ($teamPlayers->isEmpty()) {
            return 1000; // Default rating
        }
        
        $averagePlayerRating = $teamPlayers->avg('rating');
        
        // Get team's recent match performance
        $teamMatches = DB::table('matches')
            ->where(function($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })
            ->where('status', 'completed')
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();
        
        $teamPerformanceModifier = 0;
        foreach ($teamMatches as $match) {
            if ($this->didTeamWin($teamId, $match)) {
                $teamPerformanceModifier += 10;
            } else {
                $teamPerformanceModifier -= 8;
            }
        }
        
        return max(0, round($averagePlayerRating + $teamPerformanceModifier));
    }
    
    private function applySeasonReset($rating)
    {
        // Marvel Rivals: 9 divisions down
        // Each division is approximately 100 points, so 9 divisions = 900 points
        return max(0, $rating - 900);
    }
    
    private function didPlayerWin($playerId, $match)
    {
        // Simplified win detection - would need proper implementation based on match structure
        if ($match->team1_score > $match->team2_score) {
            return $this->isPlayerInTeam($playerId, $match->team1_id);
        } else {
            return $this->isPlayerInTeam($playerId, $match->team2_id);
        }
    }
    
    private function didTeamWin($teamId, $match)
    {
        if ($match->team1_id == $teamId) {
            return $match->team1_score > $match->team2_score;
        } else if ($match->team2_id == $teamId) {
            return $match->team2_score > $match->team1_score;
        }
        return false;
    }
    
    private function isPlayerInTeam($playerId, $teamId)
    {
        return DB::table('players')
            ->where('id', $playerId)
            ->where('team_id', $teamId)
            ->exists();
    }
    
    private function getOpponentAverageRating($playerId, $match)
    {
        // Simplified - get opponent team's average rating
        $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
        
        $opponentTeamId = $match->team1_id == $playerTeamId ? $match->team2_id : $match->team1_id;
        
        return DB::table('players')
            ->where('team_id', $opponentTeamId)
            ->where('status', 'active')
            ->avg('rating') ?? 1000;
    }
    
    private function calculateCurrentWinStreak($playerId)
    {
        try {
            // Get recent match results for this player in chronological order
            $recentMatches = DB::table('player_match_stats as pms')
                ->join('matches as m', 'pms.match_id', '=', 'm.id')
                ->where('pms.player_id', $playerId)
                ->where('m.status', 'completed')
                ->orderBy('m.created_at', 'desc')
                ->limit(10)
                ->pluck('pms.won')
                ->toArray();
            
            $streak = 0;
            foreach ($recentMatches as $won) {
                if ($won == 1) {
                    $streak++;
                } else {
                    break; // Streak broken by a loss
                }
            }
            
            return $streak;
        } catch (\Exception $e) {
            return 0;
        }
    }
}