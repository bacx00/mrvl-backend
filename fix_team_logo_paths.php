<?php

/**
 * Script to fix team logo paths in the database
 * This script identifies incorrect team logo paths and corrects them to use the proper storage structure
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Helpers\ImageHelper;
use Illuminate\Support\Facades\DB;

echo "=== Team Logo Path Fixer ===\n";
echo "This script will analyze and fix team logo paths in the database.\n\n";

// Get all teams with logo paths
$teams = Team::whereNotNull('logo')->where('logo', '!=', '')->get();

echo "Found " . $teams->count() . " teams with logo paths.\n\n";

$fixed = 0;
$issues = 0;
$debugInfo = [];

foreach ($teams as $team) {
    echo "Processing: {$team->name} (ID: {$team->id})\n";
    echo "  Current logo path: {$team->logo}\n";
    
    // Debug the current logo
    $debug = ImageHelper::debugTeamLogo($team->logo, $team->name);
    $debugInfo[] = $debug;
    
    echo "  Status: {$debug['status']}\n";
    echo "  Found paths: " . json_encode($debug['found_paths']) . "\n";
    
    if ($debug['status'] === 'found' && !empty($debug['recommended_path'])) {
        $recommendedPath = ltrim($debug['recommended_path'], '/');
        
        // Check if we need to update the path
        if ($recommendedPath !== $team->logo) {
            // Update to use storage path format
            if (strpos($recommendedPath, 'storage/teams/logos/') === 0) {
                $newPath = str_replace('storage/teams/logos/', 'teams/logos/', $recommendedPath);
            } else if (strpos($recommendedPath, 'teams/') === 0) {
                $newPath = str_replace('teams/', 'teams/logos/', $recommendedPath);
            } else {
                $newPath = $team->logo;
            }
            
            echo "  Updating path to: {$newPath}\n";
            
            // Update the database
            DB::table('teams')
                ->where('id', $team->id)
                ->update(['logo' => $newPath]);
            
            $fixed++;
        } else {
            echo "  Path is already correct\n";
        }
    } else {
        echo "  ⚠️  Issue: Logo file not found for {$team->name}\n";
        $issues++;
    }
    
    echo "\n";
}

echo "=== Summary ===\n";
echo "Teams processed: " . $teams->count() . "\n";
echo "Paths fixed: {$fixed}\n";
echo "Issues found: {$issues}\n";

if ($issues > 0) {
    echo "\n=== Teams with missing logo files ===\n";
    foreach ($debugInfo as $debug) {
        if ($debug['status'] === 'not_found') {
            echo "- {$debug['team_name']}: {$debug['original_path']}\n";
        }
    }
}

// Test the API response
echo "\n=== Testing API Response ===\n";
$testTeams = Team::whereNotNull('logo')
    ->where('logo', '!=', '')
    ->limit(3)
    ->get();

foreach ($testTeams as $team) {
    $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
    echo "{$team->name}:\n";
    echo "  URL: {$logoInfo['url']}\n";
    echo "  Exists: " . ($logoInfo['exists'] ? 'Yes' : 'No') . "\n";
    echo "  Fallback text: {$logoInfo['fallback']['text']}\n";
    echo "\n";
}

echo "Script completed!\n";