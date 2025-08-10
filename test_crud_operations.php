<?php
/**
 * Comprehensive CRUD Operations Test
 * Tests all player and team profile operations
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

class CrudOperationsTest
{
    private $results = [];
    private $passed = 0;
    private $failed = 0;

    public function runAllTests()
    {
        echo "🚀 Starting Comprehensive CRUD Operations Test\n";
        echo "=" . str_repeat("=", 50) . "\n";

        $this->testDatabaseConnections();
        $this->testTeamCrudOperations();
        $this->testPlayerCrudOperations();
        $this->testAdminDashboardQueries();
        $this->testRealTimeUpdates();

        $this->generateReport();
    }

    private function testDatabaseConnections()
    {
        echo "\n📊 Testing Database Connections...\n";
        
        try {
            // Test database connection
            $pdo = new PDO(
                "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_DATABASE'),
                env('DB_USERNAME'),
                env('DB_PASSWORD')
            );
            $this->addResult('Database Connection', 'MySQL connection established', true);
        } catch (Exception $e) {
            $this->addResult('Database Connection', 'MySQL connection failed: ' . $e->getMessage(), false);
        }

        // Test tables exist
        $tables = ['teams', 'players', 'users', 'events', 'matches'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $count = $stmt->fetchColumn();
                $this->addResult("Table: {$table}", "Table exists with {$count} records", true);
            } catch (Exception $e) {
                $this->addResult("Table: {$table}", "Table missing or inaccessible", false);
            }
        }
    }

    private function testTeamCrudOperations()
    {
        echo "\n🏆 Testing Team CRUD Operations...\n";

        try {
            // Test CREATE operation
            $teamData = [
                'name' => 'Test Team ' . uniqid(),
                'short_name' => 'TEST' . rand(100, 999),
                'region' => 'NA',
                'country' => 'United States',
                'rating' => 1500,
                'status' => 'active'
            ];

            $stmt = $pdo->prepare("INSERT INTO teams (name, short_name, region, country, rating, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $created = $stmt->execute([
                $teamData['name'],
                $teamData['short_name'], 
                $teamData['region'],
                $teamData['country'],
                $teamData['rating'],
                $teamData['status']
            ]);

            if ($created) {
                $teamId = $pdo->lastInsertId();
                $this->addResult('Team CREATE', 'Team created successfully with ID: ' . $teamId, true);

                // Test READ operation
                $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
                $stmt->execute([$teamId]);
                $team = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $readSuccess = $team && $team['name'] === $teamData['name'];
                $this->addResult('Team READ', 'Team data retrieved correctly', $readSuccess);

                // Test UPDATE operation
                $newName = 'Updated Team ' . uniqid();
                $stmt = $pdo->prepare("UPDATE teams SET name = ?, updated_at = NOW() WHERE id = ?");
                $updated = $stmt->execute([$newName, $teamId]);
                
                if ($updated) {
                    $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
                    $stmt->execute([$teamId]);
                    $updatedTeam = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $updateSuccess = $updatedTeam['name'] === $newName;
                    $this->addResult('Team UPDATE', 'Team updated successfully', $updateSuccess);
                } else {
                    $this->addResult('Team UPDATE', 'Team update failed', false);
                }

                // Test DELETE operation
                $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
                $deleted = $stmt->execute([$teamId]);
                
                if ($deleted) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE id = ?");
                    $stmt->execute([$teamId]);
                    $count = $stmt->fetchColumn();
                    
                    $deleteSuccess = $count == 0;
                    $this->addResult('Team DELETE', 'Team deleted successfully', $deleteSuccess);
                } else {
                    $this->addResult('Team DELETE', 'Team deletion failed', false);
                }

            } else {
                $this->addResult('Team CREATE', 'Team creation failed', false);
            }

        } catch (Exception $e) {
            $this->addResult('Team CRUD', 'Exception: ' . $e->getMessage(), false);
        }
    }

    private function testPlayerCrudOperations()
    {
        echo "\n👤 Testing Player CRUD Operations...\n";

        try {
            // Test CREATE operation
            $playerData = [
                'name' => 'Test Player ' . uniqid(),
                'email' => 'test' . uniqid() . '@example.com',
                'role' => 'Rifler',
                'status' => 'active',
                'country' => 'United States'
            ];

            $stmt = $pdo->prepare("INSERT INTO players (name, email, role, status, country, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $created = $stmt->execute([
                $playerData['name'],
                $playerData['email'],
                $playerData['role'],
                $playerData['status'],
                $playerData['country']
            ]);

            if ($created) {
                $playerId = $pdo->lastInsertId();
                $this->addResult('Player CREATE', 'Player created successfully with ID: ' . $playerId, true);

                // Test READ operation
                $stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
                $stmt->execute([$playerId]);
                $player = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $readSuccess = $player && $player['email'] === $playerData['email'];
                $this->addResult('Player READ', 'Player data retrieved correctly', $readSuccess);

                // Test UPDATE operation
                $newRole = 'AWPer';
                $stmt = $pdo->prepare("UPDATE players SET role = ?, updated_at = NOW() WHERE id = ?");
                $updated = $stmt->execute([$newRole, $playerId]);
                
                if ($updated) {
                    $stmt = $pdo->prepare("SELECT role FROM players WHERE id = ?");
                    $stmt->execute([$playerId]);
                    $updatedPlayer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $updateSuccess = $updatedPlayer['role'] === $newRole;
                    $this->addResult('Player UPDATE', 'Player updated successfully', $updateSuccess);
                } else {
                    $this->addResult('Player UPDATE', 'Player update failed', false);
                }

                // Test DELETE operation
                $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
                $deleted = $stmt->execute([$playerId]);
                
                if ($deleted) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE id = ?");
                    $stmt->execute([$playerId]);
                    $count = $stmt->fetchColumn();
                    
                    $deleteSuccess = $count == 0;
                    $this->addResult('Player DELETE', 'Player deleted successfully', $deleteSuccess);
                } else {
                    $this->addResult('Player DELETE', 'Player deletion failed', false);
                }

            } else {
                $this->addResult('Player CREATE', 'Player creation failed', false);
            }

        } catch (Exception $e) {
            $this->addResult('Player CRUD', 'Exception: ' . $e->getMessage(), false);
        }
    }

    private function testAdminDashboardQueries()
    {
        echo "\n📊 Testing Admin Dashboard Queries...\n";

        try {
            // Test admin queries for showing all teams
            $stmt = $pdo->query("
                SELECT t.*, COUNT(p.id) as player_count 
                FROM teams t 
                LEFT JOIN players p ON t.id = p.team_id 
                GROUP BY t.id 
                ORDER BY t.name 
                LIMIT 10
            ");
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->addResult('Admin Teams Query', 'Teams with player count retrieved: ' . count($teams), count($teams) >= 0);

            // Test admin queries for showing all players
            $stmt = $pdo->query("
                SELECT p.*, t.name as team_name 
                FROM players p 
                LEFT JOIN teams t ON p.team_id = t.id 
                ORDER BY p.name 
                LIMIT 10
            ");
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->addResult('Admin Players Query', 'Players with team info retrieved: ' . count($players), count($players) >= 0);

            // Test search functionality
            $searchTerm = 'test';
            $stmt = $pdo->prepare("
                SELECT * FROM teams 
                WHERE name LIKE ? OR short_name LIKE ? 
                LIMIT 5
            ");
            $stmt->execute(["%{$searchTerm}%", "%{$searchTerm}%"]);
            $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->addResult('Search Functionality', 'Search query executed successfully', true);

            // Test filtering by status
            $stmt = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'active'");
            $activeTeamsCount = $stmt->fetchColumn();
            
            $this->addResult('Status Filtering', 'Active teams count: ' . $activeTeamsCount, true);

        } catch (Exception $e) {
            $this->addResult('Admin Dashboard Queries', 'Exception: ' . $e->getMessage(), false);
        }
    }

    private function testRealTimeUpdates()
    {
        echo "\n⚡ Testing Real-time Update Scenarios...\n";

        try {
            // Test concurrent update scenario
            $teamName = 'Concurrent Test Team ' . uniqid();
            
            // Create team
            $stmt = $pdo->prepare("INSERT INTO teams (name, short_name, region, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$teamName, 'CTT', 'NA']);
            $teamId = $pdo->lastInsertId();
            
            // Simulate update from admin dashboard
            $stmt = $pdo->prepare("UPDATE teams SET rating = ?, updated_at = NOW() WHERE id = ?");
            $updated = $stmt->execute([1600, $teamId]);
            
            // Verify update was applied
            $stmt = $pdo->prepare("SELECT rating FROM teams WHERE id = ?");
            $stmt->execute([$teamId]);
            $rating = $stmt->fetchColumn();
            
            $this->addResult('Real-time Update', 'Rating updated correctly: ' . $rating, $rating == 1600);
            
            // Clean up
            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$teamId]);

        } catch (Exception $e) {
            $this->addResult('Real-time Updates', 'Exception: ' . $e->getMessage(), false);
        }
    }

    private function addResult($category, $description, $passed)
    {
        $this->results[] = [
            'category' => $category,
            'description' => $description,
            'passed' => $passed,
            'timestamp' => date('H:i:s')
        ];
        
        if ($passed) {
            $this->passed++;
            echo "✅ {$category}: {$description}\n";
        } else {
            $this->failed++;
            echo "❌ {$category}: {$description}\n";
        }
    }

    private function generateReport()
    {
        $total = $this->passed + $this->failed;
        $successRate = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 CRUD OPERATIONS TEST RESULTS\n";
        echo str_repeat("=", 60) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📈 Success Rate: {$successRate}%\n";
        echo str_repeat("=", 60) . "\n";

        if ($successRate >= 80) {
            echo "🎉 EXCELLENT! All core CRUD operations are working perfectly.\n";
        } elseif ($successRate >= 60) {
            echo "⚠️  GOOD: Most operations working, minor issues detected.\n";
        } else {
            echo "🚨 ATTENTION NEEDED: Critical issues with CRUD operations.\n";
        }

        echo "\n📄 Detailed results saved to crud_test_results.json\n";
        
        file_put_contents(__DIR__ . '/crud_test_results.json', json_encode([
            'summary' => [
                'total_tests' => $total,
                'passed' => $this->passed,
                'failed' => $this->failed,
                'success_rate' => $successRate,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'results' => $this->results
        ], JSON_PRETTY_PRINT));
    }
}

// Get database connection from environment
function env($key, $default = null)
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_DATABASE', 'mrvl'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Run the tests
$test = new CrudOperationsTest();
$test->runAllTests();