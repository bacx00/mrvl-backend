<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔧 Adding Database Profile Optimization Indexes...\n\n";

function indexExists($table, $indexName) {
    try {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    } catch (Exception $e) {
        return false;
    }
}

$indexesToAdd = [
    [
        'table' => 'players',
        'name' => 'idx_players_profile_fast',
        'sql' => 'CREATE INDEX idx_players_profile_fast ON players (team_id, role, rating DESC, id)',
        'description' => 'Player profile page optimization'
    ],
    [
        'table' => 'players', 
        'name' => 'idx_players_search_fast',
        'sql' => 'CREATE INDEX idx_players_search_fast ON players (name, username)',
        'description' => 'Player search optimization'
    ],
    [
        'table' => 'players',
        'name' => 'idx_players_country_region', 
        'sql' => 'CREATE INDEX idx_players_country_region ON players (country, region)',
        'description' => 'Player filtering by country/region'
    ],
    [
        'table' => 'teams',
        'name' => 'idx_teams_profile_fast',
        'sql' => 'CREATE INDEX idx_teams_profile_fast ON teams (region, rating DESC, wins DESC, id)',  
        'description' => 'Team profile page optimization'
    ],
    [
        'table' => 'teams',
        'name' => 'idx_teams_rankings_fast',
        'sql' => 'CREATE INDEX idx_teams_rankings_fast ON teams (region, elo_rating DESC, wins DESC)',
        'description' => 'Team rankings optimization'
    ],
    [
        'table' => 'teams', 
        'name' => 'idx_teams_country_region',
        'sql' => 'CREATE INDEX idx_teams_country_region ON teams (country, region)', 
        'description' => 'Team filtering by country/region'
    ],
    [
        'table' => 'player_team_history',
        'name' => 'idx_player_team_history_fast', 
        'sql' => 'CREATE INDEX idx_player_team_history_fast ON player_team_history (player_id, change_date DESC)',
        'description' => 'Player team history optimization'
    ],
    [
        'table' => 'player_match_stats',
        'name' => 'idx_player_match_stats_fast',
        'sql' => 'CREATE INDEX idx_player_match_stats_fast ON player_match_stats (player_id, created_at DESC)',
        'description' => 'Player match statistics optimization'
    ]
];

$indexesAdded = 0;
$indexesSkipped = 0;

foreach ($indexesToAdd as $index) {
    echo "Checking index: {$index['name']}...";
    
    if (indexExists($index['table'], $index['name'])) {
        echo " ✅ Already exists\n";
        $indexesSkipped++;
        continue;
    }
    
    try {
        DB::statement($index['sql']);
        echo " 📈 Added successfully - {$index['description']}\n";
        $indexesAdded++;
    } catch (Exception $e) {
        echo " ⚠️ Failed: " . $e->getMessage() . "\n";
    }
}

echo "\n📊 Summary:\n";
echo "   ✅ Indexes added: {$indexesAdded}\n";
echo "   ⏭️ Indexes skipped (already exist): {$indexesSkipped}\n";

// Now let's run some test queries to verify performance
echo "\n🧪 Testing Query Performance...\n";

$testQueries = [
    [
        'name' => 'Player profile lookup',
        'sql' => 'SELECT * FROM players WHERE team_id = 1 AND role = "Duelist" ORDER BY rating DESC LIMIT 10'
    ],
    [
        'name' => 'Player search by name', 
        'sql' => 'SELECT * FROM players WHERE name LIKE "John%" OR username LIKE "john%" LIMIT 10'
    ],
    [
        'name' => 'Team rankings by region',
        'sql' => 'SELECT * FROM teams WHERE region = "North America" ORDER BY elo_rating DESC, wins DESC LIMIT 10'
    ],
    [
        'name' => 'Player team history',
        'sql' => 'SELECT * FROM player_team_history WHERE player_id = 1 ORDER BY change_date DESC LIMIT 5'
    ]
];

foreach ($testQueries as $test) {
    $start = microtime(true);
    try {
        $results = DB::select($test['sql']);
        $duration = round((microtime(true) - $start) * 1000, 2);
        $count = count($results);
        echo "   ✅ {$test['name']}: {$duration}ms ({$count} rows)\n";
    } catch (Exception $e) {
        echo "   ⚠️ {$test['name']}: Query failed - " . $e->getMessage() . "\n";
    }
}

// Generate final optimization report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'indexes_added' => $indexesAdded,
    'indexes_skipped' => $indexesSkipped,
    'total_players' => DB::table('players')->count(),
    'total_teams' => DB::table('teams')->count(),
    'players_with_teams' => DB::table('players')->whereNotNull('team_id')->count(),
    'data_integrity_status' => [
        'players_with_flags' => DB::table('players')->whereNotNull('flag')->where('flag', '!=', '')->count(),
        'teams_with_flags' => DB::table('teams')->whereNotNull('flag')->where('flag', '!=', '')->count(), 
        'teams_with_logos' => DB::table('teams')->whereNotNull('logo')->where('logo', '!=', '')->count(),
        'teams_with_countries' => DB::table('teams')->whereNotNull('country')->count()
    ]
];

file_put_contents('database_profile_indexes_report.json', json_encode($report, JSON_PRETTY_PRINT));

echo "\n✅ Database Profile Index Optimization Complete!\n";
echo "📝 Report saved to database_profile_indexes_report.json\n";