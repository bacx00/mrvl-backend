<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\BracketMatch;
use App\Models\Team;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Events\Tournament\TournamentUpdated;
use App\Events\Tournament\MatchUpdated;
use App\Events\Tournament\BracketUpdated;
use App\Events\Tournament\TeamRegistrationUpdated;
use App\Events\Tournament\LiveScoreUpdated;
use App\Events\Tournament\ChatMessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentBroadcastService
{
    /**
     * Broadcast tournament status update
     */
    public function broadcastTournamentUpdate(Tournament $tournament, string $updateType, array $changes = [], array $metadata = []): void
    {
        try {
            event(new TournamentUpdated($tournament, $updateType, $changes, $metadata));
            
            Log::info("Tournament broadcast sent", [
                'tournament_id' => $tournament->id,
                'update_type' => $updateType,
                'changes' => $changes
            ]);
        } catch (\Exception $e) {
            Log::error("Tournament broadcast failed: " . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast match update
     */
    public function broadcastMatchUpdate(BracketMatch $match, string $updateType, array $previousState = [], array $metadata = []): void
    {
        try {
            event(new MatchUpdated($match, $updateType, $previousState, $metadata));
            
            Log::info("Match broadcast sent", [
                'match_id' => $match->id,
                'tournament_id' => $match->tournament_id,
                'update_type' => $updateType
            ]);
        } catch (\Exception $e) {
            Log::error("Match broadcast failed: " . $e->getMessage(), [
                'match_id' => $match->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast bracket update
     */
    public function broadcastBracketUpdate(Tournament $tournament, ?TournamentPhase $phase, string $updateType, array $bracketData = [], array $metadata = []): void
    {
        try {
            event(new BracketUpdated($tournament, $phase, $updateType, $bracketData, $metadata));
            
            Log::info("Bracket broadcast sent", [
                'tournament_id' => $tournament->id,
                'phase_id' => $phase?->id,
                'update_type' => $updateType
            ]);
        } catch (\Exception $e) {
            Log::error("Bracket broadcast failed: " . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast team registration update
     */
    public function broadcastRegistrationUpdate(Tournament $tournament, Team $team, TournamentRegistration $registration, string $updateType, array $metadata = []): void
    {
        try {
            event(new TeamRegistrationUpdated($tournament, $team, $registration, $updateType, $metadata));
            
            Log::info("Registration broadcast sent", [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'update_type' => $updateType
            ]);
        } catch (\Exception $e) {
            Log::error("Registration broadcast failed: " . $e->getMessage(), [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast live score update
     */
    public function broadcastLiveScore(BracketMatch $match, array $scoreData, string $updateType, array $metadata = []): void
    {
        try {
            event(new LiveScoreUpdated($match, $scoreData, $updateType, $metadata));
            
            Log::info("Live score broadcast sent", [
                'match_id' => $match->id,
                'tournament_id' => $match->tournament_id,
                'update_type' => $updateType
            ]);
        } catch (\Exception $e) {
            Log::error("Live score broadcast failed: " . $e->getMessage(), [
                'match_id' => $match->id,
                'update_type' => $updateType
            ]);
        }
    }

    /**
     * Broadcast chat message
     */
    public function broadcastChatMessage(User $user, string $message, string $contextType, int $contextId, bool $isSystem = false, array $metadata = []): void
    {
        try {
            // Store chat message in database
            $chatMessage = DB::table('chat_messages')->insertGetId([
                'user_id' => $user->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'message' => $message,
                'is_system' => $isSystem,
                'metadata' => json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new ChatMessageSent($user, $message, $contextType, $contextId, $isSystem, $metadata));
            
            Log::info("Chat message broadcast sent", [
                'user_id' => $user->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'is_system' => $isSystem
            ]);
        } catch (\Exception $e) {
            Log::error("Chat message broadcast failed: " . $e->getMessage(), [
                'user_id' => $user->id,
                'context_type' => $contextType,
                'context_id' => $contextId
            ]);
        }
    }

    /**
     * Broadcast tournament started
     */
    public function broadcastTournamentStarted(Tournament $tournament): void
    {
        $this->broadcastTournamentUpdate($tournament, 'tournament_started', [], [
            'message' => "Tournament '{$tournament->name}' has started!",
            'current_phase' => $tournament->current_phase,
            'total_teams' => $tournament->current_team_count
        ]);
    }

    /**
     * Broadcast tournament completed
     */
    public function broadcastTournamentCompleted(Tournament $tournament, array $results = []): void
    {
        $this->broadcastTournamentUpdate($tournament, 'tournament_completed', [], [
            'message' => "Tournament '{$tournament->name}' has been completed!",
            'results' => $results,
            'duration' => $tournament->started_at ? $tournament->started_at->diffForHumans($tournament->completed_at) : null
        ]);
    }

    /**
     * Broadcast phase started
     */
    public function broadcastPhaseStarted(Tournament $tournament, TournamentPhase $phase): void
    {
        $this->broadcastTournamentUpdate($tournament, 'phase_started', ['current_phase' => $phase->name], [
            'phase' => [
                'id' => $phase->id,
                'name' => $phase->name,
                'type' => $phase->type
            ],
            'message' => "Phase '{$phase->name}' has started!"
        ]);
    }

    /**
     * Broadcast round started
     */
    public function broadcastRoundStarted(Tournament $tournament, int $round, array $matches = []): void
    {
        $this->broadcastBracketUpdate($tournament, null, 'round_started', [], [
            'round' => $round,
            'matches_count' => count($matches),
            'message' => "Round {$round} has started!"
        ]);
    }

    /**
     * Broadcast match started
     */
    public function broadcastMatchStarted(BracketMatch $match): void
    {
        $this->broadcastMatchUpdate($match, 'match_started', [], [
            'message' => "Match between {$match->team1->name} and {$match->team2->name} has started!",
            'scheduled_time' => $match->scheduled_at?->toISOString(),
            'actual_start_time' => now()->toISOString()
        ]);
    }

    /**
     * Broadcast match completed
     */
    public function broadcastMatchCompleted(BracketMatch $match, array $previousState = []): void
    {
        $winner = $match->getWinner();
        $this->broadcastMatchUpdate($match, 'match_completed', $previousState, [
            'winner' => $winner ? ['id' => $winner->id, 'name' => $winner->name] : null,
            'final_score' => "{$match->team1_score}-{$match->team2_score}",
            'duration' => $match->started_at ? $match->started_at->diffForHumans($match->completed_at) : null,
            'message' => $winner ? "{$winner->name} wins!" : "Match completed"
        ]);
    }

    /**
     * Broadcast team registered
     */
    public function broadcastTeamRegistered(Tournament $tournament, Team $team, TournamentRegistration $registration): void
    {
        $this->broadcastRegistrationUpdate($tournament, $team, $registration, 'team_registered', [
            'message' => "{$team->name} has registered for the tournament!",
            'spots_remaining' => $tournament->max_teams - $tournament->current_team_count
        ]);
    }

    /**
     * Broadcast team checked in
     */
    public function broadcastTeamCheckedIn(Tournament $tournament, Team $team, TournamentRegistration $registration): void
    {
        $this->broadcastRegistrationUpdate($tournament, $team, $registration, 'team_checked_in', [
            'message' => "{$team->name} has checked in!",
            'checked_in_teams' => $tournament->registrations()->where('check_in_status', 'checked_in')->count()
        ]);
    }

    /**
     * Broadcast registration closed
     */
    public function broadcastRegistrationClosed(Tournament $tournament): void
    {
        $this->broadcastTournamentUpdate($tournament, 'registration_closed', ['registration_open' => false], [
            'message' => "Registration for '{$tournament->name}' is now closed!",
            'final_team_count' => $tournament->current_team_count
        ]);
    }

    /**
     * Broadcast check-in opened
     */
    public function broadcastCheckInOpened(Tournament $tournament): void
    {
        $this->broadcastTournamentUpdate($tournament, 'check_in_opened', ['check_in_open' => true], [
            'message' => "Check-in for '{$tournament->name}' is now open!",
            'check_in_deadline' => $tournament->check_in_deadline?->toISOString()
        ]);
    }

    /**
     * Send system message to tournament chat
     */
    public function sendSystemMessage(int $tournamentId, string $message, array $metadata = []): void
    {
        $systemUser = User::where('role', 'admin')->first();
        if ($systemUser) {
            $this->broadcastChatMessage($systemUser, $message, 'tournament_general', $tournamentId, true, $metadata);
        }
    }

    /**
     * Send system message to match chat
     */
    public function sendMatchSystemMessage(int $matchId, string $message, array $metadata = []): void
    {
        $systemUser = User::where('role', 'admin')->first();
        if ($systemUser) {
            $this->broadcastChatMessage($systemUser, $message, 'tournament_match', $matchId, true, $metadata);
        }
    }
}