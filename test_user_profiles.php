<?php

// Comprehensive User Profile Testing
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
$userId = $loginData['user']['id'] ?? 1;

if (!$token) {
    echo "Failed to get auth token\n";
    exit(1);
}

echo "========================================\n";
echo "USER PROFILE COMPREHENSIVE TEST\n";
echo "========================================\n\n";

// Test endpoints
$tests = [
    [
        'name' => 'Get Current User Profile',
        'endpoint' => '/api/user/profile',
        'method' => 'GET'
    ],
    [
        'name' => 'Get User by ID',
        'endpoint' => '/api/users/' . $userId,
        'method' => 'GET'
    ],
    [
        'name' => 'Get User Stats',
        'endpoint' => '/api/users/' . $userId . '/stats',
        'method' => 'GET'
    ],
    [
        'name' => 'Get User Activities',
        'endpoint' => '/api/users/' . $userId . '/activities',
        'method' => 'GET'
    ],
    [
        'name' => 'Get User Achievements',
        'endpoint' => '/api/users/' . $userId . '/achievements',
        'method' => 'GET'
    ],
    [
        'name' => 'Get User Forum Stats',
        'endpoint' => '/api/users/' . $userId . '/forum-stats',
        'method' => 'GET'
    ],
    [
        'name' => 'Get User Match History',
        'endpoint' => '/api/users/' . $userId . '/matches',
        'method' => 'GET'
    ],
    [
        'name' => 'Test Hero Avatar Setting',
        'endpoint' => '/api/user/profile',
        'method' => 'PUT',
        'data' => [
            'hero_flair' => 'Spider-Man',
            'show_hero_flair' => true,
            'use_hero_as_avatar' => true
        ]
    ]
];

foreach ($tests as $test) {
    echo "----------------------------------------\n";
    echo "Test: " . $test['name'] . "\n";
    echo "Endpoint: " . $test['method'] . " " . $test['endpoint'] . "\n";
    echo "----------------------------------------\n";
    
    try {
        $request = Illuminate\Http\Request::create(
            $test['endpoint'], 
            $test['method'],
            $test['data'] ?? []
        );
        $request->headers->set('Authorization', 'Bearer ' . $token);
        $request->headers->set('Accept', 'application/json');
        
        if ($test['method'] === 'PUT' || $test['method'] === 'POST') {
            $request->headers->set('Content-Type', 'application/json');
        }
        
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $content = $response->getContent();
        
        echo "Status: $status\n";
        
        if ($status == 200 || $status == 201) {
            $data = json_decode($content, true);
            
            if (isset($data['data']) || isset($data['user'])) {
                $userData = $data['data'] ?? $data['user'] ?? $data;
                
                // Check key profile fields
                echo "✓ Success\n";
                
                // Display key user info
                if (isset($userData['name'])) {
                    echo "  Name: " . $userData['name'] . "\n";
                }
                
                // Check hero/avatar display
                if (isset($userData['hero_flair'])) {
                    echo "  Hero Flair: " . $userData['hero_flair'] . "\n";
                    echo "  Show Hero Flair: " . ($userData['show_hero_flair'] ? 'Yes' : 'No') . "\n";
                }
                
                if (isset($userData['avatar'])) {
                    echo "  Avatar URL: " . $userData['avatar'] . "\n";
                    
                    // Check if it's a hero image
                    if (strpos($userData['avatar'], 'heroes') !== false) {
                        echo "  → Using HERO image as avatar\n";
                    } elseif (strpos($userData['avatar'], 'placeholder') !== false) {
                        echo "  → Using placeholder image\n";
                    } else {
                        echo "  → Using custom avatar\n";
                    }
                }
                
                if (isset($userData['use_hero_as_avatar'])) {
                    echo "  Use Hero as Avatar: " . ($userData['use_hero_as_avatar'] ? 'Yes' : 'No') . "\n";
                }
                
                // Display stats if present
                if (isset($userData['stats'])) {
                    echo "  Stats:\n";
                    foreach ($userData['stats'] as $key => $value) {
                        if (is_array($value)) {
                            echo "    $key: " . json_encode($value) . "\n";
                        } else {
                            echo "    $key: $value\n";
                        }
                    }
                }
                
                // Display activities count
                if (isset($userData['activities']) && is_array($userData['activities'])) {
                    echo "  Activities Count: " . count($userData['activities']) . "\n";
                }
                
                // Display achievements
                if (isset($userData['achievements'])) {
                    if (is_array($userData['achievements'])) {
                        echo "  Achievements Count: " . count($userData['achievements']) . "\n";
                    } else {
                        echo "  Achievements: " . json_encode($userData['achievements']) . "\n";
                    }
                }
                
                // Forum engagement stats
                if (isset($userData['forum_engagement_stats'])) {
                    echo "  Forum Stats:\n";
                    foreach ($userData['forum_engagement_stats'] as $key => $value) {
                        echo "    $key: $value\n";
                    }
                }
                
                // Team flair
                if (isset($userData['team_flair'])) {
                    echo "  Team Flair: " . json_encode($userData['team_flair']) . "\n";
                }
                
            } else {
                echo "  Response: " . substr($content, 0, 200) . "\n";
            }
        } else {
            echo "✗ Failed\n";
            $error = json_decode($content, true);
            if ($error) {
                echo "  Error: " . ($error['message'] ?? 'Unknown error') . "\n";
                if (isset($error['error'])) {
                    echo "  Details: " . $error['error'] . "\n";
                }
            } else {
                echo "  Raw: " . substr($content, 0, 200) . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "\n";
}

// Test hero image URLs directly
echo "========================================\n";
echo "HERO IMAGE URL TESTS\n";
echo "========================================\n\n";

$heroes = ['Spider-Man', 'Iron Man', 'Thor', 'Captain America', 'Hulk', 'Black Widow'];

foreach ($heroes as $hero) {
    $heroSlug = str_replace([' ', '&'], ['-', 'and'], strtolower($hero));
    $imageUrl = "/images/heroes/{$heroSlug}-headbig.webp";
    $fullUrl = "https://staging.mrvl.net" . $imageUrl;
    
    echo "Hero: $hero\n";
    echo "  Image Path: $imageUrl\n";
    echo "  Full URL: $fullUrl\n";
    
    // Check if file exists locally
    $localPath = "/var/www/mrvl-backend/public" . $imageUrl;
    if (file_exists($localPath)) {
        echo "  ✓ File exists locally\n";
        echo "  File size: " . filesize($localPath) . " bytes\n";
    } else {
        echo "  ✗ File NOT found at: $localPath\n";
        
        // Check alternative locations
        $altPath1 = "/var/www/mrvl-frontend/frontend/public" . $imageUrl;
        $altPath2 = "/var/www/mrvl-backend/storage/app/public" . $imageUrl;
        
        if (file_exists($altPath1)) {
            echo "  → Found in frontend: $altPath1\n";
        } elseif (file_exists($altPath2)) {
            echo "  → Found in storage: $altPath2\n";
        }
    }
    echo "\n";
}

$kernel->terminate($request, $response);