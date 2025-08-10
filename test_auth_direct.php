<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;

try {
    echo "🔐 Testing MRVL Authentication System Direct\n";
    echo "=" . str_repeat("=", 50) . "\n\n";
    
    // Test 1: Check if users exist
    echo "1. Checking test users...\n";
    $admin = User::where('email', 'admin@mrvl.gg')->first();
    $moderator = User::where('email', 'moderator@mrvl.gg')->first(); 
    $user = User::where('email', 'user@mrvl.gg')->first();
    
    if ($admin) {
        echo "✅ Admin user exists: {$admin->name} ({$admin->email}) - Role: {$admin->role}\n";
    } else {
        echo "❌ Admin user not found\n";
    }
    
    if ($moderator) {
        echo "✅ Moderator user exists: {$moderator->name} ({$moderator->email}) - Role: {$moderator->role}\n";
    } else {
        echo "❌ Moderator user not found\n";
    }
    
    if ($user) {
        echo "✅ Regular user exists: {$user->name} ({$user->email}) - Role: {$user->role}\n";
    } else {
        echo "❌ Regular user not found\n";
    }
    
    echo "\n";
    
    // Test 2: Test password verification
    echo "2. Testing password verification...\n";
    if ($admin) {
        $passwordCheck = Hash::check('Admin123!@#', $admin->password);
        echo "Admin password check: " . ($passwordCheck ? "✅ PASS" : "❌ FAIL") . "\n";
    }
    
    echo "\n";
    
    // Test 3: Test direct authentication controller
    echo "3. Testing AuthController directly...\n";
    
    $controller = new AuthController();
    
    // Create a mock request
    $request = Request::create('/api/auth/login', 'POST', [
        'email' => 'admin@mrvl.gg',
        'password' => 'Admin123!@#'
    ]);
    
    try {
        $response = $controller->login($request);
        $data = json_decode($response->getContent(), true);
        
        echo "Login response status: " . $response->getStatusCode() . "\n";
        echo "Login response success: " . ($data['success'] ? "✅ YES" : "❌ NO") . "\n";
        
        if ($data['success']) {
            echo "✅ Token received: " . substr($data['token'], 0, 20) . "...\n";
            echo "✅ User role: " . $data['user']['role'] . "\n";
        } else {
            echo "❌ Login failed: " . ($data['message'] ?? 'Unknown error') . "\n";
            if (isset($data['error'])) {
                echo "❌ Error details: " . $data['error'] . "\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Controller test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Test 4: Test role checking
    echo "4. Testing role checking functions...\n";
    if ($admin) {
        echo "Admin hasRole('admin'): " . ($admin->hasRole('admin') ? "✅ YES" : "❌ NO") . "\n";
        echo "Admin isAdmin(): " . ($admin->isAdmin() ? "✅ YES" : "❌ NO") . "\n";
        echo "Admin hasAnyRole(['admin']): " . ($admin->hasAnyRole(['admin']) ? "✅ YES" : "❌ NO") . "\n";
    }
    
    echo "\n";
    
    // Test 5: Test API endpoint protection
    echo "5. Testing API protection...\n";
    
    // Test unauthenticated access to protected endpoint
    echo "Testing unauthenticated access to /admin/users...\n";
    $request = Request::create('/api/admin/users', 'GET');
    
    try {
        // This should fail with 401
        $response = \Route::dispatch($request);
        echo "Response status: " . $response->getStatusCode() . "\n";
        
        if ($response->getStatusCode() === 401) {
            echo "✅ Endpoint properly protected (401 Unauthorized)\n";
        } else {
            echo "❌ Security issue: Endpoint returned " . $response->getStatusCode() . " instead of 401\n";
        }
    } catch (Exception $e) {
        echo "Request handling error: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🏁 Direct authentication test complete\n";
    
} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}