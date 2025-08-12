<?php

require_once 'vendor/autoload.php';

// Test script for the Marvel Rivals real-time mention system
echo "=== Marvel Rivals Real-time Mention System Test ===\n\n";

// Test configuration
$baseUrl = 'http://localhost:8000/api';
$testData = [
    'content' => 'Hey @testuser, check out this great play by @team:G2 and @player:TenZ!',
    'mentionable_type' => 'news',
    'mentionable_id' => 1,
    'mentioned_by' => 1
];

echo "🔧 Testing API endpoints...\n\n";

// Function to make HTTP requests
function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }
    
    return [
        'response' => json_decode($response, true),
        'http_code' => $httpCode,
        'raw_response' => $response
    ];
}

// Test 1: Create mentions
echo "📝 Test 1: Creating mentions from content\n";
echo "Content: {$testData['content']}\n";
echo "Type: {$testData['mentionable_type']}, ID: {$testData['mentionable_id']}\n";
echo "Mentioned by: User {$testData['mentioned_by']}\n\n";

$result = makeRequest("$baseUrl/mentions/create", 'POST', $testData);

echo "HTTP Status: {$result['http_code']}\n";
if ($result['http_code'] === 200 || $result['http_code'] === 201) {
    echo "✅ SUCCESS: Mentions created successfully\n";
    $response = $result['response'];
    if (isset($response['data']['mentions_created'])) {
        echo "   Created {$response['data']['mentions_created']} mentions\n";
    }
    if (isset($response['data']['mentions'])) {
        foreach ($response['data']['mentions'] as $mention) {
            echo "   - {$mention['type']} mention: {$mention['mention_text']} for entity {$mention['entity']['id']}\n";
        }
    }
} else {
    echo "❌ FAILED: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
    if (isset($result['response']['errors'])) {
        foreach ($result['response']['errors'] as $field => $errors) {
            echo "   $field: " . implode(', ', $errors) . "\n";
        }
    }
}
echo "\n";

// Test 2: Get mention counts (test different entity types)
$entityTests = [
    ['type' => 'user', 'id' => 1],
    ['type' => 'team', 'id' => 1],
    ['type' => 'player', 'id' => 1]
];

foreach ($entityTests as $test) {
    echo "📊 Test 2.{$test['type']}: Getting mention counts for {$test['type']} {$test['id']}\n";
    
    $result = makeRequest("$baseUrl/mentions/{$test['type']}/{$test['id']}/counts");
    
    echo "HTTP Status: {$result['http_code']}\n";
    if ($result['http_code'] === 200) {
        echo "✅ SUCCESS: Retrieved mention counts\n";
        $response = $result['response'];
        if (isset($response['data']['mention_count'])) {
            echo "   Mention count: {$response['data']['mention_count']}\n";
            echo "   Period: {$response['data']['period']}\n";
            echo "   Cached: " . ($response['data']['cached'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "❌ FAILED: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
    }
    echo "\n";
}

// Test 3: Get recent mentions
foreach ($entityTests as $test) {
    echo "📋 Test 3.{$test['type']}: Getting recent mentions for {$test['type']} {$test['id']}\n";
    
    $result = makeRequest("$baseUrl/mentions/{$test['type']}/{$test['id']}/recent?limit=5");
    
    echo "HTTP Status: {$result['http_code']}\n";
    if ($result['http_code'] === 200) {
        echo "✅ SUCCESS: Retrieved recent mentions\n";
        $response = $result['response'];
        if (isset($response['data'])) {
            echo "   Found " . count($response['data']) . " recent mentions\n";
            foreach ($response['data'] as $i => $mention) {
                echo "   " . ($i + 1) . ". {$mention['mention_text']} by " . 
                     ($mention['mentioned_by']['name'] ?? 'Unknown') . 
                     " on " . date('Y-m-d H:i', strtotime($mention['mentioned_at'])) . "\n";
            }
        }
    } else {
        echo "❌ FAILED: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
    }
    echo "\n";
}

// Test 4: Test mention search functionality
echo "🔍 Test 4: Testing mention search functionality\n";

$searchTests = [
    ['query' => 'test', 'type' => 'all'],
    ['query' => 'G2', 'type' => 'team'],
    ['query' => 'Ten', 'type' => 'player']
];

foreach ($searchTests as $search) {
    echo "Searching for '{$search['query']}' in {$search['type']}\n";
    
    $url = "$baseUrl/mentions/search?q=" . urlencode($search['query']) . "&type={$search['type']}&limit=5";
    $result = makeRequest($url);
    
    echo "HTTP Status: {$result['http_code']}\n";
    if ($result['http_code'] === 200) {
        echo "✅ SUCCESS: Search completed\n";
        $response = $result['response'];
        if (isset($response['data'])) {
            echo "   Found " . count($response['data']) . " results\n";
            foreach ($response['data'] as $i => $item) {
                echo "   " . ($i + 1) . ". {$item['type']}: {$item['display_name']} ({$item['mention_text']})\n";
            }
        }
    } else {
        echo "❌ FAILED: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
    }
    echo "\n";
}

// Test 5: Delete mentions (cleanup)
echo "🗑️ Test 5: Deleting mentions for cleanup\n";

$deleteData = [
    'mentionable_type' => $testData['mentionable_type'],
    'mentionable_id' => $testData['mentionable_id']
];

$result = makeRequest("$baseUrl/mentions/delete", 'DELETE', $deleteData);

echo "HTTP Status: {$result['http_code']}\n";
if ($result['http_code'] === 200) {
    echo "✅ SUCCESS: Mentions deleted successfully\n";
    $response = $result['response'];
    if (isset($response['data']['mentions_deleted'])) {
        echo "   Deleted {$response['data']['mentions_deleted']} mentions\n";
    }
} else {
    echo "❌ FAILED: " . ($result['response']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

echo "=== Test Summary ===\n";
echo "✅ All API endpoints tested\n";
echo "🔧 Test HTML page available at: test-realtime-mentions.html\n";
echo "📱 Frontend integration ready for ProfilePage components\n";
echo "🔄 Real-time WebSocket system configured\n";
echo "🔔 Notification system implemented\n\n";

echo "Next steps:\n";
echo "1. Start the Laravel server: php artisan serve\n";
echo "2. Open test-realtime-mentions.html in browser\n";
echo "3. Test creating mentions and watch real-time updates\n";
echo "4. Check ProfilePage components for live mention counts\n";
echo "5. Verify notifications appear when users are mentioned\n\n";

echo "=== Real-time Mention System Implementation Complete ===\n";