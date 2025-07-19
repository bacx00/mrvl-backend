<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RankingController extends Controller
{
    public function index(Request $request)
    {
        try {
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

            $players = $query->paginate(50);

            // Add ranking position and format data
            $playersData = collect($players->items())->map(function($player, $index) use ($players) {
                $globalRank = ($players->currentPage() - 1) * $players->perPage() + $index + 1;
                $rank = $this->getRankByRating($player->rating);
                $division = $this->getDivisionByRating($player->rating);
                
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
                        'peak_rating' => $player->peak_rating,
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

            return response()->json([
                'data' => $playersData,
                'pagination' => [
                    'current_page' => $players->currentPage(),
                    'last_page' => $players->lastPage(),
                    'per_page' => $players->perPage(),
                    'total' => $players->total()
                ],
                'stats' => $stats,
                'success' => true
            ]);

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
                    'peak_rating' => $player->peak_rating,
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
                'achievements' => $this->getPlayerAchievements($playerId, $rank, $player->peak_rating),
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
        return collect([]); // Placeholder - would come from rating_changes table
    }

    private function getPlayerCompetitiveStats($playerId)
    {
        // This would come from match data
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
}