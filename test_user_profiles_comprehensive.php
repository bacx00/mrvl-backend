#!/usr/bin/env php
<?php

// Test user profile endpoints comprehensively

$baseUrl = 'http://localhost/api';
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5Y2UxOTM5NC04ZTFkLTRlOWUtOWJmNS1hMWU5ODI2Y2MwYzgiLCJqdGkiOiI3N2U0MmJkM2EyOTEyOGNhYWNhMjNmMzE5NDY0M2MyODViNjMzNzU1NzU1YTMxNzRmMzRhNTBkYzIxZGE0YzFkZmRhOGY2MTQzMWEzMjZjZCIsImlhdCI6MTczNjQ4Nzg3MS41MTIxMjUsIm5iZiI6MTczNjQ4Nzg3MS41MTIxMjgsImV4cCI6MTc2ODAyMzg3MS41MDQyNjUsInN1YiI6IjEiLCJzY29wZXMiOltdfQ.FmAkx37YGLdJvJhKH4lDcqeAsAAa3-eyfIENcgcn4rRayG9XxfOEzGDcCjQKNRXtN4DcMGvCcySzLT9VLrWAQxqYQpJrLx3Zqe5zbtd8XQb4W8DcbjeOo82NWWD7E6l3S0SRCiPy5Oo8Y1v0gHklGpFIczxHh_u7qB3xgmTLwPJ7Bq-m5lkJbh2sjCOPKW4bTy3TdTGK1KXzH5aASRFXYOKJPdHsJ16-qU2Z1Wlg58iXiKU1ov9Lmql_1Kp5PD7xQOQMdKqzCZ9eJH4Kp21jFG8t5hHfUk8_GBKBAz9LT6iqzBJXGqWnCsDx78h0I2G0rkkOJhQJx39jnRRvOz8pWS3vJcQFYezNX4H3s5eo8AhKN0VppzJnhRXEOcE-yqp_BJ9vQMZJnGH9L1fJUcaaxn8TU2J5d4HQAmNvJyIlU_bBm-HaUbOqXLaAKPP3iqP-XuxTFXpjqimuJxvbJLTiEA6Mx4QCvRu6K98XiQ_JNMRJPvWLaF1BPnzLHo0o9YFROcXo2-7-pXPzGhPvUIDHOkOMGOu4fXwxazOLLh2O0PrMgLY6TRMgJsxZ_rZO0PdbuQNvLLH7iCEL8FfUXyV4JJqRSz3xOZU5eOcNnTEKqvXLJzf7d2vV8HZYYUbuoO5CrsD7GRoT7cYGsj2xyY7SDV2NG6P5LXNJMBHyiwRBzXk'; // Admin token

function testEndpoint($name, $url, $token = null) {
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => $token ? "Authorization: Bearer $token\r\n" : "",
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    $statusLine = $http_response_header[0] ?? 'No response';
    preg_match('/HTTP\/\d\.\d (\d{3})/', $statusLine, $matches);
    $statusCode = $matches[1] ?? 0;
    
    $data = json_decode($response, true);
    
    echo "\n=== Testing: $name ===\n";
    echo "URL: $url\n";
    echo "Status: $statusCode\n";
    
    if ($statusCode == 200) {
        echo "✓ SUCCESS\n";
        
        // Check for hero images in profile data
        if (isset($data['data'])) {
            $profileData = $data['data'];
            
            // Check hero flair
            if (isset($profileData['hero_flair'])) {
                echo "Hero Flair: " . $profileData['hero_flair'] . "\n";
                
                // Check if hero image URL is properly set
                if (isset($profileData['avatar']) && strpos($profileData['avatar'], 'heroes/') !== false) {
                    echo "✓ Hero image as avatar: " . $profileData['avatar'] . "\n";
                } elseif (isset($profileData['hero_image'])) {
                    echo "✓ Hero image URL: " . $profileData['hero_image'] . "\n";
                }
            }
            
            // Check user stats
            if (isset($profileData['stats'])) {
                echo "\nUser Statistics:\n";
                $stats = $profileData['stats'];
                
                if (isset($stats['comments'])) {
                    echo "  Comments: News=" . $stats['comments']['news'] . 
                         ", Matches=" . $stats['comments']['matches'] . 
                         ", Total=" . $stats['comments']['total'] . "\n";
                }
                
                if (isset($stats['forum'])) {
                    echo "  Forum: Threads=" . $stats['forum']['threads'] . 
                         ", Posts=" . $stats['forum']['posts'] . 
                         ", Total=" . $stats['forum']['total'] . "\n";
                }
                
                if (isset($stats['votes'])) {
                    echo "  Votes: Given(↑" . $stats['votes']['upvotes_given'] . 
                         "/↓" . $stats['votes']['downvotes_given'] . 
                         "), Received(↑" . $stats['votes']['upvotes_received'] . 
                         "/↓" . $stats['votes']['downvotes_received'] . 
                         "), Rep=" . $stats['votes']['reputation_score'] . "\n";
                }
                
                if (isset($stats['activity'])) {
                    echo "  Activity: Last=" . ($stats['activity']['last_activity'] ?? 'Never') . 
                         ", Total Actions=" . $stats['activity']['total_actions'] . "\n";
                }
            }
            
            // Check recent activities
            if (isset($profileData['activities']) && is_array($profileData['activities'])) {
                echo "\nRecent Activities: " . count($profileData['activities']) . " items\n";
                foreach (array_slice($profileData['activities'], 0, 3) as $activity) {
                    echo "  - " . $activity['activity_type'] . " in " . $activity['context'] . 
                         " (" . $activity['time_ago'] . ")\n";
                }
            }
        }
    } else {
        echo "✗ FAILED\n";
        if (isset($data['message'])) {
            echo "Error: " . $data['message'] . "\n";
        }
    }
    
    return $statusCode == 200;
}

// Test different user profile endpoints
$userId = 1; // Admin user for testing

echo "====================================\n";
echo "    USER PROFILE ENDPOINT TESTS    \n";
echo "====================================\n";

$tests = [
    // Authenticated user's own profile
    ['Own Profile', "$baseUrl/user/profile", $token],
    ['Own Stats', "$baseUrl/user/stats", $token],
    ['Own Activities', "$baseUrl/user/activities", $token],
    
    // Public user profiles (viewing other users)
    ["Public User Details", "$baseUrl/users/$userId", null],
    ["Public User Stats", "$baseUrl/users/$userId/stats", null],
    ["Public User Activities", "$baseUrl/users/$userId/activities", null],
    
    // Profile updates (authenticated)
    ['Profile Settings', "$baseUrl/user/settings", $token],
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    if (testEndpoint($test[0], $test[1], $test[2])) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "\n====================================\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo "====================================\n";

// Test hero image URLs directly
echo "\n=== Checking Hero Image Accessibility ===\n";
$heroImages = [
    'Storm' => '/images/heroes/Storm.webp',
    'Groot' => '/images/heroes/Groot.webp',
    'Doctor_Strange' => '/images/heroes/Doctor_Strange.webp'
];

foreach ($heroImages as $hero => $path) {
    $fullPath = '/var/www/mrvl-backend/public' . $path;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        echo "✓ $hero: Found ($size bytes)\n";
    } else {
        echo "✗ $hero: Not found at $fullPath\n";
    }
}