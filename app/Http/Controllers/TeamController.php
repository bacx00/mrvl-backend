<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Mention;
use App\Helpers\ImageHelper;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('teams as t')
                ->select([
                    't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.platform', 
                    't.game', 't.division', 't.country', 't.rating', 't.rank', 't.win_rate', 
                    't.points', 't.record', 't.peak', 't.streak', 't.founded', 't.captain', 
                    't.coach', 't.coach_name', 't.coach_nationality', 't.coach_social_media', 
                    't.website', 't.earnings', 't.social_media', 't.achievements',
                    't.recent_form', 't.player_count'
                ]);

            if ($request->region && $request->region !== 'all') {
                $query->where('t.region', $request->region);
            }

            // Add platform filtering for Marvel Rivals
            if ($request->platform && $request->platform !== 'all') {
                $query->where('t.platform', $request->platform);
            }

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('t.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('t.short_name', 'LIKE', "%{$request->search}%");
                });
            }

            $teams = $query->orderBy('t.rating', 'desc')->limit(50)->get();

            // Transform to Marvel Rivals esports format
            $formattedTeams = $teams->map(function($team) {
                $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
                
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $logoInfo['url'],
                    'logo_exists' => $logoInfo['exists'],
                    'logo_fallback' => $logoInfo['fallback'],
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
                    'coach' => $team->coach,
                    'coach_name' => $team->coach_name,
                    'coach_nationality' => $team->coach_nationality,
                    'coach_social_media' => $team->coach_social_media ? json_decode($team->coach_social_media, true) : [],
                    'website' => $team->website,
                    'earnings' => $team->earnings ?? '$0',
                    'social_media' => $team->social_media ? json_decode($team->social_media, true) : [],
                    'achievements' => $team->achievements ? json_decode($team->achievements, true) : [],
                    // Marvel Rivals specific data
                    'game' => $team->game ?? 'Marvel Rivals',
                    'division' => $team->division ?? $this->getDivisionByRating($team->rating ?? 1000),
                    'recent_form' => $team->recent_form ? json_decode($team->recent_form, true) : $this->generateRecentForm($team->id),
                    'player_count' => $team->player_count ?? $this->getPlayerCount($team->id)
                ];
            });

            return response()->json([
                'data' => $formattedTeams,
                'total' => $formattedTeams->count(),
                'success' => true
            ]);

        } catch (QueryException $e) {
            Log::error('Database error fetching teams', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'request_params' => request()->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Database connection issue. Please try again later.',
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        } catch (Exception $e) {
            Log::error('Error fetching teams', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => request()->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to load teams. Please try again later.',
                'error_code' => 'TEAMS_FETCH_ERROR'
            ], 500);
        }
    }

    public function show($teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            // Get ALL team data for complete VLR.gg-style profile
            
            // Current roster (active players)
            $currentRoster = DB::table('players')
                ->where('team_id', $teamId)
                ->where('status', 'active')
                ->select(['id', 'name', 'username', 'role', 'avatar', 'rating', 'main_hero', 'country', 'age', 'real_name'])
                ->orderBy('role')
                ->get();

            // Inactive/Former players
            $formerPlayers = DB::table('players')
                ->where('team_id', $teamId)
                ->where('status', '!=', 'active')
                ->select(['id', 'name', 'username', 'role', 'avatar', 'rating', 'main_hero', 'status'])
                ->get();

            // Recent results (last 20 matches)
            $recentMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.status', 'm.team1_score', 'm.team2_score', 'm.scheduled_at', 'm.format',
                    'm.maps_data', 'm.team1_id as team1_id', 'm.team2_id as team2_id',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo'
                ])
                ->where(function($query) use ($teamId) {
                    $query->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->where('m.status', 'completed')
                ->orderBy('m.scheduled_at', 'desc')
                ->limit(20)
                ->get();

            // Upcoming matches
            $upcomingMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.status', 'm.scheduled_at', 'm.format', 'm.stream_url',
                    'm.team1_id as team1_id', 'm.team2_id as team2_id',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo'
                ])
                ->where(function($query) use ($teamId) {
                    $query->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->whereIn('m.status', ['upcoming', 'live'])
                ->orderBy('m.scheduled_at', 'asc')
                ->get();

            // Event placements/achievements
            $eventPlacements = DB::table('events as e')
                ->leftJoin('matches as m', 'e.id', '=', 'm.event_id')
                ->select(['e.id', 'e.name', 'e.type', 'e.start_date', 'e.end_date', 'e.prize_pool'])
                ->where(function($query) use ($teamId) {
                    $query->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->groupBy(['e.id', 'e.name', 'e.type', 'e.start_date', 'e.end_date', 'e.prize_pool'])
                ->orderBy('e.start_date', 'desc')
                ->get();

            // Calculate comprehensive stats
            $stats = $this->calculateDetailedTeamStats($teamId, $recentMatches);

            // Rating history (simulated for now)
            $ratingHistory = $this->generateRatingHistory($team->rating ?? 1000);

            // Get logo info using ImageHelper
            $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
            
            // Format roster data with proper image URLs using ImageHelper
            $formattedCurrentRoster = collect($currentRoster)->map(function($player) {
                $avatarInfo = ImageHelper::getPlayerAvatar($player->avatar, $player->name);
                return (object) array_merge((array) $player, [
                    'avatar' => $avatarInfo['url'],
                    'avatar_exists' => $avatarInfo['exists'],
                    'avatar_fallback' => $avatarInfo['fallback']
                ]);
            });
            
            $formattedFormerPlayers = collect($formerPlayers)->map(function($player) {
                $avatarInfo = ImageHelper::getPlayerAvatar($player->avatar, $player->name);
                return (object) array_merge((array) $player, [
                    'avatar' => $avatarInfo['url'],
                    'avatar_exists' => $avatarInfo['exists'],
                    'avatar_fallback' => $avatarInfo['fallback']
                ]);
            });
            
            $formattedTeam = [
                // Basic team info
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'logo' => $logoInfo['url'],
                'logo_exists' => $logoInfo['exists'],
                'logo_fallback' => $logoInfo['fallback'],
                'region' => $team->region,
                'country' => $team->country,
                'flag' => $team->flag ?: $this->getCountryFlag($team->country),
                'founded' => $team->founded,
                'captain' => $team->captain,
                'coach' => $team->coach,
                'website' => $team->website,
                'social_media' => $team->social_media ? json_decode($team->social_media, true) : [],
                
                // Performance metrics
                'rating' => $team->rating ?? 1000,
                'rank' => $team->rank ?? 999,
                'peak_rating' => $team->peak ?? $team->rating ?? 1000,
                'division' => $this->getDivisionByRating($team->rating ?? 1000),
                'earnings' => $team->earnings ?? '$0',
                
                // Comprehensive stats
                'stats' => $stats,
                'rating_history' => $ratingHistory,
                
                // Roster information
                'current_roster' => $formattedCurrentRoster,
                'former_players' => $formattedFormerPlayers,
                'roster_changes' => $this->getRecentRosterChanges($teamId),
                
                // Match data
                'recent_results' => $this->formatMatchResults($recentMatches, $teamId),
                'upcoming_matches' => $this->formatUpcomingMatches($upcomingMatches, $teamId),
                
                // Tournament data
                'event_placements' => $eventPlacements,
                'achievements' => $team->achievements ? json_decode($team->achievements, true) : [],
                
                // Meta information
                'team_composition' => $this->analyzeTeamComposition($formattedCurrentRoster),
                'hero_pool' => $this->getTeamHeroPool($formattedCurrentRoster),
                'form' => $this->calculateCurrentForm($recentMatches, $teamId),
                
                // Marvel Rivals specific
                'game' => 'Marvel Rivals',
                'last_active' => $this->getLastActiveDate($recentMatches)
            ];

            // COMPATIBILITY FIX: Ensure both 'players' and 'current_roster' are available for frontend compatibility
            $formattedTeam['players'] = $formattedCurrentRoster; // Add players field for frontend compatibility

            return response()->json([
                'data' => $formattedTeam,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rankings(Request $request)
    {
        try {
            $region = $request->get('region', 'all');
            $platform = $request->get('platform', 'all');
            
            $query = DB::table('teams')
                ->select(['id', 'name', 'short_name', 'logo', 'region', 'platform', 'rating', 'rank', 'win_rate', 'record', 'division', 'recent_form']);
            
            if ($region !== 'all') {
                $query->where('region', $region);
            }
            
            if ($platform !== 'all') {
                $query->where('platform', $platform);
            }
            
            $teams = $query->orderBy('rating', 'desc')->limit(30)->get();

            $rankedTeams = $teams->map(function($team, $index) {
                return [
                    'id' => $team->id,
                    'rank' => $index + 1,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'platform' => $team->platform ?? 'PC',
                    'rating' => $team->rating ?? 1000,
                    'win_rate' => $team->win_rate ?? 0,
                    'record' => $team->record ?? '0-0',
                    'division' => $team->division ?? $this->getDivisionByRating($team->rating ?? 1000),
                    'recent_form' => $team->recent_form ? json_decode($team->recent_form, true) : $this->generateRecentForm($team->id)
                ];
            });

            return response()->json([
                'data' => $rankedTeams,
                'total' => $rankedTeams->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching rankings: ' . $e->getMessage()
            ], 500);
        }
    }

    // Marvel Rivals Official Division System
    private function getDivisionByRating($rating)
    {
        if ($rating >= 2500) return 'One Above All';
        if ($rating >= 2300) return 'Eternity';
        if ($rating >= 2100) return 'Celestial';
        if ($rating >= 1900) return 'Grandmaster';
        if ($rating >= 1700) return 'Diamond';
        if ($rating >= 1500) return 'Platinum';
        if ($rating >= 1300) return 'Gold';
        if ($rating >= 1100) return 'Silver';
        return 'Bronze';
    }

    private function generateRecentForm($teamId)
    {
        // Get last 5 matches for the team
        $recentMatches = DB::table('matches')
            ->where(function($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })
            ->where('status', 'completed')
            ->orderBy('scheduled_at', 'desc')
            ->limit(5)
            ->get();
            
        $forms = [];
        foreach ($recentMatches as $match) {
            if ($match->team1_id == $teamId) {
                $forms[] = $match->team1_score > $match->team2_score ? 'W' : 'L';
            } else {
                $forms[] = $match->team2_score > $match->team1_score ? 'W' : 'L';
            }
        }
        
        return array_reverse($forms); // Show oldest to newest
    }

    private function getPlayerCount($teamId)
    {
        return DB::table('players')->where('team_id', $teamId)->count();
    }

    private function getTeamHeroesMeta($players)
    {
        $heroes = [];
        foreach ($players as $player) {
            if ($player->main_hero) {
                $heroes[] = $player->main_hero;
            }
        }
        return array_unique($heroes);
    }

    // VLR.gg-style comprehensive helper methods
    private function calculateDetailedTeamStats($teamId, $recentMatches)
    {
        $wins = 0;
        $losses = 0;
        $mapsWon = 0;
        $mapsLost = 0;
        
        foreach ($recentMatches as $match) {
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            if ($teamScore > $opponentScore) {
                $wins++;
            } else {
                $losses++;
            }
            
            $mapsWon += $teamScore;
            $mapsLost += $opponentScore;
        }
        
        $totalGames = $wins + $losses;
        $winRate = $totalGames > 0 ? round(($wins / $totalGames) * 100, 1) : 0;
        $mapWinRate = ($mapsWon + $mapsLost) > 0 ? round(($mapsWon / ($mapsWon + $mapsLost)) * 100, 1) : 0;
        
        return [
            'matches_played' => $totalGames,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $winRate,
            'maps_won' => $mapsWon,
            'maps_lost' => $mapsLost,
            'map_win_rate' => $mapWinRate,
            'map_differential' => $mapsWon - $mapsLost,
            'record' => "{$wins}-{$losses}",
            'recent_form' => $this->getRecentForm($recentMatches, $teamId, 5)
        ];
    }

    private function formatMatchResults($matches, $teamId)
    {
        return $matches->map(function($match) use ($teamId) {
            // Add null checks for match properties
            $team1Id = $match->team1_id ?? null;
            $team2Id = $match->team2_id ?? null;
            
            if (!$team1Id || !$team2Id) {
                return null; // Skip invalid matches
            }
            
            $isTeam1 = $team1Id == $teamId;
            $opponent = $isTeam1 ? 
                ['name' => $match->team2_name ?? 'Unknown', 'short_name' => $match->team2_short ?? 'UNK', 'logo' => $match->team2_logo ?? null] :
                ['name' => $match->team1_name ?? 'Unknown', 'short_name' => $match->team1_short ?? 'UNK', 'logo' => $match->team1_logo ?? null];
            
            $teamScore = $isTeam1 ? ($match->team1_score ?? 0) : ($match->team2_score ?? 0);
            $opponentScore = $isTeam1 ? ($match->team2_score ?? 0) : ($match->team1_score ?? 0);
            $result = $teamScore > $opponentScore ? 'W' : 'L';
            
            return [
                'id' => $match->id,
                'team1_id' => $team1Id,
                'team2_id' => $team2Id,
                'team1_score' => $isTeam1 ? $teamScore : $opponentScore,
                'team2_score' => $isTeam1 ? $opponentScore : $teamScore,
                'team1' => $isTeam1 ? null : $opponent,
                'team2' => $isTeam1 ? $opponent : null,
                'opponent' => $opponent,
                'result' => $result,
                'score' => "{$teamScore}-{$opponentScore}",
                'date' => $match->scheduled_at ?? null,
                'event_name' => $match->event_name ?? null,
                'event_logo' => $match->event_logo ?? null,
                'event_type' => $match->event_type ?? null,
                'format' => $match->format ?? 'BO3',
                'maps_data' => $match->maps_data ? json_decode($match->maps_data, true) : null
            ];
        })->filter(); // Remove null entries
    }

    private function formatUpcomingMatches($matches, $teamId)
    {
        return $matches->map(function($match) use ($teamId) {
            $team1Id = $match->team1_id ?? null;
            $team2Id = $match->team2_id ?? null;
            
            if (!$team1Id || !$team2Id) {
                return null; // Skip invalid matches
            }
            
            $isTeam1 = $team1Id == $teamId;
            $opponent = $isTeam1 ? 
                ['name' => $match->team2_name ?? 'Unknown', 'short_name' => $match->team2_short ?? 'UNK', 'logo' => $match->team2_logo ?? null] :
                ['name' => $match->team1_name ?? 'Unknown', 'short_name' => $match->team1_short ?? 'UNK', 'logo' => $match->team1_logo ?? null];
            
            return [
                'id' => $match->id,
                'opponent' => $opponent,
                'date' => $match->scheduled_at ?? null,
                'event' => $match->event_name ?? null,
                'event_type' => $match->event_type ?? null,
                'format' => $match->format ?? 'BO3',
                'status' => $match->status ?? 'upcoming',
                'stream_url' => $match->stream_url ?? null
            ];
        })->filter(); // Remove null entries
    }

    private function getRecentRosterChanges($teamId)
    {
        // This would typically come from a roster_changes table
        // For now, return empty array - can be implemented later
        return [];
    }

    private function analyzeTeamComposition($roster)
    {
        $composition = [
            'Vanguard' => 0,
            'Duelist' => 0,
            'Strategist' => 0
        ];
        
        foreach ($roster as $player) {
            if (isset($composition[$player->role])) {
                $composition[$player->role]++;
            }
        }
        
        return $composition;
    }

    private function getTeamHeroPool($roster)
    {
        $heroes = [];
        foreach ($roster as $player) {
            if ($player->main_hero) {
                $heroes[] = [
                    'hero' => $player->main_hero,
                    'player' => $player->name,
                    'role' => $player->role
                ];
            }
        }
        return $heroes;
    }

    private function calculateCurrentForm($matches, $teamId)
    {
        return $this->getRecentForm($matches, $teamId, 5);
    }

    private function getRecentForm($matches, $teamId, $limit = 5)
    {
        $form = [];
        $count = 0;
        
        foreach ($matches as $match) {
            if ($count >= $limit) break;
            
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            $form[] = $teamScore > $opponentScore ? 'W' : 'L';
            $count++;
        }
        
        return $form;
    }

    private function getLastActiveDate($matches)
    {
        if ($matches->isEmpty()) {
            return null;
        }
        
        return $matches->first()->scheduled_at;
    }

    private function generateRatingHistory($currentRating)
    {
        // Generate realistic rating progression for last 30 days
        $history = [];
        $rating = $currentRating;
        
        for ($i = 30; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $variation = rand(-50, 50);
            $rating = max(0, $rating + $variation);
            
            $history[] = [
                'date' => $date,
                'rating' => $rating,
                'rank' => $this->getRankByRating($rating)
            ];
        }
        
        return $history;
    }

    private function getRankByRating($rating)
    {
        // Simulate rank calculation based on rating
        if ($rating >= 2500) return rand(1, 10);
        if ($rating >= 2200) return rand(11, 50);
        if ($rating >= 1900) return rand(51, 200);
        if ($rating >= 1600) return rand(201, 500);
        if ($rating >= 1300) return rand(501, 1000);
        return rand(1001, 5000);
    }

    // Admin CRUD Methods
    public function getAllTeams(Request $request)
    {
        // Authorization is handled by middleware in routes/api.php
        
        try {
            $query = DB::table('teams as t')
                ->select('t.*');
            
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('t.name', 'LIKE', "%{$request->search}%")
                      ->orWhere('t.short_name', 'LIKE', "%{$request->search}%");
                });
            }
            
            $teams = $query->orderBy('t.rating', 'desc')->paginate(20);
            
            return response()->json([
                'data' => $teams->items(),
                'pagination' => [
                    'current_page' => $teams->currentPage(),
                    'last_page' => $teams->lastPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total()
                ],
                'success' => true
            ]);
            
        } catch (QueryException $e) {
            Log::error('Database error in team operation', [
                'error' => $e->getMessage(),
                'method' => debug_backtrace()[1]['function'] ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Database connection issue. Please try again later.',
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        } catch (Exception $e) {
            Log::error('Error in team operation', [
                'error' => $e->getMessage(),
                'method' => debug_backtrace()[1]['function'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to process request. Please try again later.',
                'error_code' => 'TEAMS_ERROR'
            ], 500);
        }
    }

    public function getTeamAdmin($teamId)
    {
        // Authorization is handled by middleware in routes/api.php
        
        try {
            // Use caching for better performance
            $cacheKey = "team_admin_{$teamId}";
            $team = \Cache::remember($cacheKey, 300, function() use ($teamId) {
                return DB::table('teams as t')
                    ->leftJoin(DB::raw('(SELECT team_id, COUNT(*) as player_count FROM players WHERE team_id = ' . $teamId . ') as pc'), 't.id', '=', 'pc.team_id')
                    ->where('t.id', $teamId)
                    ->select('t.*', 'pc.player_count')
                    ->first();
            });
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }
            
            // Get team roster for admin view
            $roster = \Cache::remember("team_roster_{$teamId}", 300, function() use ($teamId) {
                return DB::table('players')
                    ->where('team_id', $teamId)
                    ->select('id', 'username', 'real_name', 'role', 'avatar', 'rating')
                    ->orderBy('role')
                    ->get();
            });
            
            $teamData = (array) $team;
            $teamData['roster'] = $roster;
            $teamData['player_count'] = $roster->count();
            
            return response()->json([
                'data' => $teamData,
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Authorization is handled by middleware in routes/api.php
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teams',
            'short_name' => 'required|string|max:20|unique:teams',
            'region' => 'required|string|max:20',
            'platform' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:5',
            'rating' => 'nullable|numeric|min:0|max:5000',
            'elo_rating' => 'nullable|numeric|min:0|max:5000',
            'earnings' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'social_links' => 'nullable|array',
            'social_media' => 'nullable|array',
            'twitter' => 'nullable|string|max:50',
            'instagram' => 'nullable|string|max:50',
            'youtube' => 'nullable|string|max:100',
            'twitch' => 'nullable|string|max:50',
            'tiktok' => 'nullable|string|max:50',
            'discord' => 'nullable|string|max:100',
            'facebook' => 'nullable|string|url|max:255',
            'website' => 'nullable|string|url|max:255',
            'liquipedia_url' => 'nullable|string|url|max:255',
            'logo' => 'nullable|string|url|max:500',
            'coach' => 'nullable|string|max:255',
            'coach_name' => 'nullable|string|max:255',
            'coach_nationality' => 'nullable|string|max:255',
            'coach_social_media' => 'nullable|array',
            'coach_picture' => 'nullable|string|url|max:500',
            'captain' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'owner' => 'nullable|string|max:255',
            'founded' => 'nullable|string|max:50',
            'founded_date' => 'nullable|date|before:today',
            'status' => 'nullable|in:active,inactive,disbanded,suspended',
            'achievements' => 'nullable|array'
        ]);
        
        try {
            $teamId = DB::table('teams')->insertGetId([
                'name' => $request->name,
                'short_name' => $request->short_name,
                'region' => $request->region,
                'country' => $request->country,
                'rating' => $request->rating ?? 1000,
                'rank' => 999,
                'win_rate' => 0,
                'points' => 0,
                'record' => '0-0',
                'peak' => $request->rating ?? 1000,
                'social_media' => json_encode($request->social_links ?? []),
                'coach_name' => $request->coach_name,
                'coach_nationality' => $request->coach_nationality,
                'coach_social_media' => json_encode($request->coach_social_media ?? []),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Update team ranks after adding new team
            $this->updateAllTeamRanks();
            
            $team = DB::table('teams')->where('id', $teamId)->first();
            
            return response()->json([
                'data' => $team,
                'success' => true,
                'message' => 'Team created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating team: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:teams,name,' . $teamId,
                'short_name' => 'sometimes|string|max:20|unique:teams,short_name,' . $teamId,
                'region' => 'sometimes|string|max:20',
                'platform' => 'nullable|string|max:50',
                'country' => 'nullable|string|max:100',
                'country_code' => 'nullable|string|max:5',
                'rating' => 'nullable|numeric|min:0|max:5000',
                'elo_rating' => 'nullable|numeric|min:0|max:5000',
                'peak_rating' => 'nullable|numeric|min:0|max:5000',
                'peak_elo' => 'nullable|numeric|min:0|max:5000',
                'earnings' => 'nullable|numeric|min:0',
                'earnings_decimal' => 'nullable|numeric|min:0',
                'earnings_amount' => 'nullable|numeric|min:0',
                'earnings_currency' => 'nullable|string|max:10',
                'description' => 'nullable|string|max:2000',
                'social_links' => 'nullable|array',
                'social_media' => 'nullable|array',
                'twitter' => 'nullable|string|max:50',
                'twitter_url' => 'nullable|string|url|max:255',
                'instagram' => 'nullable|string|max:50',
                'instagram_url' => 'nullable|string|url|max:255',
                'youtube' => 'nullable|string|max:100',
                'youtube_url' => 'nullable|string|url|max:255',
                'twitch' => 'nullable|string|max:50',
                'twitch_url' => 'nullable|string|url|max:255',
                'tiktok' => 'nullable|string|max:50',
                'coach_name' => 'nullable|string|max:255',
                'coach_nationality' => 'nullable|string|max:255',
                'coach_social_media' => 'nullable|array',
                'discord' => 'nullable|string|max:100',
                'discord_url' => 'nullable|string|max:255',
                'facebook' => 'nullable|string|url|max:255',
                'website' => 'nullable|string|url|max:255',
                'website_url' => 'nullable|string|url|max:255',
                'liquipedia_url' => 'nullable|string|url|max:255',
                'vlr_url' => 'nullable|string|url|max:255',
                'logo' => 'nullable|string|url|max:500',
                'flag' => 'nullable|string|url|max:500',
                'country_flag' => 'nullable|string|url|max:500',
                'coach' => 'nullable|string|max:255',
                'coach_picture' => 'nullable|string|url|max:500',
                'coach_image' => 'nullable|string|url|max:500',
                'captain' => 'nullable|string|max:255',
                'manager' => 'nullable|string|max:255',
                'owner' => 'nullable|string|max:255',
                'founded' => 'nullable|string|max:50',
                'founded_date' => 'nullable|date|before:today',
                'status' => 'sometimes|in:active,inactive,disbanded,suspended',
                'achievements' => 'nullable|array'
            ]);
            
            // Handle social media fields - merge individual fields into social_media JSON
            // Support all 6 major platforms: Twitter, Instagram, YouTube, Twitch, Discord, TikTok
            $socialFields = [
                'twitter', 'twitter_url', 'instagram', 'instagram_url', 
                'youtube', 'youtube_url', 'twitch', 'twitch_url',
                'tiktok', 'discord', 'discord_url', 'facebook',
                'website', 'website_url', 'liquipedia_url', 'vlr_url'
            ];
            $currentSocialMedia = $team->social_media ? json_decode($team->social_media, true) : [];
            
            foreach ($socialFields as $field) {
                if (isset($validated[$field])) {
                    if (!empty($validated[$field])) {
                        // Store both in social_media JSON and individual columns
                        $currentSocialMedia[$field] = $validated[$field];
                        // Keep individual column for direct database access
                        if (in_array($field, ['twitter', 'instagram', 'youtube', 'twitch', 'tiktok', 'discord', 'website'])) {
                            // Don't remove from validated - allow updating individual columns
                        } else {
                            unset($validated[$field]); // Remove URL variants to avoid column conflicts
                        }
                    } else {
                        // Remove empty values
                        unset($currentSocialMedia[$field]);
                        if (in_array($field, ['twitter', 'instagram', 'youtube', 'twitch', 'tiktok', 'discord', 'website'])) {
                            $validated[$field] = null; // Set individual column to null
                        } else {
                            unset($validated[$field]);
                        }
                    }
                }
            }
            
            // Handle social_links array if provided
            if (isset($validated['social_links'])) {
                foreach ($validated['social_links'] as $platform => $url) {
                    if (!empty($url)) {
                        $currentSocialMedia[$platform] = $url;
                    }
                }
                unset($validated['social_links']);
            }
            
            // Handle direct social_media array update
            if (isset($validated['social_media']) && is_array($validated['social_media'])) {
                $currentSocialMedia = array_merge($currentSocialMedia, $validated['social_media']);
            }
            
            $validated['social_media'] = json_encode($currentSocialMedia);
            
            // Process other array fields to JSON
            $arrayFields = ['achievements'];
            foreach ($arrayFields as $field) {
                if (isset($validated[$field]) && is_array($validated[$field])) {
                    $validated[$field] = json_encode($validated[$field]);
                }
            }
            
            // Update peak ratings if new ratings are higher
            if (isset($validated['rating']) && $validated['rating'] > ($team->peak ?? 0)) {
                $validated['peak'] = $validated['rating'];
            }
            if (isset($validated['elo_rating']) && $validated['elo_rating'] > ($team->peak_elo ?? 0)) {
                $validated['peak_elo'] = $validated['elo_rating'];
            }
            
            // Set updated timestamp
            $validated['updated_at'] = now();
            
            // Update the team with transaction for data integrity
            DB::transaction(function() use ($validated, $teamId) {
                DB::table('teams')->where('id', $teamId)->update($validated);
                
                // Update team ranks after rating change if rating was updated
                if (isset($validated['rating']) || isset($validated['elo_rating'])) {
                    $this->updateAllTeamRanks();
                }
                
                // Clear relevant caches for immediate updates
                \Cache::tags(['teams', 'players', 'rankings'])->flush();
                \Cache::forget("team_{$teamId}");
                \Cache::forget("team_admin_{$teamId}");
                \Cache::forget('team_rankings');
            });
            
            // Return optimized response with fresh data
            $updatedTeam = $this->getTeamAdmin($teamId);
            
            return response()->json([
                'data' => $updatedTeam->original['data'],
                'success' => true,
                'message' => 'Team updated successfully',
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('TeamController@update DB error: ' . $e->getMessage());
            
            // Handle specific database constraint violations
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                return response()->json([
                    'success' => false,
                    'message' => 'Team name or short name already exists',
                    'error_code' => 'DUPLICATE_ENTRY'
                ], 409);
            }
            if ($e->errorInfo[1] == 1452) { // Foreign key constraint
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reference - related data does not exist',
                    'error_code' => 'FOREIGN_KEY_VIOLATION'
                ], 400);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating team',
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        } catch (\Exception $e) {
            \Log::error('TeamController@update error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error updating team: ' . $e->getMessage(),
                'error_code' => 'GENERAL_ERROR'
            ], 500);
        }
    }

    /**
     * Add a player to the team
     */
    public function addPlayer(Request $request, $teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            // Check current roster size (Marvel Rivals teams typically have 6 players)
            $currentRosterSize = DB::table('players')->where('team_id', $teamId)->where('status', 'active')->count();
            if ($currentRosterSize >= 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team roster is full (maximum 6 players)'
                ], 400);
            }

            $validated = $request->validate([
                'player_id' => 'required|exists:players,id',
                'role' => 'required|in:Vanguard,Duelist,Strategist',
                'jersey_number' => 'nullable|integer|min:1|max:99'
            ]);

            $player = DB::table('players')->where('id', $validated['player_id'])->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            if ($player->team_id && $player->team_id != $teamId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player is already on another team'
                ], 400);
            }

            // Add player to team
            DB::table('players')->where('id', $validated['player_id'])->update([
                'team_id' => $teamId,
                'role' => $validated['role'],
                'jersey_number' => $validated['jersey_number'] ?? null,
                'status' => 'active',
                'updated_at' => now()
            ]);

            // Update team player count
            $newPlayerCount = DB::table('players')->where('team_id', $teamId)->where('status', 'active')->count();
            DB::table('teams')->where('id', $teamId)->update([
                'player_count' => $newPlayerCount,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player added to team successfully',
                'data' => [
                    'player_id' => $validated['player_id'],
                    'team_id' => $teamId,
                    'role' => $validated['role'],
                    'roster_size' => $newPlayerCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding player to team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a player from the team
     */
    public function removePlayer(Request $request, $teamId, $playerId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $player = DB::table('players')->where('id', $playerId)->where('team_id', $teamId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found on this team'
                ], 404);
            }

            // Remove player from team
            DB::table('players')->where('id', $playerId)->update([
                'team_id' => null,
                'role' => null,
                'jersey_number' => null,
                'status' => 'active', // Still active, just not on a team
                'updated_at' => now()
            ]);

            // Update team player count
            $newPlayerCount = DB::table('players')->where('team_id', $teamId)->where('status', 'active')->count();
            DB::table('teams')->where('id', $teamId)->update([
                'player_count' => $newPlayerCount,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player removed from team successfully',
                'data' => [
                    'player_id' => $playerId,
                    'team_id' => $teamId,
                    'roster_size' => $newPlayerCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing player from team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a player's role on the team
     */
    public function updatePlayerRole(Request $request, $teamId, $playerId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $player = DB::table('players')->where('id', $playerId)->where('team_id', $teamId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found on this team'
                ], 404);
            }

            $validated = $request->validate([
                'role' => 'required|in:Vanguard,Duelist,Strategist',
                'jersey_number' => 'nullable|integer|min:1|max:99'
            ]);

            // Update player role
            DB::table('players')->where('id', $playerId)->update([
                'role' => $validated['role'],
                'jersey_number' => $validated['jersey_number'] ?? $player->jersey_number,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player role updated successfully',
                'data' => [
                    'player_id' => $playerId,
                    'team_id' => $teamId,
                    'role' => $validated['role'],
                    'jersey_number' => $validated['jersey_number'] ?? $player->jersey_number
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating player role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer a player to another team
     */
    public function transferPlayer(Request $request, $teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $validated = $request->validate([
                'player_id' => 'required|exists:players,id',
                'to_team_id' => 'required|exists:teams,id'
            ]);

            $player = DB::table('players')->where('id', $validated['player_id'])->where('team_id', $teamId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found on this team'
                ], 404);
            }

            $toTeam = DB::table('teams')->where('id', $validated['to_team_id'])->first();
            if (!$toTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Destination team not found'
                ], 404);
            }

            // Check if destination team has space
            $destinationRosterSize = DB::table('players')->where('team_id', $validated['to_team_id'])->where('status', 'active')->count();
            if ($destinationRosterSize >= 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Destination team roster is full (maximum 6 players)'
                ], 400);
            }

            // Transfer player
            DB::table('players')->where('id', $validated['player_id'])->update([
                'team_id' => $validated['to_team_id'],
                'updated_at' => now()
            ]);

            // Update both teams' player counts
            $oldTeamPlayerCount = DB::table('players')->where('team_id', $teamId)->where('status', 'active')->count();
            $newTeamPlayerCount = DB::table('players')->where('team_id', $validated['to_team_id'])->where('status', 'active')->count();

            DB::table('teams')->where('id', $teamId)->update([
                'player_count' => $oldTeamPlayerCount,
                'updated_at' => now()
            ]);

            DB::table('teams')->where('id', $validated['to_team_id'])->update([
                'player_count' => $newTeamPlayerCount,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Player transferred successfully',
                'data' => [
                    'player_id' => $validated['player_id'],
                    'from_team_id' => $teamId,
                    'to_team_id' => $validated['to_team_id'],
                    'old_roster_size' => $oldTeamPlayerCount,
                    'new_roster_size' => $newTeamPlayerCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error transferring player: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($teamId)
    {
        // Authorization is handled by middleware in routes/api.php
        
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }
            
            // Check if team has players
            $playerCount = DB::table('players')->where('team_id', $teamId)->count();
            if ($playerCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete team with active players. Remove players first.'
                ], 400);
            }
            
            // Check if team has matches
            $matchCount = DB::table('matches')
                ->where('team1_id', $teamId)
                ->orWhere('team2_id', $teamId)
                ->count();
            if ($matchCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete team with match history.'
                ], 400);
            }
            
            DB::table('teams')->where('id', $teamId)->delete();
            
            // Update team ranks after deletion
            $this->updateAllTeamRanks();
            
            return response()->json([
                'success' => true,
                'message' => 'Team deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting team: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateAllTeamRanks()
    {
        // Get all teams sorted by rating
        $teams = DB::table('teams')
            ->orderBy('rating', 'desc')
            ->get();
        
        // Update ranks
        foreach ($teams as $index => $team) {
            DB::table('teams')
                ->where('id', $team->id)
                ->update(['rank' => $index + 1]);
        }
    }

    public function getMentions($teamId, Request $request = null)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $query = DB::table('mentions as m')
                ->leftJoin('users as u', 'm.mentioned_by', '=', 'u.id')
                ->where('m.mentioned_type', 'team')
                ->where('m.mentioned_id', $teamId)
                ->where('m.is_active', true)
                ->select([
                    'm.id',
                    'm.mention_text',
                    'm.context',
                    'm.mentioned_at',
                    'm.mentionable_type',
                    'm.mentionable_id',
                    'm.metadata',
                    'u.id as mentioned_by_id',
                    'u.name as mentioned_by_name',
                    'u.avatar as mentioned_by_avatar',
                    'u.hero_flair as mentioned_by_hero_flair',
                    'u.team_flair_id as mentioned_by_team_flair'
                ])
                ->orderBy('m.mentioned_at', 'desc');

            // Filter by content type if specified
            if ($request && $request->content_type) {
                $query->where('m.mentionable_type', $request->content_type);
            }

            // Pagination
            $perPage = $request ? min($request->get('per_page', 20), 50) : 20;
            $page = $request ? $request->get('page', 1) : 1;
            $offset = ($page - 1) * $perPage;
            
            $total = $query->count();
            $mentions = $query->offset($offset)->limit($perPage)->get();

            // Format mentions with content context
            $formattedMentions = $mentions->map(function($mention) {
                $mentionData = [
                    'id' => $mention->id,
                    'mention_text' => $mention->mention_text,
                    'context' => $mention->context,
                    'mentioned_at' => $mention->mentioned_at,
                    'mentioned_by' => $mention->mentioned_by_id ? [
                        'id' => $mention->mentioned_by_id,
                        'name' => $mention->mentioned_by_name,
                        'avatar' => $mention->mentioned_by_avatar,
                        'hero_flair' => $mention->mentioned_by_hero_flair,
                        'team_flair_id' => $mention->mentioned_by_team_flair
                    ] : null,
                    'content' => $this->getContentContextForMention($mention),
                    'metadata' => $mention->metadata ? json_decode($mention->metadata, true) : null
                ];

                return $mentionData;
            });

            return response()->json([
                'data' => $formattedMentions,
                'pagination' => [
                    'current_page' => (int) $page,
                    'last_page' => (int) ceil($total / $perPage),
                    'per_page' => (int) $perPage,
                    'total' => $total
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('TeamController@getMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getContentContextForMention($mention)
    {
        switch ($mention->mentionable_type) {
            case 'news':
                $news = DB::table('news')->where('id', $mention->mentionable_id)->first();
                return $news ? [
                    'type' => 'news',
                    'title' => $news->title,
                    'url' => "/news/{$news->slug}"
                ] : null;
            
            case 'news_comment':
                $comment = DB::table('news_comments')->where('id', $mention->mentionable_id)->first();
                if ($comment) {
                    $news = DB::table('news')->where('id', $comment->news_id)->first();
                    return $news ? [
                        'type' => 'news_comment',
                        'title' => "Comment on: {$news->title}",
                        'url' => "/news/{$news->slug}#comment-{$comment->id}"
                    ] : null;
                }
                return null;
            
            case 'match':
                $match = DB::table('matches')->where('id', $mention->mentionable_id)->first();
                if ($match) {
                    $team1 = DB::table('teams')->where('id', $match->team1_id)->first();
                    $team2 = DB::table('teams')->where('id', $match->team2_id)->first();
                    return [
                        'type' => 'match',
                        'title' => ($team1 ? $team1->name : 'TBD') . ' vs ' . ($team2 ? $team2->name : 'TBD'),
                        'url' => "/matches/{$match->id}"
                    ];
                }
                return null;
            
            case 'forum_thread':
                $thread = DB::table('forum_threads')->where('id', $mention->mentionable_id)->first();
                return $thread ? [
                    'type' => 'forum_thread',
                    'title' => $thread->title,
                    'url' => "/forums/threads/{$thread->id}"
                ] : null;
            
            case 'forum_post':
                $post = DB::table('forum_posts')->where('id', $mention->mentionable_id)->first();
                if ($post) {
                    $thread = DB::table('forum_threads')->where('id', $post->thread_id)->first();
                    return $thread ? [
                        'type' => 'forum_post',
                        'title' => "Reply in: {$thread->title}",
                        'url' => "/forums/threads/{$thread->id}#post-{$post->id}"
                    ] : null;
                }
                return null;
            
            default:
                return null;
        }
    }

    public function getMentionsNew($teamId, Request $request)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $query = Mention::where('mentioned_type', 'team')
                           ->where('mentioned_id', $teamId)
                           ->where('is_active', true)
                           ->with(['mentionedBy'])
                           ->orderBy('mentioned_at', 'desc');

            // Filter by content type if specified
            if ($request->content_type) {
                $query->where('mentionable_type', $request->content_type);
            }

            // Pagination
            $perPage = $request->get('per_page', 20);
            $mentions = $query->paginate($perPage);

            // Format mentions with content context
            $formattedMentions = $mentions->getCollection()->map(function($mention) {
                $mentionData = [
                    'id' => $mention->id,
                    'mention_text' => $mention->mention_text,
                    'context' => $mention->context,
                    'mentioned_at' => $mention->mentioned_at,
                    'mentioned_by' => $mention->mentionedBy ? [
                        'id' => $mention->mentionedBy->id,
                        'name' => $mention->mentionedBy->name,
                        'avatar' => $mention->mentionedBy->avatar
                    ] : null,
                    'content' => $mention->getContentContext()
                ];

                return $mentionData;
            });

            return response()->json([
                'data' => $formattedMentions,
                'pagination' => [
                    'current_page' => $mentions->currentPage(),
                    'last_page' => $mentions->lastPage(),
                    'per_page' => $mentions->perPage(),
                    'total' => $mentions->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming matches for a specific team with pagination
     */
    public function getUpcomingMatches($teamId, Request $request)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $query = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.event_id', 'm.status', 'm.scheduled_at', 'm.format', 'm.stream_url',
                    'm.team1_id', 'm.team2_id', 'm.tournament_round as stage', 'm.importance_level as match_importance',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't1.rating as team1_rating', 't1.region as team1_region',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    't2.rating as team2_rating', 't2.region as team2_region',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo',
                    'e.tier as event_tier', 'e.prize_pool'
                ])
                ->where(function($q) use ($teamId) {
                    $q->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->where('m.status', 'upcoming')
                ->where('m.scheduled_at', '>', now());

            // Filter by date range if provided
            if ($request->from_date) {
                $query->where('m.scheduled_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $query->where('m.scheduled_at', '<=', $request->to_date);
            }

            // Filter by event if provided
            if ($request->event_id) {
                $query->where('m.event_id', $request->event_id);
            }

            $query->orderBy('m.scheduled_at', 'asc');

            // Pagination
            $perPage = $request->get('per_page', 10);
            $matches = $query->paginate($perPage);

            // Format matches
            $formattedMatches = $matches->getCollection()->map(function($match) use ($teamId) {
                $isTeam1 = $match->team1_id == $teamId;
                
                return [
                    'id' => $match->id,
                    'scheduled_at' => $match->scheduled_at,
                    'status' => $match->status,
                    'format' => $match->format ?? 'BO3',
                    'stage' => $match->tournament_round ?? 'Regular Season',
                    'match_importance' => $match->match_importance ?? 'regular',
                    'stream_url' => $match->stream_url,
                    'team' => [
                        'id' => $teamId,
                        'name' => $isTeam1 ? $match->team1_name : $match->team2_name,
                        'short_name' => $isTeam1 ? $match->team1_short : $match->team2_short,
                        'logo' => $isTeam1 ? $match->team1_logo : $match->team2_logo,
                        'rating' => $isTeam1 ? $match->team1_rating : $match->team2_rating,
                        'region' => $isTeam1 ? $match->team1_region : $match->team2_region
                    ],
                    'opponent' => [
                        'id' => $isTeam1 ? $match->team2_id : $match->team1_id,
                        'name' => $isTeam1 ? $match->team2_name : $match->team1_name,
                        'short_name' => $isTeam1 ? $match->team2_short : $match->team1_short,
                        'logo' => $isTeam1 ? $match->team2_logo : $match->team1_logo,
                        'rating' => $isTeam1 ? $match->team2_rating : $match->team1_rating,
                        'region' => $isTeam1 ? $match->team2_region : $match->team1_region
                    ],
                    'event' => [
                        'id' => $match->event_id,
                        'name' => $match->event_name,
                        'type' => $match->event_type,
                        'logo' => $match->event_logo,
                        'tier' => $match->event_tier,
                        'prize_pool' => $match->prize_pool
                    ],
                    'time_until' => $this->getTimeUntil($match->scheduled_at)
                ];
            });

            return response()->json([
                'data' => $formattedMatches,
                'pagination' => [
                    'current_page' => $matches->currentPage(),
                    'last_page' => $matches->lastPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching upcoming matches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live matches for a specific team
     */
    public function getLiveMatches($teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $liveMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't1.rating as team1_rating', 't1.region as team1_region',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    't2.rating as team2_rating', 't2.region as team2_region',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo'
                ])
                ->where(function($q) use ($teamId) {
                    $q->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->where('m.status', 'live')
                ->get();

            // Format live matches with real-time data
            $formattedMatches = $liveMatches->map(function($match) use ($teamId) {
                $isTeam1 = $match->team1_id == $teamId;
                
                // Parse live data if available
                $liveData = $match->live_data ? json_decode($match->live_data, true) : null;
                $mapsData = $match->maps_data ? json_decode($match->maps_data, true) : [];
                
                return [
                    'id' => $match->id,
                    'status' => 'live',
                    'current_map' => $liveData['current_map'] ?? 1,
                    'current_score' => [
                        'team' => $isTeam1 ? $match->team1_score : $match->team2_score,
                        'opponent' => $isTeam1 ? $match->team2_score : $match->team1_score
                    ],
                    'maps' => $this->formatLiveMapsData($mapsData, $teamId, $match->team1_id),
                    'team' => [
                        'id' => $teamId,
                        'name' => $isTeam1 ? $match->team1_name : $match->team2_name,
                        'short_name' => $isTeam1 ? $match->team1_short : $match->team2_short,
                        'logo' => $isTeam1 ? $match->team1_logo : $match->team2_logo
                    ],
                    'opponent' => [
                        'id' => $isTeam1 ? $match->team2_id : $match->team1_id,
                        'name' => $isTeam1 ? $match->team2_name : $match->team1_name,
                        'short_name' => $isTeam1 ? $match->team2_short : $match->team1_short,
                        'logo' => $isTeam1 ? $match->team2_logo : $match->team1_logo
                    ],
                    'event' => [
                        'name' => $match->event_name,
                        'type' => $match->event_type,
                        'logo' => $match->event_logo
                    ],
                    'stream_url' => $match->stream_url,
                    'live_stats' => $liveData['stats'] ?? null,
                    'duration' => $liveData['duration'] ?? null
                ];
            });

            return response()->json([
                'data' => $formattedMatches,
                'success' => true,
                'is_live' => $formattedMatches->isNotEmpty()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching live matches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent match results with detailed statistics
     */
    public function getRecentResults($teamId, Request $request)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $query = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't1.rating as team1_rating', 't1.region as team1_region',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    't2.rating as team2_rating', 't2.region as team2_region',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo',
                    'e.tier as event_tier'
                ])
                ->where(function($q) use ($teamId) {
                    $q->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->where('m.status', 'completed');

            // Apply filters
            if ($request->event_id) {
                $query->where('m.event_id', $request->event_id);
            }
            
            if ($request->from_date) {
                $query->where('m.scheduled_at', '>=', $request->from_date);
            }
            
            if ($request->to_date) {
                $query->where('m.scheduled_at', '<=', $request->to_date);
            }

            // Filter by result if specified
            if ($request->result === 'wins' || $request->result === 'losses') {
                $query->where(function($q) use ($teamId, $request) {
                    if ($request->result === 'wins') {
                        $q->where(function($subQ) use ($teamId) {
                            $subQ->where('m.team1_id', $teamId)
                                 ->whereColumn('m.team1_score', '>', 'm.team2_score');
                        })->orWhere(function($subQ) use ($teamId) {
                            $subQ->where('m.team2_id', $teamId)
                                 ->whereColumn('m.team2_score', '>', 'm.team1_score');
                        });
                    } else {
                        $q->where(function($subQ) use ($teamId) {
                            $subQ->where('m.team1_id', $teamId)
                                 ->whereColumn('m.team1_score', '<', 'm.team2_score');
                        })->orWhere(function($subQ) use ($teamId) {
                            $subQ->where('m.team2_id', $teamId)
                                 ->whereColumn('m.team2_score', '<', 'm.team1_score');
                        });
                    }
                });
            }

            $query->orderBy('m.scheduled_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 20);
            $matches = $query->paginate($perPage);

            // Get player stats for these matches
            $matchIds = $matches->getCollection()->pluck('id')->toArray();
            $playerStats = $this->getPlayerStatsForMatches($matchIds, $teamId);

            // Format matches with detailed stats
            $formattedMatches = $matches->getCollection()->map(function($match) use ($teamId, $playerStats) {
                $isTeam1 = $match->team1_id == $teamId;
                $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
                $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
                $result = $teamScore > $opponentScore ? 'W' : 'L';
                
                // Parse match data
                $mapsData = $match->maps_data ? json_decode($match->maps_data, true) : [];
                $matchStats = isset($match->player_stats) && $match->player_stats ? json_decode($match->player_stats, true) : null;
                
                return [
                    'id' => $match->id,
                    'date' => $match->scheduled_at,
                    'result' => $result,
                    'score' => [
                        'team' => $teamScore,
                        'opponent' => $opponentScore,
                        'display' => "{$teamScore}-{$opponentScore}"
                    ],
                    'team' => [
                        'id' => $teamId,
                        'name' => $isTeam1 ? $match->team1_name : $match->team2_name,
                        'short_name' => $isTeam1 ? $match->team1_short : $match->team2_short,
                        'logo' => $isTeam1 ? $match->team1_logo : $match->team2_logo,
                        'rating_before' => $isTeam1 ? $match->team1_rating : $match->team2_rating
                    ],
                    'opponent' => [
                        'id' => $isTeam1 ? $match->team2_id : $match->team1_id,
                        'name' => $isTeam1 ? $match->team2_name : $match->team1_name,
                        'short_name' => $isTeam1 ? $match->team2_short : $match->team1_short,
                        'logo' => $isTeam1 ? $match->team2_logo : $match->team1_logo,
                        'rating' => $isTeam1 ? $match->team2_rating : $match->team1_rating,
                        'region' => $isTeam1 ? $match->team2_region : $match->team1_region
                    ],
                    'event' => [
                        'name' => $match->event_name,
                        'type' => $match->event_type,
                        'logo' => $match->event_logo,
                        'tier' => $match->event_tier
                    ],
                    'format' => $match->format ?? 'BO3',
                    'stage' => $match->tournament_round ?? 'Regular Season',
                    'maps' => $this->formatMapsWithStats($mapsData, $teamId, $match->team1_id),
                    'player_stats' => $playerStats[$match->id] ?? [],
                    'team_stats' => $this->extractTeamStats($matchStats, $teamId, $match->team1_id),
                    'duration' => $match->match_duration ?? $match->total_duration,
                    'vod_url' => $match->vod_url
                ];
            });

            // Calculate aggregated stats for the period
            $aggregatedStats = null; // TODO: Fix this - $this->calculateAggregatedStats($formattedMatches);

            return response()->json([
                'data' => $formattedMatches,
                'aggregated_stats' => $aggregatedStats,
                'pagination' => [
                    'current_page' => $matches->currentPage(),
                    'last_page' => $matches->lastPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total()
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recent results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match statistics summary for a team
     */
    public function getMatchStats($teamId, Request $request)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            // Base query for completed matches
            $query = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.*',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't1.rating as team1_rating', 't1.region as team1_region',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    't2.rating as team2_rating', 't2.region as team2_region',
                    'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo',
                    'e.tier as event_tier'
                ])
                ->where(function($q) use ($teamId) {
                    $q->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->where('m.status', 'completed');

            // Apply date filters
            if ($request->period) {
                $date = $this->getDateFromPeriod($request->period);
                $query->where('m.scheduled_at', '>=', $date);
            }

            $matches = $query->get();

            // Calculate comprehensive statistics
            $stats = [
                'overview' => $this->calculateOverviewStats($matches, $teamId),
                'performance_by_map' => $this->calculateMapStats($matches, $teamId),
                'performance_by_opponent_region' => $this->calculateRegionStats($matches, $teamId),
                'performance_by_event_tier' => $this->calculateEventTierStats($matches, $teamId),
                'recent_form' => $this->calculateFormStats($matches, $teamId),
                'best_maps' => $this->getBestMaps($matches, $teamId),
                'worst_maps' => $this->getWorstMaps($matches, $teamId),
                'comeback_stats' => $this->calculateComebackStats($matches, $teamId),
                'close_match_stats' => $this->calculateCloseMatchStats($matches, $teamId)
            ];

            return response()->json([
                'data' => $stats,
                'period' => $request->period ?? 'all_time',
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating match statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
    private function getTimeUntil($scheduledAt)
    {
        $now = now();
        $scheduled = \Carbon\Carbon::parse($scheduledAt);
        
        if ($scheduled->isPast()) {
            return 'Started';
        }
        
        $diff = $now->diff($scheduled);
        
        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        } else {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    }

    private function formatLiveMapsData($mapsData, $teamId, $team1Id)
    {
        $isTeam1 = $team1Id == $teamId;
        
        return collect($mapsData)->map(function($map) use ($isTeam1) {
            return [
                'map_name' => $map['map_name'] ?? 'Unknown',
                'map_number' => $map['map_number'] ?? 1,
                'status' => $map['status'] ?? 'upcoming',
                'team_score' => $isTeam1 ? ($map['team1_score'] ?? 0) : ($map['team2_score'] ?? 0),
                'opponent_score' => $isTeam1 ? ($map['team2_score'] ?? 0) : ($map['team1_score'] ?? 0),
                'current_round' => $map['current_round'] ?? null,
                'team_side' => $isTeam1 ? ($map['team1_side'] ?? null) : ($map['team2_side'] ?? null)
            ];
        });
    }

    private function formatMapsWithStats($mapsData, $teamId, $team1Id)
    {
        $isTeam1 = $team1Id == $teamId;
        
        return collect($mapsData)->map(function($map) use ($isTeam1) {
            $teamScore = $isTeam1 ? ($map['team1_score'] ?? 0) : ($map['team2_score'] ?? 0);
            $opponentScore = $isTeam1 ? ($map['team2_score'] ?? 0) : ($map['team1_score'] ?? 0);
            
            return [
                'map_name' => $map['map_name'] ?? 'Unknown',
                'map_number' => $map['map_number'] ?? 1,
                'result' => $teamScore > $opponentScore ? 'W' : 'L',
                'score' => [
                    'team' => $teamScore,
                    'opponent' => $opponentScore
                ],
                'duration' => $map['duration'] ?? null,
                'team_stats' => $isTeam1 ? ($map['team1_stats'] ?? null) : ($map['team2_stats'] ?? null),
                'mvp' => $map['mvp'] ?? null
            ];
        });
    }

    private function getPlayerStatsForMatches($matchIds, $teamId)
    {
        if (empty($matchIds)) return [];
        
        $stats = DB::table('player_match_stats as mps')
            ->leftJoin('players as p', 'mps.player_id', '=', 'p.id')
            ->whereIn('mps.match_id', $matchIds)
            ->where('p.team_id', $teamId)
            ->select([
                'mps.match_id', 'mps.player_id', 'p.name as player_name', 'p.username',
                'mps.hero_played', 'mps.eliminations', 'mps.deaths', 'mps.assists',
                'mps.damage', 'mps.healing', 'mps.damage_blocked',
                'mps.ultimates_used', 'mps.performance_rating'
            ])
            ->get()
            ->groupBy('match_id');
        
        return $stats->map(function($matchStats) {
            return $matchStats->map(function($stat) {
                return [
                    'player' => [
                        'id' => $stat->player_id,
                        'name' => $stat->player_name,
                        'username' => $stat->username
                    ],
                    'hero' => $stat->hero_played,
                    'stats' => [
                        'kda' => "{$stat->eliminations}/{$stat->deaths}/{$stat->assists}",
                        'eliminations' => $stat->eliminations,
                        'deaths' => $stat->deaths,
                        'assists' => $stat->assists,
                        'damage_dealt' => $stat->damage,
                        'healing_done' => $stat->healing,
                        'damage_blocked' => $stat->damage_blocked,
                        'ultimates_used' => $stat->ultimates_used
                    ],
                    'rating_change' => $stat->performance_rating
                ];
            });
        })->toArray();
    }

    private function extractTeamStats($matchStats, $teamId, $team1Id)
    {
        if (!$matchStats) return null;
        
        $isTeam1 = $team1Id == $teamId;
        $teamKey = $isTeam1 ? 'team1_stats' : 'team2_stats';
        
        return $matchStats[$teamKey] ?? null;
    }

    private function calculateAggregatedStats($matches)
    {
        $wins = $matches->where('result', 'W')->count();
        $losses = $matches->where('result', 'L')->count();
        $totalMatches = $matches->count();
        
        if ($totalMatches === 0) {
            return [
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_rate' => 0,
                'maps_won' => 0,
                'maps_lost' => 0,
                'map_win_rate' => 0
            ];
        }
        
        $mapsWon = 0;
        $mapsLost = 0;
        
        foreach ($matches as $match) {
            foreach ($match['maps'] as $map) {
                if ($map['result'] === 'W') {
                    $mapsWon++;
                } else {
                    $mapsLost++;
                }
            }
        }
        
        $totalMaps = $mapsWon + $mapsLost;
        
        return [
            'matches_played' => $totalMatches,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => round(($wins / $totalMatches) * 100, 1),
            'maps_won' => $mapsWon,
            'maps_lost' => $mapsLost,
            'map_win_rate' => $totalMaps > 0 ? round(($mapsWon / $totalMaps) * 100, 1) : 0,
            'average_match_duration' => $this->calculateAverageMatchDuration($matches),
            'most_played_maps' => $this->getMostPlayedMaps($matches)
        ];
    }

    private function calculateAverageMatchDuration($matches)
    {
        // For now, return null until we can properly implement this
        return null;
    }

    private function getMostPlayedMaps($matches)
    {
        $mapCounts = [];
        
        foreach ($matches as $match) {
            // Parse maps data from JSON if available
            $mapsData = isset($match->maps_data) && $match->maps_data 
                ? json_decode($match->maps_data, true) 
                : [];
                
            if (empty($mapsData)) {
                // If no maps data, use current_map field
                $mapName = $match->current_map ?? 'Unknown';
                if (!isset($mapCounts[$mapName])) {
                    $mapCounts[$mapName] = ['played' => 0, 'won' => 0];
                }
                $mapCounts[$mapName]['played']++;
                // For now, just mark as played without determining win/loss
            } else {
                // Process actual maps data
                foreach ($mapsData as $map) {
                    $mapName = $map['map_name'] ?? 'Unknown';
                    if (!isset($mapCounts[$mapName])) {
                        $mapCounts[$mapName] = ['played' => 0, 'won' => 0];
                    }
                    $mapCounts[$mapName]['played']++;
                    if (isset($map['result']) && $map['result'] === 'W') {
                        $mapCounts[$mapName]['won']++;
                    }
                }
            }
        }
        
        return collect($mapCounts)->map(function($stats, $mapName) {
            return [
                'map_name' => $mapName,
                'times_played' => $stats['played'],
                'wins' => $stats['won'],
                'win_rate' => $stats['played'] > 0 ? round(($stats['won'] / $stats['played']) * 100, 1) : 0
            ];
        })->sortByDesc('times_played')->take(5)->values();
    }

    private function getDateFromPeriod($period)
    {
        switch ($period) {
            case 'last_7_days':
                return now()->subDays(7);
            case 'last_30_days':
                return now()->subDays(30);
            case 'last_90_days':
                return now()->subDays(90);
            case 'this_year':
                return now()->startOfYear();
            default:
                return null;
        }
    }

    private function calculateOverviewStats($matches, $teamId)
    {
        $wins = 0;
        $losses = 0;
        $totalKills = 0;
        $totalDeaths = 0;
        
        foreach ($matches as $match) {
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            if ($teamScore > $opponentScore) {
                $wins++;
            } else {
                $losses++;
            }
            
            // Add kill/death stats if available
            if (isset($match->player_stats) && $match->player_stats) {
                $stats = json_decode($match->player_stats, true);
                $teamStats = $isTeam1 ? ($stats['team1_stats'] ?? []) : ($stats['team2_stats'] ?? []);
                $totalKills += $teamStats['total_kills'] ?? 0;
                $totalDeaths += $teamStats['total_deaths'] ?? 0;
            }
        }
        
        $totalMatches = $wins + $losses;
        
        return [
            'total_matches' => $totalMatches,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0,
            'avg_kills_per_match' => $totalMatches > 0 ? round($totalKills / $totalMatches, 1) : 0,
            'avg_deaths_per_match' => $totalMatches > 0 ? round($totalDeaths / $totalMatches, 1) : 0,
            'kd_ratio' => $totalDeaths > 0 ? round($totalKills / $totalDeaths, 2) : 0
        ];
    }

    private function calculateMapStats($matches, $teamId)
    {
        $mapStats = [];
        
        foreach ($matches as $match) {
            if (!$match->maps_data) continue;
            
            $mapsData = json_decode($match->maps_data, true);
            $isTeam1 = $match->team1_id == $teamId;
            
            foreach ($mapsData as $map) {
                $mapName = $map['map_name'] ?? 'Unknown';
                if (!isset($mapStats[$mapName])) {
                    $mapStats[$mapName] = ['played' => 0, 'won' => 0];
                }
                
                $mapStats[$mapName]['played']++;
                
                $teamScore = $isTeam1 ? ($map['team1_score'] ?? 0) : ($map['team2_score'] ?? 0);
                $opponentScore = $isTeam1 ? ($map['team2_score'] ?? 0) : ($map['team1_score'] ?? 0);
                
                if ($teamScore > $opponentScore) {
                    $mapStats[$mapName]['won']++;
                }
            }
        }
        
        return collect($mapStats)->map(function($stats, $mapName) {
            return [
                'map_name' => $mapName,
                'times_played' => $stats['played'],
                'wins' => $stats['won'],
                'losses' => $stats['played'] - $stats['won'],
                'win_rate' => $stats['played'] > 0 ? round(($stats['won'] / $stats['played']) * 100, 1) : 0
            ];
        })->sortByDesc('times_played')->values();
    }

    private function calculateRegionStats($matches, $teamId)
    {
        $regionStats = [];
        
        foreach ($matches as $match) {
            $isTeam1 = $match->team1_id == $teamId;
            $opponentRegion = $isTeam1 ? $match->team2_region : $match->team1_region;
            
            if (!$opponentRegion) continue;
            
            if (!isset($regionStats[$opponentRegion])) {
                $regionStats[$opponentRegion] = ['played' => 0, 'won' => 0];
            }
            
            $regionStats[$opponentRegion]['played']++;
            
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            if ($teamScore > $opponentScore) {
                $regionStats[$opponentRegion]['won']++;
            }
        }
        
        return collect($regionStats)->map(function($stats, $region) {
            return [
                'region' => $region,
                'matches_played' => $stats['played'],
                'wins' => $stats['won'],
                'losses' => $stats['played'] - $stats['won'],
                'win_rate' => $stats['played'] > 0 ? round(($stats['won'] / $stats['played']) * 100, 1) : 0
            ];
        })->sortByDesc('matches_played')->values();
    }

    private function calculateEventTierStats($matches, $teamId)
    {
        $tierStats = [];
        
        foreach ($matches as $match) {
            $tier = $match->event_tier ?? 'Unknown';
            
            if (!isset($tierStats[$tier])) {
                $tierStats[$tier] = ['played' => 0, 'won' => 0];
            }
            
            $tierStats[$tier]['played']++;
            
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            if ($teamScore > $opponentScore) {
                $tierStats[$tier]['won']++;
            }
        }
        
        return collect($tierStats)->map(function($stats, $tier) {
            return [
                'tier' => $tier,
                'matches_played' => $stats['played'],
                'wins' => $stats['won'],
                'losses' => $stats['played'] - $stats['won'],
                'win_rate' => $stats['played'] > 0 ? round(($stats['won'] / $stats['played']) * 100, 1) : 0
            ];
        })->sortBy(function($item) {
            // Sort by tier importance
            $order = ['S' => 1, 'A' => 2, 'B' => 3, 'C' => 4, 'Unknown' => 5];
            return $order[$item['tier']] ?? 6;
        })->values();
    }

    private function calculateFormStats($matches, $teamId)
    {
        $recentMatches = $matches->sortByDesc('scheduled_at')->take(10);
        $form = [];
        
        foreach ($recentMatches as $match) {
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            $form[] = [
                'result' => $teamScore > $opponentScore ? 'W' : 'L',
                'score' => "{$teamScore}-{$opponentScore}",
                'date' => $match->scheduled_at
            ];
        }
        
        return $form;
    }

    private function getBestMaps($matches, $teamId)
    {
        $mapStats = $this->calculateMapStats($matches, $teamId);
        
        return collect($mapStats)
            ->filter(function($map) {
                return $map['times_played'] >= 3; // Minimum 3 times played
            })
            ->sortByDesc('win_rate')
            ->take(3)
            ->values();
    }

    private function getWorstMaps($matches, $teamId)
    {
        $mapStats = $this->calculateMapStats($matches, $teamId);
        
        return collect($mapStats)
            ->filter(function($map) {
                return $map['times_played'] >= 3; // Minimum 3 times played
            })
            ->sortBy('win_rate')
            ->take(3)
            ->values();
    }

    private function calculateComebackStats($matches, $teamId)
    {
        $comebacks = 0;
        $reverseSweeps = 0;
        
        foreach ($matches as $match) {
            if (!$match->maps_data) continue;
            
            $mapsData = json_decode($match->maps_data, true);
            $isTeam1 = $match->team1_id == $teamId;
            
            // Check for comebacks (being down and winning)
            $mapResults = [];
            foreach ($mapsData as $map) {
                $teamScore = $isTeam1 ? ($map['team1_score'] ?? 0) : ($map['team2_score'] ?? 0);
                $opponentScore = $isTeam1 ? ($map['team2_score'] ?? 0) : ($map['team1_score'] ?? 0);
                $mapResults[] = $teamScore > $opponentScore ? 'W' : 'L';
            }
            
            // Check for reverse sweep (down 0-2, win 3-2)
            if (count($mapResults) === 5) {
                if ($mapResults[0] === 'L' && $mapResults[1] === 'L' && 
                    $mapResults[2] === 'W' && $mapResults[3] === 'W' && $mapResults[4] === 'W') {
                    $reverseSweeps++;
                    $comebacks++;
                }
            }
        }
        
        return [
            'comebacks' => $comebacks,
            'reverse_sweeps' => $reverseSweeps
        ];
    }

    private function calculateCloseMatchStats($matches, $teamId)
    {
        $closeMatches = 0;
        $overtimeMatches = 0;
        
        foreach ($matches as $match) {
            $isTeam1 = $match->team1_id == $teamId;
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            
            // Close match: decided by 1 map
            if (abs($teamScore - $opponentScore) === 1) {
                $closeMatches++;
            }
            
            // Check for overtime in match stats
            if (isset($match->player_stats) && $match->player_stats) {
                $stats = json_decode($match->player_stats, true);
                if (isset($stats['had_overtime']) && $stats['had_overtime']) {
                    $overtimeMatches++;
                }
            }
        }
        
        return [
            'close_matches' => $closeMatches,
            'overtime_matches' => $overtimeMatches
        ];
    }

    /**
     * Get country flag emoji for a given country name or code
     */
    private function getCountryFlag($country)
    {
        // Return country flag emoji or URL
        $flags = [
            // North America - Full names
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'Mexico' => '🇲🇽',
            
            // North America - Country codes
            'US' => '🇺🇸',
            'USA' => '🇺🇸',
            'CA' => '🇨🇦',
            'MX' => '🇲🇽',
            
            // South America
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Colombia' => '🇨🇴',
            'Peru' => '🇵🇪',
            'BR' => '🇧🇷',
            'AR' => '🇦🇷',
            'CL' => '🇨🇱',
            'CO' => '🇨🇴',
            'PE' => '🇵🇪',
            
            // Europe - Full names
            'United Kingdom' => '🇬🇧',
            'France' => '🇫🇷',
            'Germany' => '🇩🇪',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Sweden' => '🇸🇪',
            'Denmark' => '🇩🇰',
            'Norway' => '🇳🇴',
            'Finland' => '🇫🇮',
            'Poland' => '🇵🇱',
            'Russia' => '🇷🇺',
            'Turkey' => '🇹🇷',
            'Ukraine' => '🇺🇦',
            'Czech Republic' => '🇨🇿',
            'Portugal' => '🇵🇹',
            'Belgium' => '🇧🇪',
            'Austria' => '🇦🇹',
            'Switzerland' => '🇨🇭',
            
            // Europe - Country codes
            'EU' => '🇪🇺', // European Union flag for mixed European teams
            'GB' => '🇬🇧',
            'UK' => '🇬🇧',
            'FR' => '🇫🇷',
            'DE' => '🇩🇪',
            'ES' => '🇪🇸',
            'IT' => '🇮🇹',
            'NL' => '🇳🇱',
            'SE' => '🇸🇪',
            'DK' => '🇩🇰',
            'NO' => '🇳🇴',
            'FI' => '🇫🇮',
            'PL' => '🇵🇱',
            'RU' => '🇷🇺',
            'TR' => '🇹🇷',
            'UA' => '🇺🇦',
            'CZ' => '🇨🇿',
            'PT' => '🇵🇹',
            'BE' => '🇧🇪',
            'AT' => '🇦🇹',
            'CH' => '🇨🇭',
            
            // Asia - Full names
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Singapore' => '🇸🇬',
            'Thailand' => '🇹🇭',
            'Malaysia' => '🇲🇾',
            'Philippines' => '🇵🇭',
            'Indonesia' => '🇮🇩',
            'Vietnam' => '🇻🇳',
            'India' => '🇮🇳',
            
            // Asia - Country codes
            'KR' => '🇰🇷',
            'JP' => '🇯🇵',
            'CN' => '🇨🇳',
            'TW' => '🇹🇼',
            'HK' => '🇭🇰',
            'SG' => '🇸🇬',
            'TH' => '🇹🇭',
            'MY' => '🇲🇾',
            'PH' => '🇵🇭',
            'ID' => '🇮🇩',
            'VN' => '🇻🇳',
            'IN' => '🇮🇳',
            
            // Oceania
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'AU' => '🇦🇺',
            'NZ' => '🇳🇿',
            
            // Africa
            'South Africa' => '🇿🇦',
            'ZA' => '🇿🇦',
            
            // Middle East
            'Israel' => '🇮🇱',
            'United Arab Emirates' => '🇦🇪',
            'IL' => '🇮🇱',
            'AE' => '🇦🇪',
            
            // Special cases
            'Free Agent' => '🌐',
            'International' => '🌍',
            'Unknown' => '❓'
        ];
        
        return $flags[$country] ?? '🌍';
    }

    /**
     * Test team logos with fallback support
     */
    public function testTeamLogos()
    {
        try {
            $teams = DB::table('teams')
                ->select(['id', 'name', 'logo'])
                ->limit(20)
                ->get();

            $logoTests = $teams->map(function($team) {
                $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
                
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'logo_path' => $team->logo,
                    'resolved_url' => $logoInfo['url'],
                    'exists' => $logoInfo['exists'],
                    'fallback' => $logoInfo['fallback'],
                    'status' => $logoInfo['exists'] ? 'Found' : 'Missing - Will show fallback'
                ];
            });

            return response()->json([
                'data' => $logoTests,
                'total' => $logoTests->count(),
                'success' => true,
                'note' => 'This endpoint tests team logo resolution with fallback support'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing team logos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all team logos with status
     */
    public function getAllTeamLogos()
    {
        try {
            $teams = DB::table('teams')
                ->select(['id', 'name', 'short_name', 'logo'])
                ->get();

            $teamLogos = $teams->map(function($team) {
                $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
                
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo_url' => $logoInfo['url'],
                    'logo_exists' => $logoInfo['exists'],
                    'fallback_text' => $logoInfo['fallback']['text'],
                    'fallback_color' => $logoInfo['fallback']['color'],
                    'original_path' => $team->logo
                ];
            });

            $missingLogos = $teamLogos->where('logo_exists', false);

            return response()->json([
                'data' => $teamLogos,
                'total' => $teamLogos->count(),
                'missing_count' => $missingLogos->count(),
                'missing_teams' => $missingLogos->pluck('name'),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team logos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team achievements
     * GET /api/teams/{id}/achievements
     */
    public function getAchievements($id)
    {
        try {
            $team = DB::table('teams')->where('id', $id)->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            // Get achievements from team record
            $achievements = [];
            if ($team->achievements) {
                $achievements = json_decode($team->achievements, true) ?: [];
            }

            // Get tournament placements and wins
            $tournamentResults = DB::table('matches as m')
                ->join('events as e', 'm.event_id', '=', 'e.id')
                ->where(function($query) use ($id) {
                    $query->where('m.team1_id', $id)
                          ->orWhere('m.team2_id', $id);
                })
                ->where('m.status', 'completed')
                ->select([
                    'e.id as event_id',
                    'e.name as event_name',
                    'e.tier as event_tier',
                    'e.start_date',
                    'e.end_date',
                    'e.prize_pool',
                    'e.logo as event_logo',
                    'm.winner_id',
                    'm.team1_id',
                    'm.team2_id',
                    'm.team1_score',
                    'm.team2_score'
                ])
                ->get();

            // Process tournament results to extract achievements
            $tournamentAchievements = [];
            $eventStats = [];

            foreach ($tournamentResults as $result) {
                $eventId = $result->event_id;
                
                if (!isset($eventStats[$eventId])) {
                    $eventStats[$eventId] = [
                        'event_id' => $result->event_id,
                        'event_name' => $result->event_name,
                        'event_tier' => $result->event_tier,
                        'event_logo' => $result->event_logo,
                        'start_date' => $result->start_date,
                        'end_date' => $result->end_date,
                        'prize_pool' => $result->prize_pool,
                        'matches_played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'maps_won' => 0,
                        'maps_lost' => 0
                    ];
                }

                $eventStats[$eventId]['matches_played']++;
                
                $isTeam1 = $result->team1_id == $id;
                $teamScore = $isTeam1 ? $result->team1_score : $result->team2_score;
                $opponentScore = $isTeam1 ? $result->team2_score : $result->team1_score;
                
                if ($result->winner_id == $id || ($teamScore > $opponentScore)) {
                    $eventStats[$eventId]['wins']++;
                } else {
                    $eventStats[$eventId]['losses']++;
                }

                $eventStats[$eventId]['maps_won'] += $teamScore;
                $eventStats[$eventId]['maps_lost'] += $opponentScore;
            }

            // Convert event stats to achievements format
            foreach ($eventStats as $event) {
                $winRate = $event['matches_played'] > 0 ? round(($event['wins'] / $event['matches_played']) * 100, 1) : 0;
                
                // Determine placement based on performance
                $placement = 'Participated';
                if ($winRate >= 80) {
                    $placement = '1st Place';
                } elseif ($winRate >= 60) {
                    $placement = '2nd-3rd Place';
                } elseif ($winRate >= 40) {
                    $placement = 'Top 8';
                } elseif ($winRate >= 25) {
                    $placement = 'Top 16';
                }

                $tournamentAchievements[] = [
                    'id' => 'tournament_' . $event['event_id'],
                    'type' => 'tournament',
                    'title' => $event['event_name'],
                    'description' => $placement . ' - ' . $event['wins'] . 'W/' . $event['losses'] . 'L',
                    'date' => $event['end_date'] ?: $event['start_date'],
                    'tier' => $event['event_tier'],
                    'icon' => $event['event_logo'],
                    'metadata' => [
                        'event_id' => $event['event_id'],
                        'matches_played' => $event['matches_played'],
                        'wins' => $event['wins'],
                        'losses' => $event['losses'],
                        'win_rate' => $winRate,
                        'maps_won' => $event['maps_won'],
                        'maps_lost' => $event['maps_lost'],
                        'placement' => $placement,
                        'prize_pool' => $event['prize_pool']
                    ]
                ];
            }

            // Get milestone achievements based on team stats
            $milestoneAchievements = [];
            
            if ($team->tournaments_won && $team->tournaments_won > 0) {
                $milestoneAchievements[] = [
                    'id' => 'tournaments_won',
                    'type' => 'milestone',
                    'title' => 'Tournament Champion',
                    'description' => $team->tournaments_won . ' tournament' . ($team->tournaments_won > 1 ? 's' : '') . ' won',
                    'date' => null,
                    'tier' => 'gold',
                    'icon' => '/images/achievements/champion.png',
                    'metadata' => [
                        'count' => $team->tournaments_won
                    ]
                ];
            }

            if ($team->longest_win_streak && $team->longest_win_streak >= 5) {
                $milestoneAchievements[] = [
                    'id' => 'win_streak',
                    'type' => 'milestone',
                    'title' => 'Win Streak Master',
                    'description' => $team->longest_win_streak . ' match win streak',
                    'date' => null,
                    'tier' => 'silver',
                    'icon' => '/images/achievements/streak.png',
                    'metadata' => [
                        'streak_length' => $team->longest_win_streak
                    ]
                ];
            }

            if ($team->rating && $team->rating >= 1500) {
                $milestoneAchievements[] = [
                    'id' => 'high_rating',
                    'type' => 'milestone',
                    'title' => 'Elite Team',
                    'description' => 'Reached ' . $team->rating . ' rating',
                    'date' => null,
                    'tier' => 'gold',
                    'icon' => '/images/achievements/elite.png',
                    'metadata' => [
                        'rating' => $team->rating,
                        'peak_rating' => $team->peak
                    ]
                ];
            }

            // Combine all achievements
            $allAchievements = array_merge($achievements, $tournamentAchievements, $milestoneAchievements);
            
            // Sort by date (most recent first)
            usort($allAchievements, function($a, $b) {
                $dateA = $a['date'] ?? '1970-01-01';
                $dateB = $b['date'] ?? '1970-01-01';
                return strtotime($dateB) <=> strtotime($dateA);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'team_id' => $id,
                    'team_name' => $team->name,
                    'total_achievements' => count($allAchievements),
                    'achievements' => $allAchievements,
                    'summary' => [
                        'tournaments' => count($tournamentAchievements),
                        'milestones' => count($milestoneAchievements),
                        'custom' => count($achievements)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching team achievements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update coach information for a team
     */
    public function updateCoach(Request $request, $teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $validated = $request->validate([
                'coach_name' => 'nullable|string|max:255',
                'coach_nationality' => 'nullable|string|max:100',
                'coach_social_media' => 'nullable|array',
                'coach_social_media.twitter' => 'nullable|string',
                'coach_social_media.instagram' => 'nullable|string',
                'coach_social_media.linkedin' => 'nullable|string'
            ]);

            $updateData = [];
            
            if (isset($validated['coach_name'])) {
                $updateData['coach_name'] = $validated['coach_name'];
                $updateData['coach'] = $validated['coach_name']; // Update legacy field
            }
            
            if (isset($validated['coach_nationality'])) {
                $updateData['coach_nationality'] = $validated['coach_nationality'];
            }
            
            if (isset($validated['coach_social_media'])) {
                $updateData['coach_social_media'] = json_encode($validated['coach_social_media']);
            }
            
            $updateData['updated_at'] = now();
            
            DB::table('teams')->where('id', $teamId)->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Coach information updated successfully',
                'data' => [
                    'coach_name' => $validated['coach_name'] ?? $team->coach_name,
                    'coach_nationality' => $validated['coach_nationality'] ?? $team->coach_nationality,
                    'coach_social_media' => isset($validated['coach_social_media']) ? 
                        $validated['coach_social_media'] : 
                        json_decode($team->coach_social_media, true)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating coach information: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get coach information for a team
     */
    public function getCoach($teamId)
    {
        try {
            $team = DB::table('teams')
                ->where('id', $teamId)
                ->select(['id', 'name', 'coach', 'coach_name', 'coach_nationality', 
                         'coach_image', 'coach_picture', 'coach_social_media'])
                ->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $coachImage = $team->coach_image ?? $team->coach_picture;
            $coachImageInfo = null;
            
            if ($coachImage) {
                $coachImageInfo = ImageHelper::getCoachImage($coachImage, $team->coach_name);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'coach_name' => $team->coach_name ?? $team->coach,
                    'coach_nationality' => $team->coach_nationality,
                    'coach_image' => $coachImageInfo ? $coachImageInfo['url'] : null,
                    'coach_image_exists' => $coachImageInfo ? $coachImageInfo['exists'] : false,
                    'coach_social_media' => $team->coach_social_media ? 
                        json_decode($team->coach_social_media, true) : []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching coach information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload coach image for a team
     */
    public function uploadCoachImage(Request $request, $teamId)
    {
        try {
            $team = DB::table('teams')->where('id', $teamId)->first();
            
            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found'
                ], 404);
            }

            $request->validate([
                'coach_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);

            $image = $request->file('coach_image');
            $imageName = 'coach_' . time() . '.' . $image->extension();
            
            // Store in public/teams/coaches directory
            $image->move(public_path('teams/coaches'), $imageName);
            
            $imagePath = '/teams/coaches/' . $imageName;
            
            // Update team with coach image path
            DB::table('teams')->where('id', $teamId)->update([
                'coach_image' => $imagePath,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Coach image uploaded successfully',
                'data' => [
                    'coach_image_url' => $imagePath
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading coach image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete teams
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'team_ids' => 'required|array',
                'team_ids.*' => 'integer|exists:teams,id'
            ]);

            $teamIds = $validated['team_ids'];
            
            // Get team names for logging
            $teams = DB::table('teams')
                ->whereIn('id', $teamIds)
                ->pluck('name', 'id');
            
            // Delete teams (this will cascade to related records if foreign keys are set up)
            $deletedCount = DB::table('teams')
                ->whereIn('id', $teamIds)
                ->delete();
            
            Log::info('Bulk deleted teams', [
                'count' => $deletedCount,
                'team_ids' => $teamIds,
                'team_names' => $teams->toArray()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "$deletedCount teams deleted successfully",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in bulk delete teams', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting teams: ' . $e->getMessage()
            ], 500);
        }
    }

}
