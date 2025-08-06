<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserProfileUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $updatedData;
    public $updateType;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param array $updatedData
     * @param string $updateType
     */
    public function __construct($userId, $updatedData, $updateType = 'profile')
    {
        $this->userId = $userId;
        $this->updatedData = $updatedData;
        $this->updateType = $updateType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('user.' . $this->userId),
            new Channel('profile-updates')
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'profile.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'user_id' => $this->userId,
            'updated_data' => $this->updatedData,
            'update_type' => $this->updateType,
            'timestamp' => now()->toIso8601String()
        ];
    }
}