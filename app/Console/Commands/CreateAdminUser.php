<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {--email=admin@marvelrivals.com} {--password=password123} {--name=Admin User}';
    protected $description = 'Create an admin user for testing';

    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("❌ User with email {$email} already exists!");
            
            if ($this->confirm('Do you want to update the existing user?')) {
                $user = User::where('email', $email)->first();
                $user->password = Hash::make($password);
                $user->role = 'admin';
                $user->save();
                
                $this->info("✅ Updated existing user: {$email}");
                return 0;
            }
            
            return 1;
        }

        // Create new admin user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info("✅ Admin user created successfully!");
        $this->info("📧 Email: {$email}");
        $this->info("🔑 Password: {$password}");
        $this->info("👤 Role: admin");

        return 0;
    }
}