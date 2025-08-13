<?php
namespace App\Http\Controllers;

use App\Services\OptimizedQueryService;
use App\Models\Team;
use App\Helpers\ImageHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizedTeamController extends Controller
{
    protected OptimizedQueryService $queryService;

    public function __construct(OptimizedQueryService $queryService)
    {
        $this->queryService = $queryService;
    }

    /**
     * Optimized teams index with proper pagination and caching
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'region' => $request->get('region'),
                'platform' => $request->get('platform'),
                'search' => $request->get('search'),
                'status' => $request->get('status', 'active')
            ];

            $perPage = min((int) $request->get('per_page', 50), 100); // Limit max per page
            $teams = $this->queryService->getTeams($filters, $perPage);

            // Transform data efficiently
            $formattedTeams = $teams->getCollection()->map(function($team) {
                $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
                
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $logoInfo['url'],
                    'logo_exists' => $logoInfo['exists'],
                    'region' => $team->region,
                    'platform' => $team->platform ?? 'PC',
                    'country' => $team->country,
                    'flag' => $this->getCountryFlag($team->country),
                    'rating' => $team->rating ?? 1000,
                    'rank' => $team->rank ?? 999,
                    'win_rate' => $team->win_rate ?? 0,
                    'points' => $team->points ?? 0,
                    'record' => $team->record ?? '0-0',
                    'peak' => $team->peak ?? $team->rating ?? 1000,
                    'streak' => $team->streak ?? 'N/A',
                    'founded' => $team->founded,
                    'captain' => $team->captain,
                    'coach_name' => $team->coach_name,
                    'earnings' => $team->earnings ?? '$0',
                    'game' => $team->game ?? 'Marvel Rivals',
                    'division' => $team->division ?? $this->getDivisionByRating($team->rating ?? 1000),
                    'player_count' => $team->player_count ?? 0
                ];
            });

            return response()->json([
                'data' => $formattedTeams,
                'pagination' => [
                    'current_page' => $teams->currentPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total(),
                    'last_page' => $teams->lastPage(),
                    'has_more_pages' => $teams->hasMorePages()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching teams', [
                'error' => $e->getMessage(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching teams',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Optimized team detail with controlled data loading
     */
    public function show($id)
    {
        try {
            $team = $this->queryService->getTeamDetail($id);
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            // Get additional data with caching
            $recentMatches = $this->queryService->getTeamMatches($id, 10);
            
            $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
            
            $formattedTeam = [
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'logo' => $logoInfo['url'],
                'logo_exists' => $logoInfo['exists'],
                'region' => $team->region,
                'country' => $team->country,
                'flag' => $this->getCountryFlag($team->country),
                'rating' => $team->rating ?? 1000,
                'rank' => $team->rank ?? 999,
                'win_rate' => $team->win_rate ?? 0,
                'peak' => $team->peak ?? $team->rating ?? 1000,
                'streak' => $team->streak ?? 'N/A',
                'founded' => $team->founded,
                'captain' => $team->captain,
                'coach' => $team->coach,
                'coach_name' => $team->coach_name,
                'website' => $team->website,
                'earnings' => $team->earnings ?? '$0',
                'social_media' => $team->social_media ?? [],
                'achievements' => $team->achievements ?? [],
                'recent_form' => $team->recent_form ?? [],
                
                // Players with optimized loading
                'roster' => $team->players->map(function($player) {
                    $avatarInfo = ImageHelper::getPlayerAvatar($player->avatar, $player->username);
                    return [
                        'id' => $player->id,
                        'username' => $player->username,
                        'real_name' => $player->real_name,
                        'role' => $player->role,
                        'avatar' => $avatarInfo['url'],
                        'rating' => $player->rating ?? 1000
                    ];
                }),
                
                // Recent matches (limited to prevent over-fetching)
                'recent_matches' => $recentMatches->take(5)->map(function($match) {
                    return [
                        'id' => $match->id,
                        'opponent' => $match->team1_id === $this->team->id ? 
                            ['name' => $match->team2->name, 'logo' => $match->team2->logo] :
                            ['name' => $match->team1->name, 'logo' => $match->team1->logo],
                        'score' => [$match->team1_score, $match->team2_score],
                        'status' => $match->status,
                        'scheduled_at' => $match->scheduled_at,
                        'event' => $match->event ? $match->event->name : null
                    ];
                })
            ];

            return response()->json([
                'data' => $formattedTeam,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching team detail', [
                'team_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team details'
            ], 500);
        }
    }

    /**
     * Optimized team matches endpoint with pagination
     */
    public function matches($id, Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 20), 50);
            $matches = $this->queryService->getTeamMatches($id, $perPage);
            
            $formattedMatches = $matches->getCollection()->map(function($match) use ($id) {
                $isTeam1 = $match->team1_id == $id;
                $opponent = $isTeam1 ? $match->team2 : $match->team1;
                
                return [
                    'id' => $match->id,
                    'opponent' => [
                        'id' => $opponent->id,
                        'name' => $opponent->name,
                        'short_name' => $opponent->short_name,
                        'logo' => $opponent->logo
                    ],
                    'score' => [$match->team1_score, $match->team2_score],
                    'status' => $match->status,
                    'format' => $match->format,
                    'scheduled_at' => $match->scheduled_at,
                    'event' => $match->event ? [
                        'id' => $match->event->id,
                        'name' => $match->event->name
                    ] : null,
                    'result' => $this->getMatchResult($match, $id)
                ];
            });

            return response()->json([
                'data' => $formattedMatches,
                'pagination' => [
                    'current_page' => $matches->currentPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total(),
                    'last_page' => $matches->lastPage()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching team matches', [
                'team_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team matches'
            ], 500);
        }
    }

    /**
     * Update team with cache invalidation
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'short_name' => 'sometimes|string|max:10',
                'region' => 'sometimes|string|max:10',
                'country' => 'sometimes|string|max:100',
                'website' => 'sometimes|nullable|url',
                'social_media' => 'sometimes|array',
                // Add other validation rules as needed
            ]);

            $team = Team::findOrFail($id);
            $team->update($validated);
            
            // Clear related caches
            $this->queryService->clearTeamCaches($id);
            
            return response()->json([
                'data' => $team,
                'success' => true,
                'message' => 'Team updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating team', [
                'team_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating team'
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getCountryFlag($country)
    {
        $flagMapping = [
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'South Korea' => '🇰🇷',
            'China' => '🇨🇳',
            'Japan' => '🇯🇵',
            'United Kingdom' => '🇬🇧',
            'Germany' => '🇩🇪',
            'France' => '🇫🇷',
            'Brazil' => '🇧🇷',
            'Australia' => '🇦🇺'
        ];

        return $flagMapping[$country] ?? '🏳️';
    }

    private function getDivisionByRating($rating)
    {
        if ($rating >= 1800) return 'Grandmaster';
        if ($rating >= 1600) return 'Master';
        if ($rating >= 1400) return 'Diamond';
        if ($rating >= 1200) return 'Platinum';
        if ($rating >= 1000) return 'Gold';
        return 'Silver';
    }

    private function getMatchResult($match, $teamId)
    {
        if ($match->status !== 'completed') {
            return null;
        }

        $isTeam1 = $match->team1_id == $teamId;
        $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
        $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;

        if ($teamScore > $opponentScore) return 'win';
        if ($teamScore < $opponentScore) return 'loss';
        return 'draw';
    }
}