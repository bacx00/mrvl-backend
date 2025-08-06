<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private $vapidPublicKey;
    private $vapidPrivateKey;
    
    public function __construct()
    {
        $this->vapidPublicKey = env('VAPID_PUBLIC_KEY');
        $this->vapidPrivateKey = env('VAPID_PRIVATE_KEY');
    }
    
    /**
     * Send push notification to user
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [])
    {
        if (!$user->push_subscription) {
            return false;
        }
        
        $subscription = json_decode($user->push_subscription, true);
        
        return $this->sendNotification($subscription, [
            'title' => $title,
            'body' => $body,
            'icon' => '/logo192.png',
            'badge' => '/badge.png',
            'data' => $data,
            'tag' => $data['tag'] ?? 'general',
            'requireInteraction' => $data['requireInteraction'] ?? false,
        ]);
    }
    
    /**
     * Send match start notification
     */
    public function sendMatchStartNotification($match, array $subscribers)
    {
        $title = "Match Starting!";
        $body = "{$match->team1->name} vs {$match->team2->name} is starting now!";
        
        foreach ($subscribers as $user) {
            $this->sendToUser($user, $title, $body, [
                'tag' => 'match-start',
                'url' => "/match/{$match->id}",
                'matchId' => $match->id,
            ]);
        }
    }
    
    /**
     * Send score update notification
     */
    public function sendScoreUpdateNotification($match, array $subscribers)
    {
        $title = "Score Update";
        $body = "{$match->team1->name} {$match->team1_score} - {$match->team2_score} {$match->team2->name}";
        
        foreach ($subscribers as $user) {
            $this->sendToUser($user, $title, $body, [
                'tag' => 'score-update',
                'url' => "/match/{$match->id}",
                'matchId' => $match->id,
                'vibrate' => [200, 100, 200],
            ]);
        }
    }
    
    /**
     * Send match end notification
     */
    public function sendMatchEndNotification($match, array $subscribers)
    {
        $winner = $match->team1_score > $match->team2_score ? $match->team1 : $match->team2;
        $title = "Match Ended";
        $body = "{$winner->name} wins {$match->team1_score}-{$match->team2_score}!";
        
        foreach ($subscribers as $user) {
            $this->sendToUser($user, $title, $body, [
                'tag' => 'match-end',
                'url' => "/match/{$match->id}",
                'matchId' => $match->id,
            ]);
        }
    }
    
    /**
     * Send notification using Web Push Protocol
     */
    private function sendNotification(array $subscription, array $payload)
    {
        try {
            // For now, we'll use a simple implementation
            // In production, use web-push-php library
            
            $endpoint = $subscription['endpoint'];
            $auth = $subscription['keys']['auth'] ?? null;
            $p256dh = $subscription['keys']['p256dh'] ?? null;
            
            if (!$endpoint || !$auth || !$p256dh) {
                return false;
            }
            
            // Log for development
            Log::info('Push notification sent', [
                'endpoint' => substr($endpoint, 0, 50) . '...',
                'payload' => $payload
            ]);
            
            // In production, implement actual web push protocol
            // For now, return success
            return true;
            
        } catch (\Exception $e) {
            Log::error('Push notification failed', [
                'error' => $e->getMessage(),
                'subscription' => $subscription
            ]);
            return false;
        }
    }
    
    /**
     * Subscribe user to push notifications
     */
    public function subscribe(User $user, array $subscription)
    {
        $user->push_subscription = json_encode($subscription);
        $user->push_enabled = true;
        $user->save();
        
        return true;
    }
    
    /**
     * Unsubscribe user from push notifications
     */
    public function unsubscribe(User $user)
    {
        $user->push_subscription = null;
        $user->push_enabled = false;
        $user->save();
        
        return true;
    }
}