<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$migrationName = '2025_08_06_231633_create_news_video_embeds_table';

// Check if migration is already marked as complete
$exists = DB::table('migrations')->where('migration', $migrationName)->exists();

if (!$exists) {
    // Get the latest batch number
    $batch = DB::table('migrations')->max('batch') + 1;
    
    // Insert the migration as completed
    DB::table('migrations')->insert([
        'migration' => $migrationName,
        'batch' => $batch
    ]);
    
    echo "Migration $migrationName marked as completed in batch $batch\n";
} else {
    echo "Migration $migrationName already marked as completed\n";
}