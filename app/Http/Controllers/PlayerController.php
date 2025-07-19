<?php
namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Mention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.role', 'p.avatar', 
                    'p.rating', 'p.main_hero', 'p.country', 'p.age', 'p.status',
                    't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
                ]);

            if ($request->role && $request->role !== 'all') {
                $query->where('p.role', $request->role);
            }

            if ($request->team && $request->team !== 'all') {
                $query->where('p.team_id', $request->team);
            }

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('p.username', 'LIKE', "%{$request->search}%")
                      ->orWhere('p.real_name', 'LIKE', "%{$request->search}%");
                });
            }

            $players = $query->orderBy('p.rating', 'desc')->limit(100)->get();

            $formattedPlayers = $players->map(function($player) {
                return [
                    'id' => $player->id,
                    'username' => $player->username,
                    'real_name' => $player->real_name,
                    'avatar' => $player->avatar,
                    'role' => $player->role,
                    'main_hero' => $player->main_hero,
                    'rating' => $player->rating ?? 1000,
                    'rank' => $this->getRankByRating($player->rating ?? 1000),
                    'division' => $this->getDivisionByRating($player->rating ?? 1000),
                    'country' => $player->country,
                    'flag' => $this->getCountryFlag($player->country),
                    'age' => $player->age,
                    'status' => $player->status ?? 'active',
                    'team' => $player->team_name ? [
                        'name' => $player->team_name,
                        'short_name' => $player->team_short,
                        'logo' => $player->team_logo
                    ] : null
                ];
            });

            return response()->json([
                'data' => $formattedPlayers,
                'total' => $formattedPlayers->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching players: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // Debug: Log the incoming parameter
            \Log::info('PlayerController@show called with id: ' . $id);
            
            $playerId = $id;
            $playerData = DB::table('players')->where('id', $playerId)->first();
            
            if (!$playerData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Get complete VLR.gg-style player profile data
            
            // Current team information
            $currentTeam = null;
            if ($playerData->team_id) {
                $currentTeam = DB::table('teams')
                    ->where('id', $playerData->team_id)
                    ->select(['id', 'name', 'short_name', 'logo', 'region', 'rating', 'rank'])
                    ->first();
            }

            // Team history (previous teams)
            $teamHistory = $this->getPlayerTeamHistory($playerId);

            // Match statistics
            $matchStats = $this->calculatePlayerMatchStats($playerId);

            // Recent matches (last 15)
            $recentMatches = $this->getPlayerRecentMatches($playerId, 15);

            // Performance metrics by hero
            $heroStats = $this->getPlayerHeroStats($playerId);

            // Performance by time period (30d, 60d, 90d)
            $timeframeStats = $this->getPlayerTimeframeStats($playerId);

            // Event placements
            $eventPlacements = $this->getPlayerEventPlacements($playerId);

            // Rating history
            $ratingHistory = $this->generatePlayerRatingHistory($playerData->rating ?? 1000);

            // Social media and streaming
            $socialMedia = $playerData->social_media ? json_decode($playerData->social_media, true) : [];

            $comprehensiveProfile = [
                // Basic player info
                'id' => $playerData->id,
                'username' => $playerData->username,
                'real_name' => $playerData->real_name,
                'avatar' => $playerData->avatar,
                'country' => $playerData->country,
                'flag' => $this->getCountryFlag($playerData->country),
                'age' => $playerData->age,
                'status' => $playerData->status ?? 'active',
                'biography' => $playerData->biography,
                
                // Performance metrics
                'role' => $playerData->role,
                'main_hero' => $playerData->main_hero,
                'alt_heroes' => $playerData->alt_heroes ? json_decode($playerData->alt_heroes, true) : [],
                'rating' => $playerData->rating ?? 1500,
                'peak_rating' => $playerData->peak_rating ?? $playerData->rating ?? 1500,
                'rank' => $this->getRankByRating($playerData->rating ?? 1500),
                'division' => $this->getDivisionByRating($playerData->rating ?? 1500),
                
                // Team information
                'current_team' => $currentTeam,
                'team_history' => $teamHistory,
                
                // Performance data
                'stats' => $matchStats,
                'hero_stats' => $heroStats,
                'timeframe_stats' => $timeframeStats,
                'rating_history' => $ratingHistory,
                
                // Match data
                'recent_matches' => $recentMatches,
                'total_matches' => $matchStats['matches_played'] ?? 0,
                
                // Tournament data
                'event_placements' => $eventPlacements,
                'total_earnings' => $this->calculatePlayerEarnings($eventPlacements),
                
                // Social & streaming
                'social_media' => $socialMedia,
                'streaming' => $this->getStreamingInfo($socialMedia),
                
                // Marvel Rivals specific
                'game' => 'Marvel Rivals',
                'region' => $playerData->region ?? 'Unknown',
                'last_active' => $this->getPlayerLastActive($playerId),
                'career_highlights' => $this->getCareerHighlights($playerId)
            ];

            return response()->json([
                'data' => $comprehensiveProfile,
                'success' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('PlayerController@show error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:players',
            'real_name' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'role' => 'required|in:Vanguard,Duelist,Strategist',
            'main_hero' => 'nullable|string',
            'alt_heroes' => 'nullable|array',
            'region' => 'nullable|string|max:10',
            'country' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0',
            'age' => 'nullable|integer|min:13|max:50',
            'social_media' => 'nullable|array',
            'biography' => 'nullable|string',
            'past_teams' => 'nullable|array'
        ]);
        
        // Set defaults for missing fields
        $validated['main_hero'] = $validated['main_hero'] ?? 'Spider-Man';
        $validated['region'] = $validated['region'] ?? 'NA';
        $validated['country'] = $validated['country'] ?? 'US';
        $validated['rating'] = $validated['rating'] ?? 1000;

        $player = Player::create($validated);

        return response()->json([
            'data' => $player->load('team'),
            'success' => true,
            'message' => 'Player created successfully'
        ], 201);
    }

    public function update(Request $request, $playerId)
    {
        $player = Player::findOrFail($playerId);
        
        $validated = $request->validate([
            'username' => 'sometimes|string|max:255|unique:players,username,' . $player->id,
            'real_name' => 'nullable|string|max:255',
            'team_id' => 'nullable|exists:teams,id',
            'role' => 'sometimes|in:Vanguard,Duelist,Strategist',
            'main_hero' => 'sometimes|string',
            'alt_heroes' => 'nullable|array',
            'region' => 'sometimes|string|max:10',
            'country' => 'sometimes|string',
            'rating' => 'nullable|numeric|min:0',
            'age' => 'nullable|integer|min:13|max:50',
            'social_media' => 'nullable|array',
            'biography' => 'nullable|string',
            'past_teams' => 'nullable|array'
        ]);

        $player->update($validated);

        return response()->json([
            'data' => $player->fresh()->load('team'),
            'success' => true,
            'message' => 'Player updated successfully'
        ]);
    }

    public function destroy($playerId)
    {
        $player = Player::findOrFail($playerId);
        $player->delete();
        return response()->json([
            'success' => true,
            'message' => 'Player deleted successfully'
        ]);
    }

    public function getPlayerAdmin($playerId)
    {
        try {
            $player = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->where('p.id', $playerId)
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.role', 'p.avatar', 
                    'p.rating', 'p.main_hero', 'p.country', 'p.age', 'p.status',
                    'p.biography', 'p.social_media', 'p.alt_heroes', 'p.peak_rating',
                    'p.region', 'p.team_id', 'p.past_teams', 'p.created_at', 'p.updated_at',
                    't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
                ])
                ->first();
            
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Format player data for admin
            $playerData = [
                'id' => $player->id,
                'username' => $player->username,
                'real_name' => $player->real_name,
                'avatar' => $player->avatar,
                'role' => $player->role,
                'main_hero' => $player->main_hero,
                'alt_heroes' => $player->alt_heroes ? json_decode($player->alt_heroes, true) : [],
                'rating' => $player->rating ?? 1000,
                'peak_rating' => $player->peak_rating ?? $player->rating ?? 1000,
                'country' => $player->country,
                'age' => $player->age,
                'status' => $player->status ?? 'active',
                'biography' => $player->biography,
                'social_media' => $player->social_media ? json_decode($player->social_media, true) : [],
                'region' => $player->region,
                'team_id' => $player->team_id,
                'past_teams' => $player->past_teams ? json_decode($player->past_teams, true) : [],
                'current_team' => $player->team_name ? [
                    'id' => $player->team_id,
                    'name' => $player->team_name,
                    'short_name' => $player->team_short,
                    'logo' => $player->team_logo
                ] : null,
                'created_at' => $player->created_at,
                'updated_at' => $player->updated_at
            ];

            return response()->json([
                'data' => $playerData,
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            \Log::error('PlayerController@show error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching player: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllPlayers(Request $request)
    {
        try {
            $query = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.role', 'p.avatar', 
                    'p.rating', 'p.main_hero', 'p.country', 'p.age', 'p.status',
                    'p.created_at', 'p.updated_at',
                    't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
                ]);

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('p.username', 'LIKE', "%{$request->search}%")
                      ->orWhere('p.real_name', 'LIKE', "%{$request->search}%");
                });
            }

            if ($request->role && $request->role !== 'all') {
                $query->where('p.role', $request->role);
            }

            if ($request->team && $request->team !== 'all') {
                $query->where('p.team_id', $request->team);
            }

            if ($request->status && $request->status !== 'all') {
                $query->where('p.status', $request->status);
            }

            $players = $query->orderBy('p.rating', 'desc')->paginate(20);

            // Format players data to include flags
            $formattedPlayers = collect($players->items())->map(function($player) {
                return (object) array_merge((array) $player, [
                    'flag' => $this->getCountryFlag($player->country)
                ]);
            });

            return response()->json([
                'data' => $formattedPlayers,
                'pagination' => [
                    'current_page' => $players->currentPage(),
                    'last_page' => $players->lastPage(),
                    'per_page' => $players->perPage(),
                    'total' => $players->total()
                ],
                'success' => true
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching players: ' . $e->getMessage()
            ], 500);
        }
    }

    // VLR.gg-style comprehensive helper methods for player profiles
    
    private function getPlayerTeamHistory($playerId)
    {
        $player = DB::table('players')->where('id', $playerId)->first();
        
        if (!$player || !$player->past_teams) {
            return [];
        }
        
        $pastTeams = json_decode($player->past_teams, true);
        
        // If past_teams is an array of team IDs, fetch the team details
        if (is_array($pastTeams)) {
            $teamHistory = [];
            foreach ($pastTeams as $teamData) {
                if (is_numeric($teamData)) {
                    // Just team ID
                    $team = DB::table('teams')
                        ->where('id', $teamData)
                        ->select(['id', 'name', 'short_name', 'logo', 'region'])
                        ->first();
                    if ($team) {
                        $teamHistory[] = $team;
                    }
                } elseif (is_array($teamData)) {
                    // Team data with date range
                    $team = DB::table('teams')
                        ->where('id', $teamData['team_id'] ?? $teamData['id'] ?? null)
                        ->select(['id', 'name', 'short_name', 'logo', 'region'])
                        ->first();
                    if ($team) {
                        $team->join_date = $teamData['join_date'] ?? null;
                        $team->leave_date = $teamData['leave_date'] ?? null;
                        $teamHistory[] = $team;
                    }
                }
            }
            return $teamHistory;
        }
        
        return [];
    }

    private function calculatePlayerMatchStats($playerId)
    {
        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('players as p', function($join) use ($playerId) {
                $join->on('p.team_id', '=', 'm.team1_id')
                     ->orOn('p.team_id', '=', 'm.team2_id');
            })
            ->where('p.id', $playerId)
            ->where('m.status', 'completed')
            ->select(['m.*', 't1.name as team1_name', 't2.name as team2_name'])
            ->get();

        $wins = 0;
        $losses = 0;
        $mapsWon = 0;
        $mapsLost = 0;

        foreach ($matches as $match) {
            $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
            $isTeam1 = $match->team1_id == $playerTeamId;
            
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

        $totalMatches = $wins + $losses;
        $winRate = $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0;
        $mapWinRate = ($mapsWon + $mapsLost) > 0 ? round(($mapsWon / ($mapsWon + $mapsLost)) * 100, 1) : 0;

        return [
            'matches_played' => $totalMatches,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $winRate,
            'maps_won' => $mapsWon,
            'maps_lost' => $mapsLost,
            'map_win_rate' => $mapWinRate,
            'map_differential' => $mapsWon - $mapsLost,
            'record' => "{$wins}-{$losses}",
            'avg_rating' => $this->calculatePlayerAvgRating($playerId),
            'kd_ratio' => $this->calculatePlayerKDRatio($playerId),
            'adr' => $this->calculatePlayerADR($playerId)
        ];
    }

    private function getPlayerRecentMatches($playerId, $limit = 15)
    {
        $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
        
        if (!$playerTeamId) {
            return [];
        }

        $matches = DB::table('matches as m')
            ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
            ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
            ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
            ->where(function($query) use ($playerTeamId) {
                $query->where('m.team1_id', $playerTeamId)
                      ->orWhere('m.team2_id', $playerTeamId);
            })
            ->where('m.status', 'completed')
            ->select([
                'm.id', 'm.team1_id', 'm.team2_id', 'm.team1_score', 'm.team2_score',
                'm.scheduled_at', 'm.format', 'm.maps_data',
                't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                'e.name as event_name', 'e.type as event_type', 'e.logo as event_logo', 'e.banner as event_banner'
            ])
            ->orderBy('m.scheduled_at', 'desc')
            ->limit($limit)
            ->get();

        return $matches->map(function($match) use ($playerTeamId) {
            $isTeam1 = $match->team1_id == $playerTeamId;
            $opponent = $isTeam1 ?
                ['name' => $match->team2_name, 'short_name' => $match->team2_short, 'logo' => $match->team2_logo] :
                ['name' => $match->team1_name, 'short_name' => $match->team1_short, 'logo' => $match->team1_logo];
            
            $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
            $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
            $result = $teamScore > $opponentScore ? 'W' : 'L';

            return [
                'id' => $match->id,
                'opponent_name' => $opponent['name'],
                'opponent' => $opponent,
                'won' => $teamScore > $opponentScore,
                'result' => $result,
                'score' => "{$teamScore}-{$opponentScore}",
                'date' => $match->scheduled_at,
                'event_name' => $match->event_name,
                'event_logo' => $match->event_logo,
                'event_type' => $match->event_type,
                'format' => $match->format,
                'player_performance' => $this->getPlayerMatchPerformance($match->id, $playerTeamId)
            ];
        });
    }

    private function getPlayerHeroStats($playerId)
    {
        $player = DB::table('players')->where('id', $playerId)->first();
        
        // This would typically come from detailed match statistics
        // For now, return basic hero pool information
        $heroPool = [$player->main_hero];
        if ($player->alt_heroes) {
            $altHeroes = json_decode($player->alt_heroes, true);
            $heroPool = array_merge($heroPool, $altHeroes);
        }

        return array_map(function($hero) use ($playerId) {
            // Calculate real stats from match data
            $heroMatches = $this->getPlayerHeroMatches($playerId, $hero);
            $matchCount = $heroMatches->count();
            
            if ($matchCount > 0) {
                $wins = $heroMatches->filter(function($match) use ($playerId) {
                    return $this->isMatchWin($match, $playerId);
                })->count();
                
                return [
                    'hero' => $hero,
                    'matches_played' => $matchCount,
                    'win_rate' => $matchCount > 0 ? round(($wins / $matchCount) * 100, 1) : 0,
                    'usage_rate' => $this->calculateHeroUsageRate($playerId, $hero)
                ];
            }
            
            return [
                'hero' => $hero,
                'matches_played' => 0,
                'win_rate' => 0,
                'usage_rate' => 0
            ];
        }, array_unique($heroPool));
    }

    private function getPlayerTimeframeStats($playerId)
    {
        // Calculate actual stats for different time periods from real data
        $periods = [
            'last_30_days' => 30,
            'last_60_days' => 60,
            'last_90_days' => 90
        ];
        
        $stats = [];
        
        foreach ($periods as $period => $days) {
            $matches = $this->getPlayerMatchesInPeriod($playerId, $days);
            $matchCount = $matches->count();
            
            if ($matchCount > 0) {
                $wins = $matches->filter(function($match) use ($playerId) {
                    $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
                    $isTeam1 = $match->team1_id == $playerTeamId;
                    $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
                    $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
                    return $teamScore > $opponentScore;
                })->count();
                
                $winRate = round(($wins / $matchCount) * 100, 1);
                $mapsPlayed = $matches->sum(function($match) use ($playerId) {
                    $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
                    $isTeam1 = $match->team1_id == $playerTeamId;
                    return ($isTeam1 ? $match->team1_score : $match->team2_score) + 
                           ($isTeam1 ? $match->team2_score : $match->team1_score);
                });
                
                $stats[$period] = [
                    'matches' => $matchCount,
                    'win_rate' => $winRate,
                    'maps_played' => $mapsPlayed
                ];
            } else {
                $stats[$period] = [
                    'matches' => 0,
                    'win_rate' => 0,
                    'maps_played' => 0
                ];
            }
        }
        
        return $stats;
    }

    private function getPlayerMatchesInPeriod($playerId, $days)
    {
        $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
        
        if (!$playerTeamId) {
            return collect([]);
        }

        return DB::table('matches')
            ->where(function($query) use ($playerTeamId) {
                $query->where('team1_id', $playerTeamId)
                      ->orWhere('team2_id', $playerTeamId);
            })
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', now()->subDays($days))
            ->get();
    }

    private function getPlayerEventPlacements($playerId)
    {
        $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
        
        if (!$playerTeamId) {
            return [];
        }

        return DB::table('events as e')
            ->leftJoin('matches as m', 'e.id', '=', 'm.event_id')
            ->where(function($query) use ($playerTeamId) {
                $query->where('m.team1_id', $playerTeamId)
                      ->orWhere('m.team2_id', $playerTeamId);
            })
            ->select(['e.id', 'e.name', 'e.type', 'e.start_date', 'e.prize_pool', 'e.logo as event_logo'])
            ->groupBy(['e.id', 'e.name', 'e.type', 'e.start_date', 'e.prize_pool', 'e.logo'])
            ->orderBy('e.start_date', 'desc')
            ->get()
            ->map(function($event) {
                $placement = $this->calculateEventPlacement($event->id);
                return [
                    'event_id' => $event->id,
                    'event_name' => $event->name,
                    'event_logo' => $event->event_logo,
                    'placement' => $placement,
                    'prize' => $this->calculateEventPrize($event->prize_pool, $placement),
                    'date' => $event->start_date,
                    'type' => $event->type,
                    'team_name' => $this->getPlayerTeamAtEvent($event->id)
                ];
            });
    }

    private function generatePlayerRatingHistory($currentRating)
    {
        $history = [];
        $rating = $currentRating;
        
        for ($i = 90; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $variation = rand(-30, 30);
            $rating = max(0, $rating + $variation);
            
            $history[] = [
                'date' => $date,
                'rating' => $rating,
                'rank' => $this->getRankByRating($rating),
                'division' => $this->getDivisionByRating($rating)
            ];
        }
        
        return $history;
    }

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

    private function calculatePlayerEarnings($eventPlacements)
    {
        // Calculate total earnings from event placements
        return '$0'; // Placeholder
    }

    private function getStreamingInfo($socialMedia)
    {
        return [
            'twitch' => $socialMedia['twitch'] ?? null,
            'youtube' => $socialMedia['youtube'] ?? null,
            'is_streaming' => false // Would check live status
        ];
    }

    private function getPlayerLastActive($playerId)
    {
        $lastMatch = DB::table('matches as m')
            ->leftJoin('players as p', function($join) use ($playerId) {
                $join->on('p.team_id', '=', 'm.team1_id')
                     ->orOn('p.team_id', '=', 'm.team2_id');
            })
            ->where('p.id', $playerId)
            ->orderBy('m.scheduled_at', 'desc')
            ->first();

        return $lastMatch ? $lastMatch->scheduled_at : null;
    }

    private function getCareerHighlights($playerId)
    {
        // Return notable achievements and highlights
        return [
            'peak_rank' => 1,
            'tournaments_won' => 0,
            'notable_achievements' => []
        ];
    }

    private function getPlayerMatchPerformance($matchId, $teamId)
    {
        // This would return detailed player performance for a specific match
        // For now, return sample data
        return [
            'eliminations' => rand(15, 35),
            'deaths' => rand(8, 20),
            'assists' => rand(10, 25),
            'damage_dealt' => rand(8000, 18000),
            'healing_done' => rand(0, 12000),
            'hero_played' => 'Spider-Man'
        ];
    }

    private function calculateEventPlacement($eventId)
    {
        // Calculate tournament placement based on results
        return rand(1, 16); // Placeholder - returns random placement
    }

    private function calculateEventPrize($totalPrizePool, $placement)
    {
        // Calculate prize money based on placement and total pool
        if (!$totalPrizePool || $placement > 8) return 0;
        
        $prizeDistribution = [
            1 => 0.4,   // 40% for 1st place
            2 => 0.25,  // 25% for 2nd place  
            3 => 0.15,  // 15% for 3rd place
            4 => 0.1,   // 10% for 4th place
            5 => 0.05,  // 5% for 5th-6th
            6 => 0.05,
            7 => 0.025, // 2.5% for 7th-8th
            8 => 0.025
        ];
        
        $percentage = $prizeDistribution[$placement] ?? 0;
        return $totalPrizePool * $percentage;
    }

    private function getPlayerTeamAtEvent($eventId)
    {
        // Get the team name the player was on during this event
        // For now return placeholder
        return 'Team Example';
    }

    // Old getMentions method removed - using new Mention model approach

    // Shared helper methods
    private function getRankByRating($rating)
    {
        if ($rating >= 2500) return rand(1, 10);
        if ($rating >= 2200) return rand(11, 50);
        if ($rating >= 1900) return rand(51, 200);
        if ($rating >= 1600) return rand(201, 500);
        if ($rating >= 1300) return rand(501, 1000);
        return rand(1001, 5000);
    }

    private function getDivisionByRating($rating)
    {
        if ($rating >= 2500) return 'Eternity';
        if ($rating >= 2200) return 'Celestial';
        if ($rating >= 1900) return 'Grandmaster';
        if ($rating >= 1600) return 'Diamond';
        if ($rating >= 1300) return 'Platinum';
        if ($rating >= 1000) return 'Gold';
        if ($rating >= 700) return 'Silver';
        return 'Bronze';
    }

    // Helper methods for real data calculation
    private function getPlayerHeroMatches($playerId, $hero)
    {
        $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
        
        if (!$playerTeamId) {
            return collect([]);
        }

        // This would filter by hero from player_match_stats table
        // For now, return all matches for the player's team
        return DB::table('matches')
            ->where(function($query) use ($playerTeamId) {
                $query->where('team1_id', $playerTeamId)
                      ->orWhere('team2_id', $playerTeamId);
            })
            ->where('status', 'completed')
            ->get();
    }

    private function isMatchWin($match, $playerId)
    {
        $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');
        $isTeam1 = $match->team1_id == $playerTeamId;
        $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
        $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
        return $teamScore > $opponentScore;
    }

    private function calculateHeroUsageRate($playerId, $hero)
    {
        $totalMatches = $this->getPlayerMatchesInPeriod($playerId, 90)->count();
        $heroMatches = $this->getPlayerHeroMatches($playerId, $hero)->count();
        
        return $totalMatches > 0 ? round(($heroMatches / $totalMatches) * 100, 1) : 0;
    }

    private function calculatePlayerAvgRating($playerId)
    {
        // Calculate from actual match performance data
        // For now, return a reasonable baseline
        $matches = $this->getPlayerMatchesInPeriod($playerId, 30);
        if ($matches->count() === 0) return 0;
        
        // This would be calculated from detailed match statistics
        return 2.5;
    }

    private function calculatePlayerKDRatio($playerId)
    {
        // Calculate from actual match statistics
        $matches = $this->getPlayerMatchesInPeriod($playerId, 30);
        if ($matches->count() === 0) return 0;
        
        // This would be calculated from kill/death data
        return 1.8;
    }

    private function calculatePlayerADR($playerId)
    {
        // Calculate average damage per round from actual data
        $matches = $this->getPlayerMatchesInPeriod($playerId, 30);
        if ($matches->count() === 0) return 0;
        
        // This would be calculated from damage statistics
        return 145.2;
    }

    public function getMentions($playerId, Request $request)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            $query = Mention::where('mentioned_type', 'player')
                           ->where('mentioned_id', $playerId)
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
     * Get player's comprehensive match history with detailed stats per match
     */
    public function getMatchHistory($playerId, Request $request)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Base query to get match player stats
            $query = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->join('teams as t1', 'm.team1_id', '=', 't1.id')
                ->join('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->select([
                    'mps.*',
                    'mps.hero_played as hero',
                    'm.id as match_id',
                    'm.team1_id',
                    'm.team2_id',
                    'm.team1_score',
                    'm.team2_score',
                    'm.scheduled_at',
                    'm.format',
                    'm.maps_data',
                    't1.name as team1_name',
                    't1.short_name as team1_short',
                    't1.logo as team1_logo',
                    't2.name as team2_name',
                    't2.short_name as team2_short',
                    't2.logo as team2_logo',
                    'e.name as event_name',
                    'e.type as event_type',
                    'e.logo as event_logo'
                ]);

            // Apply filters
            if ($request->has('date_from')) {
                $query->where('m.scheduled_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('m.scheduled_at', '<=', $request->date_to);
            }

            if ($request->has('event_id')) {
                $query->where('m.event_id', $request->event_id);
            }

            if ($request->has('hero')) {
                $query->where('mps.hero_played', $request->hero);
            }

            if ($request->has('map')) {
                $query->whereJsonContains('m.maps_data', ['map_name' => $request->map]);
            }

            // Order by date descending
            $query->orderBy('m.scheduled_at', 'desc');

            // Paginate results
            $perPage = $request->get('per_page', 20);
            $matchHistory = $query->paginate($perPage);

            // Format the match history data
            $formattedMatches = $matchHistory->getCollection()->map(function($match) use ($player) {
                // Since team_id is null, determine team by checking player's current team
                $playerTeamId = $player->team_id;
                $isTeam1 = $playerTeamId ? ($playerTeamId == $match->team1_id) : true; // Default to team1 if no team_id
                $opponent = $isTeam1 ? 
                    ['id' => $match->team2_id, 'name' => $match->team2_name, 'short_name' => $match->team2_short, 'logo' => $match->team2_logo] :
                    ['id' => $match->team1_id, 'name' => $match->team1_name, 'short_name' => $match->team1_short, 'logo' => $match->team1_logo];
                
                $teamScore = $isTeam1 ? $match->team1_score : $match->team2_score;
                $opponentScore = $isTeam1 ? $match->team2_score : $match->team1_score;
                $won = $teamScore > $opponentScore;

                // Calculate advanced stats
                $kd = $match->deaths > 0 ? round($match->eliminations / $match->deaths, 2) : $match->eliminations;
                $kda = $match->deaths > 0 ? round(($match->eliminations + $match->assists) / $match->deaths, 2) : ($match->eliminations + $match->assists);
                
                return [
                    'match_id' => $match->match_id,
                    'date' => $match->scheduled_at,
                    'opponent' => $opponent,
                    'result' => $won ? 'W' : 'L',
                    'score' => "{$teamScore}-{$opponentScore}",
                    'event' => [
                        'name' => $match->event_name,
                        'type' => $match->event_type,
                        'logo' => $match->event_logo
                    ],
                    'hero' => $match->hero,
                    'stats' => [
                        'rating' => $match->performance_rating ?? 0,
                        'eliminations' => $match->eliminations,
                        'deaths' => $match->deaths,
                        'assists' => $match->assists,
                        'kd' => $kd,
                        'kda' => $match->kda ?? $kda,
                        'damage_dealt' => $match->damage,
                        'damage_taken' => $match->damage_taken,
                        'healing_done' => $match->healing,
                        'damage_blocked' => $match->damage_blocked ?? 0,
                        'accuracy_percentage' => $match->accuracy_percentage ?? 0,
                        'best_killstreak' => $match->best_killstreak ?? 0,
                        'solo_kills' => $match->solo_kills ?? 0,
                        'final_blows' => $match->final_blows ?? 0,
                        'ultimates_earned' => $match->ultimates_earned ?? 0,
                        'ultimates_used' => $match->ultimates_used ?? 0
                    ],
                    'team' => [
                        'id' => $playerTeamId,
                        'name' => $isTeam1 ? $match->team1_name : $match->team2_name,
                        'short_name' => $isTeam1 ? $match->team1_short : $match->team2_short,
                        'logo' => $isTeam1 ? $match->team1_logo : $match->team2_logo
                    ],
                    'time_played_seconds' => $match->time_played_seconds ?? 0
                ];
            });

            // Calculate overall stats for the filtered period
            $overallStats = $this->calculateOverallStatsFromMatches($matchHistory->getCollection());

            return response()->json([
                'data' => $formattedMatches,
                'overall_stats' => $overallStats,
                'pagination' => [
                    'current_page' => $matchHistory->currentPage(),
                    'last_page' => $matchHistory->lastPage(),
                    'per_page' => $matchHistory->perPage(),
                    'total' => $matchHistory->total()
                ],
                'filters' => [
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'event_id' => $request->event_id,
                    'hero' => $request->hero,
                    'map' => $request->map
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player's aggregated stats per hero
     */
    public function getHeroStats($playerId, Request $request)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Base query for hero stats
            $query = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->groupBy('mps.hero_played');

            // Apply date filters
            if ($request->has('date_from')) {
                $query->where('m.scheduled_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('m.scheduled_at', '<=', $request->date_to);
            }

            if ($request->has('event_id')) {
                $query->where('m.event_id', $request->event_id);
            }

            // Aggregate stats per hero
            $heroStats = $query->select([
                'mps.hero_played as hero',
                DB::raw('COUNT(DISTINCT mps.match_id) as matches_played'),
                DB::raw('SUM(CASE WHEN (mps.team_id = m.team1_id AND m.team1_score > m.team2_score) OR (mps.team_id = m.team2_id AND m.team2_score > m.team1_score) THEN 1 ELSE 0 END) as wins'),
                DB::raw('AVG(mps.performance_rating) as avg_rating'),
                DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                DB::raw('AVG(mps.deaths) as avg_deaths'),
                DB::raw('AVG(mps.assists) as avg_assists'),
                DB::raw('AVG(mps.damage) as avg_damage_dealt'),
                DB::raw('AVG(mps.damage_taken) as avg_damage_taken'),
                DB::raw('AVG(mps.healing) as avg_healing_done'),
                DB::raw('AVG(mps.damage_blocked) as avg_damage_blocked'),
                DB::raw('AVG(mps.accuracy_percentage) as avg_accuracy'),
                DB::raw('SUM(mps.final_blows) as total_final_blows'),
                DB::raw('SUM(mps.solo_kills) as total_solo_kills'),
                DB::raw('AVG(mps.best_killstreak) as avg_best_killstreak'),
                DB::raw('SUM(mps.ultimates_earned) as total_ultimates_earned'),
                DB::raw('SUM(mps.ultimates_used) as total_ultimates_used'),
                DB::raw('SUM(mps.time_played_seconds) as total_time_played')
            ])
            ->orderBy('matches_played', 'desc')
            ->get();

            // Format hero stats with calculated metrics
            $formattedHeroStats = $heroStats->map(function($stats) {
                $winRate = $stats->matches_played > 0 ? round(($stats->wins / $stats->matches_played) * 100, 1) : 0;
                $kd = $stats->avg_deaths > 0 ? round($stats->avg_eliminations / $stats->avg_deaths, 2) : $stats->avg_eliminations;
                $kda = $stats->avg_deaths > 0 ? round(($stats->avg_eliminations + $stats->avg_assists) / $stats->avg_deaths, 2) : ($stats->avg_eliminations + $stats->avg_assists);
                $totalTimeHours = round($stats->total_time_played / 3600, 1);
                $ultEfficacy = $stats->total_ultimates_earned > 0 ? round(($stats->total_ultimates_used / $stats->total_ultimates_earned) * 100, 1) : 0;

                return [
                    'hero' => $stats->hero,
                    'matches_played' => $stats->matches_played,
                    'total_time_played_hours' => $totalTimeHours,
                    'win_rate' => $winRate,
                    'performance' => [
                        'rating' => round($stats->avg_rating, 2),
                        'kd' => $kd,
                        'kda' => $kda,
                        'accuracy' => round($stats->avg_accuracy, 1),
                        'avg_best_killstreak' => round($stats->avg_best_killstreak, 1)
                    ],
                    'combat' => [
                        'avg_eliminations' => round($stats->avg_eliminations, 1),
                        'avg_deaths' => round($stats->avg_deaths, 1),
                        'avg_assists' => round($stats->avg_assists, 1),
                        'avg_damage_dealt' => round($stats->avg_damage_dealt, 0),
                        'avg_damage_taken' => round($stats->avg_damage_taken, 0),
                        'avg_healing_done' => round($stats->avg_healing_done, 0),
                        'avg_damage_blocked' => round($stats->avg_damage_blocked, 0)
                    ],
                    'impact' => [
                        'total_final_blows' => $stats->total_final_blows,
                        'total_solo_kills' => $stats->total_solo_kills,
                        'ultimates_earned' => $stats->total_ultimates_earned,
                        'ultimates_used' => $stats->total_ultimates_used,
                        'ultimate_efficacy' => $ultEfficacy
                    ]
                ];
            });

            // Calculate usage rate for each hero
            $totalMatches = $formattedHeroStats->sum('matches_played');
            $formattedHeroStats = $formattedHeroStats->map(function($heroStat) use ($totalMatches) {
                $heroStat['usage_rate'] = $totalMatches > 0 ? round(($heroStat['matches_played'] / $totalMatches) * 100, 1) : 0;
                return $heroStat;
            });

            return response()->json([
                'data' => $formattedHeroStats,
                'summary' => [
                    'total_heroes_played' => $formattedHeroStats->count(),
                    'most_played_hero' => $formattedHeroStats->first()['hero'] ?? null,
                    'highest_win_rate_hero' => $formattedHeroStats->sortByDesc('win_rate')->first()['hero'] ?? null,
                    'highest_rating_hero' => $formattedHeroStats->sortByDesc('performance.rating')->first()['hero'] ?? null
                ],
                'filters' => [
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'event_id' => $request->event_id
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player's overall performance metrics
     */
    public function getPerformanceStats($playerId, Request $request)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Base query for performance stats
            $query = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed');

            // Apply filters
            if ($request->has('date_from')) {
                $query->where('m.scheduled_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('m.scheduled_at', '<=', $request->date_to);
            }

            if ($request->has('event_id')) {
                $query->where('m.event_id', $request->event_id);
            }

            if ($request->has('hero')) {
                $query->where('mps.hero_played', $request->hero);
            }

            // Get overall stats
            $overallStats = $query->select([
                DB::raw('COUNT(DISTINCT mps.match_id) as total_matches'),
                DB::raw('SUM(CASE WHEN (mps.team_id = m.team1_id AND m.team1_score > m.team2_score) OR (mps.team_id = m.team2_id AND m.team2_score > m.team1_score) THEN 1 ELSE 0 END) as wins'),
                DB::raw('AVG(mps.performance_rating) as avg_rating'),
                DB::raw('MAX(mps.performance_rating) as peak_rating'),
                DB::raw('MIN(mps.performance_rating) as lowest_rating'),
                DB::raw('SUM(mps.eliminations) as total_eliminations'),
                DB::raw('SUM(mps.deaths) as total_deaths'),
                DB::raw('SUM(mps.assists) as total_assists'),
                DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                DB::raw('AVG(mps.deaths) as avg_deaths'),
                DB::raw('AVG(mps.assists) as avg_assists'),
                DB::raw('SUM(mps.damage) as total_damage_dealt'),
                DB::raw('SUM(mps.damage_taken) as total_damage_taken'),
                DB::raw('SUM(mps.healing) as total_healing_done'),
                DB::raw('SUM(mps.damage_blocked) as total_damage_blocked'),
                DB::raw('AVG(mps.accuracy_percentage) as avg_accuracy'),
                DB::raw('SUM(mps.final_blows) as total_final_blows'),
                DB::raw('SUM(mps.solo_kills) as total_solo_kills'),
                DB::raw('MAX(mps.best_killstreak) as best_killstreak'),
                DB::raw('SUM(mps.ultimates_earned) as total_ultimates_earned'),
                DB::raw('SUM(mps.ultimates_used) as total_ultimates_used'),
                DB::raw('SUM(mps.ultimate_eliminations) as total_ultimate_eliminations'),
                DB::raw('SUM(mps.time_played_seconds) as total_time_played')
            ])->first();

            // Calculate derived metrics
            $winRate = $overallStats->total_matches > 0 ? round(($overallStats->wins / $overallStats->total_matches) * 100, 1) : 0;
            $kd = $overallStats->total_deaths > 0 ? round($overallStats->total_eliminations / $overallStats->total_deaths, 2) : $overallStats->total_eliminations;
            $kda = $overallStats->total_deaths > 0 ? round(($overallStats->total_eliminations + $overallStats->total_assists) / $overallStats->total_deaths, 2) : ($overallStats->total_eliminations + $overallStats->total_assists);
            $totalTimeHours = round($overallStats->total_time_played / 3600, 1);
            $ultEfficacy = $overallStats->total_ultimates_earned > 0 ? round(($overallStats->total_ultimates_used / $overallStats->total_ultimates_earned) * 100, 1) : 0;
            $damagePerSecond = $overallStats->total_time_played > 0 ? round($overallStats->total_damage_dealt / $overallStats->total_time_played, 1) : 0;

            // Get performance trends over time
            $performanceTrends = $this->getPerformanceTrends($playerId, $request);

            // Get player's current team for opponent determination
            $playerTeamId = DB::table('players')->where('id', $playerId)->value('team_id');

            // Get best and worst performances
            $bestPerformances = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->join('teams as t1', 'm.team1_id', '=', 't1.id')
                ->join('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->orderBy('mps.performance_rating', 'desc')
                ->limit(5)
                ->select([
                    'mps.*',
                    'mps.hero_played as hero',
                    'm.scheduled_at',
                    'm.team1_id',
                    'm.team2_id',
                    't1.name as team1_name',
                    't2.name as team2_name',
                    'm.team1_score',
                    'm.team2_score'
                ])
                ->get();

            $worstPerformances = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->join('teams as t1', 'm.team1_id', '=', 't1.id')
                ->join('teams as t2', 'm.team2_id', '=', 't2.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->orderBy('mps.performance_rating', 'asc')
                ->limit(5)
                ->select([
                    'mps.*',
                    'mps.hero_played as hero',
                    'm.scheduled_at',
                    'm.team1_id',
                    'm.team2_id',
                    't1.name as team1_name',
                    't2.name as team2_name',
                    'm.team1_score',
                    'm.team2_score'
                ])
                ->get();

            return response()->json([
                'data' => [
                    'overview' => [
                        'matches_played' => $overallStats->total_matches,
                        'total_time_played_hours' => $totalTimeHours,
                        'wins' => $overallStats->wins,
                        'losses' => $overallStats->total_matches - $overallStats->wins,
                        'win_rate' => $winRate
                    ],
                    'ratings' => [
                        'current' => $player->rating ?? 1000,
                        'average' => round($overallStats->avg_rating, 2),
                        'peak' => round($overallStats->peak_rating, 2),
                        'lowest' => round($overallStats->lowest_rating, 2)
                    ],
                    'combat_stats' => [
                        'total_eliminations' => $overallStats->total_eliminations,
                        'total_deaths' => $overallStats->total_deaths,
                        'total_assists' => $overallStats->total_assists,
                        'kd_ratio' => $kd,
                        'kda_ratio' => $kda,
                        'avg_eliminations' => round($overallStats->avg_eliminations, 1),
                        'avg_deaths' => round($overallStats->avg_deaths, 1),
                        'avg_assists' => round($overallStats->avg_assists, 1),
                        'best_killstreak' => $overallStats->best_killstreak
                    ],
                    'performance_metrics' => [
                        'accuracy_percentage' => round($overallStats->avg_accuracy, 1),
                        'total_final_blows' => $overallStats->total_final_blows,
                        'total_solo_kills' => $overallStats->total_solo_kills,
                        'damage_per_second' => $damagePerSecond
                    ],
                    'damage_stats' => [
                        'total_damage_dealt' => $overallStats->total_damage_dealt,
                        'total_damage_taken' => $overallStats->total_damage_taken,
                        'total_damage_blocked' => $overallStats->total_damage_blocked,
                        'total_healing_done' => $overallStats->total_healing_done,
                        'damage_differential' => $overallStats->total_damage_dealt - $overallStats->total_damage_taken
                    ],
                    'ultimate_stats' => [
                        'total_ultimates_earned' => $overallStats->total_ultimates_earned,
                        'total_ultimates_used' => $overallStats->total_ultimates_used,
                        'total_ultimate_eliminations' => $overallStats->total_ultimate_eliminations,
                        'ultimate_efficacy' => $ultEfficacy
                    ],
                    'trends' => $performanceTrends,
                    'best_performances' => $bestPerformances->map(function($perf) use ($playerTeamId) {
                        return [
                            'match_id' => $perf->match_id,
                            'date' => $perf->scheduled_at,
                            'rating' => $perf->performance_rating,
                            'eliminations' => $perf->eliminations,
                            'deaths' => $perf->deaths,
                            'assists' => $perf->assists,
                            'kd' => $perf->deaths > 0 ? round($perf->eliminations / $perf->deaths, 2) : $perf->eliminations,
                            'hero' => $perf->hero,
                            'damage_dealt' => $perf->damage,
                            'opponent' => $perf->team2_name
                        ];
                    }),
                    'worst_performances' => $worstPerformances->map(function($perf) use ($playerTeamId) {
                        return [
                            'match_id' => $perf->match_id,
                            'date' => $perf->scheduled_at,
                            'rating' => $perf->performance_rating,
                            'eliminations' => $perf->eliminations,
                            'deaths' => $perf->deaths,
                            'assists' => $perf->assists,
                            'kd' => $perf->deaths > 0 ? round($perf->eliminations / $perf->deaths, 2) : $perf->eliminations,
                            'hero' => $perf->hero,
                            'damage_dealt' => $perf->damage,
                            'opponent' => $perf->team2_name
                        ];
                    })
                ],
                'filters' => [
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'event_id' => $request->event_id,
                    'hero' => $request->hero
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching performance stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player's stats per map
     */
    public function getMapStats($playerId, Request $request)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Query to get map-specific stats
            $query = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->join('match_maps as mm', function($join) {
                    $join->on('mm.match_id', '=', 'm.id')
                         ->on('mm.map_number', '=', 'mps.map_number');
                })
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->groupBy('mm.map_name');

            // Apply filters
            if ($request->has('date_from')) {
                $query->where('m.scheduled_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('m.scheduled_at', '<=', $request->date_to);
            }

            if ($request->has('event_id')) {
                $query->where('m.event_id', $request->event_id);
            }

            if ($request->has('hero')) {
                $query->where('mps.hero_played', $request->hero);
            }

            // Aggregate stats per map
            $mapStats = $query->select([
                'mm.map_name as map',
                DB::raw('COUNT(DISTINCT mps.match_id) as matches_played'),
                DB::raw('SUM(CASE WHEN mm.winner_id = mps.team_id THEN 1 ELSE 0 END) as wins'),
                DB::raw('AVG(mps.performance_rating) as avg_rating'),
                DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                DB::raw('AVG(mps.deaths) as avg_deaths'),
                DB::raw('AVG(mps.assists) as avg_assists'),
                DB::raw('AVG(mps.damage) as avg_damage_dealt'),
                DB::raw('AVG(mps.damage_taken) as avg_damage_taken'),
                DB::raw('AVG(mps.healing) as avg_healing_done'),
                DB::raw('AVG(mps.accuracy_percentage) as avg_accuracy'),
                DB::raw('SUM(mps.final_blows) as total_final_blows'),
                DB::raw('SUM(mps.solo_kills) as total_solo_kills'),
                DB::raw('SUM(mps.ultimates_earned) as total_ultimates_earned'),
                DB::raw('SUM(mps.ultimates_used) as total_ultimates_used'),
                DB::raw('AVG(mps.objective_time) as avg_objective_time')
            ])
            ->orderBy('matches_played', 'desc')
            ->get();

            // Format map stats
            $formattedMapStats = $mapStats->map(function($stats) {
                $winRate = $stats->matches_played > 0 ? round(($stats->wins / $stats->matches_played) * 100, 1) : 0;
                $kd = $stats->avg_deaths > 0 ? round($stats->avg_eliminations / $stats->avg_deaths, 2) : $stats->avg_eliminations;
                $ultEfficacy = $stats->total_ultimates_earned > 0 ? round(($stats->total_ultimates_used / $stats->total_ultimates_earned) * 100, 1) : 0;

                return [
                    'map' => $stats->map,
                    'matches_played' => $stats->matches_played,
                    'wins' => $stats->wins,
                    'losses' => $stats->matches_played - $stats->wins,
                    'win_rate' => $winRate,
                    'performance' => [
                        'avg_rating' => round($stats->avg_rating, 2),
                        'avg_kd' => $kd,
                        'avg_accuracy' => round($stats->avg_accuracy, 1),
                        'avg_objective_time' => round($stats->avg_objective_time / 60, 1) // Convert to minutes
                    ],
                    'combat' => [
                        'avg_eliminations' => round($stats->avg_eliminations, 1),
                        'avg_deaths' => round($stats->avg_deaths, 1),
                        'avg_assists' => round($stats->avg_assists, 1),
                        'avg_damage_dealt' => round($stats->avg_damage_dealt, 0),
                        'avg_damage_taken' => round($stats->avg_damage_taken, 0),
                        'avg_healing_done' => round($stats->avg_healing_done, 0)
                    ],
                    'impact' => [
                        'total_final_blows' => $stats->total_final_blows,
                        'total_solo_kills' => $stats->total_solo_kills,
                        'ultimate_efficacy' => $ultEfficacy,
                        'total_ultimates_earned' => $stats->total_ultimates_earned
                    ]
                ];
            });

            // Get map type statistics (attack/defense sided maps)
            $mapTypeStats = $this->getMapTypeStats($playerId, $request);

            return response()->json([
                'data' => $formattedMapStats,
                'map_types' => $mapTypeStats,
                'summary' => [
                    'total_maps_played_on' => $formattedMapStats->count(),
                    'best_map' => $formattedMapStats->sortByDesc('win_rate')->first()['map'] ?? null,
                    'worst_map' => $formattedMapStats->sortBy('win_rate')->first()['map'] ?? null,
                    'most_played_map' => $formattedMapStats->first()['map'] ?? null
                ],
                'filters' => [
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'event_id' => $request->event_id,
                    'hero' => $request->hero
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching map stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get player's stats per event/tournament
     */
    public function getEventStats($playerId, Request $request)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Query to get event-specific stats
            $query = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->join('events as e', 'm.event_id', '=', 'e.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->groupBy(['e.id', 'e.name', 'e.type', 'e.logo', 'e.start_date', 'e.end_date', 'e.prize_pool']);

            // Apply filters
            if ($request->has('date_from')) {
                $query->where('e.start_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('e.end_date', '<=', $request->date_to);
            }

            if ($request->has('event_type')) {
                $query->where('e.type', $request->event_type);
            }

            if ($request->has('hero')) {
                $query->where('mps.hero_played', $request->hero);
            }

            // Aggregate stats per event
            $eventStats = $query->select([
                'e.id as event_id',
                'e.name as event_name',
                'e.type as event_type',
                'e.logo as event_logo',
                'e.start_date',
                'e.end_date',
                'e.prize_pool',
                DB::raw('COUNT(DISTINCT mps.match_id) as matches_played'),
                DB::raw('SUM(CASE WHEN (mps.team_id = m.team1_id AND m.team1_score > m.team2_score) OR (mps.team_id = m.team2_id AND m.team2_score > m.team1_score) THEN 1 ELSE 0 END) as wins'),
                DB::raw('AVG(mps.performance_rating) as avg_rating'),
                DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                DB::raw('AVG(mps.deaths) as avg_deaths'),
                DB::raw('AVG(mps.assists) as avg_assists'),
                DB::raw('AVG(mps.damage) as avg_damage_dealt'),
                DB::raw('AVG(mps.accuracy_percentage) as avg_accuracy'),
                DB::raw('SUM(mps.final_blows) as total_final_blows'),
                DB::raw('SUM(mps.solo_kills) as total_solo_kills'),
                DB::raw('SUM(mps.ultimates_earned) as total_ultimates_earned'),
                DB::raw('SUM(mps.ultimates_used) as total_ultimates_used'),
                DB::raw('SUM(mps.time_played_seconds) as total_time_played')
            ])
            ->orderBy('e.start_date', 'desc')
            ->get();

            // Format event stats and calculate placement
            $formattedEventStats = $eventStats->map(function($stats) use ($playerId) {
                $winRate = $stats->matches_played > 0 ? round(($stats->wins / $stats->matches_played) * 100, 1) : 0;
                $kd = $stats->avg_deaths > 0 ? round($stats->avg_eliminations / $stats->avg_deaths, 2) : $stats->avg_eliminations;
                $timePlayedHours = round($stats->total_time_played / 3600, 1);
                $ultEfficacy = $stats->total_ultimates_earned > 0 ? round(($stats->total_ultimates_used / $stats->total_ultimates_earned) * 100, 1) : 0;

                // Calculate placement for this event
                $placement = $this->calculatePlayerEventPlacement($stats->event_id, $playerId);
                $prizeWon = $this->calculateEventPrize($stats->prize_pool, $placement);

                return [
                    'event' => [
                        'id' => $stats->event_id,
                        'name' => $stats->event_name,
                        'type' => $stats->event_type,
                        'logo' => $stats->event_logo,
                        'start_date' => $stats->start_date,
                        'end_date' => $stats->end_date,
                        'prize_pool' => $stats->prize_pool
                    ],
                    'placement' => $placement,
                    'prize_won' => $prizeWon,
                    'matches_played' => $stats->matches_played,
                    'time_played_hours' => $timePlayedHours,
                    'wins' => $stats->wins,
                    'losses' => $stats->matches_played - $stats->wins,
                    'win_rate' => $winRate,
                    'performance' => [
                        'avg_rating' => round($stats->avg_rating, 2),
                        'avg_kd' => $kd,
                        'avg_damage_dealt' => round($stats->avg_damage_dealt, 0),
                        'avg_accuracy' => round($stats->avg_accuracy, 1)
                    ],
                    'combat' => [
                        'avg_eliminations' => round($stats->avg_eliminations, 1),
                        'avg_deaths' => round($stats->avg_deaths, 1),
                        'avg_assists' => round($stats->avg_assists, 1),
                        'total_final_blows' => $stats->total_final_blows,
                        'total_solo_kills' => $stats->total_solo_kills
                    ],
                    'impact' => [
                        'ultimate_efficacy' => $ultEfficacy,
                        'total_ultimates_earned' => $stats->total_ultimates_earned,
                        'total_ultimates_used' => $stats->total_ultimates_used
                    ]
                ];
            });

            // Calculate career earnings and achievements
            $careerStats = [
                'total_events' => $formattedEventStats->count(),
                'total_prize_money' => $formattedEventStats->sum('prize_won'),
                'tournaments_won' => $formattedEventStats->where('placement', 1)->count(),
                'top_3_finishes' => $formattedEventStats->whereIn('placement', [1, 2, 3])->count(),
                'top_8_finishes' => $formattedEventStats->where('placement', '<=', 8)->count()
            ];

            return response()->json([
                'data' => $formattedEventStats,
                'career_stats' => $careerStats,
                'filters' => [
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'event_type' => $request->event_type,
                    'hero' => $request->hero
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching event stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to calculate overall stats from a collection of matches
     */
    private function calculateOverallStatsFromMatches($matches)
    {
        if ($matches->isEmpty()) {
            return [
                'matches' => 0,
                'wins' => 0,
                'win_rate' => 0,
                'avg_rating' => 0,
                'avg_acs' => 0,
                'avg_kd' => 0,
                'avg_adr' => 0,
                'avg_kast' => 0
            ];
        }

        $totalMatches = $matches->count();
        $wins = $matches->where('result', 'W')->count();
        $avgRating = $matches->avg('rating');
        $avgAcs = $matches->avg('acs');
        $totalElims = $matches->sum('eliminations');
        $totalDeaths = $matches->sum('deaths');
        $avgAdr = $matches->avg('adr');
        $avgKast = $matches->avg('kast');

        return [
            'matches' => $totalMatches,
            'wins' => $wins,
            'win_rate' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0,
            'avg_rating' => round($avgRating, 2),
            'avg_acs' => round($avgAcs, 1),
            'avg_kd' => $totalDeaths > 0 ? round($totalElims / $totalDeaths, 2) : $totalElims,
            'avg_adr' => round($avgAdr, 1),
            'avg_kast' => round($avgKast, 1)
        ];
    }

    /**
     * Helper method to get performance trends over time
     */
    private function getPerformanceTrends($playerId, Request $request)
    {
        // Get weekly performance averages for the last 12 weeks
        $trends = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            
            $weekStats = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->whereBetween('m.scheduled_at', [$weekStart, $weekEnd])
                ->select([
                    DB::raw('AVG(mps.performance_rating) as avg_rating'),
                    DB::raw('AVG(mps.damage) as avg_damage'),
                    DB::raw('AVG(mps.eliminations) as avg_eliminations'),
                    DB::raw('COUNT(DISTINCT mps.match_id) as matches')
                ])
                ->first();
            
            if ($weekStats && $weekStats->matches > 0) {
                $trends[] = [
                    'week' => $weekStart->format('Y-m-d'),
                    'rating' => round($weekStats->avg_rating, 2),
                    'avg_damage' => round($weekStats->avg_damage, 0),
                    'avg_eliminations' => round($weekStats->avg_eliminations, 1),
                    'matches' => $weekStats->matches
                ];
            }
        }
        
        return $trends;
    }

    /**
     * Helper method to get map type statistics
     */
    private function getMapTypeStats($playerId, Request $request)
    {
        // Define map types for Marvel Rivals (this would ideally be in a database table)
        $mapTypes = [
            'domination' => ['Tokyo 2099: Shibuya', 'Tokyo 2099: Spider-Islands', 'Asgard: Royal Palace', 'Klyntar: Symbiote Research Station'],
            'convoy' => ['Midtown: Oscorp Tower', 'Wakanda: Vibranium Mine', 'Yashida Research Station: Kenuichio', 'Sanctum Sanctorum: Ancient Ruins'],
            'convergence' => ['Asgard: Throne Room', 'Klaw Mining Station: Vibranium Caverns', 'Hydra Chariot Base: Command Center', 'Stark Tower: Reactor Core']
        ];
        
        $mapTypeStats = [];
        
        foreach ($mapTypes as $type => $maps) {
            $stats = DB::table('player_match_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->join('match_maps as mm', 'mm.match_id', '=', 'm.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed')
                ->whereIn('mm.map_name', $maps)
                ->select([
                    DB::raw('COUNT(DISTINCT mps.match_id) as matches_played'),
                    DB::raw('SUM(CASE WHEN mm.winner_id = mps.team_id THEN 1 ELSE 0 END) as wins'),
                    DB::raw('AVG(mps.performance_rating) as avg_rating'),
                    DB::raw('AVG(mps.eliminations) as avg_eliminations')
                ])
                ->first();
            
            if ($stats && $stats->matches_played > 0) {
                $mapTypeStats[$type] = [
                    'matches_played' => $stats->matches_played,
                    'win_rate' => round(($stats->wins / $stats->matches_played) * 100, 1),
                    'avg_rating' => round($stats->avg_rating, 2),
                    'avg_eliminations' => round($stats->avg_eliminations, 1)
                ];
            }
        }
        
        return $mapTypeStats;
    }

    /**
     * Helper method to calculate player's placement in an event
     */
    private function calculatePlayerEventPlacement($eventId, $playerId)
    {
        // Get the player's team
        $playerTeam = DB::table('player_match_stats as mps')
            ->join('matches as m', 'mps.match_id', '=', 'm.id')
            ->where('m.event_id', $eventId)
            ->where('mps.player_id', $playerId)
            ->value('mps.team_id');
        
        if (!$playerTeam) {
            return null;
        }
        
        // This is a simplified placement calculation
        // In a real scenario, this would involve tournament brackets and elimination logic
        $teams = DB::table('matches as m')
            ->where('m.event_id', $eventId)
            ->where('m.status', 'completed')
            ->selectRaw('
                CASE 
                    WHEN team1_score > team2_score THEN team1_id
                    ELSE team2_id
                END as winning_team
            ')
            ->groupBy('winning_team')
            ->orderByRaw('COUNT(*) DESC')
            ->pluck('winning_team');
        
        $placement = $teams->search($playerTeam);
        
        return $placement !== false ? $placement + 1 : null;
    }

    /**
     * Get player's performance statistics per hero (like vlr.gg agent stats)
     */
    public function getHeroPerformance($playerId)
    {
        try {
            $player = DB::table('players')->where('id', $playerId)->first();
            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player not found'
                ], 404);
            }

            // Get hero performance from matches
            $heroStats = DB::table('player_match_stats as pms')
                ->join('matches as m', 'pms.match_id', '=', 'm.id')
                ->where('pms.player_id', $playerId)
                ->where('m.status', 'completed')
                ->whereNotNull('pms.hero_played')
                ->groupBy('pms.hero_played')
                ->select([
                    'pms.hero_played as hero_name',
                    DB::raw('COUNT(DISTINCT pms.match_id) as matches_played'),
                    DB::raw('SUM(CASE WHEN pms.won = 1 THEN 1 ELSE 0 END) as wins'),
                    DB::raw('SUM(CASE WHEN pms.won = 0 THEN 1 ELSE 0 END) as losses'),
                    DB::raw('AVG(pms.performance_rating) as rating'),
                    DB::raw('AVG(pms.eliminations / NULLIF(pms.rounds_played, 0)) as kpr'),
                    DB::raw('AVG(pms.deaths / NULLIF(pms.rounds_played, 0)) as dpr'),
                    DB::raw('AVG(pms.assists / NULLIF(pms.rounds_played, 0)) as apr'),
                    DB::raw('AVG(pms.damage / NULLIF(pms.rounds_played, 0)) as adr'),
                    DB::raw('AVG(pms.healing / NULLIF(pms.rounds_played, 0)) as ahr'),
                    DB::raw('AVG(pms.eliminations / NULLIF(pms.deaths, 0)) as kd_ratio'),
                    DB::raw('AVG(pms.first_eliminations / NULLIF(pms.rounds_played, 0)) as fkpr'),
                    DB::raw('AVG(pms.first_deaths / NULLIF(pms.rounds_played, 0)) as fdpr'),
                    DB::raw('SUM(pms.eliminations) as total_kills'),
                    DB::raw('SUM(pms.deaths) as total_deaths'),
                    DB::raw('SUM(pms.assists) as total_assists'),
                    DB::raw('SUM(pms.damage) as total_damage'),
                    DB::raw('SUM(pms.healing) as total_healing'),
                    DB::raw('SUM(pms.damage_blocked) as total_damage_blocked'),
                    DB::raw('SUM(pms.ultimate_usage) as total_ultimate_usage'),
                    DB::raw('SUM(pms.objective_time) as total_objective_time'),
                    DB::raw('SUM(pms.rounds_played) as total_rounds_played'),
                    DB::raw('MAX(m.scheduled_at) as last_played'),
                    DB::raw('MIN(m.scheduled_at) as first_played')
                ])
                ->orderByDesc('matches_played')
                ->get();

            // Calculate additional metrics and update/create hero stats
            $heroPerformance = $heroStats->map(function($stat) use ($playerId) {
                $winRate = $stat->matches_played > 0 ? 
                    round(($stat->wins / $stat->matches_played) * 100, 2) : 0;
                
                // Calculate ACS (Average Combat Score) - Marvel Rivals version
                $acs = round(
                    ($stat->kpr * 150) + 
                    ($stat->apr * 50) + 
                    ($stat->adr * 0.75) + 
                    ($stat->ahr * 0.5),
                    1
                );

                // Calculate KAST (Kill, Assist, Survive, Trade %)
                // Simplified version - in real implementation would need round-by-round data
                $kast = round(
                    (($stat->kpr + $stat->apr) / max(1, $stat->kpr + $stat->apr + $stat->dpr)) * 100,
                    2
                );

                // Get hero role
                $heroInfo = DB::table('marvel_rivals_heroes')
                    ->where('name', $stat->hero_name)
                    ->first();
                $heroRole = $heroInfo->role ?? 'Unknown';

                // Update or create hero stats record
                DB::table('player_hero_stats')->updateOrInsert(
                    [
                        'player_id' => $playerId,
                        'hero_name' => $stat->hero_name
                    ],
                    [
                        'matches_played' => $stat->matches_played,
                        'wins' => $stat->wins,
                        'losses' => $stat->losses,
                        'win_rate' => $winRate,
                        'rating' => round($stat->rating, 2),
                        'acs' => $acs,
                        'kd_ratio' => round($stat->kd_ratio ?? 0, 2),
                        'kpr' => round($stat->kpr ?? 0, 2),
                        'apr' => round($stat->apr ?? 0, 2),
                        'dpr' => round($stat->dpr ?? 0, 2),
                        'adr' => round($stat->adr ?? 0, 1),
                        'ahr' => round($stat->ahr ?? 0, 1),
                        'kast' => $kast,
                        'fkpr' => round($stat->fkpr ?? 0, 2),
                        'fdpr' => round($stat->fdpr ?? 0, 2),
                        'total_kills' => $stat->total_kills,
                        'total_deaths' => $stat->total_deaths,
                        'total_assists' => $stat->total_assists,
                        'total_damage' => $stat->total_damage,
                        'total_healing' => $stat->total_healing,
                        'total_damage_blocked' => $stat->total_damage_blocked,
                        'total_ultimate_usage' => $stat->total_ultimate_usage,
                        'total_objective_time' => $stat->total_objective_time,
                        'total_rounds_played' => $stat->total_rounds_played,
                        'hero_role' => $heroRole,
                        'last_played' => $stat->last_played,
                        'first_played' => $stat->first_played,
                        'updated_at' => now()
                    ]
                );

                return [
                    'hero_name' => $stat->hero_name,
                    'hero_role' => $heroRole,
                    'hero_image' => $this->getHeroImagePath($stat->hero_name),
                    'usage_rate' => $this->calculateUsageRate($stat->matches_played, $playerId),
                    'matches_played' => $stat->matches_played,
                    'wins' => $stat->wins,
                    'losses' => $stat->losses,
                    'win_rate' => $winRate . '%',
                    'performance' => [
                        'rating' => round($stat->rating, 2),
                        'acs' => $acs,
                        'kd_ratio' => round($stat->kd_ratio ?? 0, 2),
                        'kast' => $kast . '%'
                    ],
                    'per_round' => [
                        'kpr' => round($stat->kpr ?? 0, 2),
                        'apr' => round($stat->apr ?? 0, 2),
                        'dpr' => round($stat->dpr ?? 0, 2),
                        'adr' => round($stat->adr ?? 0, 1),
                        'ahr' => round($stat->ahr ?? 0, 1),
                        'fkpr' => round($stat->fkpr ?? 0, 2),
                        'fdpr' => round($stat->fdpr ?? 0, 2)
                    ],
                    'totals' => [
                        'kills' => $stat->total_kills,
                        'deaths' => $stat->total_deaths,
                        'assists' => $stat->total_assists,
                        'damage' => $stat->total_damage,
                        'healing' => $stat->total_healing,
                        'damage_blocked' => $stat->total_damage_blocked,
                        'ultimate_usage' => $stat->total_ultimate_usage,
                        'objective_time' => $stat->total_objective_time,
                        'rounds_played' => $stat->total_rounds_played
                    ],
                    'timeline' => [
                        'first_played' => $stat->first_played,
                        'last_played' => $stat->last_played,
                        'days_since_last_played' => $stat->last_played ? 
                            now()->diffInDays($stat->last_played) : null
                    ]
                ];
            });

            // Get total matches for usage rate calculation
            $totalMatches = DB::table('player_match_stats')
                ->where('player_id', $playerId)
                ->distinct('match_id')
                ->count('match_id');

            return response()->json([
                'data' => [
                    'player' => [
                        'id' => $player->id,
                        'username' => $player->username,
                        'real_name' => $player->real_name,
                        'avatar' => $player->avatar,
                        'main_hero' => $player->main_hero
                    ],
                    'hero_performance' => $heroPerformance,
                    'summary' => [
                        'total_heroes_played' => $heroPerformance->count(),
                        'total_matches' => $totalMatches,
                        'most_played_hero' => $heroPerformance->first(),
                        'best_performing_hero' => $heroPerformance->sortByDesc('performance.rating')->first(),
                        'highest_win_rate_hero' => $heroPerformance->sortByDesc(function($hero) {
                            return floatval(str_replace('%', '', $hero['win_rate']));
                        })->first()
                    ],
                    'role_distribution' => $heroPerformance->groupBy('hero_role')->map(function($heroes, $role) {
                        return [
                            'role' => $role,
                            'count' => $heroes->count(),
                            'total_matches' => $heroes->sum('matches_played'),
                            'avg_rating' => round($heroes->avg('performance.rating'), 2)
                        ];
                    })
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching hero performance: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateUsageRate($heroMatches, $playerId)
    {
        $totalMatches = DB::table('player_match_stats')
            ->where('player_id', $playerId)
            ->distinct('match_id')
            ->count('match_id');
        
        return $totalMatches > 0 ? round(($heroMatches / $totalMatches) * 100, 2) : 0;
    }

    private function getHeroImagePath($heroName)
    {
        $slug = $this->createHeroSlug($heroName);
        $webpPath = "/images/heroes/{$slug}-headbig.webp";
        
        if (file_exists(public_path($webpPath))) {
            return $webpPath;
        }
        
        return "/images/heroes/portraits/{$slug}.png";
    }

    private function createHeroSlug($heroName)
    {
        $slug = strtolower($heroName);
        
        // Special case for Cloak & Dagger
        if (strpos($slug, 'cloak') !== false && strpos($slug, 'dagger') !== false) {
            return 'cloak-dagger';
        }
        
        $slug = str_replace([' ', '&', '.', "'", '-'], ['-', '-', '', '', '-'], $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
