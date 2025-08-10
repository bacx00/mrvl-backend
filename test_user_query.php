<?php

// Test user query directly
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Enable query logging
DB::enableQueryLog();

try {
    echo "Testing User query...\n";
    
    // Test basic query
    echo "1. Basic user count: ";
    $count = User::count();
    echo $count . "\n";
    
    // Test with teamFlair relationship
    echo "2. Testing teamFlair relationship...\n";
    $users = User::with(['teamFlair' => function($q) {
        $q->select('id', 'name', 'logo', 'region', 'short_name');
    }])->limit(1)->get();
    
    echo "   Loaded " . count($users) . " users\n";
    
    if ($users->count() > 0) {
        $user = $users->first();
        echo "   First user: " . $user->name . "\n";
        echo "   Has teamFlair: " . ($user->teamFlair ? 'Yes' : 'No') . "\n";
    }
    
    // Test paginated query (as used in controller)
    echo "3. Testing paginated query...\n";
    $paginated = User::with(['teamFlair' => function($q) {
        $q->select('id', 'name', 'logo', 'region', 'short_name');
    }])->paginate(10);
    
    echo "   Total users: " . $paginated->total() . "\n";
    echo "   Per page: " . $paginated->perPage() . "\n";
    
    // Show queries
    echo "\n4. Executed queries:\n";
    $queries = DB::getQueryLog();
    foreach ($queries as $i => $query) {
        echo "   Query " . ($i + 1) . ": " . $query['query'] . "\n";
        if (!empty($query['bindings'])) {
            echo "   Bindings: " . json_encode($query['bindings']) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}