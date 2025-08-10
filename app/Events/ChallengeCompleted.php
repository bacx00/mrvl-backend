<?php

namespace App\Events;

use App\Models\UserChallenge;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChallengeCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UserChallenge $userChallenge;

    /**
     * Create a new event instance.
     */
    public function __construct(UserChallenge $userChallenge)
    {
        $this->userChallenge = $userChallenge;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userChallenge->user_id}")
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'challenge.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $challenge = $this->userChallenge->challenge;
        $rank = $this->userChallenge->getRank();
        
        return [
            'challenge' => [
                'id' => $challenge->id,
                'name' => $challenge->name,
                'description' => $challenge->description,
                'icon' => $challenge->icon,
                'difficulty' => $challenge->difficulty,
                'rewards' => $challenge->rewards
            ],
            'user_challenge' => [
                'score' => $this->userChallenge->current_score,
                'rank' => $rank,
                'completed_at' => $this->userChallenge->completed_at,
                'time_spent' => $this->userChallenge->getTimeSpent()
            ],
            'message' => "🎯 Challenge '{$challenge->name}' completed! You finished in position #{$rank}.",
            'timestamp' => now()->toISOString()
        ];
    }
}