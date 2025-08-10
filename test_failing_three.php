<?php

// Test the 3 failing endpoints to get detailed error messages
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

echo "Testing 3 failing endpoints...\n\n";

$endpoints = [
    'News Moderation' => '/api/admin/news-moderation',
    'Forum Dashboard' => '/api/api/admin/forums-moderation/dashboard',
    'Forum Statistics' => '/api/api/admin/forums-moderation/statistics',
];

foreach ($endpoints as $name => $endpoint) {
    echo "==========================================\n";
    echo "Testing: $name\n";
    echo "Endpoint: $endpoint\n";
    echo "------------------------------------------\n";
    
    try {
        $request = Illuminate\Http\Request::create($endpoint, 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $request->headers->set('Accept', 'application/json');
        
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $content = $response->getContent();
        
        echo "Status: $status\n";
        
        if ($status >= 500) {
            $data = json_decode($content, true);
            if (isset($data['message'])) {
                echo "Error Message: " . $data['message'] . "\n";
            }
            if (isset($data['error'])) {
                echo "Error Details: " . $data['error'] . "\n";
            }
            
            // If no JSON error, show raw
            if (!$data) {
                echo "Raw error: " . substr($content, 0, 500) . "\n";
            }
        } else {
            echo "✓ Success!\n";
            $data = json_decode($content, true);
            if ($data) {
                if (isset($data['data'])) {
                    if (is_array($data['data'])) {
                        echo "  Data count: " . count($data['data']) . "\n";
                    } else {
                        echo "  Data type: " . gettype($data['data']) . "\n";
                    }
                }
            }
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace:\n" . substr($e->getTraceAsString(), 0, 500) . "\n";
    }
    
    echo "\n";
}

$kernel->terminate($request, $response);