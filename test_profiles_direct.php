#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Test user profile endpoints directly
echo "====================================\n";
echo "  DIRECT USER PROFILE TESTS\n";
echo "====================================\n\n";

// Get UserProfileController
$controller = new \App\Http\Controllers\UserProfileController();
$userId = 1;

// Test 1: Get user with details
echo "=== Test 1: Get User With Details (User ID: $userId) ===\n";
try {
    $request = \Illuminate\Http\Request::create("/api/users/$userId", 'GET');
    $response = $controller->getUserWithDetails($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ SUCCESS\n";
        $profile = $data['data'];
        
        echo "\nUser Profile:\n";
        echo "  ID: " . $profile['id'] . "\n";
        echo "  Name: " . $profile['name'] . "\n";
        echo "  Avatar: " . ($profile['avatar'] ?? 'None') . "\n";
        echo "  Hero Flair: " . ($profile['hero_flair'] ?? 'None') . "\n";
        echo "  Team Flair: " . ($profile['team_flair']['name'] ?? 'None') . "\n";
        
        // Check if hero image is being used
        if (isset($profile['avatar']) && strpos($profile['avatar'], 'heroes/') !== false) {
            echo "  ✓ Using hero image as avatar\n";
        }
    } else {
        echo "✗ FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Get user stats
echo "\n=== Test 2: Get User Stats (User ID: $userId) ===\n";
try {
    $response = $controller->getUserStatsPublic($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ SUCCESS\n";
        $stats = $data['data'];
        
        echo "\nUser Statistics:\n";
        if (isset($stats['comments'])) {
            echo "  Comments:\n";
            echo "    News: " . $stats['comments']['news'] . "\n";
            echo "    Matches: " . $stats['comments']['matches'] . "\n";
            echo "    Total: " . $stats['comments']['total'] . "\n";
        }
        
        if (isset($stats['forum'])) {
            echo "  Forum:\n";
            echo "    Threads: " . $stats['forum']['threads'] . "\n";
            echo "    Posts: " . $stats['forum']['posts'] . "\n";
            echo "    Total: " . $stats['forum']['total'] . "\n";
        }
        
        if (isset($stats['votes'])) {
            echo "  Votes:\n";
            echo "    Upvotes Given: " . $stats['votes']['upvotes_given'] . "\n";
            echo "    Downvotes Given: " . $stats['votes']['downvotes_given'] . "\n";
            echo "    Upvotes Received: " . $stats['votes']['upvotes_received'] . "\n";
            echo "    Downvotes Received: " . $stats['votes']['downvotes_received'] . "\n";
            echo "    Reputation Score: " . $stats['votes']['reputation_score'] . "\n";
        }
        
        if (isset($stats['activity'])) {
            echo "  Activity:\n";
            echo "    Last Activity: " . ($stats['activity']['last_activity'] ?? 'Never') . "\n";
            echo "    Total Actions: " . $stats['activity']['total_actions'] . "\n";
        }
    } else {
        echo "✗ FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 3: Get user activities
echo "\n=== Test 3: Get User Activities (User ID: $userId) ===\n";
try {
    $response = $controller->getUserActivities($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ SUCCESS\n";
        $activities = $data['data'];
        
        echo "\nRecent Activities: " . count($activities) . " items\n";
        foreach (array_slice($activities, 0, 5) as $activity) {
            echo "  - " . $activity['activity_type'] . " in " . $activity['context'];
            echo " (" . $activity['time_ago'] . ")\n";
            if (isset($activity['preview'])) {
                echo "    Preview: " . substr($activity['preview'], 0, 50) . "...\n";
            }
        }
    } else {
        echo "✗ FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Check hero images
echo "\n=== Test 4: Hero Image Availability ===\n";
$heroesDir = __DIR__ . '/public/images/heroes/';
if (is_dir($heroesDir)) {
    $heroFiles = glob($heroesDir . '*.{webp,png,jpg,jpeg}', GLOB_BRACE);
    echo "Found " . count($heroFiles) . " hero images:\n";
    foreach (array_slice($heroFiles, 0, 10) as $file) {
        $heroName = pathinfo(basename($file), PATHINFO_FILENAME);
        $size = filesize($file);
        echo "  ✓ $heroName (" . round($size/1024) . " KB)\n";
    }
} else {
    echo "✗ Heroes directory not found at: $heroesDir\n";
    
    // Check alternative locations
    $altDirs = [
        __DIR__ . '/public/assets/heroes/',
        __DIR__ . '/storage/app/public/heroes/',
        __DIR__ . '/resources/images/heroes/'
    ];
    
    foreach ($altDirs as $dir) {
        if (is_dir($dir)) {
            echo "  Found heroes at: $dir\n";
            $files = glob($dir . '*.{webp,png,jpg,jpeg}', GLOB_BRACE);
            echo "  Contains " . count($files) . " images\n";
        }
    }
}

echo "\n====================================\n";
echo "       TESTS COMPLETED\n";
echo "====================================\n";