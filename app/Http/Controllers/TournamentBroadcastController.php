<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\BracketMatch;
use App\Models\User;
use App\Services\TournamentBroadcastService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TournamentBroadcastController extends Controller
{
    protected $broadcastService;

    public function __construct(TournamentBroadcastService $broadcastService)
    {
        $this->broadcastService = $broadcastService;
        $this->middleware('auth:api')->except(['getChannels', 'getPublicChannels']);
    }

    /**
     * Get available broadcast channels for a tournament
     */
    public function getChannels(Tournament $tournament): JsonResponse
    {
        try {
            $channels = [
                'public' => [
                    'tournament' => "tournament.{$tournament->id}",
                    'matches' => "tournament.{$tournament->id}.matches",
                    'bracket' => "tournament.{$tournament->id}.bracket",
                    'registrations' => "tournament.{$tournament->id}.registrations",
                    'live' => "tournament.{$tournament->id}.live",
                ],
                'authenticated' => [
                    'chat' => "tournament.{$tournament->id}.chat",
                ],
                'admin' => [
                    'admin' => "tournament.{$tournament->id}.admin",
                ]
            ];

            // Add phase-specific channels if phases exist
            $phases = $tournament->phases;
            foreach ($phases as $phase) {
                $channels['public']['phase_' . $phase->id] = "tournament.{$tournament->id}.phase.{$phase->id}";
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tournament_id' => $tournament->id,
                    'channels' => $channels,
                    'pusher_config' => [
                        'key' => config('broadcasting.connections.pusher.key'),
                        'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get broadcast channels',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all public broadcast channels
     */
    public function getPublicChannels(): JsonResponse
    {
        try {
            $channels = [
                'tournaments' => 'tournaments.public',
                'pusher_config' => [
                    'key' => config('broadcasting.connections.pusher.key'),
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $channels
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get public broadcast channels',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send chat message to tournament general chat
     */
    public function sendTournamentMessage(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $this->broadcastService->broadcastChatMessage(
                $user,
                $request->message,
                'tournament_general',
                $tournament->id,
                false,
                $request->metadata ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send chat message to match chat
     */
    public function sendMatchMessage(Request $request, BracketMatch $match): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            
            // Check if user can send messages to this match
            if (!$this->canUserAccessMatchChat($user, $match)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to send messages to this match'
                ], 403);
            }

            $this->broadcastService->broadcastChatMessage(
                $user,
                $request->message,
                'tournament_match',
                $match->id,
                false,
                $request->metadata ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tournament chat history
     */
    public function getTournamentChat(Tournament $tournament, Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 50), 100);
            $offset = $request->get('offset', 0);

            $messages = DB::table('chat_messages')
                ->join('users', 'chat_messages.user_id', '=', 'users.id')
                ->where('chat_messages.context_type', 'tournament_general')
                ->where('chat_messages.context_id', $tournament->id)
                ->select([
                    'chat_messages.*',
                    'users.name as user_name',
                    'users.avatar as user_avatar',
                    'users.role as user_role'
                ])
                ->orderBy('chat_messages.created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($message) {
                    $message->metadata = $message->metadata ? json_decode($message->metadata, true) : [];
                    return $message;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => $messages->count() === $limit
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get chat history',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get match chat history
     */
    public function getMatchChat(BracketMatch $match, Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$this->canUserAccessMatchChat($user, $match)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to access this match chat'
                ], 403);
            }

            $limit = min($request->get('limit', 50), 100);
            $offset = $request->get('offset', 0);

            $messages = DB::table('chat_messages')
                ->join('users', 'chat_messages.user_id', '=', 'users.id')
                ->where('chat_messages.context_type', 'tournament_match')
                ->where('chat_messages.context_id', $match->id)
                ->select([
                    'chat_messages.*',
                    'users.name as user_name',
                    'users.avatar as user_avatar',
                    'users.role as user_role'
                ])
                ->orderBy('chat_messages.created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($message) {
                    $message->metadata = $message->metadata ? json_decode($message->metadata, true) : [];
                    return $message;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => $messages->count() === $limit
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get match chat history',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Trigger live score update (Admin only)
     */
    public function triggerLiveScoreUpdate(Request $request, BracketMatch $match): JsonResponse
    {
        try {
            $this->authorize('admin', $match->tournament);

            $validator = Validator::make($request->all(), [
                'team1_score' => 'nullable|integer|min:0',
                'team2_score' => 'nullable|integer|min:0',
                'live_stats' => 'nullable|array',
                'current_map' => 'nullable|string',
                'map_results' => 'nullable|array',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $scoreData = [
                'team1_score' => $request->team1_score ?? $match->team1_score,
                'team2_score' => $request->team2_score ?? $match->team2_score,
                'live_stats' => $request->live_stats ?? [],
                'current_map' => $request->current_map,
                'map_results' => $request->map_results ?? [],
            ];

            $this->broadcastService->broadcastLiveScore(
                $match,
                $scoreData,
                'manual_update',
                $request->metadata ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Live score update triggered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger live score update',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if user can access match chat
     */
    private function canUserAccessMatchChat(User $user, BracketMatch $match): bool
    {
        // Admins and moderators can access any match chat
        if ($user->isAdmin() || $user->isModerator()) {
            return true;
        }

        // Check if user is part of either team
        $userTeams = $user->teams()->pluck('teams.id');
        return $userTeams->contains($match->team1_id) || $userTeams->contains($match->team2_id);
    }
}