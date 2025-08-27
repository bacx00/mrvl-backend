<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Load Laravel app
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "🔐 Testing 2FA Implementation for MRVL Platform\n";
echo "==============================================\n\n";

// Test 1: Check if packages are installed
echo "1. Checking if 2FA packages are installed...\n";
$composerContent = file_get_contents('composer.json');
$composer = json_decode($composerContent, true);

$required2FAPackages = [
    'pragmarx/google2fa-laravel',
    'pragmarx/google2fa-qrcode'
];

foreach ($required2FAPackages as $package) {
    if (isset($composer['require'][$package])) {
        echo "   ✅ $package: " . $composer['require'][$package] . "\n";
    } else {
        echo "   ❌ $package: NOT FOUND\n";
    }
}

// Test 2: Check if migration was run
echo "\n2. Checking database migration...\n";
try {
    $pdo = new PDO(
        'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('DB_DATABASE') ?: 'mrvl'),
        getenv('DB_USERNAME') ?: 'root',
        getenv('DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check if 2FA columns exist
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $expected2FAColumns = [
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at'
    ];
    
    foreach ($expected2FAColumns as $column) {
        if (in_array($column, $columnNames)) {
            echo "   ✅ Column '$column' exists\n";
        } else {
            echo "   ❌ Column '$column' missing\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Test 3: Check if files were created
echo "\n3. Checking if required files exist...\n";
$requiredFiles = [
    'app/Services/TwoFactorService.php',
    'app/Http/Controllers/Api/TwoFactorController.php',
    'app/Http/Middleware/RequireTwoFactor.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file exists\n";
    } else {
        echo "   ❌ $file missing\n";
    }
}

// Test 4: Check if routes are registered
echo "\n4. Checking if 2FA routes are registered...\n";
$routesContent = file_get_contents('routes/api.php');

$expectedRoutes = [
    "Route::get('/status'",
    "Route::post('/setup'",
    "Route::post('/enable'",
    "Route::post('/verify'"
];

foreach ($expectedRoutes as $route) {
    if (strpos($routesContent, $route) !== false) {
        echo "   ✅ $route found in routes\n";
    } else {
        echo "   ❌ $route not found in routes\n";
    }
}

// Test 5: Check if middleware is registered
echo "\n5. Checking if middleware is registered...\n";
$kernelContent = file_get_contents('app/Http/Kernel.php');

if (strpos($kernelContent, "'require.2fa'") !== false) {
    echo "   ✅ 2FA middleware alias registered\n";
} else {
    echo "   ❌ 2FA middleware alias not found\n";
}

if (strpos($kernelContent, "RequireTwoFactor::class") !== false) {
    echo "   ✅ RequireTwoFactor middleware class referenced\n";
} else {
    echo "   ❌ RequireTwoFactor middleware class not found\n";
}

// Test 6: Check if admin routes have 2FA middleware
echo "\n6. Checking if admin routes are protected with 2FA...\n";
if (strpos($routesContent, "'require.2fa'") !== false) {
    echo "   ✅ 2FA middleware applied to routes\n";
} else {
    echo "   ❌ 2FA middleware not applied to admin routes\n";
}

// Test 7: Check User model updates
echo "\n7. Checking User model updates...\n";
$userModelContent = file_get_contents('app/Models/User.php');

$expectedMethods = [
    'hasTwoFactorEnabled',
    'isTwoFactorConfirmed',
    'generateRecoveryCodes',
    'useRecoveryCode',
    'mustUseTwoFactor'
];

foreach ($expectedMethods as $method) {
    if (strpos($userModelContent, "function $method") !== false) {
        echo "   ✅ Method '$method' found in User model\n";
    } else {
        echo "   ❌ Method '$method' missing in User model\n";
    }
}

echo "\n==============================================\n";
echo "🔐 2FA Implementation Test Complete!\n";
echo "\n📋 Next Steps:\n";
echo "1. Test the API endpoints with a REST client\n";
echo "2. Create an admin user and test the 2FA flow\n";
echo "3. Verify QR code generation works\n";
echo "4. Test recovery codes functionality\n";
echo "5. Test admin access restrictions\n";

echo "\n📚 Available API Endpoints:\n";
echo "- GET /api/auth/2fa/status - Check 2FA status\n";
echo "- POST /api/auth/2fa/setup - Setup 2FA (get QR code)\n";
echo "- POST /api/auth/2fa/enable - Enable 2FA with verification code\n";
echo "- POST /api/auth/2fa/verify - Verify 2FA code during login\n";
echo "- POST /api/auth/2fa/disable - Disable 2FA\n";
echo "- GET /api/auth/2fa/recovery-codes - Get recovery codes\n";
echo "- POST /api/auth/2fa/recovery-codes/regenerate - Regenerate recovery codes\n";