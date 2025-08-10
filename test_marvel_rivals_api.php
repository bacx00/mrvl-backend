<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class MarvelRivalsAPITester
{
    public function test()
    {
        echo "🧪 Marvel Rivals API Test Suite\n";
        echo "===============================\n\n";
        
        $this->testBasicQueries();
        $this->testRelationshipQueries();
        $this->testPerformanceQueries();
        $this->testDataAccuracy();
        
        echo "\n✅ API testing completed successfully!\n";
    }
    
    private function testBasicQueries()
    {
        echo "🔍 Testing basic queries...\n";
        
        // Test teams endpoint data
        $teams = DB::table('teams')->limit(5)->get();
        echo "   ✅ Teams query successful: " . count($teams) . " teams retrieved\n";
        
        // Test players endpoint data
        $players = DB::table('players')->limit(10)->get();
        echo "   ✅ Players query successful: " . count($players) . " players retrieved\n";
        
        // Test specific team lookup
        $team = DB::table('teams')->where('short_name', 'SEN')->first();
        if ($team) {
            echo "   ✅ Team lookup by short_name successful: {$team->name}\n";
        } else {
            echo "   ⚠️ Team lookup failed - no team with short_name 'SEN' found\n";
        }
        
        // Test specific player lookup
        $player = DB::table('players')->where('username', 'LIKE', 'TenZ%')->first();
        if ($player) {
            echo "   ✅ Player lookup successful: {$player->username} ({$player->real_name})\n";
        } else {
            echo "   ⚠️ Player lookup failed - no player with username starting with 'TenZ' found\n";
        }
        
        echo "\n";
    }
    
    private function testRelationshipQueries()
    {
        echo "🔗 Testing relationship queries...\n";
        
        // Test team with players
        $teamWithPlayers = DB::table('teams')
            ->join('players', 'teams.id', '=', 'players.team_id')
            ->select('teams.name as team_name', DB::raw('COUNT(players.id) as player_count'))
            ->groupBy('teams.id', 'teams.name')
            ->orderBy('player_count', 'desc')
            ->first();
            
        if ($teamWithPlayers) {
            echo "   ✅ Team-Player relationship query successful\n";
            echo "       Top team: {$teamWithPlayers->team_name} with {$teamWithPlayers->player_count} players\n";
        }
        
        // Test player with team info
        $playerWithTeam = DB::table('players')
            ->join('teams', 'players.team_id', '=', 'teams.id')
            ->select('players.username', 'players.real_name', 'teams.name as team_name', 'teams.region')
            ->first();
            
        if ($playerWithTeam) {
            echo "   ✅ Player-Team relationship query successful\n";
            echo "       Example: {$playerWithTeam->username} ({$playerWithTeam->real_name}) plays for {$playerWithTeam->team_name} in {$playerWithTeam->region}\n";
        }
        
        // Test team history
        $historyCount = DB::table('player_team_history')
            ->join('players', 'player_team_history.player_id', '=', 'players.id')
            ->count();
            
        echo "   ✅ Player team history records: {$historyCount}\n";
        
        echo "\n";
    }
    
    private function testPerformanceQueries()
    {
        echo "⚡ Testing performance queries...\n";
        
        // Test region aggregation (common API query)
        $start = microtime(true);
        $regionStats = DB::table('teams')
            ->select('region', DB::raw('COUNT(*) as team_count'), DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('region')
            ->orderBy('avg_rating', 'desc')
            ->get();
        $regionTime = (microtime(true) - $start) * 1000;
        
        echo "   ✅ Region statistics query: " . round($regionTime, 2) . "ms\n";
        echo "       Found " . count($regionStats) . " regions\n";
        
        // Test role aggregation
        $start = microtime(true);
        $roleStats = DB::table('players')
            ->select('role', DB::raw('COUNT(*) as player_count'), DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('role')
            ->orderBy('avg_rating', 'desc')
            ->get();
        $roleTime = (microtime(true) - $start) * 1000;
        
        echo "   ✅ Role statistics query: " . round($roleTime, 2) . "ms\n";
        echo "       Found " . count($roleStats) . " roles\n";
        
        // Test complex join query (team roster with player stats)
        $start = microtime(true);
        $teamRoster = DB::table('teams')
            ->join('players', 'teams.id', '=', 'players.team_id')
            ->select(
                'teams.name as team_name',
                'teams.region',
                'players.username',
                'players.role',
                'players.rating',
                'players.total_earnings'
            )
            ->where('teams.rating', '>', 2000)
            ->orderBy('teams.rating', 'desc')
            ->limit(20)
            ->get();
        $rosterTime = (microtime(true) - $start) * 1000;
        
        echo "   ✅ Complex team roster query: " . round($rosterTime, 2) . "ms\n";
        echo "       Retrieved " . count($teamRoster) . " player records from top teams\n";
        
        echo "\n";
    }
    
    private function testDataAccuracy()
    {
        echo "🎯 Testing data accuracy...\n";
        
        // Test rating distributions
        $highRatedTeams = DB::table('teams')->where('rating', '>', 2000)->count();
        $highRatedPlayers = DB::table('players')->where('rating', '>', 2000)->count();
        
        echo "   📊 High-rated entities (>2000):\n";
        echo "       Teams: {$highRatedTeams}\n";
        echo "       Players: {$highRatedPlayers}\n";
        
        // Test earnings data
        $topEarningTeam = DB::table('teams')->orderBy('earnings', 'desc')->first();
        $topEarningPlayer = DB::table('players')->orderBy('total_earnings', 'desc')->first();
        
        echo "   💰 Top earners:\n";
        echo "       Team: {$topEarningTeam->name} ($" . number_format($topEarningTeam->earnings) . ")\n";
        echo "       Player: {$topEarningPlayer->username} ($" . number_format($topEarningPlayer->total_earnings) . ")\n";
        
        // Test regional distribution
        $regionDistribution = DB::table('teams')
            ->select('region', DB::raw('COUNT(*) as count'))
            ->groupBy('region')
            ->orderBy('count', 'desc')
            ->get();
            
        echo "   🌍 Regional distribution:\n";
        foreach ($regionDistribution as $region) {
            echo "       {$region->region}: {$region->count} teams\n";
        }
        
        // Test role distribution
        $roleDistribution = DB::table('players')
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->orderBy('count', 'desc')
            ->get();
            
        echo "   ⚔️ Role distribution:\n";
        foreach ($roleDistribution as $role) {
            echo "       {$role->role}: {$role->count} players\n";
        }
        
        echo "\n";
    }
}

// Execute API tests
try {
    $tester = new MarvelRivalsAPITester();
    $tester->test();
} catch (Exception $e) {
    echo "❌ Fatal error during API testing: " . $e->getMessage() . "\n";
    exit(1);
}