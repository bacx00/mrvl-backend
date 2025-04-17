<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create an admin user
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => 'password'
        ]);
        $admin->assignRole('admin');

        // Create a regular user
        $user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => 'password'
        ]);
        $user->assignRole('user');
    }
}