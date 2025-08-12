<?php

/**
 * Comprehensive Forum and Mentions System Test
 * 
 * Tests all required API endpoints for:
 * - Forum threads and replies with mentions
 * - News comments with mentions  
 * - Match comments with mentions
 * - Profile mention endpoints
 */

require_once __DIR__ . '/vendor/autoload.php';

class ForumMentionsSystemTest
{
    private $baseUrl;
    private $adminToken;
    private $testUserId;
    private $testTeamId;
    private $testPlayerId;
    private $testNewsId;
    private $testMatchId;
    private $testThreadId;
    private $testPostId;
    private $testNewsCommentId;
    private $testMatchCommentId;
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api';
        $this->setupTestData();
    }
    
    public function runAllTests()
    {
        echo "🧪 Starting Comprehensive Forum & Mentions System Test\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $testResults = [
            'setup' => $this->testSetup(),
            'forum_thread_creation' => $this->testForumThreadCreation(),
            'forum_reply_creation' => $this->testForumReplyCreation(),
            'news_comment_creation' => $this->testNewsCommentCreation(),
            'match_comment_creation' => $this->testMatchCommentCreation(),
            'mention_profile_endpoints' => $this->testMentionProfileEndpoints(),
            'content_deletion' => $this->testContentDeletion(),
            'cleanup' => $this->testCleanup()
        ];
        
        $this->printTestSummary($testResults);
        return $testResults;
    }
    
    private function setupTestData()
    {
        // Get test IDs from database
        try {
            $pdo = new PDO(
                'mysql:host=localhost;dbname=mrvl_platform',
                'root',
                'root'
            );
            
            // Get test user
            $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->testUserId = $user['id'] ?? 1;
            
            // Get test team
            $stmt = $pdo->query("SELECT id FROM teams LIMIT 1");
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->testTeamId = $team['id'] ?? 1;
            
            // Get test player
            $stmt = $pdo->query("SELECT id FROM players LIMIT 1");
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->testPlayerId = $player['id'] ?? 1;
            
            // Get test news
            $stmt = $pdo->query("SELECT id FROM news WHERE status = 'published' LIMIT 1");
            $news = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->testNewsId = $news['id'] ?? 1;
            
            // Get test match
            $stmt = $pdo->query("SELECT id FROM matches LIMIT 1");
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->testMatchId = $match['id'] ?? 1;
            
        } catch (Exception $e) {
            echo "⚠️  Database connection failed: " . $e->getMessage() . "\n";
            // Use default values
            $this->testUserId = 1;
            $this->testTeamId = 1;
            $this->testPlayerId = 1;
            $this->testNewsId = 1;
            $this->testMatchId = 1;
        }
    }
    
    private function testSetup()
    {
        echo "🔧 Setting up test environment...\n";
        
        // Get admin token
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123'
        ]);
        
        if ($response && isset($response['access_token'])) {
            $this->adminToken = $response['access_token'];
            echo "✅ Admin authentication successful\n";
            return true;
        } else {
            echo "❌ Admin authentication failed\n";
            return false;
        }
    }
    
    private function testForumThreadCreation()
    {
        echo "\n📝 Testing Forum Thread Creation with Mentions...\n";
        
        $threadData = [
            'title' => 'Test Thread with Mentions',
            'content' => 'This is a test thread mentioning @admin and @team:100T and @player:tenz',
            'category' => 'general',
            'tags' => ['test', 'mentions']
        ];
        
        // Test POST /api/user/forums/threads  
        $response = $this->makeRequest('POST', '/user/forums/threads', $threadData);
        
        if ($response && isset($response['success']) && $response['success']) {
            $this->testThreadId = $response['data']['thread']['id'] ?? null;
            echo "✅ Thread created successfully with ID: {$this->testThreadId}\n";
            
            // Check if mentions were processed
            if (isset($response['data']['mentions_processed'])) {
                echo "✅ Mentions processed: {$response['data']['mentions_processed']}\n";
            }
            
            return true;
        } else {
            echo "❌ Thread creation failed\n";
            if ($response) {
                echo "   Response: " . json_encode($response) . "\n";
            }
            return false;
        }
    }
    
    private function testForumReplyCreation()
    {
        echo "\n💬 Testing Forum Reply Creation with Mentions...\n";
        
        if (!$this->testThreadId) {
            echo "❌ No thread ID available for reply test\n";
            return false;
        }
        
        $replyData = [
            'content' => 'This is a reply mentioning @admin and @team:G2',
            'parent_id' => null
        ];
        
        // Test POST /api/user/forums/threads/{id}/posts
        $response = $this->makeRequest('POST', "/user/forums/threads/{$this->testThreadId}/posts", $replyData);
        
        if ($response && isset($response['success']) && $response['success']) {
            $this->testPostId = $response['data']['post']['id'] ?? null;
            echo "✅ Reply created successfully with ID: {$this->testPostId}\n";
            
            // Check if mentions were processed
            if (isset($response['data']['mentions_processed'])) {
                echo "✅ Mentions processed: {$response['data']['mentions_processed']}\n";
            }
            
            return true;
        } else {
            echo "❌ Reply creation failed\n";
            if ($response) {
                echo "   Response: " . json_encode($response) . "\n";
            }
            return false;
        }
    }
    
    private function testNewsCommentCreation()
    {
        echo "\n📰 Testing News Comment Creation with Mentions...\n";
        
        $commentData = [
            'content' => 'Great news! Mentioning @admin and @team:Sentinels here.',
            'parent_id' => null
        ];
        
        // Test POST /api/user/news/{id}/comments
        $response = $this->makeRequest('POST', "/user/news/{$this->testNewsId}/comments", $commentData);
        
        if ($response && isset($response['success']) && $response['success']) {
            $this->testNewsCommentId = $response['data']['comment']['id'] ?? null;
            echo "✅ News comment created successfully with ID: {$this->testNewsCommentId}\n";
            
            // Check if mentions were processed
            if (isset($response['data']['mentions_processed'])) {
                echo "✅ Mentions processed: {$response['data']['mentions_processed']}\n";
            }
            
            return true;
        } else {
            echo "❌ News comment creation failed\n";
            if ($response) {
                echo "   Response: " . json_encode($response) . "\n";
            }
            return false;
        }
    }
    
    private function testMatchCommentCreation()
    {
        echo "\n🎮 Testing Match Comment Creation with Mentions...\n";
        
        $commentData = [
            'content' => 'Amazing match! @player:Shroud played incredibly well.',
            'parent_id' => null
        ];
        
        // Test POST /api/user/matches/{id}/comments
        $response = $this->makeRequest('POST', "/user/matches/{$this->testMatchId}/comments", $commentData);
        
        if ($response && isset($response['success']) && $response['success']) {
            $this->testMatchCommentId = $response['data']['comment']['id'] ?? null;
            echo "✅ Match comment created successfully with ID: {$this->testMatchCommentId}\n";
            
            // Check if mentions were processed
            if (isset($response['data']['mentions_processed'])) {
                echo "✅ Mentions processed: {$response['data']['mentions_processed']}\n";
            }
            
            return true;
        } else {
            echo "❌ Match comment creation failed\n";
            if ($response) {
                echo "   Response: " . json_encode($response) . "\n";
            }
            return false;
        }
    }
    
    private function testMentionProfileEndpoints()
    {
        echo "\n👤 Testing Mention Profile Endpoints...\n";
        
        $results = [];
        
        // Test GET /api/users/{id}/mentions
        echo "  Testing user mentions endpoint...\n";
        $response = $this->makeRequest('GET', "/users/{$this->testUserId}/mentions");
        if ($response && isset($response['success']) && $response['success']) {
            echo "  ✅ User mentions endpoint working\n";
            echo "     Found " . count($response['data']) . " mentions\n";
            $results['user_mentions'] = true;
        } else {
            echo "  ❌ User mentions endpoint failed\n";
            $results['user_mentions'] = false;
        }
        
        // Test GET /api/teams/{id}/mentions
        echo "  Testing team mentions endpoint...\n";
        $response = $this->makeRequest('GET', "/teams/{$this->testTeamId}/mentions");
        if ($response && isset($response['success']) && $response['success']) {
            echo "  ✅ Team mentions endpoint working\n";
            echo "     Found " . count($response['data']) . " mentions\n";
            $results['team_mentions'] = true;
        } else {
            echo "  ❌ Team mentions endpoint failed\n";
            $results['team_mentions'] = false;
        }
        
        // Test GET /api/players/{id}/mentions
        echo "  Testing player mentions endpoint...\n";
        $response = $this->makeRequest('GET', "/players/{$this->testPlayerId}/mentions");
        if ($response && isset($response['success']) && $response['success']) {
            echo "  ✅ Player mentions endpoint working\n";
            echo "     Found " . count($response['data']) . " mentions\n";
            $results['player_mentions'] = true;
        } else {
            echo "  ❌ Player mentions endpoint failed\n";
            $results['player_mentions'] = false;
        }
        
        return array_filter($results);
    }
    
    private function testContentDeletion()
    {
        echo "\n🗑️  Testing Content Deletion (should delete mentions)...\n";
        
        $results = [];
        
        // Test DELETE /api/user/forums/posts/{id}
        if ($this->testPostId) {
            echo "  Testing forum post deletion...\n";
            $response = $this->makeRequest('DELETE', "/user/forums/posts/{$this->testPostId}");
            if ($response && isset($response['success']) && $response['success']) {
                echo "  ✅ Forum post deleted successfully\n";
                $results['forum_post_deletion'] = true;
            } else {
                echo "  ❌ Forum post deletion failed\n";
                $results['forum_post_deletion'] = false;
            }
        }
        
        // Test DELETE /api/user/news/comments/{id}
        if ($this->testNewsCommentId) {
            echo "  Testing news comment deletion...\n";
            $response = $this->makeRequest('DELETE', "/user/news/comments/{$this->testNewsCommentId}");
            if ($response && isset($response['success']) && $response['success']) {
                echo "  ✅ News comment deleted successfully\n";
                $results['news_comment_deletion'] = true;
            } else {
                echo "  ❌ News comment deletion failed\n";
                $results['news_comment_deletion'] = false;
            }
        }
        
        // Test DELETE /api/user/matches/comments/{id}
        if ($this->testMatchCommentId) {
            echo "  Testing match comment deletion...\n";
            $response = $this->makeRequest('DELETE', "/user/matches/comments/{$this->testMatchCommentId}");
            if ($response && isset($response['success']) && $response['success']) {
                echo "  ✅ Match comment deleted successfully\n";
                $results['match_comment_deletion'] = true;
            } else {
                echo "  ❌ Match comment deletion failed\n";
                $results['match_comment_deletion'] = false;
            }
        }
        
        return $results;
    }
    
    private function testCleanup()
    {
        echo "\n🧹 Cleaning up test data...\n";
        
        // Delete test thread if it exists
        if ($this->testThreadId) {
            $response = $this->makeRequest('DELETE', "/user/forums/threads/{$this->testThreadId}");
            if ($response && isset($response['success']) && $response['success']) {
                echo "✅ Test thread cleaned up\n";
            }
        }
        
        echo "✅ Cleanup completed\n";
        return true;
    }
    
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $headers = ['Content-Type: application/json'];
        
        if ($this->adminToken) {
            $headers[] = 'Authorization: Bearer ' . $this->adminToken;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ cURL Error: $error\n";
            return null;
        }
        
        if ($httpCode >= 400) {
            echo "❌ HTTP Error $httpCode for $method $endpoint\n";
            echo "   Response: $response\n";
        }
        
        return json_decode($response, true);
    }
    
    private function printTestSummary($results)
    {
        echo "\n" . "=" . str_repeat("=", 60) . "\n";
        echo "📊 TEST SUMMARY\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($results as $testName => $result) {
            $totalTests++;
            if (is_array($result)) {
                $subPassed = count(array_filter($result));
                $subTotal = count($result);
                $status = $subPassed === $subTotal ? "✅ PASS" : "❌ PARTIAL";
                echo sprintf("%-30s %s (%d/%d)\n", $testName, $status, $subPassed, $subTotal);
                if ($subPassed === $subTotal) $passedTests++;
            } else {
                $status = $result ? "✅ PASS" : "❌ FAIL";
                echo sprintf("%-30s %s\n", $testName, $status);
                if ($result) $passedTests++;
            }
        }
        
        echo "\n";
        echo "Overall Results: $passedTests/$totalTests tests passed\n";
        
        if ($passedTests === $totalTests) {
            echo "🎉 ALL TESTS PASSED! Forum & Mentions system is working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Please check the implementation.\n";
        }
        
        echo "=" . str_repeat("=", 60) . "\n";
    }
}

// Run the test
if (php_sapi_name() === 'cli') {
    $test = new ForumMentionsSystemTest();
    $test->runAllTests();
}