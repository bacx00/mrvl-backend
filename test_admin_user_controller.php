<?php

// Test AdminUsersController directly
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

echo "Testing AdminUsersController index method...\n\n";

// Test basic request
$request = Illuminate\Http\Request::create('/api/admin/users', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

try {
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $content = $response->getContent();
    
    echo "Status: $status\n";
    
    if ($status == 500) {
        // Parse error
        $data = json_decode($content, true);
        
        if ($data) {
            echo "Error message: " . ($data['message'] ?? 'Unknown') . "\n";
            echo "Error details: " . ($data['error'] ?? 'None') . "\n";
        } else {
            // Check if it's an HTML error page
            if (strpos($content, '<!DOCTYPE') !== false) {
                echo "Received HTML error page\n";
                
                // Extract error message from HTML
                if (preg_match('/<title>(.*?)<\/title>/', $content, $matches)) {
                    echo "Page title: " . $matches[1] . "\n";
                }
                
                // Look for exception message
                if (preg_match('/<!-- (.*?) -->/', $content, $matches)) {
                    echo "Exception: " . $matches[1] . "\n";
                }
            } else {
                echo "Raw response: " . substr($content, 0, 500) . "\n";
            }
        }
    } else {
        echo "Success!\n";
        $data = json_decode($content, true);
        if ($data && isset($data['data'])) {
            echo "Users returned: " . count($data['data']) . "\n";
        }
    }
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$kernel->terminate($request, $response);