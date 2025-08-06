<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = ['tournament_participants', 'player_statistics', 'team_rankings'];

echo "Checking for missing tables:\n";
foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    echo "$table: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}

// Check events table structure
echo "\nChecking events table structure:\n";
if (Schema::hasTable('events')) {
    $columns = Schema::getColumnListing('events');
    echo "Events table columns: " . implode(', ', $columns) . "\n";
    
    if (!in_array('slug', $columns)) {
        echo "MISSING: slug column in events table\n";
    }
} else {
    echo "Events table does not exist\n";
}

// Check matches table structure
echo "\nChecking matches table structure:\n";
if (Schema::hasTable('matches')) {
    $columns = Schema::getColumnListing('matches');
    echo "Matches table columns: " . implode(', ', $columns) . "\n";
    
    if (!in_array('scheduled_at', $columns)) {
        echo "MISSING: scheduled_at column in matches table\n";
    }
} else {
    echo "Matches table does not exist\n";
}