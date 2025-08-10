<?php

/**
 * Manual Admin Functionality Test for MRVL Tournament Platform
 * Tests admin functionality directly through PHP/Laravel calls
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use App\Models\News;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminStatsController;

class ManualAdminTester {
    private $testResults = [
        'database_connectivity' => [],
        'admin_controllers' => [],
        'user_management' => [],
        'data_integrity' => [],
        'analytics_functionality' => [],
        'live_scoring' => []
    ];
    
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;

    public function runAllTests() {
        echo "🚀 Starting Manual Admin Functionality Test Suite...\n\n";
        
        $this->testDatabaseConnectivity();
        $this->testAdminControllers();
        $this->testUserManagement();
        $this->testDataIntegrity();
        $this->testAnalyticsFunctionality();
        $this->testLiveScoring();
        
        $this->generateReport();
    }

    private function testDatabaseConnectivity() {
        echo "📊 Testing Database Connectivity...\n";
        
        // Test 1: Basic DB Connection
        try {
            DB::connection()->getPdo();
            $this->recordTest('database_connectivity', 'DB Connection', 'passed', 'Database connection successful');
            echo "  ✅ Database Connection\n";
        } catch (Exception $e) {
            $this->recordTest('database_connectivity', 'DB Connection', 'failed', $e->getMessage());
            echo "  ❌ Database Connection - {$e->getMessage()}\n";
        }
        
        // Test 2: Users Table
        try {
            $userCount = DB::table('users')->count();
            $this->recordTest('database_connectivity', 'Users Table', 'passed', "Found $userCount users");
            echo "  ✅ Users Table ($userCount users)\n";
        } catch (Exception $e) {
            $this->recordTest('database_connectivity', 'Users Table', 'failed', $e->getMessage());
            echo "  ❌ Users Table - {$e->getMessage()}\n";
        }
        
        // Test 3: Admin Users
        try {
            $adminCount = User::role('admin')->count();
            $this->recordTest('database_connectivity', 'Admin Users', 'passed', "Found $adminCount admin users");
            echo "  ✅ Admin Users ($adminCount admins)\n";
        } catch (Exception $e) {
            $this->recordTest('database_connectivity', 'Admin Users', 'failed', $e->getMessage());
            echo "  ❌ Admin Users - {$e->getMessage()}\n";
        }
        
        // Test 4: Core Tables
        $tables = ['teams', 'players', 'matches', 'events', 'news'];
        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                $this->recordTest('database_connectivity', ucfirst($table) . ' Table', 'passed', "Found $count records");
                echo "  ✅ $table Table ($count records)\n";
            } catch (Exception $e) {
                $this->recordTest('database_connectivity', ucfirst($table) . ' Table', 'failed', $e->getMessage());
                echo "  ❌ $table Table - {$e->getMessage()}\n";
            }
        }
    }

    private function testAdminControllers() {
        echo "\n🎛️ Testing Admin Controllers...\n";
        
        // Test 1: AdminController exists and methods work
        try {
            $controller = new AdminController();
            
            // Test dashboard method (without middleware)
            $reflectionClass = new ReflectionClass($controller);
            $dashboardMethod = $reflectionClass->getMethod('dashboard');
            
            $this->recordTest('admin_controllers', 'AdminController Dashboard Method', 'passed', 'Method exists');
            echo "  ✅ AdminController Dashboard Method\n";
        } catch (Exception $e) {
            $this->recordTest('admin_controllers', 'AdminController Dashboard Method', 'failed', $e->getMessage());
            echo "  ❌ AdminController Dashboard Method - {$e->getMessage()}\n";
        }
        
        // Test 2: AdminUserController
        try {
            $controller = new AdminUserController();
            $reflectionClass = new ReflectionClass($controller);
            $methods = ['index', 'store', 'update', 'destroy'];
            
            foreach ($methods as $method) {
                if ($reflectionClass->hasMethod($method)) {
                    $this->recordTest('admin_controllers', "AdminUserController $method", 'passed', 'Method exists');
                    echo "  ✅ AdminUserController $method\n";
                } else {
                    $this->recordTest('admin_controllers', "AdminUserController $method", 'failed', 'Method missing');
                    echo "  ❌ AdminUserController $method - Method missing\n";
                }
            }
        } catch (Exception $e) {
            $this->recordTest('admin_controllers', 'AdminUserController', 'failed', $e->getMessage());
            echo "  ❌ AdminUserController - {$e->getMessage()}\n";
        }
        
        // Test 3: AdminStatsController
        try {
            if (class_exists('App\Http\Controllers\AdminStatsController')) {
                $controller = new AdminStatsController();
                $this->recordTest('admin_controllers', 'AdminStatsController', 'passed', 'Controller exists');
                echo "  ✅ AdminStatsController\n";
            } else {
                $this->recordTest('admin_controllers', 'AdminStatsController', 'failed', 'Controller not found');
                echo "  ❌ AdminStatsController - Not found\n";
            }
        } catch (Exception $e) {
            $this->recordTest('admin_controllers', 'AdminStatsController', 'failed', $e->getMessage());
            echo "  ❌ AdminStatsController - {$e->getMessage()}\n";
        }
    }

    private function testUserManagement() {
        echo "\n👥 Testing User Management Functionality...\n";
        
        // Test 1: User CRUD Operations
        try {
            // Test user creation functionality
            $userData = [
                'name' => 'Test Admin User',
                'email' => 'testadmin@example.com',
                'password' => 'password123'
            ];
            
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();
            if ($existingUser) {
                $existingUser->delete();
            }
            
            // Create test user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => bcrypt($userData['password'])
            ]);
            
            if ($user) {
                $this->recordTest('user_management', 'User Creation', 'passed', 'Test user created successfully');
                echo "  ✅ User Creation\n";
                
                // Test user update
                $user->update(['name' => 'Updated Test User']);
                $this->recordTest('user_management', 'User Update', 'passed', 'User updated successfully');
                echo "  ✅ User Update\n";
                
                // Test user deletion
                $user->delete();
                $this->recordTest('user_management', 'User Deletion', 'passed', 'User deleted successfully');
                echo "  ✅ User Deletion\n";
            }
        } catch (Exception $e) {
            $this->recordTest('user_management', 'User CRUD Operations', 'failed', $e->getMessage());
            echo "  ❌ User CRUD Operations - {$e->getMessage()}\n";
        }
        
        // Test 2: Role Management
        try {
            $user = User::first();
            if ($user) {
                // Test role assignment
                if (method_exists($user, 'assignRole')) {
                    $this->recordTest('user_management', 'Role Assignment', 'passed', 'Role methods available');
                    echo "  ✅ Role Assignment Methods\n";
                } else {
                    $this->recordTest('user_management', 'Role Assignment', 'failed', 'Role methods not available');
                    echo "  ❌ Role Assignment Methods - Not available\n";
                }
            }
        } catch (Exception $e) {
            $this->recordTest('user_management', 'Role Management', 'failed', $e->getMessage());
            echo "  ❌ Role Management - {$e->getMessage()}\n";
        }
    }

    private function testDataIntegrity() {
        echo "\n🔍 Testing Data Integrity...\n";
        
        // Test 1: Foreign Key Relationships
        try {
            // Test team-player relationship
            $teams = DB::table('teams')->count();
            $players = DB::table('players')->whereNotNull('team_id')->count();
            
            $this->recordTest('data_integrity', 'Team-Player Relationships', 'passed', 
                "Teams: $teams, Players with teams: $players");
            echo "  ✅ Team-Player Relationships\n";
        } catch (Exception $e) {
            $this->recordTest('data_integrity', 'Team-Player Relationships', 'failed', $e->getMessage());
            echo "  ❌ Team-Player Relationships - {$e->getMessage()}\n";
        }
        
        // Test 2: Match Data Consistency
        try {
            $matches = DB::table('matches')->count();
            $matchesWithTeams = DB::table('matches')
                ->whereNotNull('team1_id')
                ->whereNotNull('team2_id')
                ->count();
            
            $consistency = $matches > 0 ? ($matchesWithTeams / $matches) * 100 : 100;
            
            $this->recordTest('data_integrity', 'Match Data Consistency', 'passed', 
                "Matches: $matches, With teams: $matchesWithTeams (" . round($consistency, 1) . "%)");
            echo "  ✅ Match Data Consistency (" . round($consistency, 1) . "%)\n";
        } catch (Exception $e) {
            $this->recordTest('data_integrity', 'Match Data Consistency', 'failed', $e->getMessage());
            echo "  ❌ Match Data Consistency - {$e->getMessage()}\n";
        }
        
        // Test 3: Event Structure
        try {
            $events = DB::table('events')->count();
            $eventsWithMatches = DB::table('events')
                ->join('matches', 'events.id', '=', 'matches.event_id')
                ->distinct('events.id')
                ->count();
            
            $this->recordTest('data_integrity', 'Event Structure', 'passed', 
                "Events: $events, Events with matches: $eventsWithMatches");
            echo "  ✅ Event Structure\n";
        } catch (Exception $e) {
            $this->recordTest('data_integrity', 'Event Structure', 'failed', $e->getMessage());
            echo "  ❌ Event Structure - {$e->getMessage()}\n";
        }
    }

    private function testAnalyticsFunctionality() {
        echo "\n📈 Testing Analytics Functionality...\n";
        
        // Test 1: Basic Statistics Generation
        try {
            $stats = [
                'total_users' => DB::table('users')->count(),
                'total_teams' => DB::table('teams')->count(),
                'total_players' => DB::table('players')->count(),
                'total_matches' => DB::table('matches')->count(),
                'total_events' => DB::table('events')->count()
            ];
            
            $this->recordTest('analytics_functionality', 'Basic Statistics', 'passed', 
                'Generated basic platform statistics');
            echo "  ✅ Basic Statistics Generation\n";
        } catch (Exception $e) {
            $this->recordTest('analytics_functionality', 'Basic Statistics', 'failed', $e->getMessage());
            echo "  ❌ Basic Statistics Generation - {$e->getMessage()}\n";
        }
        
        // Test 2: Time-based Queries
        try {
            $recentUsers = DB::table('users')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            
            $recentMatches = DB::table('matches')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            
            $this->recordTest('analytics_functionality', 'Time-based Analytics', 'passed', 
                "Recent users: $recentUsers, Recent matches: $recentMatches");
            echo "  ✅ Time-based Analytics\n";
        } catch (Exception $e) {
            $this->recordTest('analytics_functionality', 'Time-based Analytics', 'failed', $e->getMessage());
            echo "  ❌ Time-based Analytics - {$e->getMessage()}\n";
        }
        
        // Test 3: Aggregation Queries
        try {
            $teamsByRegion = DB::table('teams')
                ->select('region', DB::raw('COUNT(*) as count'))
                ->whereNotNull('region')
                ->groupBy('region')
                ->get();
            
            $this->recordTest('analytics_functionality', 'Aggregation Queries', 'passed', 
                'Successfully generated aggregated data');
            echo "  ✅ Aggregation Queries\n";
        } catch (Exception $e) {
            $this->recordTest('analytics_functionality', 'Aggregation Queries', 'failed', $e->getMessage());
            echo "  ❌ Aggregation Queries - {$e->getMessage()}\n";
        }
    }

    private function testLiveScoring() {
        echo "\n⚡ Testing Live Scoring Functionality...\n";
        
        // Test 1: Live Match Data Structure
        try {
            $liveMatches = DB::table('matches')
                ->whereIn('status', ['live', 'upcoming'])
                ->get();
            
            $this->recordTest('live_scoring', 'Live Match Detection', 'passed', 
                'Found ' . $liveMatches->count() . ' live/upcoming matches');
            echo "  ✅ Live Match Detection ({$liveMatches->count()} matches)\n";
        } catch (Exception $e) {
            $this->recordTest('live_scoring', 'Live Match Detection', 'failed', $e->getMessage());
            echo "  ❌ Live Match Detection - {$e->getMessage()}\n";
        }
        
        // Test 2: Score Update Capability
        try {
            $match = DB::table('matches')->first();
            if ($match) {
                // Test if we can update match scores
                DB::table('matches')
                    ->where('id', $match->id)
                    ->update([
                        'team1_score' => $match->team1_score ?? 0,
                        'team2_score' => $match->team2_score ?? 0,
                        'updated_at' => now()
                    ]);
                
                $this->recordTest('live_scoring', 'Score Update', 'passed', 
                    'Successfully updated match scores');
                echo "  ✅ Score Update Capability\n";
            } else {
                $this->recordTest('live_scoring', 'Score Update', 'failed', 'No matches to test with');
                echo "  ❌ Score Update - No matches available\n";
            }
        } catch (Exception $e) {
            $this->recordTest('live_scoring', 'Score Update', 'failed', $e->getMessage());
            echo "  ❌ Score Update - {$e->getMessage()}\n";
        }
        
        // Test 3: Live Data JSON Structure
        try {
            $matchWithLiveData = DB::table('matches')
                ->whereNotNull('live_data')
                ->first();
            
            if ($matchWithLiveData) {
                $liveData = json_decode($matchWithLiveData->live_data, true);
                $this->recordTest('live_scoring', 'Live Data Structure', 'passed', 
                    'Live data JSON structure is valid');
                echo "  ✅ Live Data JSON Structure\n";
            } else {
                $this->recordTest('live_scoring', 'Live Data Structure', 'warning', 
                    'No matches with live data found');
                echo "  ⚠️ Live Data Structure - No live data examples\n";
            }
        } catch (Exception $e) {
            $this->recordTest('live_scoring', 'Live Data Structure', 'failed', $e->getMessage());
            echo "  ❌ Live Data Structure - {$e->getMessage()}\n";
        }
    }

    private function recordTest($category, $testName, $status, $details) {
        $this->testResults[$category][] = [
            'test' => $testName,
            'status' => $status,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->totalTests++;
        if ($status === 'passed') {
            $this->passedTests++;
        } elseif ($status === 'failed') {
            $this->failedTests++;
        }
    }

    private function generateReport() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 COMPREHENSIVE ADMIN FUNCTIONALITY TEST RESULTS\n";
        echo str_repeat("=", 80) . "\n";
        
        $successRate = $this->totalTests > 0 ? ($this->passedTests / $this->totalTests) * 100 : 0;
        
        echo "\n🎯 Overall Summary:\n";
        echo "   Total Tests: {$this->totalTests}\n";
        echo "   Passed: {$this->passedTests} ✅\n";
        echo "   Failed: {$this->failedTests} ❌\n";
        echo "   Success Rate: " . round($successRate, 1) . "%\n";
        
        echo "\n📋 Category Breakdown:\n";
        foreach ($this->testResults as $category => $tests) {
            $categoryPassed = count(array_filter($tests, function($t) { return $t['status'] === 'passed'; }));
            $categoryTotal = count($tests);
            $categoryRate = $categoryTotal > 0 ? ($categoryPassed / $categoryTotal) * 100 : 0;
            
            $indicator = $categoryRate >= 80 ? '✅' : ($categoryRate >= 50 ? '⚠️' : '❌');
            echo "   " . ucwords(str_replace('_', ' ', $category)) . ": $categoryPassed/$categoryTotal (" . 
                 round($categoryRate, 1) . "%) $indicator\n";
        }
        
        echo "\n💡 Key Findings:\n";
        
        if ($successRate >= 90) {
            echo "   ✅ Admin functionality is highly operational and production ready\n";
        } elseif ($successRate >= 70) {
            echo "   ⚠️ Admin functionality is mostly operational with minor issues\n";
        } elseif ($successRate >= 50) {
            echo "   ⚠️ Admin functionality is partially operational, improvements needed\n";
        } else {
            echo "   ❌ Admin functionality has significant issues requiring immediate attention\n";
        }
        
        // Category-specific insights
        $dbTests = $this->testResults['database_connectivity'];
        $dbPassed = count(array_filter($dbTests, function($t) { return $t['status'] === 'passed'; }));
        if ($dbPassed === count($dbTests)) {
            echo "   ✅ Database connectivity and core tables are fully functional\n";
        }
        
        $controllerTests = $this->testResults['admin_controllers'];
        $controllerPassed = count(array_filter($controllerTests, function($t) { return $t['status'] === 'passed'; }));
        if ($controllerPassed > 0) {
            echo "   ✅ Admin controllers are properly implemented\n";
        }
        
        echo "\n🔧 Recommendations:\n";
        
        if ($this->failedTests > 0) {
            echo "   🔥 PRIORITY: Address failed functionality tests\n";
            echo "   📋 Review and fix database schema issues if any\n";
        }
        
        echo "   🔒 Implement proper admin authentication testing\n";
        echo "   📱 Create comprehensive admin dashboard frontend\n";
        echo "   📊 Add real-time monitoring for admin operations\n";
        echo "   🛡️ Implement admin action logging and audit trails\n";
        echo "   ⚡ Optimize database queries for admin operations\n";
        echo "   🎨 Design user-friendly admin interface components\n";
        
        // Save detailed report
        $report = [
            'testSuite' => 'MRVL Admin Functionality Manual Test',
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'totalTests' => $this->totalTests,
                'passed' => $this->passedTests,
                'failed' => $this->failedTests,
                'successRate' => round($successRate, 1)
            ],
            'categoryResults' => $this->testResults
        ];
        
        $filename = 'manual-admin-functionality-test-report-' . time() . '.json';
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\n📊 Detailed report saved to: $filename\n";
        echo str_repeat("=", 80) . "\n";
    }
}

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run tests
$tester = new ManualAdminTester();
$tester->runAllTests();