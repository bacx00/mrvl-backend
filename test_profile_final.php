#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use App\Http\Controllers\UserProfileController;
use App\Services\OptimizedUserProfileService;

echo "====================================\n";
echo "  FINAL USER PROFILE TESTS\n";
echo "====================================\n\n";

// Test 1: Direct User Model Check
echo "=== Test 1: User Model Data ===\n";
$user = User::find(1);
echo "Name: " . $user->name . "\n";
echo "Hero Flair: " . ($user->hero_flair ?? 'None') . "\n";
echo "Use Hero as Avatar: " . ($user->use_hero_as_avatar ? 'Yes' : 'No') . "\n";
echo "Show Hero Flair: " . ($user->show_hero_flair ? 'Yes' : 'No') . "\n";

// Test 2: User Profile Service
echo "\n=== Test 2: Profile Service Stats ===\n";
$service = new OptimizedUserProfileService();
try {
    $stats = $service->getUserStatisticsOptimized(1);
    echo "✓ Stats retrieved successfully\n";
    echo "  Comments: News=" . $stats['comments']['news'] . ", Matches=" . $stats['comments']['matches'] . "\n";
    echo "  Forum: Threads=" . $stats['forum']['threads'] . ", Posts=" . $stats['forum']['posts'] . "\n";
    echo "  Votes: Reputation=" . $stats['votes']['reputation_score'] . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Controller Methods
echo "\n=== Test 3: Controller Methods ===\n";
$controller = app(UserProfileController::class);

// Test getUserWithDetails
try {
    $response = $controller->getUserWithDetails(1);
    $data = json_decode($response->getContent(), true);
    
    if (isset($data['success']) && $data['success']) {
        echo "✓ getUserWithDetails: SUCCESS\n";
        $profile = $data['data'];
        echo "  Avatar: " . ($profile['avatar'] ?? 'None') . "\n";
        
        // Check if hero image path is correct
        if ($profile['hero_flair'] && $profile['use_hero_as_avatar']) {
            $expectedPath = "/images/heroes/{$profile['hero_flair']}-headbig.webp";
            echo "  Expected hero image: $expectedPath\n";
            
            $fullPath = __DIR__ . '/public' . $expectedPath;
            if (file_exists($fullPath)) {
                echo "  ✓ Hero image file exists\n";
            } else {
                echo "  ✗ Hero image file not found at: $fullPath\n";
            }
        }
    } else {
        echo "✗ getUserWithDetails: " . ($data['message'] ?? 'Failed') . "\n";
    }
} catch (Exception $e) {
    echo "✗ getUserWithDetails Error: " . $e->getMessage() . "\n";
}

// Test getUserStatsPublic
try {
    $response = $controller->getUserStatsPublic(1);
    $data = json_decode($response->getContent(), true);
    
    if (isset($data['success']) && $data['success']) {
        echo "✓ getUserStatsPublic: SUCCESS\n";
    } else {
        echo "✗ getUserStatsPublic: " . ($data['message'] ?? 'Failed') . "\n";
    }
} catch (Exception $e) {
    echo "✗ getUserStatsPublic Error: " . $e->getMessage() . "\n";
}

// Test getUserActivities
try {
    $response = $controller->getUserActivities(1);
    $data = json_decode($response->getContent(), true);
    
    if (isset($data['success']) && $data['success']) {
        echo "✓ getUserActivities: SUCCESS\n";
        echo "  Activities count: " . count($data['data']) . "\n";
    } else {
        echo "✗ getUserActivities: " . ($data['message'] ?? 'Failed') . "\n";
    }
} catch (Exception $e) {
    echo "✗ getUserActivities Error: " . $e->getMessage() . "\n";
}

// Test 4: Hero Images Available
echo "\n=== Test 4: Available Hero Images ===\n";
$heroesDir = __DIR__ . '/public/images/heroes/';
$heroFiles = glob($heroesDir . '*-headbig.webp');
echo "Found " . count($heroFiles) . " hero images\n";

// Show first 5 heroes
foreach (array_slice($heroFiles, 0, 5) as $file) {
    $heroName = str_replace('-headbig.webp', '', basename($file));
    echo "  - $heroName\n";
}

echo "\n====================================\n";
echo "       TESTS COMPLETED\n";
echo "====================================\n";