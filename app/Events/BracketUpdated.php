<?php

namespace App\Events;

use App\Models\BracketMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BracketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public BracketMatch $match;
    public array $advancement;
    public array $tournamentState;

    public function __construct(BracketMatch $match, array $advancement = [], array $tournamentState = [])
    {
        $this->match = $match->load(['team1', 'team2', 'winner', 'loser', 'bracketStage']);
        $this->advancement = $advancement;
        $this->tournamentState = $tournamentState;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Tournament-specific channel
        if ($this->match->tournament_id) {
            $channels[] = new Channel("tournament.{$this->match->tournament_id}");
        }
        
        // Event-specific channel  
        if ($this->match->event_id) {
            $channels[] = new Channel("event.{$this->match->event_id}");
        }
        
        // Bracket stage channel
        $channels[] = new Channel("bracket.stage.{$this->match->bracket_stage_id}");
        
        // Match-specific channel
        $channels[] = new Channel("match.{$this->match->id}");
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'bracket_updated',
            'timestamp' => now()->toISOString(),
            'match' => [
                'id' => $this->match->id,
                'match_id' => $this->match->match_id,
                'liquipedia_id' => $this->match->liquipedia_id,
                'round_name' => $this->match->round_name,
                'round_number' => $this->match->round_number,
                'match_number' => $this->match->match_number,
                'teams' => [
                    'team1' => $this->formatTeam($this->match->team1),
                    'team2' => $this->formatTeam($this->match->team2)
                ],
                'score' => [
                    'team1' => $this->match->team1_score,
                    'team2' => $this->match->team2_score,
                    'best_of' => $this->match->best_of
                ],
                'result' => [
                    'winner' => $this->formatTeam($this->match->winner),
                    'loser' => $this->formatTeam($this->match->loser),
                    'status' => $this->match->status
                ],
                'stage' => [
                    'id' => $this->match->bracketStage->id,
                    'name' => $this->match->bracketStage->name,
                    'type' => $this->match->bracketStage->type
                ],
                'completed_at' => $this->match->completed_at
            ],
            'advancement' => $this->advancement,
            'tournament_state' => $this->tournamentState
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bracket.updated';
    }

    private function formatTeam($team): ?array
    {
        if (!$team) return null;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo,
            'region' => $team->region
        ];
    }
}