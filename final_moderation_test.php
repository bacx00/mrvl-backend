<?php

echo "========================================\n";
echo "FINAL MODERATION TABS COMPREHENSIVE TEST\n";
echo "========================================\n\n";

$baseUrl = 'https://staging.mrvl.net';
$results = [];

function testEndpoint($method, $endpoint, $description, $data = null) {
    global $baseUrl, $results;
    
    echo "Testing: $description... ";
    
    $cmd = "curl -s";
    if ($method === 'POST') {
        $cmd .= " -X POST";
        if ($data) {
            $cmd .= " -H 'Content-Type: application/json' -d '$data'";
        }
    } else {
        $cmd .= " -X GET";
    }
    
    $response = shell_exec("$cmd '$baseUrl$endpoint'");
    $decoded = json_decode($response, true);
    
    if ($decoded && (isset($decoded['success']) || isset($decoded['data']))) {
        echo "✅ PASSED\n";
        $results[$description] = true;
        return true;
    } else {
        echo "❌ FAILED\n";
        if (strlen($response) < 200) {
            echo "   Response: $response\n";
        }
        $results[$description] = false;
        return false;
    }
}

echo "🔧 FORUM MODERATION TAB TESTS\n";
echo "-----------------------------\n";

// Core forum moderation endpoints
testEndpoint('GET', '/api/api/admin/forums-moderation/threads', 'Forum Threads List');
testEndpoint('GET', '/api/api/admin/forums-moderation/categories', 'Forum Categories List');
testEndpoint('GET', '/api/api/admin/forums-moderation/statistics', 'Forum Statistics');
testEndpoint('GET', '/api/api/admin/forums-moderation/dashboard', 'Forum Dashboard');
testEndpoint('GET', '/api/api/admin/forums-moderation/reports', 'Forum Reports');

echo "\n📰 NEWS MODERATION TAB TESTS\n";
echo "-----------------------------\n";

// Core news moderation endpoints
testEndpoint('GET', '/api/api/admin/news-moderation?page=1&limit=50', 'News Articles List');
testEndpoint('GET', '/api/api/admin/news-moderation/categories', 'News Categories List');
testEndpoint('GET', '/api/api/admin/news-moderation/stats/overview', 'News Statistics');
testEndpoint('GET', '/api/api/admin/news-moderation/pending/all', 'Pending News');
testEndpoint('GET', '/api/api/admin/news-moderation/comments', 'News Comments');

echo "\n🎛️ ADVANCED MODERATION FEATURES\n";
echo "--------------------------------\n";

// Advanced features
testEndpoint('GET', '/api/api/admin/news-moderation/search?query=test', 'News Search');
testEndpoint('GET', '/api/api/admin/forums-moderation/posts', 'Forum Posts Management');
testEndpoint('GET', '/api/api/admin/forums-moderation/users', 'Users Management');
testEndpoint('GET', '/api/api/admin/news-moderation/flags/all', 'Flagged Content');

echo "\n📊 STATISTICS & ANALYTICS\n";
echo "-------------------------\n";

// Check if we can get meaningful data from statistics endpoints
echo "Checking News Statistics... ";
$response = shell_exec("curl -s '$baseUrl/api/api/admin/news-moderation/stats/overview'");
$data = json_decode($response, true);
if ($data && isset($data['data']) && count($data['data']) > 0) {
    echo "✅ PASSED - Contains data\n";
    $results['News Stats Data'] = true;
} else {
    echo "❌ FAILED - No data\n";
    $results['News Stats Data'] = false;
}

echo "Checking Forum Statistics... ";
$response = shell_exec("curl -s '$baseUrl/api/api/admin/forums-moderation/statistics'");
$data = json_decode($response, true);
if ($data && isset($data['data']) && count($data['data']) > 0) {
    echo "✅ PASSED - Contains data\n";
    $results['Forum Stats Data'] = true;
} else {
    echo "❌ FAILED - No data\n";
    $results['Forum Stats Data'] = false;
}

echo "\n🔍 CONTENT VERIFICATION\n";
echo "----------------------\n";

// Verify actual content is returned
echo "Verifying Forum Threads Content... ";
$response = shell_exec("curl -s '$baseUrl/api/api/admin/forums-moderation/threads'");
$data = json_decode($response, true);
if ($data && isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
    echo "✅ PASSED - Contains " . count($data['data']) . " threads\n";
    $results['Forum Threads Content'] = true;
} else {
    echo "❌ FAILED - No threads data\n";
    $results['Forum Threads Content'] = false;
}

echo "Verifying News Articles Content... ";
$response = shell_exec("curl -s '$baseUrl/api/api/admin/news-moderation?page=1&limit=50'");
$data = json_decode($response, true);
if ($data && isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
    echo "✅ PASSED - Contains " . count($data['data']) . " articles\n";
    $results['News Articles Content'] = true;
} else {
    echo "❌ FAILED - No articles data\n";
    $results['News Articles Content'] = false;
}

echo "Verifying Categories Content... ";
$response = shell_exec("curl -s '$baseUrl/api/api/admin/news-moderation/categories'");
$data = json_decode($response, true);
if ($data && isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
    echo "✅ PASSED - Contains " . count($data['data']) . " categories\n";
    $results['Categories Content'] = true;
} else {
    echo "❌ FAILED - No categories data\n";
    $results['Categories Content'] = false;
}

// Summary
echo "\n========================================\n";
echo "FINAL MODERATION TEST SUMMARY\n";
echo "========================================\n";

$passed = 0;
$failed = 0;
$total = count($results);

foreach ($results as $test => $result) {
    $status = $result ? '✅' : '❌';
    echo "$status $test\n";
    if ($result) $passed++; else $failed++;
}

echo "\n";
echo "Total Tests: $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($total > 0) {
    $successRate = round(($passed / $total) * 100, 2);
    echo "Success Rate: {$successRate}%\n";
    
    if ($successRate >= 90) {
        echo "\n🎉 EXCELLENT! Both moderation tabs are working perfectly!\n";
    } elseif ($successRate >= 75) {
        echo "\n✅ GOOD! Most moderation features are working.\n";
    } else {
        echo "\n⚠️ NEEDS ATTENTION! Some moderation features need fixing.\n";
    }
}

echo "\n📋 MODERATION TAB STATUS:\n";
echo "Forum Moderation Tab: " . (($results['Forum Threads List'] && $results['Forum Categories List']) ? "✅ OPERATIONAL" : "❌ NEEDS FIX") . "\n";
echo "News Moderation Tab: " . (($results['News Articles List'] && $results['News Categories List']) ? "✅ OPERATIONAL" : "❌ NEEDS FIX") . "\n";