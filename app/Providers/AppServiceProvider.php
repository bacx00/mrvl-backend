<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\MvrlMatch;
use App\Observers\MatchObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the Match observer for automatic stats syncing
        MvrlMatch::observe(MatchObserver::class);
    }
}
