<?php

namespace App\Events;

use App\Models\Tournament;
use App\Models\BracketMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentMatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $match;
    public $updateType;
    public $eventData;

    /**
     * Create a new event instance.
     */
    public function __construct(Tournament $tournament, BracketMatch $match, string $updateType = 'updated', array $eventData = [])
    {
        $this->tournament = $tournament;
        $this->match = $match;
        $this->updateType = $updateType;
        $this->eventData = $eventData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("tournament.{$this->tournament->id}"),
            new Channel("tournament.{$this->tournament->id}.matches"),
            new Channel("match.{$this->match->id}"),
            new Channel('tournaments.live'),
            new Channel('matches.live'),
            new Channel("tournament-bracket.{$this->match->tournament_bracket_id}"),
            new Channel("tournament-phase.{$this->match->tournament_phase_id}")
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'match_updated',
            'update_type' => $this->updateType,
            'tournament' => [
                'id' => $this->tournament->id,
                'name' => $this->tournament->name,
                'slug' => $this->tournament->slug,
                'current_phase' => $this->tournament->current_phase,
                'format' => $this->tournament->format
            ],
            'match' => [
                'id' => $this->match->id,
                'match_identifier' => $this->match->match_identifier,
                'round' => $this->match->round,
                'match_number' => $this->match->match_number,
                'status' => $this->match->status,
                'team1' => $this->match->team1 ? [
                    'id' => $this->match->team1->id,
                    'name' => $this->match->team1->name,
                    'short_name' => $this->match->team1->short_name,
                    'logo' => $this->match->team1->logo
                ] : null,
                'team2' => $this->match->team2 ? [
                    'id' => $this->match->team2->id,
                    'name' => $this->match->team2->name,
                    'short_name' => $this->match->team2->short_name,
                    'logo' => $this->match->team2->logo
                ] : null,
                'team1_score' => $this->match->team1_score,
                'team2_score' => $this->match->team2_score,
                'match_format' => $this->match->match_format,
                'scheduled_at' => $this->match->scheduled_at?->toISOString(),
                'started_at' => $this->match->started_at?->toISOString(),
                'completed_at' => $this->match->completed_at?->toISOString(),
                'is_walkover' => $this->match->is_walkover,
                'walkover_reason' => $this->match->walkover_reason,
                'stream_url' => $this->match->stream_url,
                'tournament_phase_id' => $this->match->tournament_phase_id,
                'tournament_bracket_id' => $this->match->tournament_bracket_id
            ],
            'bracket_info' => [
                'bracket_id' => $this->match->tournament_bracket_id,
                'phase_id' => $this->match->tournament_phase_id,
                'advancement_info' => $this->getAdvancementInfo()
            ],
            'event_data' => $this->eventData,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return "tournament.match.{$this->updateType}";
    }

    /**
     * Get advancement information for the match
     */
    private function getAdvancementInfo(): array
    {
        if ($this->match->status !== 'completed') {
            return [];
        }

        $winnerId = null;
        $loserId = null;

        if ($this->match->team1_score > $this->match->team2_score) {
            $winnerId = $this->match->team1_id;
            $loserId = $this->match->team2_id;
        } elseif ($this->match->team2_score > $this->match->team1_score) {
            $winnerId = $this->match->team2_id;
            $loserId = $this->match->team1_id;
        }

        return [
            'winner_id' => $winnerId,
            'loser_id' => $loserId,
            'advances_to' => $this->getNextMatchInfo(),
            'loser_fate' => $this->getLoserFate()
        ];
    }

    /**
     * Get next match information for winner
     */
    private function getNextMatchInfo(): ?array
    {
        // This would require bracket data analysis
        // Implementation depends on bracket structure
        return null;
    }

    /**
     * Get information about what happens to the loser
     */
    private function getLoserFate(): string
    {
        $bracket = $this->match->tournamentBracket;
        
        if (!$bracket) return 'unknown';

        switch ($bracket->bracket_type) {
            case 'single_elimination':
                return 'eliminated';
            case 'double_elimination_upper':
                return 'drops_to_lower_bracket';
            case 'double_elimination_lower':
                return 'eliminated';
            case 'swiss_system':
                return 'continues_swiss';
            case 'round_robin':
            case 'group_stage':
                return 'continues_group';
            default:
                return 'unknown';
        }
    }
}