<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Bye Match Issue\n";
echo "=======================\n\n";

try {
    // Test inserting a match with is_bye field
    $testMatch = [
        'event_id' => 17,
        'round' => 1,
        'bracket_position' => 999,
        'bracket_type' => 'main',
        'team1_id' => 1,
        'team2_id' => null,
        'team1_score' => 0,
        'team2_score' => 0,
        'status' => 'upcoming',
        'format' => 'BO3',
        'scheduled_at' => now(),
        'is_bye' => true,
        'created_at' => now(),
        'updated_at' => now()
    ];
    
    echo "Attempting to insert match with is_bye field...\n";
    DB::table('matches')->insert($testMatch);
    echo "✓ SUCCESS: Match with is_bye inserted\n";
    
    // Clean up
    DB::table('matches')->where('bracket_position', 999)->delete();
    echo "✓ Cleanup completed\n";
    
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    
    // Try without is_bye field
    echo "\nTrying without is_bye field...\n";
    
    try {
        $testMatchNoBye = [
            'event_id' => 17,
            'round' => 1,
            'bracket_position' => 999,
            'bracket_type' => 'main',
            'team1_id' => 1,
            'team2_id' => null,
            'team1_score' => 0,
            'team2_score' => 0,
            'status' => 'upcoming',
            'format' => 'BO3',
            'scheduled_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        DB::table('matches')->insert($testMatchNoBye);
        echo "✓ SUCCESS: Match without is_bye inserted\n";
        
        // Clean up
        DB::table('matches')->where('bracket_position', 999)->delete();
        echo "✓ Cleanup completed\n";
        
    } catch (Exception $e2) {
        echo "✗ FAILED: " . $e2->getMessage() . "\n";
    }
}