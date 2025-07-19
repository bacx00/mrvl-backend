<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create the main admin user from frontend credentials
        $mainAdmin = User::firstOrCreate(
            ['email' => 'jhonny@ar-mediia.com'],
            [
                'name' => 'Jhonny Admin',
                'password' => 'password123',
                'status' => 'active'
            ]
        );
        $mainAdmin->assignRole('admin');

        // Create an additional admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password'
            ]
        );
        $admin->assignRole('admin');

        // Create a regular user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => 'password'
            ]
        );
        $user->assignRole('user');
    }
}