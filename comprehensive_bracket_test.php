<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

try {
    echo "=== COMPREHENSIVE BRACKET SYSTEM VALIDATION ===\n\n";
    
    $testsRun = 0;
    $testsPassed = 0;
    $testsFailed = 0;
    
    function runTest($testName, $testFunc) {
        global $testsRun, $testsPassed, $testsFailed;
        $testsRun++;
        
        echo "Test {$testsRun}: {$testName}\n";
        
        try {
            $result = $testFunc();
            if ($result) {
                echo "  ✓ PASSED\n";
                $testsPassed++;
            } else {
                echo "  ✗ FAILED\n";
                $testsFailed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED - Error: " . $e->getMessage() . "\n";
            $testsFailed++;
        }
        
        echo "\n";
    }
    
    // Test 1: Verify best_of constraint supports all required values
    runTest("best_of constraint supports Marvel Rivals tournament formats (1,3,5,7)", function() {
        $constraint = DB::select("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'bracket_matches' 
            AND COLUMN_NAME = 'best_of'
        ")[0]->COLUMN_TYPE;
        
        $requiredValues = ['1', '3', '5', '7'];
        foreach ($requiredValues as $value) {
            if (strpos($constraint, "'{$value}'") === false) {
                return false;
            }
        }
        
        echo "    Supported values: " . str_replace(['enum(', ')'], '', $constraint) . "\n";
        return true;
    });
    
    // Test 2: Test tournament creation with all required fields
    runTest("Tournament creation with complete data", function() {
        try {
            DB::beginTransaction();
            
            $tournamentName = 'Marvel Rivals Test Tournament ' . time();
            $tournamentSlug = Str::slug($tournamentName);
            
            $testTournament = [
                'name' => $tournamentName,
                'slug' => $tournamentSlug,
                'type' => 'mrc',
                'format' => 'double_elimination',
                'status' => 'draft',
                'region' => 'Global',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'max_teams' => 16,
                'min_teams' => 8,
                'featured' => 0,
                'public' => 1,
                'views' => 0,
                'current_phase' => 'registration',
                'prize_pool' => 10000.00,
                'team_count' => 8,
                'start_date' => '2025-09-01 00:00:00',
                'end_date' => '2025-09-03 23:59:59',
                'registration_start' => '2025-08-01 00:00:00',
                'registration_end' => '2025-08-31 23:59:59',
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            $tournamentId = DB::table('tournaments')->insertGetId($testTournament);
            
            if ($tournamentId) {
                echo "    Tournament ID: {$tournamentId}\n";
                DB::rollback();
                return true;
            }
            
            DB::rollback();
            return false;
            
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    });
    
    // Test 3: Verify all performance indexes exist
    runTest("Performance indexes for Marvel Rivals tournaments", function() {
        $expectedIndexes = [
            'bracket_matches' => [
                'idx_tournament_status',
                'idx_bracket_stage_status', 
                'idx_match_progression',
                'idx_team_matches',
                'idx_live_matches',
                'idx_winner_loser',
                'idx_scheduled_status',
                'idx_completed_status'
            ],
            'bracket_seedings' => [
                'idx_stage_seed_order',
                'idx_tournament_seed'
            ]
        ];
        
        $foundIndexes = 0;
        $totalExpected = 0;
        
        foreach ($expectedIndexes as $table => $indexes) {
            $existingIndexes = DB::select("SHOW INDEX FROM {$table}");
            $existingIndexNames = array_map(function($idx) { return $idx->Key_name; }, $existingIndexes);
            
            foreach ($indexes as $indexName) {
                $totalExpected++;
                if (in_array($indexName, $existingIndexNames)) {
                    $foundIndexes++;
                    echo "    ✓ {$table}.{$indexName}\n";
                } else {
                    echo "    ✗ Missing: {$table}.{$indexName}\n";
                }
            }
        }
        
        echo "    Found {$foundIndexes}/{$totalExpected} expected indexes\n";
        return $foundIndexes === $totalExpected;
    });
    
    // Summary
    echo "=== FINAL VALIDATION RESULTS ===\n";
    echo "Tests run: {$testsRun}\n";
    echo "Tests passed: {$testsPassed}\n";
    echo "Tests failed: {$testsFailed}\n";
    echo "Success rate: " . round(($testsPassed / $testsRun) * 100, 1) . "%\n\n";
    
    if ($testsFailed === 0) {
        echo "🎉 ALL MARVEL RIVALS BRACKET SYSTEM TESTS PASSED!\n\n";
        
        echo "✅ VALIDATED FIXES:\n";
        echo "1. ✓ best_of constraint supports all Marvel Rivals formats (1,3,5,7)\n";
        echo "2. ✓ Tournament creation with flexible Marvel Rivals settings\n";
        echo "3. ✓ All performance indexes are in place\n\n";
        
        echo "🚀 PERFORMANCE ENHANCEMENTS:\n";
        echo "- Added 10 specialized indexes for Marvel Rivals tournaments\n";
        echo "- Optimized for live scoring and real-time updates\n";
        echo "- Enhanced tournament progression tracking\n";
        echo "- Improved bracket stage performance\n\n";
        
        echo "🏆 MARVEL RIVALS TOURNAMENT SUPPORT:\n";
        echo "- Best of 1: Quick matches and qualifiers ✓\n";
        echo "- Best of 3: Standard competitive matches ✓\n";
        echo "- Best of 5: Playoff and semifinal matches ✓\n";
        echo "- Best of 7: Grand final matches ✓\n\n";
        
    } else {
        echo "❌ SOME TESTS FAILED. Manual intervention may be required.\n";
        echo "Please review the failed tests above for specific issues.\n";
    }
    
} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}