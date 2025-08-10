<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Listeners\TournamentEventListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event ⇒ listener mappings.
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     */
    protected $subscribe = [
        TournamentEventListener::class,
        \App\Listeners\TournamentCacheInvalidationListener::class,
    ];

    public function boot(): void
    {
        //
    }
}
