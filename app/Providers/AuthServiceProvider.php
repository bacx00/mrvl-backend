<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Thread;
use App\Models\Post;
use App\Policies\ThreadPolicy;
use App\Policies\PostPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Thread::class => ThreadPolicy::class,
        Post::class   => PostPolicy::class,
        // ... (other policy mappings)
    ];

    public function boot()
    {
        // Nothing additional needed here for now
    }
}