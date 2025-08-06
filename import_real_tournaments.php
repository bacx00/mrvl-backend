<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\RealLiquipediaScraper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Marvel Rivals REAL Tournament Data Import\n";
echo "==========================================\n\n";
echo "Using actual tournament data from 2025 competitions\n\n";

try {
    Schema::disableForeignKeyConstraints();
    
    echo "Clearing existing tournament data...\n";
    
    DB::table('event_standings')->truncate();
    DB::table('match_maps')->truncate();
    DB::table('matches')->truncate();
    DB::table('event_teams')->truncate();
    DB::table('player_team_history')->truncate();
    DB::table('players')->truncate();
    DB::table('teams')->truncate();
    DB::table('events')->truncate();
    
    Schema::enableForeignKeyConstraints();
    
    echo "✓ Existing data cleared\n\n";
    
    echo "Starting REAL tournament import...\n";
    echo "Data sourced from official tournament results\n\n";
    
    $scraper = new RealLiquipediaScraper();
    $results = $scraper->importAllTournaments();
    
    echo "\n✓ Import completed successfully!\n\n";
    
    $totalTeams = 0;
    $totalPrize = 0;
    
    foreach ($results as $key => $result) {
        echo "Tournament: " . $result['event']->name . "\n";
        echo "  - Teams: " . $result['teams_imported'] . "\n";
        echo "  - Prize Pool: $" . number_format($result['total_prize_pool']) . "\n";
        echo "  - Date: " . $result['event']->start_date . " to " . $result['event']->end_date . "\n\n";
        
        $totalTeams += $result['teams_imported'];
        $totalPrize += $result['total_prize_pool'];
    }
    
    echo "\n=== REAL DATA IMPORT SUMMARY ===\n";
    echo "Total Events: " . \App\Models\Event::count() . "\n";
    echo "Total Teams: " . \App\Models\Team::count() . "\n";
    echo "Total Prize Pool: $" . number_format($totalPrize) . "\n";
    echo "Teams with Social Media: " . \App\Models\Team::whereNotNull('social_media')->count() . "\n";
    echo "Average Team ELO: " . round(\App\Models\Team::avg('rating')) . "\n";
    
    echo "\n=== TOURNAMENT WINNERS ===\n";
    $winners = \App\Models\EventStanding::where('position', 1)
        ->with(['team', 'event'])
        ->get();
    
    foreach ($winners as $winner) {
        echo $winner->event->name . ": " . $winner->team->name . " (Prize: $" . number_format($winner->prize_won) . ")\n";
    }
    
    echo "\n=== TOP TEAMS BY EARNINGS ===\n";
    $topEarners = \App\Models\Team::orderBy('earnings', 'desc')->take(10)->get();
    
    foreach ($topEarners as $index => $team) {
        echo ($index + 1) . ". " . $team->name . " - $" . number_format($team->earnings) . " (" . $team->region . ")\n";
    }
    
    echo "\n✓ All REAL tournament data has been imported successfully!\n";
    echo "This data reflects actual tournament results from 2025.\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error during import: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}