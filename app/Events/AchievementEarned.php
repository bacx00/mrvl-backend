<?php

namespace App\Events;

use App\Models\UserAchievement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementEarned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public UserAchievement $userAchievement;

    /**
     * Create a new event instance.
     */
    public function __construct(UserAchievement $userAchievement)
    {
        $this->userAchievement = $userAchievement;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userAchievement->user_id}")
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'achievement.earned';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $achievement = $this->userAchievement->achievement;
        
        return [
            'achievement' => [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'badge_color' => $achievement->badge_color,
                'category' => $achievement->category,
                'rarity' => $achievement->rarity,
                'points' => $achievement->points
            ],
            'user_achievement' => [
                'completed_at' => $this->userAchievement->completed_at,
                'completion_count' => $this->userAchievement->completion_count
            ],
            'timestamp' => now()->toISOString()
        ];
    }
}