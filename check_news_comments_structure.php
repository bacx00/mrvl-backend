<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Checking news_comments table structure:\n";
echo "========================================\n";

if (!Schema::hasTable('news_comments')) {
    echo "news_comments table does not exist!\n";
    exit;
}

// Get all columns
$columns = DB::select("SHOW COLUMNS FROM news_comments");

foreach ($columns as $column) {
    echo "Column: {$column->Field} | Type: {$column->Type} | Null: {$column->Null} | Key: {$column->Key} | Default: {$column->Default}\n";
}

echo "\nChecking specific columns:\n";
$columnsToCheck = ['status', 'is_edited', 'edited_at', 'upvotes', 'downvotes', 'score'];
foreach ($columnsToCheck as $columnName) {
    $exists = Schema::hasColumn('news_comments', $columnName);
    echo "Column '$columnName': " . ($exists ? "EXISTS" : "MISSING") . "\n";
}