<?php

namespace App\Events;

use App\Models\MvrlMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveMatchUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;
    public $updateType;
    public $updateData;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(MvrlMatch $match, string $updateType, array $updateData = [])
    {
        $this->match = $match;
        $this->updateType = $updateType;
        $this->updateData = $updateData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new Channel('match.' . $this->match->id),
            new Channel('live-scoring')
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'live.update';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $mapsData = $this->match->maps_data ?? [];
        $playerStats = $this->match->player_stats ?? [];
        $timerData = $this->match->match_timer ?? null;
        
        // Extract hero selections from player stats
        $heroSelections = [];
        foreach ($playerStats as $playerId => $stats) {
            if (isset($stats['hero'])) {
                $heroSelections[] = [
                    'player_id' => $playerId,
                    'hero' => $stats['hero'],
                    'team' => $stats['team'] ?? null
                ];
            }
        }
        
        // Calculate current map scores
        $currentMapIndex = ($this->match->current_map_number ?? 1) - 1;
        $currentMapScores = null;
        if (isset($mapsData[$currentMapIndex])) {
            $currentMapScores = [
                'map_number' => $this->match->current_map_number,
                'team1_score' => $mapsData[$currentMapIndex]['team1_score'] ?? 0,
                'team2_score' => $mapsData[$currentMapIndex]['team2_score'] ?? 0,
                'status' => $mapsData[$currentMapIndex]['status'] ?? 'ongoing'
            ];
        }
        
        return [
            'match_id' => $this->match->id,
            'update_type' => $this->updateType,
            'timestamp' => now()->toISOString(),
            'match_status' => $this->match->status,
            'current_map' => $this->match->current_map_number,
            'series_score' => [
                'team1' => $this->match->team1_score,
                'team2' => $this->match->team2_score
            ],
            'current_map_scores' => $currentMapScores,
            'all_maps' => $mapsData,
            'hero_selections' => $heroSelections,
            'player_stats' => $playerStats,
            'timer' => $timerData,
            'format' => $this->match->format,
            'team1_id' => $this->match->team1_id,
            'team2_id' => $this->match->team2_id,
            'specific_update' => $this->updateData,
            'urls' => [
                'stream' => $this->match->stream_urls ?? [],
                'betting' => $this->match->betting_urls ?? [],
                'vod' => $this->match->vod_urls ?? []
            ]
        ];
    }
}