<?php

namespace App\Events;

use App\Models\UserStreak;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreakMilestone implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UserStreak $streak;
    public int $milestone;

    /**
     * Create a new event instance.
     */
    public function __construct(UserStreak $streak, int $milestone)
    {
        $this->streak = $streak;
        $this->milestone = $milestone;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->streak->user_id}")
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'streak.milestone';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $streakInfo = $this->streak->getStreakInfo();
        
        return [
            'streak' => [
                'type' => $this->streak->streak_type,
                'name' => $streakInfo['name'],
                'icon' => $streakInfo['icon'],
                'current_count' => $this->streak->current_count,
                'best_count' => $this->streak->best_count,
                'milestone' => $this->milestone
            ],
            'message' => "🔥 {$this->milestone}-day {$streakInfo['name']} achieved!",
            'timestamp' => now()->toISOString()
        ];
    }
}