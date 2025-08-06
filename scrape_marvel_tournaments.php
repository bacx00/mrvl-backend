<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\MarvelRivalsTournamentScraper;

echo "Starting Marvel Rivals tournament scraping...\n";

try {
    $scraper = new MarvelRivalsTournamentScraper();
    $scraper->scrapeAllTournaments();
    
    echo "\nScraping completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}