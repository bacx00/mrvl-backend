<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that
| your application supports.  To learn more about broadcasting,
| see the documentation at https://laravel.com/docs/broadcasting
|
*/

use Illuminate\Support\Facades\Broadcast;

// Public channel for match updates - anyone can listen to live match data
Broadcast::channel('match.{matchId}', function ($user = null, $matchId) {
    // Allow all users (authenticated or not) to listen to match updates
    return true;
});

// Example:
// Broadcast::channel('order.{orderId}', function ($user, $orderId) {
//     return (int) $user->id === (int) Order::find($orderId)->user_id;
// });
