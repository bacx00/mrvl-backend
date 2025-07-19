<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use SerializesModels;

    public $username;
    public $message;
    public $timestamp;

    public function __construct(User $user, ChatMessage $message)
    {
        $this->username = $user->name;
        $this->message = $message->message;
        $this->timestamp = now()->toDateTimeString();
    }

    public function broadcastOn()
    {
        return new Channel('chat');
    }

    public function broadcastAs()
    {
        return 'ChatMessageCreated';
    }
}