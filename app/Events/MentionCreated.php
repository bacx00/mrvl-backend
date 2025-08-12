<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Mention;
use App\Models\User;

class MentionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $mention;
    public $mentionedUser;
    public $contentContext;

    /**
     * Create a new event instance.
     */
    public function __construct(Mention $mention, User $mentionedUser, array $contentContext = [])
    {
        $this->mention = $mention;
        $this->mentionedUser = $mentionedUser;
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
            new Channel('mentions.' . $this->mention->mentionable_type)
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'mention.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'mention' => [
                'id' => $this->mention->id,
                'mention_text' => $this->mention->mention_text,
                'context' => $this->mention->context,
                'position_start' => $this->mention->position_start,
                'position_end' => $this->mention->position_end,
                'mentioned_at' => $this->mention->mentioned_at->toISOString(),
            ],
            'mentioned_user' => [
                'id' => $this->mentionedUser->id,
                'name' => $this->mentionedUser->name,
                'avatar' => $this->mentionedUser->avatar,
            ],
            'mentioned_by' => $this->mention->mentionedBy ? [
                'id' => $this->mention->mentionedBy->id,
                'name' => $this->mention->mentionedBy->name,
                'avatar' => $this->mention->mentionedBy->avatar,
            ] : null,
            'content' => [
                'type' => $this->mention->mentionable_type,
                'id' => $this->mention->mentionable_id,
                'title' => $this->contentContext['title'] ?? null,
                'url' => $this->contentContext['url'] ?? null,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}