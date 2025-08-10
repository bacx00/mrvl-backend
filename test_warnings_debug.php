<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Testing whereHas warnings query...\n";

// Enable query logging
DB::enableQueryLog();

try {
    echo "1. Check if expires_at exists: ";
    $hasExpires = Schema::hasColumn('user_warnings', 'expires_at');
    echo $hasExpires ? "YES\n" : "NO\n";
    
    echo "2. Testing whereHas query with column check...\n";
    
    $query = User::query();
    
    $query->whereHas('warnings', function($q) {
        echo "   Inside whereHas callback\n";
        echo "   Checking column in callback: ";
        $hasCol = Schema::hasColumn('user_warnings', 'expires_at');
        echo $hasCol ? "YES\n" : "NO\n";
        
        if ($hasCol) {
            echo "   Adding expires_at condition\n";
            $q->where('expires_at', '>', now())->orWhereNull('expires_at');
        } else {
            echo "   Skipping expires_at condition\n";
        }
    });
    
    echo "3. Executing query...\n";
    $users = $query->get();
    echo "   Found " . count($users) . " users\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Show queries
echo "\n4. Executed queries:\n";
$queries = DB::getQueryLog();
foreach ($queries as $i => $query) {
    echo "   Query " . ($i + 1) . ": " . substr($query['query'], 0, 200) . "\n";
}