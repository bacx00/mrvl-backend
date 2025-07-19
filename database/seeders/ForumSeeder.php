<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Thread;
use App\Models\Post;

class ForumSeeder extends Seeder
{
    public function run()
    {
        $user = User::whereHas('roles', fn($q) => $q->where('name', 'user'))->first();
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->first();
        if (!$user || !$admin) {
            return;
        }
        // Create a sample thread
        $thread = Thread::create([
            'title'   => 'Welcome to the forum',
            'user_id' => $user->id
        ]);
        // First post by the thread creator
        Post::create([
            'thread_id' => $thread->id,
            'user_id'   => $user->id,
            'content'   => 'Hello everyone! This is the first post in this thread.'
        ]);
        // A reply by the admin user
        Post::create([
            'thread_id' => $thread->id,
            'user_id'   => $admin->id,
            'content'   => 'Welcome to the community! Feel free to discuss anything about MRVL esports here.'
        ]);
    }
}