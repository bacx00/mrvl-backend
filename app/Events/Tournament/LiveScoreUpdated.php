<?php

namespace App\Events\Tournament;

use App\Models\BracketMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;
    public $scoreData;
    public $updateType;
    public $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(BracketMatch $match, array $scoreData, string $updateType, array $metadata = [])
    {
        $this->match = $match->load(['team1', 'team2', 'tournament']);
        $this->scoreData = $scoreData;
        $this->updateType = $updateType;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.' . $this->match->tournament_id),
            new Channel('tournament.' . $this->match->tournament_id . '.live'),
            new Channel('match.' . $this->match->id . '.live'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'tournament_id' => $this->match->tournament_id,
            'round' => $this->match->round,
            'match_number' => $this->match->match_number,
            'status' => $this->match->status,
            'team1' => [
                'id' => $this->match->team1?->id,
                'name' => $this->match->team1?->name,
                'short_name' => $this->match->team1?->short_name,
                'score' => $this->scoreData['team1_score'] ?? $this->match->team1_score,
                'map_scores' => $this->scoreData['team1_map_scores'] ?? [],
            ],
            'team2' => [
                'id' => $this->match->team2?->id,
                'name' => $this->match->team2?->name,
                'short_name' => $this->match->team2?->short_name,
                'score' => $this->scoreData['team2_score'] ?? $this->match->team2_score,
                'map_scores' => $this->scoreData['team2_map_scores'] ?? [],
            ],
            'current_map' => $this->scoreData['current_map'] ?? null,
            'map_results' => $this->scoreData['map_results'] ?? [],
            'live_stats' => $this->scoreData['live_stats'] ?? [],
            'match_time' => $this->scoreData['match_time'] ?? null,
            'update_type' => $this->updateType,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'live.score.updated';
    }
}