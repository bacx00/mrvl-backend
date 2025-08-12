<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Team;
use App\Models\Player;
use App\Models\Mention;

echo "=== MENTION SYSTEM OPTIMIZATION TEST ===\n\n";

// Test 1: Check new columns exist
echo "1. CHECKING NEW COLUMNS:\n";
$usersHasMentionCount = Schema::hasColumn('users', 'mention_count');
$teamsHasMentionCount = Schema::hasColumn('teams', 'mention_count');
$playersHasMentionCount = Schema::hasColumn('players', 'mention_count');

echo "Users mention_count column: " . ($usersHasMentionCount ? 'EXISTS' : 'MISSING') . "\n";
echo "Teams mention_count column: " . ($teamsHasMentionCount ? 'EXISTS' : 'MISSING') . "\n";
echo "Players mention_count column: " . ($playersHasMentionCount ? 'EXISTS' : 'MISSING') . "\n\n";

// Test 2: Check optimized indexes
echo "2. CHECKING OPTIMIZED INDEXES:\n";
$indexes = DB::select("SHOW INDEX FROM mentions WHERE Key_name LIKE 'idx_mentions_%'");
foreach ($indexes as $index) {
    echo "Index: {$index->Key_name}, Column: {$index->Column_name}\n";
}
echo "\n";

// Test 3: Check stored procedures
echo "3. CHECKING STORED PROCEDURES:\n";
$procedures = DB::select("SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name LIKE '%Mention%'");
foreach ($procedures as $proc) {
    echo "Procedure: {$proc->Name}\n";
}
echo "\n";

// Test 4: Check triggers
echo "4. CHECKING TRIGGERS:\n";
$triggers = DB::select("SHOW TRIGGERS WHERE `Trigger` LIKE 'tr_mentions_%'");
foreach ($triggers as $trigger) {
    echo "Trigger: {$trigger->Trigger}, Event: {$trigger->Event}\n";
}
echo "\n";

// Test 5: Performance test queries
echo "5. PERFORMANCE TESTS:\n";

// Test optimized query for user mentions
$start = microtime(true);
$userMentions = Mention::where('mentioned_type', 'App\Models\User')
    ->where('is_active', true)
    ->orderBy('mentioned_at', 'desc')
    ->limit(10)
    ->get();
$userQueryTime = (microtime(true) - $start) * 1000;
echo "User mentions query: {$userQueryTime}ms\n";

// Test optimized query for team mentions
$start = microtime(true);
$teamMentions = Mention::where('mentioned_type', 'App\Models\Team')
    ->where('is_active', true)
    ->orderBy('mentioned_at', 'desc')
    ->limit(10)
    ->get();
$teamQueryTime = (microtime(true) - $start) * 1000;
echo "Team mentions query: {$teamQueryTime}ms\n";

// Test optimized query for player mentions  
$start = microtime(true);
$playerMentions = Mention::where('mentioned_type', 'App\Models\Player')
    ->where('is_active', true)
    ->orderBy('mentioned_at', 'desc')
    ->limit(10)
    ->get();
$playerQueryTime = (microtime(true) - $start) * 1000;
echo "Player mentions query: {$playerQueryTime}ms\n";

// Test mention count queries
$start = microtime(true);
$mentionCounts = DB::table('mentions')
    ->select('mentioned_type', DB::raw('COUNT(*) as count'))
    ->where('is_active', true)
    ->groupBy('mentioned_type')
    ->get();
$countQueryTime = (microtime(true) - $start) * 1000;
echo "Mention counts aggregation: {$countQueryTime}ms\n\n";

// Test 6: Model methods
echo "6. TESTING MODEL METHODS:\n";

// Test User model
$user = User::first();
if ($user) {
    echo "User {$user->name}:\n";
    echo "  - Mention count (method): " . $user->getMentionCount() . "\n";
    echo "  - Mention count (column): " . ($user->mention_count ?? 'NULL') . "\n";
    
    $recentMentions = $user->getRecentMentions(5);
    echo "  - Recent mentions: " . $recentMentions->count() . "\n";
}

// Test Team model
$team = Team::first();
if ($team) {
    echo "Team {$team->name}:\n";
    echo "  - Mention count (method): " . $team->getMentionCount() . "\n";
    echo "  - Mention count (column): " . ($team->mention_count ?? 'NULL') . "\n";
}

// Test Player model
$player = Player::first();
if ($player) {
    echo "Player {$player->name}:\n";
    echo "  - Mention count (method): " . $player->getMentionCount() . "\n";
    echo "  - Mention count (column): " . ($player->mention_count ?? 'NULL') . "\n";
}
echo "\n";

// Test 7: Database integrity
echo "7. DATABASE INTEGRITY CHECK:\n";
try {
    $result = DB::select('CALL ValidateMentionIntegrity()');
    echo "Integrity issues found: " . ($result[0]->issues_found ?? 'N/A') . "\n";
} catch (Exception $e) {
    echo "Integrity check failed: " . $e->getMessage() . "\n";
}

// Test 8: Performance monitoring view
echo "\n8. PERFORMANCE MONITORING:\n";
try {
    $stats = DB::select('SELECT * FROM mention_performance_stats');
    foreach ($stats as $stat) {
        echo "Entity: {$stat->entity_type}\n";
        echo "  - Total entities: {$stat->total_entities}\n";
        echo "  - Total mentions: {$stat->total_mentions}\n";
        echo "  - Avg mentions/entity: " . round($stat->avg_mentions_per_entity, 2) . "\n";
        echo "  - Max mentions: {$stat->max_mentions}\n";
        echo "  - Entities with mentions: {$stat->entities_with_mentions}\n\n";
    }
} catch (Exception $e) {
    echo "Performance stats failed: " . $e->getMessage() . "\n";
}

echo "=== OPTIMIZATION TEST COMPLETE ===\n";