<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Register morph map for polymorphic relationships
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'news' => \App\Models\News::class,
            'news_comment' => \App\Models\NewsComment::class,
            'forum_thread' => \App\Models\ForumThread::class,
            'forum_post' => 'forum_post', // Keep as string since model doesn't exist
            'match' => \App\Models\MvrlMatch::class,
            'match_comment' => 'match_comment', // Keep as string since model doesn't exist
            'player' => \App\Models\Player::class,
            'team' => \App\Models\Team::class,
            'user' => \App\Models\User::class,
        ]);
    }
}
