<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\Mention;

class MentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $mention;
    protected $contentType;
    protected $contentTitle;
    protected $contentUrl;
    protected $mentionedBy;

    public function __construct(Mention $mention, array $contentContext = [])
    {
        $this->mention = $mention;
        $this->contentType = $contentContext['type'] ?? 'content';
        $this->contentTitle = $contentContext['title'] ?? 'Unknown Content';
        $this->contentUrl = $contentContext['url'] ?? '#';
        $this->mentionedBy = $mention->mentionedBy;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('You were mentioned')
                    ->greeting("Hello {$notifiable->name}!")
                    ->line("You were mentioned by {$this->mentionedBy->name} in {$this->contentTitle}.")
                    ->line("Context: \"{$this->mention->context}\"")
                    ->action('View Content', $this->contentUrl)
                    ->line('Thank you for being part of our community!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        return [
            'type' => 'mention',
            'mention_id' => $this->mention->id,
            'mention_text' => $this->mention->mention_text,
            'content_type' => $this->contentType,
            'content_title' => $this->contentTitle,
            'content_url' => $this->contentUrl,
            'context' => $this->mention->context,
            'mentioned_by' => [
                'id' => $this->mentionedBy->id,
                'name' => $this->mentionedBy->name,
                'avatar' => $this->mentionedBy->avatar
            ],
            'created_at' => now(),
            'message' => "{$this->mentionedBy->name} mentioned you in {$this->contentTitle}"
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'mention',
            'mention_id' => $this->mention->id,
            'mention_text' => $this->mention->mention_text,
            'content_type' => $this->contentType,
            'content_title' => $this->contentTitle,
            'content_url' => $this->contentUrl,
            'context' => $this->mention->context,
            'mentioned_by' => [
                'id' => $this->mentionedBy->id,
                'name' => $this->mentionedBy->name,
                'avatar' => $this->mentionedBy->avatar
            ],
            'created_at' => now()->toISOString(),
            'message' => "{$this->mentionedBy->name} mentioned you in {$this->contentTitle}",
            'read_at' => null
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'mention_id' => $this->mention->id,
            'mention_text' => $this->mention->mention_text,
            'content_type' => $this->contentType,
            'content_title' => $this->contentTitle,
            'content_url' => $this->contentUrl,
            'mentioned_by' => $this->mentionedBy->name,
            'message' => "{$this->mentionedBy->name} mentioned you in {$this->contentTitle}"
        ];
    }
}