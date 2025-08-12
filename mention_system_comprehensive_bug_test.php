<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Services\MentionService;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Mention;

class MentionSystemBugTest
{
    private $mentionService;
    private $testResults = [];
    private $errors = [];
    
    public function __construct()
    {
        $this->mentionService = new MentionService();
    }
    
    public function runComprehensiveTest()
    {
        echo "\n=== COMPREHENSIVE MENTION SYSTEM BUG TEST ===\n";
        
        // Setup: Create all test entities first
        $this->createTestEntities();
        
        // Test 1: Backend mention parsing
        $this->testMentionParsing();
        
        // Test 2: Database mention recording
        $this->testMentionRecording();
        
        // Test 3: Check for missing MentionService injections
        $this->testControllerInjections();
        
        // Test 4: Test mention retrieval
        $this->testMentionRetrieval();
        
        // Test 5: Test mention URL generation
        $this->testMentionUrls();
        
        // Test 6: Test database constraints
        $this->testDatabaseConstraints();
        
        $this->generateReport();
    }
    
    private function createTestEntities()
    {
        echo "\n--- Setting Up Test Entities ---\n";
        
        try {
            // Create test users
            User::firstOrCreate(['name' => 'testuser'], [
                'email' => 'testuser@example.com',
                'password' => bcrypt('password'),
                'status' => 'active'
            ]);
            
            User::firstOrCreate(['name' => 'user_with_underscores'], [
                'email' => 'user_underscores@example.com',
                'password' => bcrypt('password'),
                'status' => 'active'
            ]);
            
            User::firstOrCreate(['name' => '123numbers'], [
                'email' => 'numbers@example.com',
                'password' => bcrypt('password'),
                'status' => 'active'
            ]);
            
            // Create test teams
            Team::firstOrCreate(['short_name' => 'TSM'], [
                'name' => 'Team SoloMid',
                'region' => 'NA'
            ]);
            
            Team::firstOrCreate(['short_name' => 'C9'], [
                'name' => 'Cloud9',
                'region' => 'NA'
            ]);
            
            Team::firstOrCreate(['short_name' => 'G2'], [
                'name' => 'G2 Esports',
                'region' => 'EU'
            ]);
            
            Team::firstOrCreate(['short_name' => 'FNC'], [
                'name' => 'Fnatic',
                'region' => 'EU'
            ]);
            
            // Create test players
            Player::firstOrCreate(['username' => 'shroud'], [
                'real_name' => 'Michael Grzesiek',
                'role' => 'DPS',
                'main_hero' => 'Tracer',
                'region' => 'NA',
                'country' => 'USA',
                'age' => 25
            ]);
            
            Player::firstOrCreate(['username' => 'ninja'], [
                'real_name' => 'Tyler Blevins',
                'role' => 'DPS',
                'main_hero' => 'Genji',
                'region' => 'NA',
                'country' => 'USA',
                'age' => 28
            ]);
            
            Player::firstOrCreate(['username' => 'caps'], [
                'real_name' => 'Rasmus Winther',
                'role' => 'DPS',
                'main_hero' => 'Sombra',
                'region' => 'EU',
                'country' => 'Denmark',
                'age' => 24
            ]);
            
            echo "✅ Test entities created successfully\n";
            
        } catch (Exception $e) {
            echo "❌ ERROR creating test entities: " . $e->getMessage() . "\n";
            $this->errors[] = "Entity creation error: " . $e->getMessage();
        }
    }

    private function testMentionParsing()
    {
        echo "\n--- Testing Mention Parsing Logic ---\n";
        
        $testCases = [
            // User mentions
            'Hey @testuser how are you?' => [
                ['type' => 'user', 'mention_text' => '@testuser']
            ],
            
            // Team mentions
            'Check out @team:TSM and @team:C9' => [
                ['type' => 'team', 'mention_text' => '@team:TSM'],
                ['type' => 'team', 'mention_text' => '@team:C9']
            ],
            
            // Player mentions
            'Great play by @player:shroud and @player:ninja' => [
                ['type' => 'player', 'mention_text' => '@player:shroud'],
                ['type' => 'player', 'mention_text' => '@player:ninja']
            ],
            
            // Mixed mentions
            'Tournament @team:G2 vs @team:FNC with @player:caps and @testuser watching' => [
                ['type' => 'team', 'mention_text' => '@team:G2'],
                ['type' => 'team', 'mention_text' => '@team:FNC'],
                ['type' => 'player', 'mention_text' => '@player:caps'],
                ['type' => 'user', 'mention_text' => '@testuser']
            ],
            
            // Edge cases
            'Email test@example.com should not be parsed' => [],
            '@user_with_underscores is valid' => [
                ['type' => 'user', 'mention_text' => '@user_with_underscores']
            ],
            '@123numbers should work' => [
                ['type' => 'user', 'mention_text' => '@123numbers']
            ]
        ];
        
        foreach ($testCases as $content => $expectedMentions) {
            try {
                $extractedMentions = $this->mentionService->extractMentions($content);
                
                $success = true;
                $issues = [];
                
                // Check if expected number of mentions were found
                if (count($extractedMentions) !== count($expectedMentions)) {
                    $success = false;
                    $issues[] = "Expected " . count($expectedMentions) . " mentions, got " . count($extractedMentions);
                }
                
                // Check each expected mention
                foreach ($expectedMentions as $expected) {
                    $found = false;
                    foreach ($extractedMentions as $extracted) {
                        if ($extracted['type'] === $expected['type'] && 
                            $extracted['mention_text'] === $expected['mention_text']) {
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        $success = false;
                        $issues[] = "Missing expected mention: " . $expected['mention_text'] . " (type: " . $expected['type'] . ")";
                    }
                }
                
                $this->testResults['parsing'][] = [
                    'content' => $content,
                    'success' => $success,
                    'expected_count' => count($expectedMentions),
                    'extracted_count' => count($extractedMentions),
                    'issues' => $issues,
                    'extracted' => $extractedMentions
                ];
                
                if ($success) {
                    echo "✅ PASS: $content\n";
                } else {
                    echo "❌ FAIL: $content\n";
                    foreach ($issues as $issue) {
                        echo "   - $issue\n";
                    }
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Mention parsing error for '$content': " . $e->getMessage();
                echo "❌ ERROR: $content - " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function testMentionRecording()
    {
        echo "\n--- Testing Mention Recording in Database ---\n";
        
        try {
            // Test content with mentions
            $testContent = "Great match! @testuser thanks for organizing, @team:TSM played well, and @player:shroud was amazing!";
            
            // Clear existing mentions for clean test
            Mention::where('mentionable_type', 'test')->delete();
            
            // Store mentions
            $mentionCount = $this->mentionService->storeMentions($testContent, 'test', 1);
            
            if ($mentionCount > 0) {
                echo "✅ PASS: Stored $mentionCount mentions\n";
                
                // Verify mentions were stored correctly
                $storedMentions = Mention::where('mentionable_type', 'test')
                    ->where('mentionable_id', 1)
                    ->get();
                
                foreach ($storedMentions as $mention) {
                    echo "   - Stored: {$mention->mention_text} (type: {$mention->mentioned_type}, id: {$mention->mentioned_id})\n";
                }
                
                $this->testResults['recording'] = [
                    'success' => true,
                    'mentions_stored' => $mentionCount,
                    'details' => $storedMentions->toArray()
                ];
            } else {
                echo "❌ FAIL: No mentions were stored\n";
                $this->testResults['recording'] = [
                    'success' => false,
                    'error' => 'No mentions stored'
                ];
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Mention recording error: " . $e->getMessage();
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function testControllerInjections()
    {
        echo "\n--- Testing Controller MentionService Injections ---\n";
        
        $controllersToCheck = [
            '/app/Http/Controllers/ForumController.php',
            '/app/Http/Controllers/NewsController.php',
            '/app/Http/Controllers/MatchController.php',
            '/app/Http/Controllers/Admin/AdminNewsController.php'
        ];
        
        foreach ($controllersToCheck as $controllerPath) {
            $fullPath = __DIR__ . $controllerPath;
            
            if (!file_exists($fullPath)) {
                echo "❌ MISSING: $controllerPath\n";
                continue;
            }
            
            $content = file_get_contents($fullPath);
            
            // Check for MentionService import
            $hasMentionServiceImport = strpos($content, 'use App\Services\MentionService') !== false;
            
            // Check for MentionService injection in constructor
            $hasMentionServiceInjection = strpos($content, 'MentionService $mentionService') !== false;
            
            // Check for MentionService property
            $hasMentionServiceProperty = strpos($content, '$mentionService') !== false;
            
            $issues = [];
            
            if (!$hasMentionServiceImport) {
                $issues[] = "Missing MentionService import";
            }
            
            if (!$hasMentionServiceInjection && $hasMentionServiceProperty) {
                $issues[] = "Uses MentionService but missing proper constructor injection";
            }
            
            if (empty($issues)) {
                echo "✅ PASS: $controllerPath\n";
            } else {
                echo "❌ FAIL: $controllerPath\n";
                foreach ($issues as $issue) {
                    echo "   - $issue\n";
                }
                $this->errors[] = "Controller injection issue in $controllerPath: " . implode(', ', $issues);
            }
        }
    }
    
    private function testMentionRetrieval()
    {
        echo "\n--- Testing Mention Retrieval ---\n";
        
        try {
            // Test getting mentions for content
            $mentions = $this->mentionService->getMentionsForContent('test', 1);
            
            if (is_array($mentions) && count($mentions) > 0) {
                echo "✅ PASS: Retrieved " . count($mentions) . " mentions\n";
                foreach ($mentions as $mention) {
                    echo "   - {$mention['mention_text']} -> {$mention['url']}\n";
                }
            } else {
                echo "⚠️ WARNING: No mentions retrieved (may be expected if no test data)\n";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Mention retrieval error: " . $e->getMessage();
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function testMentionUrls()
    {
        echo "\n--- Testing Mention URL Generation ---\n";
        
        $testMentions = [
            ['type' => 'user', 'id' => 1],
            ['type' => 'team', 'id' => 1],
            ['type' => 'player', 'id' => 1],
        ];
        
        foreach ($testMentions as $mention) {
            try {
                $mentionModel = new Mention();
                $mentionModel->mentioned_type = $mention['type'];
                $mentionModel->mentioned_id = $mention['id'];
                
                $url = $mentionModel->getMentionedUrl();
                $expectedUrl = "/{$mention['type']}s/{$mention['id']}";
                
                if ($url === $expectedUrl) {
                    echo "✅ PASS: {$mention['type']} URL: $url\n";
                } else {
                    echo "❌ FAIL: {$mention['type']} URL. Expected: $expectedUrl, Got: $url\n";
                    $this->errors[] = "URL generation error for {$mention['type']}";
                }
                
            } catch (Exception $e) {
                $this->errors[] = "URL generation error for {$mention['type']}: " . $e->getMessage();
                echo "❌ ERROR: {$mention['type']} - " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function testDatabaseConstraints()
    {
        echo "\n--- Testing Database Constraints ---\n";
        
        try {
            // Check if mentions table exists
            $tableExists = DB::getSchemaBuilder()->hasTable('mentions');
            
            if ($tableExists) {
                echo "✅ PASS: Mentions table exists\n";
                
                // Check table structure
                $columns = DB::getSchemaBuilder()->getColumnListing('mentions');
                $requiredColumns = [
                    'id', 'mentionable_type', 'mentionable_id', 'mentioned_type', 
                    'mentioned_id', 'mention_text', 'context', 'position_start', 
                    'position_end', 'mentioned_by', 'mentioned_at', 'is_active', 
                    'metadata', 'created_at', 'updated_at'
                ];
                
                $missingColumns = array_diff($requiredColumns, $columns);
                
                if (empty($missingColumns)) {
                    echo "✅ PASS: All required columns present\n";
                } else {
                    echo "❌ FAIL: Missing columns: " . implode(', ', $missingColumns) . "\n";
                    $this->errors[] = "Missing database columns: " . implode(', ', $missingColumns);
                }
                
            } else {
                echo "❌ FAIL: Mentions table does not exist\n";
                $this->errors[] = "Mentions table missing";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Database constraint test error: " . $e->getMessage();
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function generateReport()
    {
        echo "\n=== MENTION SYSTEM BUG TEST REPORT ===\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        // Count parsing tests
        if (isset($this->testResults['parsing'])) {
            foreach ($this->testResults['parsing'] as $result) {
                $totalTests++;
                if ($result['success']) $passedTests++;
            }
        }
        
        // Count other tests
        if (isset($this->testResults['recording']) && $this->testResults['recording']['success']) {
            $passedTests++;
        }
        $totalTests++;
        
        echo "\nTest Summary:\n";
        echo "- Total Tests: $totalTests\n";
        echo "- Passed: $passedTests\n";
        echo "- Failed: " . ($totalTests - $passedTests) . "\n";
        echo "- Errors Found: " . count($this->errors) . "\n";
        
        if (!empty($this->errors)) {
            echo "\n=== CRITICAL ISSUES FOUND ===\n";
            foreach ($this->errors as $error) {
                echo "❌ $error\n";
            }
        }
        
        // Generate detailed JSON report
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_tests' => $totalTests,
                'passed_tests' => $passedTests,
                'failed_tests' => $totalTests - $passedTests,
                'error_count' => count($this->errors)
            ],
            'test_results' => $this->testResults,
            'errors' => $this->errors,
            'recommendations' => $this->generateRecommendations()
        ];
        
        file_put_contents(__DIR__ . '/mention_system_bug_test_report_' . time() . '.json', 
                         json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\n✅ Detailed report saved to mention_system_bug_test_report_*.json\n";
    }
    
    private function generateRecommendations()
    {
        $recommendations = [];
        
        if (in_array('Controller injection issue in /app/Http/Controllers/MatchController.php: Uses MentionService but missing proper constructor injection', $this->errors)) {
            $recommendations[] = "Fix MatchController to properly inject MentionService in constructor";
        }
        
        if (strpos(implode(' ', $this->errors), 'Missing database columns') !== false) {
            $recommendations[] = "Run database migrations to ensure mentions table has all required columns";
        }
        
        if (strpos(implode(' ', $this->errors), 'Mentions table missing') !== false) {
            $recommendations[] = "Run php artisan migrate to create mentions table";
        }
        
        $recommendations[] = "Implement notification system for new mentions";
        $recommendations[] = "Add real-time updates when mentions are created";
        $recommendations[] = "Test mention functionality in all contexts (forums, news, matches)";
        
        return $recommendations;
    }
}

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Run the test
$test = new MentionSystemBugTest();
$test->runComprehensiveTest();