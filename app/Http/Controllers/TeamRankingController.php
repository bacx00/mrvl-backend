<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\EventStanding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TeamRankingController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Create cache key based on request parameters
            $cacheKey = 'team_rankings_' . md5(serialize([
                'region' => $request->region,
                'sort' => $request->sort,
                'search' => $request->search,
                'page' => $request->page ?? 1
            ]));
            
            // Check cache first (cache for 10 minutes)
            if ($cachedData = Cache::get($cacheKey)) {
                return response()->json($cachedData);
            }
            $query = Team::with(['players' => function($q) {
                $q->where('status', 'active');
            }])
            ->where('status', 'active')
            ->select([
                'id', 'name', 'short_name', 'logo', 'region', 'country',
                'rating', 'earnings', 'wins', 'losses', 'founded', 'social_media'
            ]);

            // Filter by region
            if ($request->has('region') && $request->region !== 'all') {
                // Map frontend regions to database regions
                $regionMap = [
                    'na' => ['NA', 'AMERICAS'],
                    'americas' => ['NA', 'AMERICAS'],
                    'eu' => ['EU', 'EMEA'],
                    'emea' => ['EU', 'EMEA'], 
                    'asia' => ['ASIA'],
                    'china' => ['CN'],
                    'sa' => 'SA',
                    'oce' => 'OCE',
                    'oceania' => 'OCE',
                    'mena' => 'MENA',
                    'ca' => 'CA'
                ];

                $mappedRegion = $regionMap[strtolower($request->region)] ?? $request->region;
                
                if (is_array($mappedRegion)) {
                    $query->whereIn('region', $mappedRegion);
                } else {
                    $query->where('region', $mappedRegion);
                }
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('short_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhereHas('players', function($playerQuery) use ($searchTerm) {
                          $playerQuery->where('username', 'LIKE', "%{$searchTerm}%")
                                      ->orWhere('real_name', 'LIKE', "%{$searchTerm}%");
                      });
                });
            }

            // Sort by different criteria
            $sortBy = $request->get('sort', 'rating');
            switch ($sortBy) {
                case 'earnings':
                    $query->orderBy('earnings', 'desc');
                    break;
                case 'wins':
                    $query->orderBy('wins', 'desc');
                    break;
                case 'winrate':
                    $query->orderByRaw('CASE WHEN (wins + losses) > 0 THEN wins / (wins + losses) ELSE 0 END DESC');
                    break;
                default:
                    $query->orderBy('rating', 'desc');
            }

            $teams = $query->paginate(20);

            // Add ranking position and additional stats
            $teamsData = collect($teams->items())->map(function($team, $index) use ($teams) {
                $globalRank = ($teams->currentPage() - 1) * $teams->perPage() + $index + 1;
                
                // Get tournament placements
                $placements = EventStanding::where('team_id', $team->id)
                    ->where('position', '<=', 3)
                    ->count();
                
                $totalMatches = $team->wins + $team->losses;
                $winRate = $totalMatches > 0 ? round(($team->wins / $totalMatches) * 100, 1) : 0;
                
                return [
                    'rank' => $globalRank,
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'country' => $team->country,
                    'rating' => $team->rating,
                    'earnings' => $team->earnings,
                    'wins' => $team->wins,
                    'losses' => $team->losses,
                    'win_rate' => $winRate,
                    'founded' => $team->founded,
                    'active_roster' => $team->players->count(),
                    'tournament_wins' => $placements,
                    'social_media' => $team->social_media
                ];
            });

            // Get region statistics
            $regionStats = $this->getRegionStats();

            $responseData = [
                'success' => true,
                'data' => $teamsData,
                'pagination' => [
                    'current_page' => $teams->currentPage(),
                    'last_page' => $teams->lastPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total()
                ],
                'region_stats' => $regionStats,
                'available_regions' => $this->getAvailableRegions()
            ];
            
            // Cache the response for 10 minutes
            Cache::put($cacheKey, $responseData, 600);

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team rankings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($teamId)
    {
        try {
            $team = Team::with(['players' => function($q) {
                $q->where('status', 'active');
            }])->find($teamId);

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            // Get global and regional rank
            $globalRank = Team::where('rating', '>', $team->rating)->count() + 1;
            $regionRank = Team::where('region', $team->region)
                ->where('rating', '>', $team->rating)
                ->count() + 1;

            // Get tournament history
            $tournaments = EventStanding::with('event')
                ->where('team_id', $team->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($standing) {
                    return [
                        'event' => $standing->event->name,
                        'position' => $standing->position,
                        'prize_won' => $standing->prize_won,
                        'date' => $standing->event->end_date
                    ];
                });

            $totalMatches = $team->wins + $team->losses;
            $winRate = $totalMatches > 0 ? round(($team->wins / $totalMatches) * 100, 1) : 0;

            $teamData = [
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'logo' => $team->logo,
                'region' => $team->region,
                'country' => $team->country,
                'founded' => $team->founded,
                'ranking' => [
                    'global_rank' => $globalRank,
                    'region_rank' => $regionRank,
                    'rating' => $team->rating,
                    'total_teams_global' => Team::count(),
                    'total_teams_region' => Team::where('region', $team->region)->count()
                ],
                'stats' => [
                    'earnings' => $team->earnings,
                    'wins' => $team->wins,
                    'losses' => $team->losses,
                    'win_rate' => $winRate,
                    'matches_played' => $totalMatches
                ],
                'roster' => $team->players->map(function($player) {
                    return [
                        'id' => $player->id,
                        'username' => $player->username,
                        'real_name' => $player->real_name,
                        'role' => $player->role,
                        'main_hero' => $player->main_hero,
                        'rating' => $player->rating
                    ];
                }),
                'tournament_history' => $tournaments,
                'social_media' => $team->social_media
            ];

            return response()->json([
                'success' => true,
                'data' => $teamData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team details: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getRegionStats()
    {
        $stats = [];
        $regions = $this->getAvailableRegions();

        foreach ($regions as $region) {
            $regionQuery = Team::where('status', 'active');
            
            if ($region['value'] === 'asia') {
                $regionQuery->whereIn('region', ['ASIA', 'CN']);
            } else {
                $regionQuery->where('region', $region['db_value']);
            }

            $teamCount = $regionQuery->count();
            $avgRating = $regionQuery->avg('rating');
            $totalEarnings = $regionQuery->sum('earnings');
            $topTeam = $regionQuery->orderBy('rating', 'desc')->first();

            $stats[] = [
                'region' => $region['value'],
                'name' => $region['label'],
                'team_count' => $teamCount,
                'avg_rating' => round($avgRating ?? 0),
                'total_earnings' => $totalEarnings,
                'top_team' => $topTeam ? [
                    'name' => $topTeam->name,
                    'rating' => $topTeam->rating
                ] : null
            ];
        }

        return $stats;
    }

    private function getAvailableRegions()
    {
        return [
            ['value' => 'all', 'label' => 'All Regions', 'db_value' => null],
            ['value' => 'na', 'label' => 'North America', 'db_value' => 'NA'],
            ['value' => 'eu', 'label' => 'Europe', 'db_value' => 'EU'],
            ['value' => 'asia', 'label' => 'Asia', 'db_value' => ['ASIA', 'CN']],
            ['value' => 'sa', 'label' => 'South America', 'db_value' => 'SA'],
            ['value' => 'oce', 'label' => 'Oceania', 'db_value' => 'OCE'],
            ['value' => 'mena', 'label' => 'Middle East', 'db_value' => 'MENA']
        ];
    }

    public function getTopEarners()
    {
        try {
            $teams = Team::where('earnings', '>', 0)
                ->orderBy('earnings', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'short_name', 'logo', 'region', 'earnings']);

            return response()->json([
                'success' => true,
                'data' => $teams
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching top earners'
            ], 500);
        }
    }
}