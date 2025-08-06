<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SimplifiedTournamentScraper;

echo "Starting simplified Marvel Rivals tournament import...\n";

try {
    $importer = new SimplifiedTournamentScraper();
    $importer->importAllTournamentData();
    
    echo "\nImport completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}