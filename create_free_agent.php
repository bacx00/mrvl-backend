<?php
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Create a free agent player
    $playerId = DB::table('players')->insertGetId([
        'username' => 'TestPlayer_' . time(),
        'real_name' => 'Test Player',
        'role' => 'Duelist',
        'main_hero' => 'Spider-Man',
        'rating' => 1400,
        'country' => 'US',
        'region' => 'NA',
        'status' => 'active',
        'team_id' => null, // Free agent
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "Created free agent player with ID: $playerId\n";
    
    // Get player details
    $player = DB::table('players')->where('id', $playerId)->first();
    echo "Player Details:\n";
    echo "- Username: {$player->username}\n";
    echo "- Role: {$player->role}\n";
    echo "- Rating: {$player->rating}\n";
    echo "- Team ID: " . ($player->team_id ?: 'Free Agent') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}