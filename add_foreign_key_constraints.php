<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔧 Adding Foreign Key Constraints for Profile Tables...\n\n";

function foreignKeyExists($table, $constraintName) {
    try {
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ? 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $constraintName]);
        return count($constraints) > 0;
    } catch (Exception $e) {
        return false;
    }
}

$foreignKeys = [
    [
        'table' => 'players',
        'constraint' => 'fk_players_team_id',
        'sql' => 'ALTER TABLE players ADD CONSTRAINT fk_players_team_id FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'description' => 'Player team relationship constraint'
    ],
    [
        'table' => 'player_team_history', 
        'constraint' => 'fk_player_team_history_player_id',
        'sql' => 'ALTER TABLE player_team_history ADD CONSTRAINT fk_player_team_history_player_id FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE ON UPDATE CASCADE',
        'description' => 'Player team history player relationship'
    ],
    [
        'table' => 'player_team_history',
        'constraint' => 'fk_player_team_history_from_team_id', 
        'sql' => 'ALTER TABLE player_team_history ADD CONSTRAINT fk_player_team_history_from_team_id FOREIGN KEY (from_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE',
        'description' => 'Player team history from team relationship'
    ],
    [
        'table' => 'player_team_history',
        'constraint' => 'fk_player_team_history_to_team_id',
        'sql' => 'ALTER TABLE player_team_history ADD CONSTRAINT fk_player_team_history_to_team_id FOREIGN KEY (to_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE', 
        'description' => 'Player team history to team relationship'
    ],
    [
        'table' => 'player_match_stats',
        'constraint' => 'fk_player_match_stats_player_id',
        'sql' => 'ALTER TABLE player_match_stats ADD CONSTRAINT fk_player_match_stats_player_id FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE ON UPDATE CASCADE',
        'description' => 'Player match stats player relationship'
    ]
];

$constraintsAdded = 0;
$constraintsSkipped = 0;
$constraintsFailed = 0;

foreach ($foreignKeys as $fk) {
    echo "Checking constraint: {$fk['constraint']}...";
    
    if (foreignKeyExists($fk['table'], $fk['constraint'])) {
        echo " ✅ Already exists\n";
        $constraintsSkipped++;
        continue;
    }
    
    try {
        DB::statement($fk['sql']);
        echo " 🔗 Added successfully - {$fk['description']}\n";
        $constraintsAdded++;
    } catch (Exception $e) {
        echo " ⚠️ Failed: " . $e->getMessage() . "\n";
        $constraintsFailed++;
    }
}

echo "\n📊 Foreign Key Constraints Summary:\n";
echo "   ✅ Constraints added: {$constraintsAdded}\n";
echo "   ⏭️ Constraints skipped (already exist): {$constraintsSkipped}\n";
echo "   ❌ Constraints failed: {$constraintsFailed}\n";

// Verify data integrity after adding constraints
echo "\n🔍 Verifying Data Integrity...\n";

$integrityChecks = [
    [
        'name' => 'Orphaned players (invalid team_id)',
        'sql' => 'SELECT COUNT(*) as count FROM players WHERE team_id IS NOT NULL AND team_id NOT IN (SELECT id FROM teams)',
        'expected' => 0
    ],
    [
        'name' => 'Invalid player references in team history',
        'sql' => 'SELECT COUNT(*) as count FROM player_team_history WHERE player_id NOT IN (SELECT id FROM players)',
        'expected' => 0
    ],
    [
        'name' => 'Invalid from_team_id in team history', 
        'sql' => 'SELECT COUNT(*) as count FROM player_team_history WHERE from_team_id IS NOT NULL AND from_team_id NOT IN (SELECT id FROM teams)',
        'expected' => 0
    ],
    [
        'name' => 'Invalid to_team_id in team history',
        'sql' => 'SELECT COUNT(*) as count FROM player_team_history WHERE to_team_id IS NOT NULL AND to_team_id NOT IN (SELECT id FROM teams)', 
        'expected' => 0
    ]
];

$allIntegrityChecksPassed = true;

foreach ($integrityChecks as $check) {
    try {
        $result = DB::select($check['sql']);
        $count = $result[0]->count;
        
        if ($count == $check['expected']) {
            echo "   ✅ {$check['name']}: {$count} (expected {$check['expected']})\n";
        } else {
            echo "   ❌ {$check['name']}: {$count} (expected {$check['expected']})\n";
            $allIntegrityChecksPassed = false;
        }
    } catch (Exception $e) {
        echo "   ⚠️ {$check['name']}: Query failed - " . $e->getMessage() . "\n";
        $allIntegrityChecksPassed = false;
    }
}

// Generate report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'constraints_added' => $constraintsAdded,
    'constraints_skipped' => $constraintsSkipped, 
    'constraints_failed' => $constraintsFailed,
    'data_integrity_passed' => $allIntegrityChecksPassed,
    'summary' => [
        'total_players' => DB::table('players')->count(),
        'total_teams' => DB::table('teams')->count(),
        'players_with_teams' => DB::table('players')->whereNotNull('team_id')->count(),
        'team_history_records' => DB::table('player_team_history')->count()
    ]
];

file_put_contents('foreign_key_constraints_report.json', json_encode($report, JSON_PRETTY_PRINT));

if ($allIntegrityChecksPassed) {
    echo "\n✅ All data integrity checks passed!\n";
} else {
    echo "\n⚠️ Some data integrity issues found - check report for details\n";
}

echo "📝 Report saved to foreign_key_constraints_report.json\n";