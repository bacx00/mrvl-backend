<?php

// Test User Profile System
$baseUrl = 'https://staging.mrvl.net/api';
$token = file_get_contents('admin_token.txt');

function testEndpoint($name, $method, $endpoint, $data = null, $token = null) {
    global $baseUrl;
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    if ($data && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = [
        'test' => $name,
        'endpoint' => $endpoint,
        'method' => $method,
        'status_code' => $httpCode,
        'success' => $httpCode >= 200 && $httpCode < 300,
        'response' => json_decode($response, true)
    ];
    
    echo "Test: {$name}\n";
    echo "Status: " . ($result['success'] ? "✓ PASSED" : "✗ FAILED") . " (HTTP {$httpCode})\n";
    
    if (!$result['success'] && $result['response']) {
        echo "Error: " . json_encode($result['response']) . "\n";
    }
    
    echo str_repeat('-', 50) . "\n";
    
    return $result;
}

echo "=== USER PROFILE SYSTEM TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 50) . "\n\n";

$results = [];

// Test 1: Get user profile (public)
$results[] = testEndpoint(
    'Get User Profile (ID 1)',
    'GET',
    '/users/1'
);

// Test 2: Get user profile (ID 2)
$results[] = testEndpoint(
    'Get User Profile (ID 2)',
    'GET',
    '/users/2'
);

// Test 3: Get current user profile (authenticated)
$results[] = testEndpoint(
    'Get Current User Profile',
    'GET',
    '/user',
    null,
    $token
);

// Test 4: Update user profile
$results[] = testEndpoint(
    'Update User Profile',
    'PUT',
    '/user/profile',
    [
        'bio' => 'Testing user profile update - ' . date('Y-m-d H:i:s'),
        'location' => 'Test Location',
        'website' => 'https://example.com'
    ],
    $token
);

// Test 5: Get user stats
$results[] = testEndpoint(
    'Get User Stats',
    'GET',
    '/users/1/stats'
);

// Test 6: Get user activity
$results[] = testEndpoint(
    'Get User Activity',
    'GET',
    '/users/1/activity'
);

// Test 7: Get user forum posts
$results[] = testEndpoint(
    'Get User Forum Posts',
    'GET',
    '/users/1/forum-posts'
);

// Test 8: Get user achievements
$results[] = testEndpoint(
    'Get User Achievements',
    'GET',
    '/users/1/achievements'
);

// Test 9: Get user match history
$results[] = testEndpoint(
    'Get User Match History',
    'GET',
    '/users/1/matches'
);

// Test 10: Update user avatar
$results[] = testEndpoint(
    'Update User Avatar URL',
    'POST',
    '/user/avatar',
    [
        'avatar_url' => 'https://example.com/avatar.jpg'
    ],
    $token
);

// Test 11: Update user settings
$results[] = testEndpoint(
    'Update User Settings',
    'PUT',
    '/user/settings',
    [
        'email_notifications' => true,
        'show_online_status' => true,
        'profile_visibility' => 'public'
    ],
    $token
);

// Test 12: Get user notifications
$results[] = testEndpoint(
    'Get User Notifications',
    'GET',
    '/user/notifications',
    null,
    $token
);

// Test 13: Mark notification as read
$results[] = testEndpoint(
    'Mark Notification as Read',
    'POST',
    '/user/notifications/1/read',
    null,
    $token
);

// Test 14: Get user mentions
$results[] = testEndpoint(
    'Get User Mentions',
    'GET',
    '/user/mentions',
    null,
    $token
);

// Test 15: Get leaderboard position
$results[] = testEndpoint(
    'Get User Leaderboard Position',
    'GET',
    '/users/1/leaderboard'
);

// Summary
echo "\n=== TEST SUMMARY ===\n";
$passed = array_filter($results, fn($r) => $r['success']);
$failed = array_filter($results, fn($r) => !$r['success']);

echo "Total Tests: " . count($results) . "\n";
echo "Passed: " . count($passed) . "\n";
echo "Failed: " . count($failed) . "\n";
echo "Success Rate: " . round((count($passed) / count($results)) * 100, 2) . "%\n";

if (count($failed) > 0) {
    echo "\n=== FAILED TESTS ===\n";
    foreach ($failed as $test) {
        echo "- {$test['test']} ({$test['method']} {$test['endpoint']}): HTTP {$test['status_code']}\n";
        if (isset($test['response']['message'])) {
            echo "  Error: {$test['response']['message']}\n";
        }
    }
}

// Save report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'total_tests' => count($results),
    'passed' => count($passed),
    'failed' => count($failed),
    'success_rate' => round((count($passed) / count($results)) * 100, 2),
    'results' => $results
];

file_put_contents('user_profile_test_report_' . time() . '.json', json_encode($report, JSON_PRETTY_PRINT));
echo "\nReport saved to user_profile_test_report_" . time() . ".json\n";