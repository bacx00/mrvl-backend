<?php

namespace App\Events\Tournament;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BracketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tournament;
    public $phase;
    public $updateType;
    public $bracketData;
    public $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Tournament $tournament, ?TournamentPhase $phase, string $updateType, array $bracketData = [], array $metadata = [])
    {
        $this->tournament = $tournament;
        $this->phase = $phase;
        $this->updateType = $updateType;
        $this->bracketData = $bracketData;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('tournament.' . $this->tournament->id),
            new Channel('tournament.' . $this->tournament->id . '.bracket'),
        ];

        if ($this->phase) {
            $channels[] = new Channel('tournament.' . $this->tournament->id . '.phase.' . $this->phase->id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name,
            'tournament_format' => $this->tournament->format,
            'phase_id' => $this->phase?->id,
            'phase_name' => $this->phase?->name,
            'phase_type' => $this->phase?->type,
            'update_type' => $this->updateType,
            'bracket_data' => $this->bracketData,
            'current_round' => $this->getCurrentRound(),
            'total_rounds' => $this->getTotalRounds(),
            'matches_completed' => $this->getCompletedMatches(),
            'matches_pending' => $this->getPendingMatches(),
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bracket.updated';
    }

    private function getCurrentRound(): int
    {
        return $this->tournament->bracketMatches()
                   ->where('tournament_phase_id', $this->phase?->id)
                   ->max('round') ?? 0;
    }

    private function getTotalRounds(): int
    {
        if ($this->tournament->format === 'swiss') {
            $settings = $this->tournament->qualification_settings ?? [];
            return $settings['swiss_rounds'] ?? ceil(log($this->tournament->current_team_count, 2));
        }

        return ceil(log($this->tournament->current_team_count, 2));
    }

    private function getCompletedMatches(): int
    {
        return $this->tournament->bracketMatches()
                   ->where('tournament_phase_id', $this->phase?->id)
                   ->where('status', 'completed')
                   ->count();
    }

    private function getPendingMatches(): int
    {
        return $this->tournament->bracketMatches()
                   ->where('tournament_phase_id', $this->phase?->id)
                   ->where('status', 'pending')
                   ->count();
    }
}