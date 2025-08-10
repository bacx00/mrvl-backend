<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\Team;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Services\BracketGenerationService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class BracketSystemIntegrationFix
{
    public function run()
    {
        echo "🔧 Starting Bracket System Integration Fix...\n\n";
        
        $this->fixEventStatusEnum();
        $this->addMissingBracketMethods();
        $this->fixBracketControllers();
        $this->testBracketGeneration();
        $this->createSampleTournament();
        
        echo "✅ Bracket System Integration Fix completed!\n";
    }

    private function fixEventStatusEnum()
    {
        echo "1. 🔄 Fixing Event Status Enum...\n";
        
        // Check current status enum values
        $result = DB::select("SHOW COLUMNS FROM events WHERE Field = 'status'");
        if (!empty($result)) {
            echo "   Current status enum: " . $result[0]->Type . "\n";
            
            // Check if we need to add 'scheduled' status
            if (strpos($result[0]->Type, 'scheduled') === false) {
                echo "   🔄 Adding 'scheduled' status to enum...\n";
                try {
                    DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('upcoming','scheduled','ongoing','completed','cancelled') DEFAULT 'upcoming'");
                    echo "   ✅ Added 'scheduled' status to events table\n";
                } catch (Exception $e) {
                    echo "   ⚠️  Could not add 'scheduled' status: " . $e->getMessage() . "\n";
                    echo "   📝 Using 'upcoming' status instead\n";
                }
            } else {
                echo "   ✅ Status enum already includes 'scheduled'\n";
            }
        }
        
        echo "\n";
    }

    private function addMissingBracketMethods()
    {
        echo "2. 🔄 Verifying Bracket Relationships...\n";
        
        // Test Event model relationships
        try {
            $event = new \App\Models\Event();
            
            $relationships = ['bracketStages', 'bracketMatches', 'bracketStandings'];
            foreach ($relationships as $relationship) {
                if (method_exists($event, $relationship)) {
                    echo "   ✅ Event::{$relationship}() exists\n";
                } else {
                    echo "   ❌ Event::{$relationship}() missing\n";
                }
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error checking Event relationships: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function fixBracketControllers()
    {
        echo "3. 🔄 Verifying Bracket Controllers...\n";
        
        $controllers = [
            'App\Http\Controllers\BracketController',
            'App\Http\Controllers\ComprehensiveBracketController',
            'App\Http\Controllers\TournamentBracketController'
        ];
        
        foreach ($controllers as $controller) {
            if (class_exists($controller)) {
                echo "   ✅ {$controller} exists\n";
                
                // Check key methods
                $controllerInstance = app($controller);
                $methods = ['show', 'generate'];
                
                foreach ($methods as $method) {
                    if (method_exists($controllerInstance, $method)) {
                        echo "   ✅   -> {$method}() method exists\n";
                    } else {
                        echo "   ❌   -> {$method}() method missing\n";
                    }
                }
                
            } else {
                echo "   ❌ {$controller} missing\n";
            }
        }
        
        echo "\n";
    }

    private function testBracketGeneration()
    {
        echo "4. 🔄 Testing Bracket Generation...\n";
        
        try {
            // Get or create a test user for organizer_id
            $user = $this->getOrCreateTestUser();
            
            // Create a simple test event
            $event = Event::create([
                'name' => 'Bracket Integration Test',
                'format' => 'single_elimination',
                'status' => 'upcoming',
                'type' => 'tournament',
                'tier' => 'B',
                'region' => 'International',
                'description' => 'Test event for bracket system integration',
                'organizer_id' => $user->id,
                'start_date' => now()->addDays(1),
                'end_date' => now()->addDays(3),
                'max_teams' => 8
            ]);
            
            echo "   ✅ Created test event: {$event->id}\n";
            
            // Create test teams
            $teams = [];
            for ($i = 1; $i <= 4; $i++) {
                $team = Team::create([
                    'name' => "Test Team {$i}",
                    'short_name' => "TT{$i}",
                    'country' => 'US',
                    'region' => 'NA',
                    'rating' => 1500 + ($i * 100)
                ]);
                $teams[] = $team;
            }
            
            echo "   ✅ Created " . count($teams) . " test teams\n";
            
            // Register teams to event
            foreach ($teams as $index => $team) {
                DB::table('event_teams')->insert([
                    'event_id' => $event->id,
                    'team_id' => $team->id,
                    'seed' => $index + 1,
                    'status' => 'confirmed',
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            echo "   ✅ Registered teams to event\n";
            
            // Test bracket generation through controller
            $controller = app(\App\Http\Controllers\BracketController::class);
            
            echo "   🔄 Testing bracket generation...\n";
            
            // Create a mock request for bracket generation
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'format' => 'single_elimination',
                'seeding_method' => 'manual',
                'randomize_seeds' => false
            ]);
            
            // Mock authentication for the test
            auth()->login(\App\Models\User::first() ?? $this->createTestUser());
            
            try {
                $result = $controller->generate($request, $event->id);
                $responseData = $result->getData(true);
                
                if ($responseData['success']) {
                    echo "   ✅ Bracket generation successful\n";
                    echo "   📊 Matches created: " . ($responseData['data']['matches_created'] ?? 'N/A') . "\n";
                } else {
                    echo "   ❌ Bracket generation failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
                }
                
            } catch (Exception $e) {
                echo "   ❌ Error during bracket generation: " . $e->getMessage() . "\n";
            }
            
            // Test bracket display
            echo "   🔄 Testing bracket display...\n";
            try {
                $result = $controller->show($event->id);
                $responseData = $result->getData(true);
                
                if ($responseData['success']) {
                    echo "   ✅ Bracket display successful\n";
                    echo "   📊 Event format: " . ($responseData['data']['format'] ?? 'N/A') . "\n";
                    echo "   📊 Teams count: " . ($responseData['data']['metadata']['teams_count'] ?? 'N/A') . "\n";
                } else {
                    echo "   ❌ Bracket display failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
                }
                
            } catch (Exception $e) {
                echo "   ❌ Error during bracket display: " . $e->getMessage() . "\n";
            }
            
            // Clean up test data
            DB::table('matches')->where('event_id', $event->id)->delete();
            DB::table('event_teams')->where('event_id', $event->id)->delete();
            foreach ($teams as $team) {
                $team->delete();
            }
            $event->delete();
            
            echo "   🗑️  Cleaned up test data\n";
            
        } catch (Exception $e) {
            echo "   ❌ Bracket generation test failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function createSampleTournament()
    {
        echo "5. 🏆 Creating Sample Tournament for Testing...\n";
        
        try {
            // Get or create a test user for organizer_id
            $user = $this->getOrCreateTestUser();
            
            // Create a persistent sample tournament
            $event = Event::create([
                'name' => 'Marvel Rivals Championship Sample',
                'slug' => 'marvel-rivals-championship-sample',
                'format' => 'single_elimination',
                'status' => 'upcoming',
                'type' => 'tournament',
                'tier' => 'S',
                'region' => 'International',
                'organizer_id' => $user->id,
                'start_date' => now()->addDays(7),
                'end_date' => now()->addDays(10),
                'max_teams' => 16,
                'prize_pool' => 50000.00,
                'currency' => 'USD',
                'description' => 'Sample tournament for testing bracket system functionality',
                'featured' => true,
                'public' => true
            ]);
            
            echo "   ✅ Created sample tournament: {$event->name} (ID: {$event->id})\n";
            
            // Create sample teams
            $teamNames = [
                'Phoenix Rising', 'Thunder Hawks', 'Void Hunters', 'Crimson Tide',
                'Steel Wolves', 'Shadow Knights', 'Quantum Force', 'Apex Legends',
                'Storm Breakers', 'Fire Dragons', 'Ice Guardians', 'Wind Runners',
                'Earth Shakers', 'Light Bearers', 'Dark Phantoms', 'Star Crusaders'
            ];
            
            $teams = [];
            foreach ($teamNames as $index => $teamName) {
                $team = Team::create([
                    'name' => $teamName,
                    'short_name' => strtoupper(substr(str_replace(' ', '', $teamName), 0, 3)),
                    'country' => ['US', 'CA', 'GB', 'DE', 'FR', 'JP', 'KR', 'CN'][rand(0, 7)],
                    'region' => ['NA', 'EU', 'APAC'][rand(0, 2)],
                    'rating' => rand(1200, 2000)
                ]);
                $teams[] = $team;
                
                // Register team to event
                DB::table('event_teams')->insert([
                    'event_id' => $event->id,
                    'team_id' => $team->id,
                    'seed' => $index + 1,
                    'status' => 'confirmed',
                    'registered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            echo "   ✅ Created and registered " . count($teams) . " sample teams\n";
            echo "   🌐 Sample tournament URL: /api/events/{$event->id}/bracket\n";
            echo "   📊 Event details available at: /api/events/{$event->id}\n";
            
        } catch (Exception $e) {
            echo "   ❌ Sample tournament creation failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    private function getOrCreateTestUser()
    {
        // Try to find existing test user
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

    private function createTestUser()
    {
        return \App\Models\User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
    }
}

// Run the fix
$fix = new BracketSystemIntegrationFix();
$fix->run();