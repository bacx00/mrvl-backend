<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\MatchModel;
use App\Models\MatchMap;

echo "=== MATCH DATA STRUCTURE OPTIMIZATION VALIDATION ===\n\n";

// Check matches table structure
echo "1. CHECKING MATCHES TABLE STRUCTURE:\n";
$matchesColumns = DB::select("DESCRIBE matches");
$columnNames = array_column($matchesColumns, 'Field');

$requiredColumns = ['maps_won_team1', 'maps_won_team2', 'current_map_status'];
$deprecatedColumns = ['series_score_team1', 'series_score_team2', 'current_timer', 'live_timer'];

foreach ($requiredColumns as $column) {
    if (in_array($column, $columnNames)) {
        echo "  ✓ Required column '{$column}' exists\n";
    } else {
        echo "  ✗ Required column '{$column}' MISSING\n";
    }
}

foreach ($deprecatedColumns as $column) {
    if (!in_array($column, $columnNames)) {
        echo "  ✓ Deprecated column '{$column}' removed\n";
    } else {
        echo "  ⚠ Deprecated column '{$column}' still exists\n";
    }
}

// Check match_maps table
echo "\n2. CHECKING MATCH_MAPS TABLE STRUCTURE:\n";
try {
    $mapsColumns = DB::select("DESCRIBE match_maps");
    echo "  ✓ match_maps table exists\n";
    
    $mapColumnNames = array_column($mapsColumns, 'Field');
    $requiredMapColumns = ['capture_progress', 'team1_composition', 'team2_composition', 'hero_swaps'];
    
    foreach ($requiredMapColumns as $column) {
        if (in_array($column, $mapColumnNames)) {
            echo "  ✓ Required column '{$column}' exists\n";
        } else {
            echo "  ✗ Required column '{$column}' MISSING\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ match_maps table MISSING or inaccessible: " . $e->getMessage() . "\n";
}

// Check indexes
echo "\n3. CHECKING DATABASE INDEXES:\n";
try {
    $indexes = DB::select("SHOW INDEXES FROM matches");
    $indexNames = array_column($indexes, 'Key_name');
    
    $requiredIndexes = ['idx_matches_status_scheduled', 'idx_matches_event_status', 'idx_matches_teams', 'idx_matches_score'];
    
    foreach ($requiredIndexes as $index) {
        if (in_array($index, $indexNames)) {
            echo "  ✓ Performance index '{$index}' exists\n";
        } else {
            echo "  ✗ Performance index '{$index}' MISSING\n";
        }
    }
} catch (Exception $e) {
    echo "  ⚠ Could not check indexes: " . $e->getMessage() . "\n";
}

// Test data consistency
echo "\n4. CHECKING DATA CONSISTENCY:\n";
try {
    $matches = DB::table('matches')
        ->select(['id', 'team1_score', 'team2_score', 'maps_won_team1', 'maps_won_team2', 'status'])
        ->limit(10)
        ->get();
    
    echo "  Sample matches data:\n";
    foreach ($matches as $match) {
        $consistent = ($match->team1_score == $match->maps_won_team1) && ($match->team2_score == $match->maps_won_team2);
        $symbol = $consistent ? "✓" : "⚠";
        echo "    {$symbol} Match {$match->id}: team1_score={$match->team1_score} maps_won_team1={$match->maps_won_team1} | team2_score={$match->team2_score} maps_won_team2={$match->maps_won_team2}\n";
    }
    
    // Check for inconsistencies
    $inconsistent = DB::table('matches')
        ->whereRaw('team1_score != maps_won_team1 OR team2_score != maps_won_team2')
        ->count();
    
    if ($inconsistent === 0) {
        echo "  ✓ All matches have consistent scores\n";
    } else {
        echo "  ⚠ {$inconsistent} matches have inconsistent scores\n";
    }
    
} catch (Exception $e) {
    echo "  ✗ Could not check data consistency: " . $e->getMessage() . "\n";
}

// Test model functionality
echo "\n5. TESTING MODEL FUNCTIONALITY:\n";
try {
    $match = MatchModel::first();
    if ($match) {
        echo "  ✓ MatchModel can be loaded\n";
        
        // Test relationships
        try {
            $maps = $match->maps;
            if ($maps) {
                echo "  ✓ Match->maps relationship works (found " . $maps->count() . " maps)\n";
            } else {
                echo "  ⚠ Match->maps relationship returns null\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Match->maps relationship failed: " . $e->getMessage() . "\n";
        }
        
        // Test methods
        if (method_exists($match, 'updateSeriesScore')) {
            echo "  ✓ updateSeriesScore method exists\n";
        }
        
        if (method_exists($match, 'getCurrentLiveMap')) {
            echo "  ✓ getCurrentLiveMap method exists\n";
        }
        
        if (method_exists($match, 'getFormatDetailsAttribute')) {
            $details = $match->getFormatDetailsAttribute();
            echo "  ✓ getFormatDetailsAttribute method works - Win condition: {$details['win_condition']}\n";
        }
    } else {
        echo "  ⚠ No matches found to test\n";
    }
} catch (Exception $e) {
    echo "  ✗ Model functionality test failed: " . $e->getMessage() . "\n";
}

// Test MatchMap model
echo "\n6. TESTING MATCHMAP MODEL:\n";
try {
    // Check if table name is correct
    $mapModel = new MatchMap();
    $tableName = $mapModel->getTable();
    echo "  ✓ MatchMap uses table: {$tableName}\n";
    
    if (MatchMap::count() > 0) {
        $map = MatchMap::first();
        echo "  ✓ MatchMap can load data\n";
        
        // Test relationship
        $match = $map->match;
        if ($match) {
            echo "  ✓ MatchMap->match relationship works\n";
        }
    } else {
        echo "  ⚠ No match maps found to test\n";
    }
} catch (Exception $e) {
    echo "  ✗ MatchMap model test failed: " . $e->getMessage() . "\n";
}

// Performance test
echo "\n7. PERFORMANCE TEST:\n";
try {
    $startTime = microtime(true);
    
    $matches = MatchModel::with(['team1', 'team2', 'maps'])
        ->where('status', '!=', 'cancelled')
        ->limit(5)
        ->get();
    
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);
    
    echo "  ✓ Loaded 5 matches with relationships in {$duration}ms\n";
    
    if ($duration < 100) {
        echo "  ✓ Performance is GOOD (< 100ms)\n";
    } elseif ($duration < 500) {
        echo "  ⚠ Performance is ACCEPTABLE (< 500ms)\n";
    } else {
        echo "  ✗ Performance is POOR (>= 500ms)\n";
    }
    
} catch (Exception $e) {
    echo "  ✗ Performance test failed: " . $e->getMessage() . "\n";
}

echo "\n=== VALIDATION COMPLETE ===\n";

// Test the new API endpoints
echo "\n8. TESTING NEW API ENDPOINTS:\n";
try {
    // Get a sample match
    $match = MatchModel::first();
    if ($match) {
        $matchId = $match->id;
        echo "  Testing with match ID: {$matchId}\n";
        
        // Test the OptimizedMatchController
        $controller = new \App\Http\Controllers\OptimizedMatchController();
        
        // Test show method
        try {
            $response = $controller->show($matchId);
            $responseData = $response->getData(true);
            
            if ($responseData['success']) {
                echo "  ✓ OptimizedMatchController->show() works\n";
            } else {
                echo "  ✗ OptimizedMatchController->show() failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
            }
        } catch (Exception $e) {
            echo "  ✗ OptimizedMatchController->show() threw exception: " . $e->getMessage() . "\n";
        }
        
        // Test live data method
        try {
            $response = $controller->getLiveData($matchId);
            $responseData = $response->getData(true);
            
            if ($responseData['success']) {
                echo "  ✓ OptimizedMatchController->getLiveData() works\n";
            } else {
                echo "  ✗ OptimizedMatchController->getLiveData() failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
            }
        } catch (Exception $e) {
            echo "  ✗ OptimizedMatchController->getLiveData() threw exception: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ⚠ No matches available to test API endpoints\n";
    }
} catch (Exception $e) {
    echo "  ✗ API endpoint test failed: " . $e->getMessage() . "\n";
}

echo "\n✅ MATCH DATA STRUCTURE OPTIMIZATION VALIDATION COMPLETE!\n";