<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes();

        /*
         * Here you may register all of the event broadcasting channels
         * that your application supports.
         */
        require base_path('routes/channels.php');
    }
}
