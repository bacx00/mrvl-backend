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
        
        // Configure mail to bypass SSL verification for Gmail
        if (config('mail.mailers.smtp.host') === 'smtp.gmail.com') {
            config([
                'mail.mailers.smtp.stream' => [
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]
            ]);
        }
    }
}
