<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Thread;
use App\Models\Post;
use App\Models\Match;
use App\Models\Event;

class AdminStatsController extends Controller
{
    public function index()
    {
        $stats = [
            'users'   => User::count(),
            'threads' => Thread::count(),
            'posts'   => Post::count(),
            'matches' => Match::count(),
            'events'  => Event::count(),
        ];
        return response()->json($stats);
    }
}