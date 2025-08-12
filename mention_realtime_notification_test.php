<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\MentionService;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Mention;
use App\Models\News;
use App\Models\ForumThread;
use Illuminate\Support\Facades\Notification;

class MentionRealtimeNotificationTest
{
    private $mentionService;
    private $testResults = [];
    private $errors = [];
    
    public function __construct()
    {
        $this->mentionService = new MentionService();
    }
    
    public function runTests()
    {
        echo "\n=== MENTION REAL-TIME & NOTIFICATION TESTS ===\n";
        
        // Test 1: Test mention API endpoints
        $this->testMentionApiEndpoints();
        
        // Test 2: Test mention display in different contexts
        $this->testMentionDisplayContexts();
        
        // Test 3: Test notification triggers (if notification system exists)
        $this->testNotificationTriggers();
        
        $this->generateReport();
    }
    
    private function testMentionApiEndpoints()
    {
        echo "\n--- Testing Mention API Endpoints ---\n";
        
        $baseUrl = 'http://localhost:8000/api';
        $endpoints = [
            '/public/mentions/search?q=test',
            '/public/mentions/popular',
            '/mentions/search?q=test',
            '/mentions/popular'
        ];
        
        foreach ($endpoints as $endpoint) {
            try {
                $url = $baseUrl . $endpoint;
                
                // Use cURL to test the endpoint
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    echo "❌ FAIL: $endpoint - cURL Error: $error\n";
                    $this->errors[] = "API endpoint $endpoint cURL error: $error";
                } elseif ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if (isset($data['success']) && $data['success']) {
                        echo "✅ PASS: $endpoint - Status: $httpCode\n";
                    } else {
                        echo "⚠️ WARNING: $endpoint - Status: $httpCode, but success=false\n";
                        echo "   Response: " . substr($response, 0, 200) . "...\n";
                    }
                } else {
                    echo "❌ FAIL: $endpoint - HTTP Status: $httpCode\n";
                    echo "   Response: " . substr($response, 0, 200) . "...\n";
                    $this->errors[] = "API endpoint $endpoint returned HTTP $httpCode";
                }
                
            } catch (Exception $e) {
                echo "❌ ERROR: $endpoint - " . $e->getMessage() . "\n";
                $this->errors[] = "API endpoint $endpoint error: " . $e->getMessage();
            }
        }
    }
    
    private function testMentionDisplayContexts()
    {
        echo "\n--- Testing Mention Display in Different Contexts ---\n";
        
        try {
            // Test mentions in news
            $this->testNewsContextMentions();
            
            // Test mentions in forum threads
            $this->testForumContextMentions();
            
            // Test mention processing for display
            $this->testMentionProcessingForDisplay();
            
        } catch (Exception $e) {
            echo "❌ ERROR in mention display context test: " . $e->getMessage() . "\n";
            $this->errors[] = "Mention display context error: " . $e->getMessage();
        }
    }
    
    private function testNewsContextMentions()
    {
        echo "  Testing News Context Mentions...\n";
        
        // Create test news article with mentions
        $newsContent = "Breaking: @team:TSM signs new player @player:shroud! @testuser reported this first.";
        
        // Extract mentions
        $mentions = $this->mentionService->extractMentions($newsContent);
        
        if (count($mentions) > 0) {
            echo "  ✅ PASS: Extracted " . count($mentions) . " mentions from news content\n";
            
            // Process for display
            $processedContent = $this->mentionService->processMentionsForDisplay($newsContent, $mentions);
            
            if (strpos($processedContent, '<a href=') !== false) {
                echo "  ✅ PASS: Mentions converted to clickable links\n";
            } else {
                echo "  ❌ FAIL: Mentions not converted to links\n";
                $this->errors[] = "News mentions not converted to links";
            }
            
        } else {
            echo "  ❌ FAIL: No mentions extracted from news content\n";
            $this->errors[] = "No mentions extracted from news content";
        }
    }
    
    private function testForumContextMentions()
    {
        echo "  Testing Forum Context Mentions...\n";
        
        // Create test forum post with mentions
        $forumContent = "What do you think about @team:G2 vs @team:FNC? @player:caps played amazing!";
        
        // Extract mentions
        $mentions = $this->mentionService->extractMentions($forumContent);
        
        if (count($mentions) > 0) {
            echo "  ✅ PASS: Extracted " . count($mentions) . " mentions from forum content\n";
            
            // Test mention storage for forum content
            Mention::where('mentionable_type', 'forum_test')->delete();
            $storedCount = $this->mentionService->storeMentions($forumContent, 'forum_test', 1);
            
            if ($storedCount > 0) {
                echo "  ✅ PASS: Stored $storedCount mentions for forum context\n";
            } else {
                echo "  ❌ FAIL: No mentions stored for forum context\n";
                $this->errors[] = "Forum mentions not stored";
            }
            
        } else {
            echo "  ❌ FAIL: No mentions extracted from forum content\n";
            $this->errors[] = "No mentions extracted from forum content";
        }
    }
    
    private function testMentionProcessingForDisplay()
    {
        echo "  Testing Mention Processing for Display...\n";
        
        // Test with stored mentions
        $mentions = $this->mentionService->getMentionsForContent('forum_test', 1);
        
        if (count($mentions) > 0) {
            echo "  ✅ PASS: Retrieved " . count($mentions) . " stored mentions\n";
            
            $testContent = "What do you think about @team:G2 vs @team:FNC? @player:caps played amazing!";
            $processedContent = $this->mentionService->processMentionsForDisplay($testContent, $mentions);
            
            // Check if content was processed correctly
            $hasLinks = strpos($processedContent, 'href=') !== false;
            $hasClasses = strpos($processedContent, 'mention') !== false;
            
            if ($hasLinks && $hasClasses) {
                echo "  ✅ PASS: Content processed with clickable mention links\n";
            } else {
                echo "  ⚠️ WARNING: Content processed but missing links or classes\n";
                echo "     Processed content: " . substr($processedContent, 0, 200) . "...\n";
            }
            
        } else {
            echo "  ❌ FAIL: No mentions retrieved for display processing\n";
            $this->errors[] = "No mentions retrieved for display processing";
        }
    }
    
    private function testNotificationTriggers()
    {
        echo "\n--- Testing Notification Triggers ---\n";
        
        try {
            // Check if notification system exists
            $notificationExists = class_exists('Illuminate\Notifications\Notification');
            
            if ($notificationExists) {
                echo "  ✅ Laravel Notification system available\n";
                
                // Test if mention notifications are implemented
                $this->checkMentionNotificationImplementation();
                
            } else {
                echo "  ⚠️ WARNING: Laravel Notification system not available\n";
                echo "  📝 RECOMMENDATION: Implement notification system for mentions\n";
            }
            
            // Test real-time broadcasting setup
            $this->checkBroadcastingSetup();
            
        } catch (Exception $e) {
            echo "  ❌ ERROR testing notifications: " . $e->getMessage() . "\n";
            $this->errors[] = "Notification testing error: " . $e->getMessage();
        }
    }
    
    private function checkMentionNotificationImplementation()
    {
        echo "  Checking Mention Notification Implementation...\n";
        
        // Check if mention notification classes exist
        $mentionNotificationFiles = [
            '/app/Notifications/MentionNotification.php',
            '/app/Notifications/NewMentionNotification.php',
            '/app/Notifications/UserMentionedNotification.php'
        ];
        
        $notificationFound = false;
        foreach ($mentionNotificationFiles as $file) {
            if (file_exists(__DIR__ . $file)) {
                $notificationFound = true;
                echo "  ✅ PASS: Found notification file: $file\n";
                break;
            }
        }
        
        if (!$notificationFound) {
            echo "  ❌ MISSING: No mention notification classes found\n";
            echo "  📝 RECOMMENDATION: Create mention notification classes\n";
            $this->errors[] = "Missing mention notification implementation";
        }
        
        // Check if MentionService triggers notifications
        $mentionServiceContent = file_get_contents(__DIR__ . '/app/Services/MentionService.php');
        $hasNotificationTrigger = strpos($mentionServiceContent, 'Notification::') !== false || 
                                  strpos($mentionServiceContent, '->notify(') !== false;
        
        if ($hasNotificationTrigger) {
            echo "  ✅ PASS: MentionService appears to trigger notifications\n";
        } else {
            echo "  ❌ MISSING: MentionService does not trigger notifications\n";
            echo "  📝 RECOMMENDATION: Add notification triggers to MentionService\n";
            $this->errors[] = "MentionService missing notification triggers";
        }
    }
    
    private function checkBroadcastingSetup()
    {
        echo "  Checking Broadcasting Setup for Real-time Updates...\n";
        
        // Check broadcasting configuration
        $broadcastingConfig = __DIR__ . '/config/broadcasting.php';
        if (file_exists($broadcastingConfig)) {
            echo "  ✅ PASS: Broadcasting config exists\n";
            
            // Check if channels are configured
            $channelsFile = __DIR__ . '/routes/channels.php';
            if (file_exists($channelsFile)) {
                echo "  ✅ PASS: Broadcast channels file exists\n";
            } else {
                echo "  ⚠️ WARNING: Broadcast channels file missing\n";
            }
            
        } else {
            echo "  ❌ MISSING: Broadcasting configuration not found\n";
            echo "  📝 RECOMMENDATION: Configure broadcasting for real-time mention updates\n";
            $this->errors[] = "Broadcasting configuration missing";
        }
        
        // Check if mention events exist
        $mentionEventFiles = [
            '/app/Events/MentionCreated.php',
            '/app/Events/NewMentionEvent.php',
            '/app/Events/UserMentioned.php'
        ];
        
        $eventFound = false;
        foreach ($mentionEventFiles as $file) {
            if (file_exists(__DIR__ . $file)) {
                $eventFound = true;
                echo "  ✅ PASS: Found mention event file: $file\n";
                break;
            }
        }
        
        if (!$eventFound) {
            echo "  ❌ MISSING: No mention broadcast events found\n";
            echo "  📝 RECOMMENDATION: Create mention broadcast events for real-time updates\n";
            $this->errors[] = "Missing mention broadcast events";
        }
    }
    
    private function generateReport()
    {
        echo "\n=== MENTION REAL-TIME & NOTIFICATION TEST REPORT ===\n";
        
        $totalIssues = count($this->errors);
        
        echo "\nSummary:\n";
        echo "- Issues Found: $totalIssues\n";
        
        if ($totalIssues > 0) {
            echo "\n=== ISSUES FOUND ===\n";
            foreach ($this->errors as $error) {
                echo "❌ $error\n";
            }
            
            echo "\n=== RECOMMENDATIONS FOR REAL-TIME MENTIONS ===\n";
            echo "1. Implement MentionNotification class to notify users when mentioned\n";
            echo "2. Add notification triggers in MentionService::storeMentions()\n";
            echo "3. Create MentionCreated broadcast event for real-time updates\n";
            echo "4. Configure broadcasting channels for mention notifications\n";
            echo "5. Add frontend WebSocket/Pusher integration for real-time mention display\n";
            echo "6. Implement mention badges/counters in user interfaces\n";
            echo "7. Add mention notification preferences for users\n";
        } else {
            echo "\n✅ All basic mention functionality is working!\n";
        }
        
        // Generate detailed JSON report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_issues' => $totalIssues,
            'errors' => $this->errors,
            'test_results' => $this->testResults
        ];
        
        file_put_contents(__DIR__ . '/mention_realtime_notification_test_report_' . time() . '.json', 
                         json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\n✅ Detailed report saved to mention_realtime_notification_test_report_*.json\n";
    }
}

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Run the test
$test = new MentionRealtimeNotificationTest();
$test->runTests();