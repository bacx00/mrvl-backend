<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

// Create basic test without full Laravel bootstrap
echo "=== MRVL Tournament System Test ===" . PHP_EOL;

// Test database connectivity
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=mrvl_production',
        'mrvl_user',
        '1f9ER!ancao13$18jdw9ioqs',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Database connection successful" . PHP_EOL;
    
    // Test events table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Events table accessible: {$result['count']} events found" . PHP_EOL;
    
    // Test teams table  
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teams");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Teams table accessible: {$result['count']} teams found" . PHP_EOL;
    
    // Get a valid user ID
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ No users found - cannot test tournament creation" . PHP_EOL;
        return;
    }
    
    echo "✅ Found user ID: {$user['id']}" . PHP_EOL;
    
    // Test basic tournament creation capability
    $tournamentData = [
        'name' => 'Test Tournament - ' . date('Y-m-d H:i:s'),
        'slug' => 'test-tournament-' . time(),
        'description' => 'Test tournament for system validation',
        'type' => 'tournament',
        'format' => 'double_elimination',
        'region' => 'Global',
        'game_mode' => '6v6',
        'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        'end_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'max_teams' => 16,
        'status' => 'upcoming',
        'organizer_id' => $user['id'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $columns = implode(',', array_keys($tournamentData));
    $values = ':' . implode(', :', array_keys($tournamentData));
    
    $stmt = $pdo->prepare("INSERT INTO events ({$columns}) VALUES ({$values})");
    
    if ($stmt->execute($tournamentData)) {
        $tournamentId = $pdo->lastInsertId();
        echo "✅ Tournament created successfully: ID {$tournamentId}" . PHP_EOL;
        
        // Clean up test data
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$tournamentId]);
        echo "✅ Test data cleaned up" . PHP_EOL;
    } else {
        echo "❌ Failed to create tournament" . PHP_EOL;
    }
    
    echo "=== Tournament System Test Complete ===" . PHP_EOL;
    echo "✅ Basic tournament functionality confirmed" . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}