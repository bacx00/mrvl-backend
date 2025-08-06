<?php
/**
 * Comprehensive Authentication Fix Script
 * This script addresses all the authentication issues in the MRVL platform
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

echo "=== MRVL Authentication Fix Script ===" . PHP_EOL;

try {
    // 1. Check and fix Passport installation
    echo "1. Checking Passport configuration..." . PHP_EOL;
    
    if (!DB::table('oauth_clients')->count()) {
        echo "   Installing Passport keys..." . PHP_EOL;
        Artisan::call('passport:install', ['--force' => true]);
        echo "   Passport installed successfully." . PHP_EOL;
    } else {
        echo "   Passport already configured." . PHP_EOL;
    }
    
    // 2. Create/Update admin user with proper password
    echo "2. Creating admin user..." . PHP_EOL;
    
    $adminUser = App\Models\User::updateOrCreate(
        ['email' => 'admin@mrvl.net'],
        [
            'name' => 'Admin User',
            'password' => Hash::make('admin123'), // Simple password for testing
            'status' => 'active',
            'email_verified_at' => now(),
            'role' => 'admin' // Direct role assignment if using basic role system
        ]
    );
    
    // Verify password hash works
    if (Hash::check('admin123', $adminUser->password)) {
        echo "   Admin user created successfully with working password." . PHP_EOL;
    } else {
        echo "   WARNING: Password hash verification failed!" . PHP_EOL;
    }
    
    // 3. Assign admin role using Spatie if available
    try {
        if (method_exists($adminUser, 'assignRole')) {
            $adminUser->assignRole('admin');
            echo "   Admin role assigned via Spatie." . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "   Role assignment via Spatie failed: " . $e->getMessage() . PHP_EOL;
    }
    
    // 4. Create test user for API testing
    echo "3. Creating test user..." . PHP_EOL;
    
    $testUser = App\Models\User::updateOrCreate(
        ['email' => 'test@mrvl.net'],
        [
            'name' => 'Test User',
            'password' => Hash::make('test123'),
            'status' => 'active',
            'email_verified_at' => now(),
            'role' => 'user'
        ]
    );
    
    try {
        if (method_exists($testUser, 'assignRole')) {
            $testUser->assignRole('user');
        }
    } catch (Exception $e) {
        // Ignore role assignment errors for test user
    }
    
    echo "   Test user created successfully." . PHP_EOL;
    
    // 5. Test token creation
    echo "4. Testing token creation..." . PHP_EOL;
    
    try {
        $token = $adminUser->createToken('test-token');
        if ($token) {
            echo "   Token creation successful." . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "   Token creation failed: " . $e->getMessage() . PHP_EOL;
    }
    
    // 6. Check database tables exist
    echo "5. Checking required tables..." . PHP_EOL;
    
    $requiredTables = [
        'users',
        'oauth_clients',
        'oauth_access_tokens',
        'oauth_refresh_tokens',
        'oauth_personal_access_clients'
    ];
    
    foreach ($requiredTables as $table) {
        if (Schema::hasTable($table)) {
            echo "   ✓ Table '{$table}' exists." . PHP_EOL;
        } else {
            echo "   ✗ Table '{$table}' missing!" . PHP_EOL;
        }
    }
    
    // 7. Test API endpoint directly
    echo "6. Testing login endpoint..." . PHP_EOL;
    
    // Simulate login request
    $request = new Illuminate\Http\Request();
    $request->merge([
        'email' => 'admin@mrvl.net',
        'password' => 'admin123'
    ]);
    
    $authController = new App\Http\Controllers\AuthController();
    
    try {
        $response = $authController->login($request);
        $responseData = $response->getData(true);
        
        if ($responseData['success'] ?? false) {
            echo "   ✓ Login endpoint working correctly." . PHP_EOL;
            echo "   Token: " . substr($responseData['token'] ?? 'N/A', 0, 50) . "..." . PHP_EOL;
        } else {
            echo "   ✗ Login endpoint failed: " . ($responseData['message'] ?? 'Unknown error') . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "   ✗ Login endpoint error: " . $e->getMessage() . PHP_EOL;
    }
    
    // 8. Output summary
    echo PHP_EOL . "=== SUMMARY ===" . PHP_EOL;
    echo "Admin Email: admin@mrvl.net" . PHP_EOL;
    echo "Admin Password: admin123" . PHP_EOL;
    echo "Test Email: test@mrvl.net" . PHP_EOL;
    echo "Test Password: test123" . PHP_EOL;
    echo PHP_EOL . "API Base URL: http://localhost:8000/api" . PHP_EOL;
    echo "Login Endpoint: POST /auth/login" . PHP_EOL;
    echo PHP_EOL . "Authentication should now be working!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}