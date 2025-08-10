<?php

namespace App\Events\Tournament;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $message;
    public $contextType;
    public $contextId;
    public $isSystem;
    public $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $message, string $contextType, int $contextId, bool $isSystem = false, array $metadata = [])
    {
        $this->user = $user;
        $this->message = $message;
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->isSystem = $isSystem;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];

        switch ($this->contextType) {
            case 'tournament_match':
                $channels = [
                    new PrivateChannel('match.' . $this->contextId . '.chat'),
                ];
                break;
            case 'tournament_general':
                $channels = [
                    new Channel('tournament.' . $this->contextId . '.chat'),
                ];
                break;
            case 'team_chat':
                $channels = [
                    new PrivateChannel('team.' . $this->contextId . '.chat'),
                ];
                break;
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
                'role' => $this->user->role,
            ],
            'message' => $this->message,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'is_system' => $this->isSystem,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }
}