<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CompleteLiquipediaScraper;

try {
    echo "Starting comprehensive tournament import...\n";
    echo "=====================================\n\n";
    
    $scraper = new CompleteLiquipediaScraper();
    
    // Scrape all tournaments
    $results = $scraper->scrapeAllTournaments();
    
    echo "\n\nImport Summary:\n";
    echo "================\n";
    
    $totalTeams = 0;
    $totalPlayers = 0;
    
    foreach ($results as $tournament => $data) {
        if (isset($data['teams_count'])) {
            $totalTeams += $data['teams_count'];
            $playerCount = 0;
            foreach ($data['teams'] as $team) {
                $playerCount += count($team['players']);
            }
            $totalPlayers += $playerCount;
            
            echo sprintf("%-20s: %d teams, %d players\n", 
                str_replace('_', ' ', strtoupper($tournament)), 
                $data['teams_count'], 
                $playerCount
            );
        }
    }
    
    echo "\nTotal: $totalTeams teams, $totalPlayers players imported\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}