<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "Fixing critical issues...\n\n";

DB::beginTransaction();

try {
    // 1. Fix teams with more than 6 active players - keep only top 6 by rating
    echo "1. Fixing teams with too many players...\n";
    $teams = Team::where('status', 'active')->get();
    
    foreach ($teams as $team) {
        $activePlayers = Player::where('team_id', $team->id)
            ->where('status', 'active')
            ->where(function($query) {
                $query->where('team_position', 'player')
                      ->orWhereNull('team_position');
            })
            ->orderBy('rating', 'desc')
            ->get();
        
        if ($activePlayers->count() > 6) {
            echo "   - {$team->name}: {$activePlayers->count()} players, keeping top 6\n";
            
            // Keep top 6, remove team association from others
            $playersToRemove = $activePlayers->slice(6);
            foreach ($playersToRemove as $player) {
                $player->update(['team_id' => null]);
            }
        }
    }
    
    // 2. Fix invalid regions - map to closest valid region
    echo "\n2. Fixing invalid regions...\n";
    $regionMappings = [
        'MENA' => 'EU',      // Middle East to Europe
        'CIS' => 'EU',       // CIS to Europe
        'CN' => 'APAC',      // China to APAC
        'ASIA' => 'APAC',    // Asia to APAC
        'KR' => 'APAC',      // Korea to APAC
        'JP' => 'APAC',      // Japan to APAC
        'SEA' => 'APAC',     // Southeast Asia to APAC
        'SA' => 'NA'         // South America to NA
    ];
    
    foreach ($regionMappings as $oldRegion => $newRegion) {
        $updated = Team::where('region', $oldRegion)
            ->where('status', 'active')
            ->update(['region' => $newRegion]);
        
        if ($updated > 0) {
            echo "   - Updated $updated teams from $oldRegion to $newRegion\n";
        }
        
        // Also update players
        Player::where('region', $oldRegion)
            ->update(['region' => $newRegion]);
    }
    
    // 3. Fix players without country flags
    echo "\n3. Fixing missing country flags...\n";
    $playersWithoutFlags = Player::where('status', 'active')
        ->whereNull('country_flag')
        ->get();
    
    foreach ($playersWithoutFlags as $player) {
        if ($player->team) {
            // Use team's country flag
            $player->update(['country_flag' => $player->team->country_flag]);
        } else {
            // Default flag based on region
            $defaultFlags = [
                'NA' => '🇺🇸',
                'EU' => '🇪🇺',
                'APAC' => '🌏',
                'OCE' => '🇦🇺'
            ];
            $flag = $defaultFlags[$player->region] ?? '🌍';
            $player->update(['country_flag' => $flag]);
        }
    }
    echo "   - Updated {$playersWithoutFlags->count()} players with country flags\n";
    
    // 4. Delete teams without players
    echo "\n4. Removing empty teams...\n";
    $emptyTeams = Team::where('status', 'active')
        ->whereDoesntHave('players', function($query) {
            $query->where('status', 'active');
        })->get();
    
    foreach ($emptyTeams as $team) {
        echo "   - Deleting empty team: {$team->name}\n";
        $team->delete();
    }
    
    // 5. Remove players without teams from active status
    echo "\n5. Deactivating orphaned players...\n";
    $orphanedPlayers = Player::where('status', 'active')
        ->whereNull('team_id')
        ->update(['status' => 'inactive']);
    echo "   - Deactivated $orphanedPlayers orphaned players\n";
    
    DB::commit();
    echo "\n✅ All critical issues fixed!\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "\n❌ Error fixing issues: " . $e->getMessage() . "\n";
}