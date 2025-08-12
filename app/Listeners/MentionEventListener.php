<?php

namespace App\Listeners;

use App\Events\MentionCreated;
use App\Events\MentionDeleted;
use App\Models\User;
use App\Models\Player;
use App\Models\Team;
use App\Notifications\MentionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MentionEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle mention created events.
     */
    public function handleMentionCreated(MentionCreated $event)
    {
        try {
            // Send notification to the mentioned user
            if ($event->mentionedUser) {
                $event->mentionedUser->notify(new MentionNotification($event->mention, $event->contentContext));
            }

            // Update mention count cache for the mentioned entity
            $this->updateMentionCountCache($event->mention->mentioned_type, $event->mention->mentioned_id, 'increment');

            // Log the mention creation for analytics
            Log::info('Mention created', [
                'mention_id' => $event->mention->id,
                'mentioned_user_id' => $event->mentionedUser->id,
                'mentioned_type' => $event->mention->mentioned_type,
                'mentioned_id' => $event->mention->mentioned_id,
                'content_type' => $event->mention->mentionable_type,
                'content_id' => $event->mention->mentionable_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling mention created event', [
                'mention_id' => $event->mention->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle mention deleted events.
     */
    public function handleMentionDeleted(MentionDeleted $event)
    {
        try {
            // Update mention count cache for the mentioned entity
            $this->updateMentionCountCache($event->mentionedType, $event->mentionedId, 'decrement');

            // Log the mention deletion for analytics
            Log::info('Mention deleted', [
                'mention_id' => $event->mentionId,
                'mentioned_user_id' => $event->mentionedUser->id,
                'mentioned_type' => $event->mentionedType,
                'mentioned_id' => $event->mentionedId,
                'content_type' => $event->mentionableType,
                'content_id' => $event->mentionableId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling mention deleted event', [
                'mention_id' => $event->mentionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Update mention count cache for an entity.
     */
    private function updateMentionCountCache($entityType, $entityId, $operation = 'increment')
    {
        $cacheKey = "mention_count_{$entityType}_{$entityId}";
        $currentCount = Cache::get($cacheKey, 0);

        if ($operation === 'increment') {
            $newCount = $currentCount + 1;
        } else {
            $newCount = max(0, $currentCount - 1);
        }

        Cache::put($cacheKey, $newCount, now()->addDays(7));

        // Also update recent mentions cache
        $this->updateRecentMentionsCache($entityType, $entityId, $operation);
    }

    /**
     * Update recent mentions cache for an entity.
     */
    private function updateRecentMentionsCache($entityType, $entityId, $operation = 'increment')
    {
        $cacheKey = "recent_mentions_{$entityType}_{$entityId}";
        
        if ($operation === 'increment') {
            // For new mentions, we'll refresh the cache by fetching latest mentions
            $mentions = \App\Models\Mention::where('mentioned_type', $entityType)
                ->where('mentioned_id', $entityId)
                ->where('is_active', true)
                ->with(['mentionedBy', 'mentionable'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            Cache::put($cacheKey, $mentions, now()->addHours(1));
        } else {
            // For deletions, clear the cache to force refresh
            Cache::forget($cacheKey);
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events)
    {
        $events->listen(
            MentionCreated::class,
            [MentionEventListener::class, 'handleMentionCreated']
        );

        $events->listen(
            MentionDeleted::class,
            [MentionEventListener::class, 'handleMentionDeleted']
        );
    }
}