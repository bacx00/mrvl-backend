<?php

// Test failing endpoints directly with PHP
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test each failing endpoint
$endpoints = [
    '/api/admin/events',
    '/api/admin/users',
    '/api/api/admin/events',
    '/api/api/admin/users',
    '/api/api/admin/forums-moderation/dashboard',
    '/api/api/admin/forums-moderation/statistics',
];

// Get token
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

echo "Testing failing endpoints with token...\n\n";

foreach ($endpoints as $endpoint) {
    echo "Testing: $endpoint\n";
    
    try {
        $request = Illuminate\Http\Request::create($endpoint, 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $request->headers->set('Accept', 'application/json');
        
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $content = $response->getContent();
        
        echo "Status: $status\n";
        
        if ($status >= 500) {
            // Check for specific errors
            $data = json_decode($content, true);
            if (isset($data['message'])) {
                echo "Error: " . $data['message'] . "\n";
            }
            if (isset($data['error'])) {
                echo "Details: " . $data['error'] . "\n";
            }
            
            // Try to get the actual exception
            if ($status == 500 && !$data) {
                echo "Raw error (first 500 chars): " . substr($content, 0, 500) . "\n";
            }
        } else {
            echo "Success!\n";
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "---\n";
}

$kernel->terminate($request, $response);