<?php
namespace App\Http\Controllers;

use App\Services\OptimizedQueryService;
use App\Models\Player;
use App\Helpers\ImageHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizedPlayerController extends Controller
{
    protected OptimizedQueryService $queryService;

    public function __construct(OptimizedQueryService $queryService)
    {
        $this->queryService = $queryService;
    }

    /**
     * Optimized players index with proper pagination and caching
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'role' => $request->get('role'),
                'team' => $request->get('team'),
                'region' => $request->get('region'),
                'search' => $request->get('search'),
                'status' => $request->get('status', 'active')
            ];

            $perPage = min((int) $request->get('per_page', 100), 200); // Limit max per page
            $players = $this->queryService->getPlayers($filters, $perPage);

            // Transform data efficiently
            $formattedPlayers = $players->getCollection()->map(function($player) {
                $avatarInfo = ImageHelper::getPlayerAvatar($player->avatar, $player->username);
                $teamLogoInfo = $player->team && $player->team->logo ? 
                    ImageHelper::getTeamLogo($player->team->logo, $player->team->name) : null;
                
                return [
                    'id' => $player->id,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'avatar' => $avatarInfo['url'],
                    'avatar_exists' => $avatarInfo['exists'],
                    'role' => $player->role,
                    'main_hero' => $player->main_hero,
                    'rating' => $player->rating ?? 1000,
                    'rank' => $this->getRankByRating($player->rating ?? 1000),
                    'division' => $this->getDivisionByRating($player->rating ?? 1000),
                    'country' => $player->country,
                    'flag' => $this->getCountryFlag($player->country),
                    'age' => $player->age,
                    'status' => $player->status ?? 'active',
                    'team' => $player->team ? [
                        'id' => $player->team->id,
                        'name' => $player->team->name,
                        'short_name' => $player->team->short_name,
                        'logo' => $teamLogoInfo ? $teamLogoInfo['url'] : '/images/team-placeholder.svg',
                        'logo_exists' => $teamLogoInfo ? $teamLogoInfo['exists'] : false
                    ] : null
                ];
            });

            return response()->json([
                'data' => $formattedPlayers,
                'pagination' => [
                    'current_page' => $players->currentPage(),
                    'per_page' => $players->perPage(),
                    'total' => $players->total(),
                    'last_page' => $players->lastPage(),
                    'has_more_pages' => $players->hasMorePages()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching players', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching players',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Optimized player detail with controlled data loading
     */
    public function show($id)
    {
        try {
            $player = $this->queryService->getPlayerDetail($id);
            
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Get player statistics with caching
            $stats = $this->queryService->getPlayerStats($id);
            
            $avatarInfo = ImageHelper::getPlayerAvatar($player->avatar, $player->username);
            $teamLogoInfo = $player->team && $player->team->logo ? 
                ImageHelper::getTeamLogo($player->team->logo, $player->team->name) : null;
            
            $formattedPlayer = [
                'id' => $player->id,
                'username' => $player->username,
                'real_name' => $player->real_name,
                'avatar' => $avatarInfo['url'],
                'avatar_exists' => $avatarInfo['exists'],
                'role' => $player->role,
                'main_hero' => $player->main_hero,
                'alt_heroes' => $player->alt_heroes ?? [],
                'rating' => $player->rating ?? 1000,
                'rank' => $this->getRankByRating($player->rating ?? 1000),
                'division' => $this->getDivisionByRating($player->rating ?? 1000),
                'country' => $player->country,
                'flag' => $this->getCountryFlag($player->country),
                'age' => $player->age,
                'earnings' => $player->earnings ?? '$0',
                'social_media' => $player->social_media ?? [],
                'biography' => $player->biography,
                'status' => $player->status ?? 'active',
                
                // Current team information
                'team' => $player->team ? [
                    'id' => $player->team->id,
                    'name' => $player->team->name,
                    'short_name' => $player->team->short_name,
                    'logo' => $teamLogoInfo ? $teamLogoInfo['url'] : '/images/team-placeholder.svg',
                    'region' => $player->team->region
                ] : null,
                
                // Team history (limited to prevent over-fetching)
                'team_history' => $player->teamHistory->map(function($history) {
                    return [
                        'from_team' => $history->fromTeam ? [
                            'name' => $history->fromTeam->name,
                            'short_name' => $history->fromTeam->short_name
                        ] : null,
                        'to_team' => $history->toTeam ? [
                            'name' => $history->toTeam->name,
                            'short_name' => $history->toTeam->short_name
                        ] : null,
                        'change_date' => $history->change_date,
                        'change_type' => $history->change_type
                    ];
                }),
                
                // Statistics
                'statistics' => [
                    'matches_played' => $stats->matches_played ?? 0,
                    'avg_rating' => $stats->avg_rating ? round($stats->avg_rating, 2) : 0,
                    'avg_combat_score' => $stats->avg_combat_score ? round($stats->avg_combat_score, 1) : 0,
                    'avg_kda' => $stats->avg_kda ? round($stats->avg_kda, 2) : 0,
                    'total_eliminations' => $stats->total_eliminations ?? 0,
                    'total_deaths' => $stats->total_deaths ?? 0,
                    'total_assists' => $stats->total_assists ?? 0
                ]
            ];

            return response()->json([
                'data' => $formattedPlayer,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching player detail', [
                'player_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player details'
            ], 500);
        }
    }

    /**
     * Get player performance data with date filtering
     */
    public function performance($id, Request $request)
    {
        try {
            $dateRange = [];
            if ($request->has('from')) {
                $dateRange['from'] = $request->get('from');
            }
            if ($request->has('to')) {
                $dateRange['to'] = $request->get('to');
            }

            $stats = $this->queryService->getPlayerStats($id, $dateRange);
            
            // Get additional performance metrics with caching
            $cacheKey = "player_performance_{$id}_" . md5(serialize($dateRange));
            
            $performance = Cache::remember($cacheKey, 900, function () use ($id, $dateRange) {
                $query = DB::table('match_player_stats as mps')
                    ->join('matches as m', 'mps.match_id', '=', 'm.id')
                    ->where('mps.player_id', $id)
                    ->where('m.status', 'completed');

                if (!empty($dateRange['from'])) {
                    $query->where('m.scheduled_at', '>=', $dateRange['from']);
                }
                if (!empty($dateRange['to'])) {
                    $query->where('m.scheduled_at', '<=', $dateRange['to']);
                }

                // Get performance by hero
                $heroStats = $query->select('mps.hero')
                    ->selectRaw('
                        COUNT(*) as matches,
                        AVG(mps.performance_rating) as avg_rating,
                        AVG(mps.combat_score) as avg_acs,
                        AVG(mps.kda) as avg_kda
                    ')
                    ->whereNotNull('mps.hero')
                    ->groupBy('mps.hero')
                    ->orderBy('matches', 'desc')
                    ->limit(10)
                    ->get();

                // Get recent form (last 10 matches)
                $recentMatches = $query->select([
                        'm.scheduled_at', 
                        'mps.performance_rating', 
                        'mps.hero',
                        'm.team1_score',
                        'm.team2_score'
                    ])
                    ->orderBy('m.scheduled_at', 'desc')
                    ->limit(10)
                    ->get();

                return [
                    'hero_stats' => $heroStats,
                    'recent_form' => $recentMatches
                ];
            });

            return response()->json([
                'data' => [
                    'overall_stats' => $stats,
                    'hero_performance' => $performance['hero_stats'],
                    'recent_form' => $performance['recent_form']
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching player performance', [
                'player_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player performance data'
            ], 500);
        }
    }

    /**
     * Update player with cache invalidation
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'username' => 'sometimes|string|max:255|unique:players,username,' . $id,
                'real_name' => 'sometimes|nullable|string|max:255',
                'role' => 'sometimes|in:Duelist,Tank,Support,Strategist,Vanguard',
                'main_hero' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'age' => 'sometimes|nullable|integer|min:16|max:50',
                'team_id' => 'sometimes|nullable|exists:teams,id',
                'social_media' => 'sometimes|array',
                'biography' => 'sometimes|nullable|string|max:1000',
                'status' => 'sometimes|in:active,inactive,retired'
            ]);

            $player = Player::findOrFail($id);
            $player->update($validated);
            
            // Clear related caches
            $this->queryService->clearPlayerCaches($id);
            
            return response()->json([
                'data' => $player,
                'success' => true,
                'message' => 'Player updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating player', [
                'player_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating player'
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getCountryFlag($country)
    {
        $flagMapping = [
            'United States' => '🇺🇸', 'USA' => '🇺🇸',
            'Canada' => '🇨🇦',
            'South Korea' => '🇰🇷', 'Korea' => '🇰🇷',
            'China' => '🇨🇳',
            'Japan' => '🇯🇵',
            'United Kingdom' => '🇬🇧', 'UK' => '🇬🇧',
            'Germany' => '🇩🇪',
            'France' => '🇫🇷',
            'Brazil' => '🇧🇷',
            'Australia' => '🇦🇺'
        ];

        return $flagMapping[$country] ?? '🏳️';
    }

    private function getRankByRating($rating)
    {
        if ($rating >= 2000) return 1;
        if ($rating >= 1800) return 50;
        if ($rating >= 1600) return 100;
        if ($rating >= 1400) return 250;
        if ($rating >= 1200) return 500;
        if ($rating >= 1000) return 1000;
        return 9999;
    }

    private function getDivisionByRating($rating)
    {
        if ($rating >= 2000) return 'Champion';
        if ($rating >= 1800) return 'Grandmaster';
        if ($rating >= 1600) return 'Master';
        if ($rating >= 1400) return 'Diamond';
        if ($rating >= 1200) return 'Platinum';
        if ($rating >= 1000) return 'Gold';
        return 'Silver';
    }
}