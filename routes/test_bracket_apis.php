<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\BracketController;
use App\Http\Controllers\ComprehensiveBracketController;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class BracketAPITest
{
    public function run()
    {
        echo "🔄 Testing Bracket API Endpoints...\n\n";
        
        $this->testBracketEndpoints();
        $this->testBracketGeneration();
        $this->testBracketFormats();
        
        echo "✅ Bracket API Test completed!\n";
    }

    private function testBracketEndpoints()
    {
        echo "1. 🌐 Testing Bracket Display Endpoints...\n";
        
        // Get the sample event we created earlier
        $sampleEvent = \App\Models\Event::where('name', 'like', 'Marvel Rivals Championship Sample%')
                                       ->first();
        
        if (!$sampleEvent) {
            echo "   ⚠️  No sample event found, creating one...\n";
            $sampleEvent = $this->createSampleEvent();
        }
        
        echo "   📊 Testing event: {$sampleEvent->name} (ID: {$sampleEvent->id})\n";
        
        try {
            // Test BracketController::show
            $controller = app(BracketController::class);
            $result = $controller->show($sampleEvent->id);
            $data = $result->getData(true);
            
            if ($data['success']) {
                echo "   ✅ BracketController::show() works\n";
                echo "     - Format: " . ($data['data']['format'] ?? 'N/A') . "\n";
                echo "     - Teams: " . ($data['data']['metadata']['total_teams'] ?? 'N/A') . "\n";
            } else {
                echo "   ❌ BracketController::show() failed: " . ($data['message'] ?? 'Unknown error') . "\n";
            }
            
            // Test ComprehensiveBracketController::show
            $comprehensiveController = app(ComprehensiveBracketController::class);
            $comprehensiveResult = $comprehensiveController->show($sampleEvent->id);
            $comprehensiveData = $comprehensiveResult->getData(true);
            
            if ($comprehensiveData['success']) {
                echo "   ✅ ComprehensiveBracketController::show() works\n";
                echo "     - Event format: " . ($comprehensiveData['data']['event']['format'] ?? 'N/A') . "\n";
                echo "     - Teams count: " . count($comprehensiveData['data']['teams'] ?? []) . "\n";
            } else {
                echo "   ❌ ComprehensiveBracketController::show() failed: " . ($comprehensiveData['message'] ?? 'Unknown error') . "\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error testing endpoints: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function testBracketGeneration()
    {
        echo "2. ⚙️  Testing Bracket Generation...\n";
        
        try {
            // Create a fresh test event
            $user = $this->getOrCreateTestUser();
            $testEvent = \App\Models\Event::create([
                'name' => 'Bracket Generation Test - ' . date('Y-m-d H:i:s'),
                'format' => 'single_elimination',
                'status' => 'upcoming',
                'type' => 'tournament',
                'tier' => 'B',
                'region' => 'International',
                'description' => 'Test event for bracket generation',
                'organizer_id' => $user->id,
                'start_date' => now()->addDays(1),
                'end_date' => now()->addDays(3),
                'max_teams' => 8
            ]);
            
            echo "   ✅ Created test event: {$testEvent->id}\n";
            
            // Create and register teams
            $teams = $this->createTestTeams(4);
            foreach ($teams as $index => $team) {
                \DB::table('event_teams')->insert([
                    'event_id' => $testEvent->id,
                    'team_id' => $team->id,
                    'seed' => $index + 1,
                    'status' => 'confirmed',
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            echo "   ✅ Registered " . count($teams) . " teams\n";
            
            // Test bracket generation without authentication (direct method call)
            $controller = app(BracketController::class);
            
            // Create a mock request
            $request = new Request();
            $request->merge([
                'format' => 'single_elimination',
                'seeding_method' => 'manual',
                'randomize_seeds' => false
            ]);
            
            // Skip authentication for testing
            try {
                $result = $controller->generate($request, $testEvent->id);
                $data = $result->getData(true);
                
                if ($data['success']) {
                    echo "   ✅ Bracket generation successful\n";
                    echo "     - Matches created: " . ($data['data']['matches_created'] ?? 'N/A') . "\n";
                    echo "     - Format: " . ($data['data']['format'] ?? 'N/A') . "\n";
                    
                    // Test bracket display after generation
                    $displayResult = $controller->show($testEvent->id);
                    $displayData = $displayResult->getData(true);
                    
                    if ($displayData['success']) {
                        echo "   ✅ Bracket display after generation successful\n";
                        echo "     - Current round: " . ($displayData['data']['metadata']['current_round'] ?? 'N/A') . "\n";
                        echo "     - Total matches: " . ($displayData['data']['metadata']['total_matches'] ?? 'N/A') . "\n";
                    }
                    
                } else {
                    echo "   ❌ Bracket generation failed: " . ($data['message'] ?? 'Unknown error') . "\n";
                }
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'manage-events') !== false) {
                    echo "   ⚠️  Skipping bracket generation test (authorization required)\n";
                    echo "     This is expected behavior for protected endpoints\n";
                } else {
                    echo "   ❌ Bracket generation error: " . $e->getMessage() . "\n";
                }
            }
            
            // Clean up
            \DB::table('matches')->where('event_id', $testEvent->id)->delete();
            \DB::table('event_teams')->where('event_id', $testEvent->id)->delete();
            foreach ($teams as $team) {
                $team->delete();
            }
            $testEvent->delete();
            
        } catch (Exception $e) {
            echo "   ❌ Test setup error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function testBracketFormats()
    {
        echo "3. 🏆 Testing Tournament Formats...\n";
        
        $formats = [
            'single_elimination' => 'Single Elimination',
            'double_elimination' => 'Double Elimination',
            'swiss' => 'Swiss System',
            'round_robin' => 'Round Robin'
        ];
        
        foreach ($formats as $formatKey => $formatName) {
            echo "   🎯 Testing format: {$formatName}\n";
            
            try {
                $user = $this->getOrCreateTestUser();
                $event = \App\Models\Event::create([
                    'name' => "Test {$formatName} - " . date('H:i:s'),
                    'format' => $formatKey,
                    'status' => 'upcoming',
                    'type' => 'tournament',
                    'tier' => 'B',
                    'region' => 'International',
                    'description' => "Test event for {$formatName}",
                    'organizer_id' => $user->id,
                    'start_date' => now()->addDays(1),
                    'end_date' => now()->addDays(3),
                    'max_teams' => 8
                ]);
                
                $teams = $this->createTestTeams(4);
                foreach ($teams as $index => $team) {
                    \DB::table('event_teams')->insert([
                        'event_id' => $event->id,
                        'team_id' => $team->id,
                        'seed' => $index + 1,
                        'status' => 'confirmed',
                        'registered_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                // Test bracket display for this format
                $controller = app(BracketController::class);
                $result = $controller->show($event->id);
                $data = $result->getData(true);
                
                if ($data['success']) {
                    echo "     ✅ Bracket display works for {$formatName}\n";
                    echo "       - Bracket type: " . ($data['data']['bracket']['type'] ?? 'N/A') . "\n";
                } else {
                    echo "     ❌ Bracket display failed for {$formatName}: " . ($data['message'] ?? 'Unknown error') . "\n";
                }
                
                // Clean up
                \DB::table('event_teams')->where('event_id', $event->id)->delete();
                foreach ($teams as $team) {
                    $team->delete();
                }
                $event->delete();
                
            } catch (Exception $e) {
                echo "     ❌ Format test error for {$formatName}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }

    private function createSampleEvent()
    {
        $user = $this->getOrCreateTestUser();
        
        return \App\Models\Event::create([
            'name' => 'Marvel Rivals Championship Sample',
            'format' => 'single_elimination',
            'status' => 'upcoming',
            'type' => 'tournament',
            'tier' => 'S',
            'region' => 'International',
            'description' => 'Sample tournament for API testing',
            'organizer_id' => $user->id,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(10),
            'max_teams' => 16
        ]);
    }

    private function getOrCreateTestUser()
    {
        $user = \App\Models\User::where('email', 'bracket-test-admin@example.com')->first();
        
        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'Bracket Test Admin',
                'email' => 'bracket-test-admin@example.com',
                'password' => bcrypt('password'),
                'role' => 'admin'
            ]);
        }
        
        return $user;
    }

    private function createTestTeams($count = 4)
    {
        $teams = [];
        $suffix = substr(uniqid(), -4);
        for ($i = 1; $i <= $count; $i++) {
            $teams[] = \App\Models\Team::create([
                'name' => "Test Team {$i} - " . uniqid(),
                'short_name' => "T{$suffix}{$i}",
                'country' => 'US',
                'region' => 'NA',
                'rating' => 1500 + ($i * 100)
            ]);
        }
        return $teams;
    }
}

// Run the test
$test = new BracketAPITest();
$test->run();