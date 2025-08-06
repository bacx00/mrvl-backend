<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "Updating team regions based on country...\n\n";

$regionMap = [
    // North America
    'United States' => 'NA',
    'Canada' => 'NA',
    'Mexico' => 'NA',
    
    // South America
    'Brazil' => 'SA',
    'Argentina' => 'SA',
    'Chile' => 'SA',
    'Peru' => 'SA',
    'Colombia' => 'SA',
    'Venezuela' => 'SA',
    'Uruguay' => 'SA',
    
    // Europe
    'United Kingdom' => 'EU',
    'Germany' => 'EU',
    'France' => 'EU',
    'Spain' => 'EU',
    'Italy' => 'EU',
    'Netherlands' => 'EU',
    'Belgium' => 'EU',
    'Sweden' => 'EU',
    'Denmark' => 'EU',
    'Norway' => 'EU',
    'Finland' => 'EU',
    'Poland' => 'EU',
    'Iceland' => 'EU',
    'Malta' => 'EU',
    
    // CIS
    'Russia' => 'CIS',
    'Ukraine' => 'CIS',
    'Armenia' => 'CIS',
    
    // Middle East
    'Turkey' => 'MENA',
    'Saudi Arabia' => 'MENA',
    'United Arab Emirates' => 'MENA',
    'Egypt' => 'MENA',
    'Kuwait' => 'MENA',
    'Lebanon' => 'MENA',
    'Jordan' => 'MENA',
    'Qatar' => 'MENA',
    
    // Asia
    'South Korea' => 'KR',
    'Japan' => 'JP',
    'China' => 'CN',
    'Taiwan' => 'ASIA',
    'Singapore' => 'SEA',
    'Malaysia' => 'SEA',
    'Philippines' => 'SEA',
    'Indonesia' => 'SEA',
    'Thailand' => 'SEA',
    'Vietnam' => 'SEA',
    'India' => 'ASIA',
    
    // Oceania
    'Australia' => 'OCE',
    'New Zealand' => 'OCE',
];

$updated = 0;

Team::all()->each(function($team) use ($regionMap, &$updated) {
    if ($team->country && isset($regionMap[$team->country])) {
        $newRegion = $regionMap[$team->country];
        if ($team->region !== $newRegion) {
            $team->update(['region' => $newRegion]);
            echo "Updated {$team->name}: {$team->region} -> {$newRegion}\n";
            $updated++;
            
            // Update players' regions too
            $team->players()->update(['region' => $newRegion]);
        }
    }
});

echo "\nUpdated $updated teams\n\n";

// Show regional distribution
echo "Regional distribution:\n";
Team::selectRaw('region, count(*) as count')
    ->groupBy('region')
    ->orderBy('count', 'desc')
    ->get()
    ->each(function($r) {
        echo "- {$r->region}: {$r->count} teams\n";
    });

// Fix player names to remove team suffixes
echo "\n\nFixing player names (removing team suffixes)...\n";
$fixedCount = 0;

Player::where('username', 'LIKE', '%\_%')->get()->each(function($player) use (&$fixedCount) {
    // Check if username contains underscore followed by uppercase letters (likely team suffix)
    if (preg_match('/^(.+?)_[A-Z]+$/', $player->username, $matches)) {
        $cleanUsername = $matches[1];
        if ($cleanUsername === $player->name) {
            // Username already matches name, no need to update
            return;
        }
        $player->update(['username' => $cleanUsername]);
        echo "Fixed: {$player->username} -> {$cleanUsername}\n";
        $fixedCount++;
    }
});

echo "\nFixed $fixedCount player usernames\n";