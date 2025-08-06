<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SimpleLiquipediaScraper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Marvel Rivals Tournament Data Import\n";
echo "=====================================\n\n";

try {
    // Disable foreign key checks
    Schema::disableForeignKeyConstraints();
    
    // Clear existing data
    echo "Clearing existing tournament data...\n";
    
    // Clear in correct order to avoid foreign key issues
    DB::table('event_standings')->truncate();
    DB::table('match_maps')->truncate();
    DB::table('matches')->truncate();
    DB::table('event_teams')->truncate();
    DB::table('player_team_history')->truncate();
    DB::table('players')->truncate();
    DB::table('teams')->truncate();
    DB::table('events')->truncate();
    
    // Re-enable foreign key checks
    Schema::enableForeignKeyConstraints();
    
    echo "✓ Existing data cleared\n\n";
    
    // Import tournaments
    echo "Starting tournament import...\n";
    
    $scraper = new SimpleLiquipediaScraper();
    $results = $scraper->importAllTournaments();
    
    echo "\n✓ Import completed successfully!\n\n";
    
    // Display summary
    $totalTeams = 0;
    $totalPrize = 0;
    
    foreach ($results as $key => $result) {
        echo "Tournament: " . $result['event']->name . "\n";
        echo "  - Teams: " . $result['teams_imported'] . "\n";
        echo "  - Prize Pool: $" . number_format($result['total_prize_pool']) . "\n\n";
        
        $totalTeams += $result['teams_imported'];
        $totalPrize += $result['total_prize_pool'];
    }
    
    echo "\n=== IMPORT SUMMARY ===\n";
    echo "Total Events: " . \App\Models\Event::count() . "\n";
    echo "Total Teams: " . \App\Models\Team::count() . "\n";
    echo "Total Players: " . \App\Models\Player::count() . "\n";
    echo "Total Matches: " . \App\Models\GameMatch::count() . "\n";
    echo "Total Prize Pool: $" . number_format($totalPrize) . "\n";
    echo "Teams with Social Media: " . \App\Models\Team::whereNotNull('social_media')->count() . "\n";
    echo "Average Team ELO: " . round(\App\Models\Team::avg('rating')) . "\n";
    
    echo "\n=== REGIONAL DISTRIBUTION ===\n";
    $regionalData = \App\Models\Team::selectRaw('region, COUNT(*) as count')
        ->groupBy('region')
        ->get();
    
    foreach ($regionalData as $region) {
        echo $region->region . ": " . $region->count . " teams\n";
    }
    
    echo "\n=== TOP TEAMS BY ELO ===\n";
    $topTeams = \App\Models\Team::orderBy('rating', 'desc')->take(10)->get();
    
    foreach ($topTeams as $index => $team) {
        echo ($index + 1) . ". " . $team->name . " - ELO: " . $team->rating . " (" . $team->region . ")\n";
    }
    
    echo "\n✓ All tournament data has been imported successfully!\n";
    echo "You can now access the data through the API endpoints.\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error during import: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}