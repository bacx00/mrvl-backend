<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('teams as t')
                ->leftJoin('events as e', 't.id', '=', 'e.team_count') // Join for tournament data
                ->select([
                    't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.country',
                    't.rating', 't.rank', 't.win_rate', 't.points', 't.record',
                    't.peak', 't.streak', 't.founded', 't.captain', 't.coach',
                    't.website', 't.earnings', 't.social_media', 't.achievements'
                ]);

            if ($request->region && $request->region !== 'all') {
                $query->where('t.region', $request->region);
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
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $team->logo,
                    'region' => $team->region,
                    'country' => $team->country,
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
                    'website' => $team->website,
                    'earnings' => $team->earnings ?? '$0',
                    'social_media' => $team->social_media ? json_decode($team->social_media, true) : [],
                    'achievements' => $team->achievements ? json_decode($team->achievements, true) : [],
                    // Marvel Rivals specific data
                    'game' => 'Marvel Rivals',
                    'division' => $this->getDivisionByRating($team->rating ?? 1000),
                    'recent_form' => $this->generateRecentForm(),
                    'player_count' => $this->getPlayerCount($team->id)
                ];
            });

            return response()->json([
                'data' => $formattedTeams,
                'total' => $formattedTeams->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching teams: ' . $e->getMessage()
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

            // Get team players with raw DB query
            $players = DB::table('players')
                ->where('team_id', $teamId)
                ->select(['id', 'name', 'username', 'role', 'avatar', 'rating', 'main_hero'])
                ->orderBy('rating', 'desc')
                ->limit(6)
                ->get();

            // Get team recent matches
            $recentMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.status', 'm.team1_score', 'm.team2_score', 'm.scheduled_at',
                    't1.name as team1_name', 't2.name as team2_name',
                    'e.name as event_name'
                ])
                ->where(function($query) use ($teamId) {
                    $query->where('m.team1_id', $teamId)->orWhere('m.team2_id', $teamId);
                })
                ->orderBy('m.scheduled_at', 'desc')
                ->limit(5)
                ->get();

            $formattedTeam = [
                'id' => $team->id,
                'name' => $team->name,
                'short_name' => $team->short_name,
                'logo' => $team->logo,
                'region' => $team->region,
                'country' => $team->country,
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
                'website' => $team->website,
                'earnings' => $team->earnings ?? '$0',
                'social_media' => $team->social_media ? json_decode($team->social_media, true) : [],
                'achievements' => $team->achievements ? json_decode($team->achievements, true) : [],
                'players' => $players,
                'recent_matches' => $recentMatches,
                // Marvel Rivals specific
                'game' => 'Marvel Rivals',
                'division' => $this->getDivisionByRating($team->rating ?? 1000),
                'heroes_meta' => $this->getTeamHeroesMeta($players)
            ];

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
            
            $query = DB::table('teams')
                ->select(['id', 'name', 'short_name', 'logo', 'region', 'rating', 'rank', 'win_rate', 'record']);
            
            if ($region !== 'all') {
                $query->where('region', $region);
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
                    'rating' => $team->rating ?? 1000,
                    'win_rate' => $team->win_rate ?? 0,
                    'record' => $team->record ?? '0-0',
                    'division' => $this->getDivisionByRating($team->rating ?? 1000)
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

    // Marvel Rivals specific helper methods
    private function getDivisionByRating($rating)
    {
        if ($rating >= 2500) return 'Eternity';
        if ($rating >= 2200) return 'Celestial';
        if ($rating >= 1900) return 'Vibranium';
        if ($rating >= 1600) return 'Diamond';
        if ($rating >= 1300) return 'Platinum';
        if ($rating >= 1000) return 'Gold';
        return 'Silver';
    }

    private function generateRecentForm()
    {
        $forms = ['W', 'L', 'W', 'W', 'L']; // Sample recent form
        return array_slice(array_merge($forms, ['W', 'L']), 0, 5);
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
}
