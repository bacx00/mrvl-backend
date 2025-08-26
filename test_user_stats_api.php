<?php

// Test User Stats API
$baseUrl = 'https://staging.mrvl.net/api';

echo "=== USER STATS API TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 50) . "\n\n";

// Test getting stats for user ID 1
$ch = curl_init($baseUrl . '/users/1/stats');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n\n";

if ($response) {
    $data = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Response Data:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        
        if (isset($data['success']) && $data['success'] && isset($data['data'])) {
            $stats = $data['data'];
            
            echo "=== ACTIVITY STATS SUMMARY ===\n";
            echo "Forum Threads: " . ($stats['forum_threads'] ?? 0) . "\n";
            echo "Forum Posts: " . ($stats['forum_posts'] ?? 0) . "\n";
            echo "News Comments: " . ($stats['news_comments'] ?? 0) . "\n";
            echo "Match Comments: " . ($stats['match_comments'] ?? 0) . "\n";
            echo "Total Comments: " . ($stats['total_comments'] ?? 0) . "\n";
            echo "Days Active: " . ($stats['days_active'] ?? 0) . "\n";
            echo "Activity Score: " . ($stats['activity_score'] ?? 0) . "\n";
            echo "Reputation Score: " . ($stats['reputation_score'] ?? 0) . "\n";
            echo "Mentions Received: " . ($stats['mentions_received'] ?? 0) . "\n";
            echo "Total Actions: " . ($stats['total_actions'] ?? 0) . "\n";
            echo "Last Activity: " . ($stats['last_activity'] ?? 'Never') . "\n";
            echo "Join Date: " . ($stats['join_date'] ?? 'Unknown') . "\n";
        }
    } else {
        echo "Failed to parse JSON response\n";
        echo "Raw response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "No response received\n";
}

echo "\n" . str_repeat('=', 50) . "\n";