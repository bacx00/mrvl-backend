<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.team1_id', 'm.team2_id', 'm.event_id', 
                    'm.scheduled_at', 'm.status', 'm.team1_score', 'm.team2_score',
                    'm.format', 'm.current_map', 'm.viewers', 'm.stream_url',
                    't1.id as team1_id', 't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region', 't1.rating as team1_rating',
                    't2.id as team2_id', 't2.name as team2_name', 't2.short_name as team2_short', 
                    't2.logo as team2_logo', 't2.region as team2_region', 't2.rating as team2_rating',
                    'e.id as event_id', 'e.name as event_name', 'e.type as event_type'
                ]);

            if ($request->status && $request->status !== 'all') {
                $query->where('m.status', $request->status);
            }

            $matches = $query->orderBy('m.scheduled_at', 'desc')->limit(50)->get();

            // Transform to HLTV.org style format
            $formattedMatches = $matches->map(function($match) {
                return [
                    'id' => $match->id,
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'event_id' => $match->event_id,
                    'scheduled_at' => $match->scheduled_at,
                    'status' => $match->status,
                    'team1_score' => $match->team1_score ?? 0,
                    'team2_score' => $match->team2_score ?? 0,
                    'format' => $match->format,
                    'current_map' => $match->current_map,
                    'viewers' => $match->viewers ?? 0,
                    'stream_url' => $match->stream_url,
                    'team1' => [
                        'id' => $match->team1_id,
                        'name' => $match->team1_name,
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo,
                        'region' => $match->team1_region,
                        'rating' => $match->team1_rating ?? 1000
                    ],
                    'team2' => [
                        'id' => $match->team2_id,
                        'name' => $match->team2_name,
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo,
                        'region' => $match->team2_region,
                        'rating' => $match->team2_rating ?? 1000
                    ],
                    'event' => [
                        'id' => $match->event_id,
                        'name' => $match->event_name ?? 'Unknown Event',
                        'type' => $match->event_type ?? 'Tournament'
                    ],
                    // HLTV.org style broadcast data
                    'broadcast' => [
                        'stream' => $match->stream_url ?? 'https://twitch.tv/marvelrivals',
                        'vod' => null,
                        'languages' => ['en'],
                        'viewers' => $match->viewers ?? 0
                    ],
                    // HLTV.org style maps data
                    'maps' => [
                        ['name' => 'Asgard Throne Room', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
                        ['name' => 'Helicarrier Command', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
                        ['name' => 'Sanctum Sanctorum', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming']
                    ],
                    // HLTV.org style series data
                    'series' => [
                        'format' => $match->format,
                        'score' => [$match->team1_score ?? 0, $match->team2_score ?? 0],
                        'bo' => (int) substr($match->format, -1)
                    ]
                ];
            });

            return response()->json([
                'data' => $formattedMatches,
                'total' => $formattedMatches->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching matches: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($matchId)
    {
        try {
            // Handle special case for 'live' route
            if ($matchId === 'live') {
                return $this->live();
            }

            $match = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.team1_id', 'm.team2_id', 'm.event_id', 
                    'm.scheduled_at', 'm.status', 'm.team1_score', 'm.team2_score',
                    'm.format', 'm.current_map', 'm.viewers', 'm.stream_url', 'm.maps_data',
                    't1.id as team1_id', 't1.name as team1_name', 't1.short_name as team1_short',
                    't1.logo as team1_logo', 't1.region as team1_region', 't1.rating as team1_rating',
                    't2.id as team2_id', 't2.name as team2_name', 't2.short_name as team2_short', 
                    't2.logo as team2_logo', 't2.region as team2_region', 't2.rating as team2_rating',
                    'e.id as event_id', 'e.name as event_name', 'e.type as event_type'
                ])
                ->where('m.id', $matchId)
                ->first();

            if (!$match) {
                return response()->json([
                    'success' => false,
                    'message' => 'Match not found'
                ], 404);
            }

            // Get players for both teams
            $team1Players = DB::table('players')
                ->where('team_id', $match->team1_id)
                ->select(['id', 'name', 'username', 'role', 'avatar', 'rating'])
                ->limit(6)
                ->get();

            $team2Players = DB::table('players')
                ->where('team_id', $match->team2_id)
                ->select(['id', 'name', 'username', 'role', 'avatar', 'rating'])
                ->limit(6)
                ->get();

            // Format complete match data HLTV.org style
            $formattedMatch = [
                'id' => $match->id,
                'team1_id' => $match->team1_id,
                'team2_id' => $match->team2_id,
                'event_id' => $match->event_id,
                'scheduled_at' => $match->scheduled_at,
                'status' => $match->status,
                'team1_score' => $match->team1_score ?? 0,
                'team2_score' => $match->team2_score ?? 0,
                'format' => $match->format,
                'current_map' => $match->current_map,
                'viewers' => $match->viewers ?? 0,
                'stream_url' => $match->stream_url,
                'team1' => [
                    'id' => $match->team1_id,
                    'name' => $match->team1_name,
                    'short_name' => $match->team1_short,
                    'logo' => $match->team1_logo,
                    'region' => $match->team1_region,
                    'rating' => $match->team1_rating ?? 1000,
                    'players' => $team1Players
                ],
                'team2' => [
                    'id' => $match->team2_id,
                    'name' => $match->team2_name,
                    'short_name' => $match->team2_short,
                    'logo' => $match->team2_logo,
                    'region' => $match->team2_region,
                    'rating' => $match->team2_rating ?? 1000,
                    'players' => $team2Players
                ],
                'event' => [
                    'id' => $match->event_id,
                    'name' => $match->event_name ?? 'Unknown Event',
                    'type' => $match->event_type ?? 'Tournament'
                ],
                // HLTV.org style broadcast data - THIS FIXES THE FRONTEND ERROR
                'broadcast' => [
                    'stream' => $match->stream_url ?? 'https://twitch.tv/marvelrivals',
                    'vod' => null,
                    'languages' => ['en'],
                    'viewers' => $match->viewers ?? 0
                ],
                // HLTV.org style maps data
                'maps' => [
                    ['name' => 'Asgard Throne Room', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
                    ['name' => 'Helicarrier Command', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming'],
                    ['name' => 'Sanctum Sanctorum', 'team1Score' => 0, 'team2Score' => 0, 'status' => 'upcoming']
                ],
                // HLTV.org style series data
                'series' => [
                    'format' => $match->format,
                    'score' => [$match->team1_score ?? 0, $match->team2_score ?? 0],
                    'bo' => (int) substr($match->format, -1)
                ],
                // HLTV.org style statistics
                'stats' => [
                    'maps_played' => 0,
                    'duration' => '00:00',
                    'mvp' => null
                ]
            ];

            return response()->json([
                'data' => $formattedMatch,
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching match: ' . $e->getMessage()
            ], 500);
        }
    }

    public function live()
    {
        try {
            $liveMatches = DB::table('matches as m')
                ->leftJoin('teams as t1', 'm.team1_id', '=', 't1.id')
                ->leftJoin('teams as t2', 'm.team2_id', '=', 't2.id')
                ->leftJoin('events as e', 'm.event_id', '=', 'e.id')
                ->select([
                    'm.id', 'm.team1_id', 'm.team2_id', 'm.event_id', 
                    'm.scheduled_at', 'm.status', 'm.team1_score', 'm.team2_score',
                    'm.format', 'm.current_map', 'm.viewers', 'm.stream_url',
                    't1.name as team1_name', 't1.short_name as team1_short', 't1.logo as team1_logo',
                    't2.name as team2_name', 't2.short_name as team2_short', 't2.logo as team2_logo',
                    'e.name as event_name'
                ])
                ->where('m.status', 'live')
                ->orderBy('m.viewers', 'desc')
                ->limit(10)
                ->get();

            $formattedMatches = $liveMatches->map(function($match) {
                return [
                    'id' => $match->id,
                    'team1_id' => $match->team1_id,
                    'team2_id' => $match->team2_id,
                    'status' => $match->status,
                    'team1_score' => $match->team1_score ?? 0,
                    'team2_score' => $match->team2_score ?? 0,
                    'viewers' => $match->viewers ?? 0,
                    'team1' => [
                        'name' => $match->team1_name,
                        'short_name' => $match->team1_short,
                        'logo' => $match->team1_logo
                    ],
                    'team2' => [
                        'name' => $match->team2_name,
                        'short_name' => $match->team2_short,
                        'logo' => $match->team2_logo
                    ],
                    'event' => [
                        'name' => $match->event_name ?? 'Live Match'
                    ],
                    'broadcast' => [
                        'stream' => $match->stream_url ?? 'https://twitch.tv/marvelrivals',
                        'viewers' => $match->viewers ?? 0
                    ]
                ];
            });

            return response()->json([
                'data' => $formattedMatches,
                'total' => $formattedMatches->count(),
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching live matches: ' . $e->getMessage()
            ], 500);
        }
    }
}
