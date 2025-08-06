<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== FIXING ARMENIAN FLAG ===\n\n";

// Correct Armenian flag
$armenianFlag = '🇦🇲';

// Check teams with Armenia
echo "1. Checking teams with Armenia...\n";
$armenianTeams = Team::where('country', 'Armenia')
    ->orWhere('country', 'LIKE', '%armen%')
    ->get();

if ($armenianTeams->count() > 0) {
    foreach ($armenianTeams as $team) {
        echo "   Found team: {$team->name} - Current flag: {$team->country_flag}\n";
        $team->update(['country_flag' => $armenianFlag]);
        echo "   ✓ Updated to: {$armenianFlag}\n";
    }
} else {
    echo "   No Armenian teams found.\n";
}

// Check players with Armenia
echo "\n2. Checking players with Armenia...\n";
$armenianPlayers = Player::where('country', 'Armenia')
    ->orWhere('country', 'LIKE', '%armen%')
    ->get();

if ($armenianPlayers->count() > 0) {
    echo "   Found {$armenianPlayers->count()} Armenian players\n";
    foreach ($armenianPlayers as $player) {
        $player->update(['country_flag' => $armenianFlag]);
    }
    echo "   ✓ Updated all Armenian player flags to: {$armenianFlag}\n";
} else {
    echo "   No Armenian players found.\n";
}

// Also check if any flags are using wrong Armenian flag
echo "\n3. Checking for incorrect Armenian flags...\n";

// Common wrong representations
$wrongFlags = ['🇦🇲', '🇦🇷', 'AM', 'ARM'];

foreach ($wrongFlags as $wrongFlag) {
    if ($wrongFlag === '🇦🇲') continue; // Skip the correct flag
    
    $teamsWithWrongFlag = Team::where('country_flag', $wrongFlag)
        ->where('country', 'Armenia')
        ->count();
    
    $playersWithWrongFlag = Player::where('country_flag', $wrongFlag)
        ->where('country', 'Armenia')
        ->count();
    
    if ($teamsWithWrongFlag > 0 || $playersWithWrongFlag > 0) {
        echo "   Found $teamsWithWrongFlag teams and $playersWithWrongFlag players with wrong flag: $wrongFlag\n";
        
        Team::where('country_flag', $wrongFlag)
            ->where('country', 'Armenia')
            ->update(['country_flag' => $armenianFlag]);
            
        Player::where('country_flag', $wrongFlag)
            ->where('country', 'Armenia')
            ->update(['country_flag' => $armenianFlag]);
            
        echo "   ✓ Fixed!\n";
    }
}

// Verify the Armenian flag is correct
echo "\n4. Verifying Armenian flag...\n";
echo "   Correct Armenian flag should be: 🇦🇲\n";
echo "   Unicode: U+1F1E6 U+1F1F2\n";

// Show all unique country flags in the database
echo "\n5. All country flags in database:\n";
$allFlags = Team::selectRaw('DISTINCT country, country_flag')
    ->whereNotNull('country')
    ->orderBy('country')
    ->get();

foreach ($allFlags as $flag) {
    if (stripos($flag->country, 'armen') !== false) {
        echo "   >>> {$flag->country}: {$flag->country_flag} <<<\n";
    } else {
        echo "   {$flag->country}: {$flag->country_flag}\n";
    }
}

echo "\n✓ Armenian flag check complete!\n";