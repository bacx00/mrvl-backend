#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Http\Controllers\UserProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

echo "====================================\n";
echo "  COMPREHENSIVE PROFILE TESTS\n";
echo "====================================\n\n";

// Clear cache for fresh test
Cache::forget('user_display_1');
Cache::forget('user_stats_optimized_1');

$controller = app(UserProfileController::class);
$userId = 1;

// Test 1: getUserWithDetails
echo "=== Test 1: getUserWithDetails (User ID: $userId) ===\n";
try {
    $response = $controller->getUserWithDetails($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ SUCCESS\n";
        $profile = $data['data'];
        
        echo "Profile Data:\n";
        echo "  - Name: " . $profile['name'] . "\n";
        echo "  - Avatar: " . ($profile['avatar'] ?? 'None') . "\n";
        echo "  - Hero Flair: " . ($profile['hero_flair'] ?? 'None') . "\n";
        echo "  - Team: " . ($profile['team_flair']['name'] ?? 'None') . "\n";
        echo "  - Use Hero as Avatar: " . ($profile['use_hero_as_avatar'] ? 'Yes' : 'No') . "\n";
        
        // Verify hero image
        if ($profile['avatar'] && strpos($profile['avatar'], '/images/heroes/') !== false) {
            $fullPath = __DIR__ . '/public' . $profile['avatar'];
            if (file_exists($fullPath)) {
                $size = round(filesize($fullPath) / 1024);
                echo "  ✓ Hero image verified: {$size}KB\n";
            } else {
                echo "  ✗ Hero image not found at: $fullPath\n";
            }
        }
    } else {
        echo "✗ FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 2: getUserStatsPublic
echo "\n=== Test 2: getUserStatsPublic (User ID: $userId) ===\n";
try {
    $response = $controller->getUserStatsPublic($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ SUCCESS\n";
        $stats = $data['data'];
        
        echo "User Statistics:\n";
        echo "  Comments:\n";
        echo "    - News: " . $stats['comments']['news'] . "\n";
        echo "    - Matches: " . $stats['comments']['matches'] . "\n";
        echo "    - Total: " . $stats['comments']['total'] . "\n";
        
        echo "  Forum:\n";
        echo "    - Threads: " . $stats['forum']['threads'] . "\n";
        echo "    - Posts: " . $stats['forum']['posts'] . "\n";
        echo "    - Total: " . $stats['forum']['total'] . "\n";
        
        echo "  Votes:\n";
        echo "    - Reputation: " . $stats['votes']['reputation_score'] . "\n";
        echo "    - Given: ↑" . $stats['votes']['upvotes_given'] . " ↓" . $stats['votes']['downvotes_given'] . "\n";
        echo "    - Received: ↑" . $stats['votes']['upvotes_received'] . " ↓" . $stats['votes']['downvotes_received'] . "\n";
        
        echo "  Activity:\n";
        echo "    - Last: " . ($stats['activity']['last_activity'] ?? 'Never') . "\n";
        echo "    - Total Actions: " . $stats['activity']['total_actions'] . "\n";
    } else {
        echo "✗ FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 3: getUserActivities
echo "\n=== Test 3: getUserActivities (User ID: $userId) ===\n";
try {
    $response = $controller->getUserActivities($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✓ SUCCESS\n";
        $activities = $data['data'];
        
        echo "Recent Activities: " . count($activities) . " items\n";
        foreach (array_slice($activities, 0, 5) as $activity) {
            echo "  - " . $activity['activity_type'] . " in " . $activity['context'];
            echo " (" . $activity['time_ago'] . ")\n";
            if (isset($activity['preview'])) {
                $preview = substr($activity['preview'], 0, 50);
                echo "    \"$preview...\"\n";
            }
        }
    } else {
        echo "✗ FAILED: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Different hero flairs
echo "\n=== Test 4: Testing Different Hero Flairs ===\n";
$testHeroes = ['groot', 'venom', 'spider-man', 'iron-man', 'hulk'];
$user = User::find($userId);

foreach ($testHeroes as $hero) {
    $user->hero_flair = $hero;
    $user->use_hero_as_avatar = true;
    $user->save();
    
    // Clear cache for fresh test
    Cache::forget("user_display_{$userId}");
    
    $response = $controller->getUserWithDetails($userId);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success'] && $data['data']['avatar']) {
        $avatar = $data['data']['avatar'];
        $fullPath = __DIR__ . '/public' . $avatar;
        if (file_exists($fullPath)) {
            echo "  ✓ $hero: " . basename($avatar) . "\n";
        } else {
            echo "  ✗ $hero: Image not found\n";
        }
    } else {
        echo "  ✗ $hero: Failed to get avatar\n";
    }
}

// Reset to original
$user->hero_flair = 'groot';
$user->save();

echo "\n====================================\n";
echo "    ALL TESTS COMPLETED\n";
echo "====================================\n";