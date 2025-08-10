<?php

// Debug user profile error
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Get auth token
$loginResponse = $kernel->handle(
    $request = Illuminate\Http\Request::create(
        '/api/auth/login',
        'POST',
        ['email' => 'jhonny@ar-mediia.com', 'password' => 'password123']
    )
);
$loginData = json_decode($loginResponse->getContent(), true);
$token = $loginData['token'] ?? null;

if (!$token) {
    echo "Failed to get auth token\n";
    exit(1);
}

echo "Testing /api/user/profile endpoint...\n\n";

$request = Illuminate\Http\Request::create('/api/user/profile', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

try {
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $content = $response->getContent();
    
    echo "Status: $status\n";
    
    if ($status >= 500) {
        $data = json_decode($content, true);
        if ($data) {
            echo "Error Message: " . ($data['message'] ?? 'Unknown') . "\n";
            if (isset($data['error'])) {
                echo "Error Details: " . $data['error'] . "\n";
            }
            if (isset($data['trace'])) {
                echo "Stack trace (first 5 lines):\n";
                $lines = array_slice(explode("\n", $data['trace']), 0, 5);
                foreach ($lines as $line) {
                    echo "  " . $line . "\n";
                }
            }
        } else {
            // Try to extract error from HTML
            if (preg_match('/<title>(.*?)<\/title>/', $content, $matches)) {
                echo "Page title: " . $matches[1] . "\n";
            }
            if (preg_match('/<!-- (.*?) -->/', $content, $matches)) {
                echo "Exception: " . $matches[1] . "\n";
            }
        }
    } else {
        echo "Success!\n";
        $data = json_decode($content, true);
        if ($data && isset($data['data'])) {
            echo "User: " . ($data['data']['name'] ?? 'Unknown') . "\n";
            echo "Avatar: " . ($data['data']['avatar'] ?? 'None') . "\n";
            echo "Hero Flair: " . ($data['data']['hero_flair'] ?? 'None') . "\n";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 10)) . "\n";
}

// Now test directly with the controller
echo "\n----------------------------------------\n";
echo "Testing controller directly...\n";

try {
    $controller = new \App\Http\Controllers\UserProfileController();
    
    // Set up auth manually
    $user = \App\Models\User::find(1);
    auth()->login($user);
    
    // Call the method directly
    $response = $controller->show();
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($data['success'] ?? false) {
        echo "Direct call successful!\n";
        echo "User: " . ($data['data']['name'] ?? 'Unknown') . "\n";
    } else {
        echo "Direct call failed: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "Direct call exception: " . $e->getMessage() . "\n";
    echo "At: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$kernel->terminate($request, $response);