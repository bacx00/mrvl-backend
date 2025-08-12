<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class MentionDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $mentionId;
    public $mentionedUser;
    public $mentionableType;
    public $mentionableId;
    public $mentionedType;
    public $mentionedId;
    public $contentContext;

    /**
     * Create a new event instance.
     */
    public function __construct($mentionId, User $mentionedUser, $mentionableType, $mentionableId, $mentionedType, $mentionedId, array $contentContext = [])
    {
        $this->mentionId = $mentionId;
        $this->mentionedUser = $mentionedUser;
        $this->mentionableType = $mentionableType;
        $this->mentionableId = $mentionableId;
        $this->mentionedType = $mentionedType;
        $this->mentionedId = $mentionedId;
        $this->contentContext = $contentContext;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('user.' . $this->mentionedUser->id),
            new Channel('mentions'),
            new Channel('mentions.' . $this->mentionableType)
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'mention.deleted';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'mention_id' => $this->mentionId,
            'mentioned_user' => [
                'id' => $this->mentionedUser->id,
                'name' => $this->mentionedUser->name,
                'avatar' => $this->mentionedUser->avatar,
            ],
            'content' => [
                'type' => $this->mentionableType,
                'id' => $this->mentionableId,
                'title' => $this->contentContext['title'] ?? null,
                'url' => $this->contentContext['url'] ?? null,
            ],
            'mentioned_entity' => [
                'type' => $this->mentionedType,
                'id' => $this->mentionedId,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}