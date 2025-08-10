<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Checking user_warnings table...\n";

if (Schema::hasTable('user_warnings')) {
    echo "Table exists!\n";
    $columns = Schema::getColumnListing('user_warnings');
    echo "Columns: " . implode(', ', $columns) . "\n";
    
    if (in_array('expires_at', $columns)) {
        echo "expires_at column exists\n";
    } else {
        echo "expires_at column DOES NOT exist\n";
    }
} else {
    echo "Table user_warnings does not exist\n";
}