<?php
/**
 * Debug admin endpoints by calling them directly
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminTeamController;
use App\Http\Controllers\PlayerController;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🔍 Debug Admin Endpoints\n";
echo "========================\n\n";

// Test AdminTeamController
echo "1. Testing AdminTeamController...\n";
try {
    $controller = new AdminTeamController();
    $request = new Request();
    
    echo "   ✅ AdminTeamController instantiated successfully\n";
    
    // Test if the index method exists
    if (method_exists($controller, 'index')) {
        echo "   ✅ index() method exists\n";
    } else {
        echo "   ❌ index() method missing\n";
    }
    
    // Test if the store method exists
    if (method_exists($controller, 'store')) {
        echo "   ✅ store() method exists\n";
    } else {
        echo "   ❌ store() method missing\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test PlayerController
echo "2. Testing PlayerController...\n";
try {
    $controller = new PlayerController();
    $request = new Request();
    
    echo "   ✅ PlayerController instantiated successfully\n";
    
    // Test if the getAllPlayers method exists
    if (method_exists($controller, 'getAllPlayers')) {
        echo "   ✅ getAllPlayers() method exists\n";
    } else {
        echo "   ❌ getAllPlayers() method missing\n";
    }
    
    // Test if the store method exists
    if (method_exists($controller, 'store')) {
        echo "   ✅ store() method exists\n";
    } else {
        echo "   ❌ store() method missing\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test database connection
echo "3. Testing database connection...\n";
try {
    $connection = DB::connection();
    $connection->getPdo();
    echo "   ✅ Database connection successful\n";
} catch (Exception $e) {
    echo "   ❌ Database connection error: " . $e->getMessage() . "\n";
}

echo "\n";

// Check for missing dependencies
echo "4. Checking dependencies...\n";

$requiredClasses = [
    'App\Models\Team',
    'App\Models\Player', 
    'App\Helpers\ImageHelper',
    'App\Services\OptimizedAdminQueryService'
];

foreach ($requiredClasses as $class) {
    if (class_exists($class)) {
        echo "   ✅ $class exists\n";
    } else {
        echo "   ❌ $class missing\n";
    }
}

echo "\nDebug complete!\n";