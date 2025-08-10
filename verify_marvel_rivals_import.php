<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

class MarvelRivalsImportValidator
{
    public function validate()
    {
        echo "🔍 Marvel Rivals Import Validation\n";
        echo "==================================\n\n";
        
        $this->validateDatabaseCounts();
        $this->validateRelationships();
        $this->validateDataIntegrity();
        $this->validateUniqueConstraints();
        $this->validateEnumValues();
        $this->generateReport();
        
        echo "\n✅ Validation completed successfully!\n";
    }
    
    private function validateDatabaseCounts()
    {
        echo "📊 Validating database counts...\n";
        
        $teamCount = DB::table('teams')->count();
        $playerCount = DB::table('players')->count();
        $historyCount = DB::table('player_team_history')->count();
        
        echo "   - Teams: {$teamCount}\n";
        echo "   - Players: {$playerCount}\n";
        echo "   - Player team histories: {$historyCount}\n";
        
        // Expected counts
        $expectedTeams = 57; // Target was 57 teams but generator created more
        $expectedPlayers = 358;
        
        if ($playerCount >= $expectedPlayers) {
            echo "   ✅ Player count meets or exceeds target ({$expectedPlayers})\n";
        } else {
            echo "   ⚠️ Player count below target: {$playerCount} < {$expectedPlayers}\n";
        }
        
        if ($teamCount >= $expectedTeams) {
            echo "   ✅ Team count meets or exceeds target ({$expectedTeams})\n";
        } else {
            echo "   ⚠️ Team count below target: {$teamCount} < {$expectedTeams}\n";
        }
        
        echo "\n";
    }
    
    private function validateRelationships()
    {
        echo "🔗 Validating relationships...\n";
        
        // Check player-team relationships
        $playersWithTeams = DB::table('players')->whereNotNull('team_id')->count();
        $playersWithoutTeams = DB::table('players')->whereNull('team_id')->count();
        
        echo "   - Players with teams: {$playersWithTeams}\n";
        echo "   - Players without teams: {$playersWithoutTeams}\n";
        
        // Check for orphaned players (team_id references non-existent team)
        $orphanedPlayers = DB::table('players')
            ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
            ->whereNotNull('players.team_id')
            ->whereNull('teams.id')
            ->count();
            
        if ($orphanedPlayers == 0) {
            echo "   ✅ No orphaned players found\n";
        } else {
            echo "   ❌ Found {$orphanedPlayers} orphaned players\n";
        }
        
        // Check player team history relationships
        $historyWithValidPlayers = DB::table('player_team_history')
            ->join('players', 'player_team_history.player_id', '=', 'players.id')
            ->count();
            
        $totalHistoryRecords = DB::table('player_team_history')->count();
        
        if ($historyWithValidPlayers == $totalHistoryRecords) {
            echo "   ✅ All team history records have valid player references\n";
        } else {
            echo "   ❌ Some team history records have invalid player references\n";
        }
        
        echo "\n";
    }
    
    private function validateDataIntegrity()
    {
        echo "🛡️ Validating data integrity...\n";
        
        // Check for required fields
        $teamsWithoutName = DB::table('teams')->whereNull('name')->orWhere('name', '')->count();
        $playersWithoutUsername = DB::table('players')->whereNull('username')->orWhere('username', '')->count();
        
        if ($teamsWithoutName == 0) {
            echo "   ✅ All teams have names\n";
        } else {
            echo "   ❌ {$teamsWithoutName} teams missing names\n";
        }
        
        if ($playersWithoutUsername == 0) {
            echo "   ✅ All players have usernames\n";
        } else {
            echo "   ❌ {$playersWithoutUsername} players missing usernames\n";
        }
        
        // Check for valid ratings
        $invalidTeamRatings = DB::table('teams')->where('rating', '<', 0)->orWhere('rating', '>', 5000)->count();
        $invalidPlayerRatings = DB::table('players')->where('rating', '<', 0)->orWhere('rating', '>', 5000)->count();
        
        if ($invalidTeamRatings == 0) {
            echo "   ✅ All team ratings are valid\n";
        } else {
            echo "   ❌ {$invalidTeamRatings} teams have invalid ratings\n";
        }
        
        if ($invalidPlayerRatings == 0) {
            echo "   ✅ All player ratings are valid\n";
        } else {
            echo "   ❌ {$invalidPlayerRatings} players have invalid ratings\n";
        }
        
        // Check for valid JSON fields
        $invalidTeamSocialMedia = 0;
        $invalidPlayerHeroPrefs = 0;
        
        try {
            $teams = DB::table('teams')->whereNotNull('social_media')->get();
            foreach ($teams as $team) {
                $decoded = json_decode($team->social_media);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $invalidTeamSocialMedia++;
                }
            }
        } catch (Exception $e) {
            echo "   ⚠️ Error checking team social media JSON: " . $e->getMessage() . "\n";
        }
        
        try {
            $players = DB::table('players')->whereNotNull('hero_preferences')->get();
            foreach ($players as $player) {
                $decoded = json_decode($player->hero_preferences);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $invalidPlayerHeroPrefs++;
                }
            }
        } catch (Exception $e) {
            echo "   ⚠️ Error checking player hero preferences JSON: " . $e->getMessage() . "\n";
        }
        
        if ($invalidTeamSocialMedia == 0) {
            echo "   ✅ All team social media JSON is valid\n";
        } else {
            echo "   ❌ {$invalidTeamSocialMedia} teams have invalid social media JSON\n";
        }
        
        if ($invalidPlayerHeroPrefs == 0) {
            echo "   ✅ All player hero preferences JSON is valid\n";
        } else {
            echo "   ❌ {$invalidPlayerHeroPrefs} players have invalid hero preferences JSON\n";
        }
        
        echo "\n";
    }
    
    private function validateUniqueConstraints()
    {
        echo "🔑 Validating unique constraints...\n";
        
        // Check team short_name uniqueness
        $duplicateTeamShortNames = DB::table('teams')
            ->select('short_name', DB::raw('COUNT(*) as count'))
            ->groupBy('short_name')
            ->having('count', '>', 1)
            ->count();
            
        if ($duplicateTeamShortNames == 0) {
            echo "   ✅ All team short names are unique\n";
        } else {
            echo "   ❌ Found {$duplicateTeamShortNames} duplicate team short names\n";
        }
        
        // Check player username uniqueness
        $duplicatePlayerUsernames = DB::table('players')
            ->select('username', DB::raw('COUNT(*) as count'))
            ->groupBy('username')
            ->having('count', '>', 1)
            ->count();
            
        if ($duplicatePlayerUsernames == 0) {
            echo "   ✅ All player usernames are unique\n";
        } else {
            echo "   ❌ Found {$duplicatePlayerUsernames} duplicate player usernames\n";
        }
        
        echo "\n";
    }
    
    private function validateEnumValues()
    {
        echo "📝 Validating enum values...\n";
        
        // Check player roles
        $validRoles = ['Vanguard', 'Duelist', 'Strategist', 'Tank', 'Support', 'Flex', 'Sub'];
        $invalidPlayerRoles = DB::table('players')
            ->whereNotIn('role', $validRoles)
            ->count();
            
        if ($invalidPlayerRoles == 0) {
            echo "   ✅ All player roles are valid\n";
        } else {
            echo "   ❌ Found {$invalidPlayerRoles} players with invalid roles\n";
        }
        
        // Check team platform values
        $validPlatforms = ['PC', 'Console'];
        $invalidTeamPlatforms = DB::table('teams')
            ->whereNotIn('platform', $validPlatforms)
            ->count();
            
        if ($invalidTeamPlatforms == 0) {
            echo "   ✅ All team platforms are valid\n";
        } else {
            echo "   ❌ Found {$invalidTeamPlatforms} teams with invalid platforms\n";
        }
        
        echo "\n";
    }
    
    private function generateReport()
    {
        echo "📈 Generating summary report...\n";
        
        // Team statistics
        $teamsByRegion = DB::table('teams')
            ->select('region', DB::raw('COUNT(*) as count'))
            ->groupBy('region')
            ->orderBy('count', 'desc')
            ->get();
            
        echo "   Teams by region:\n";
        foreach ($teamsByRegion as $stat) {
            echo "     - {$stat->region}: {$stat->count}\n";
        }
        
        // Player statistics
        $playersByRole = DB::table('players')
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->orderBy('count', 'desc')
            ->get();
            
        echo "   Players by role:\n";
        foreach ($playersByRole as $stat) {
            echo "     - {$stat->role}: {$stat->count}\n";
        }
        
        // Player countries
        $playersByCountry = DB::table('players')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
            
        echo "   Top 10 player countries:\n";
        foreach ($playersByCountry as $stat) {
            echo "     - {$stat->country}: {$stat->count}\n";
        }
        
        // Rating statistics
        $avgTeamRating = DB::table('teams')->avg('rating');
        $avgPlayerRating = DB::table('players')->avg('rating');
        
        echo "   Average ratings:\n";
        echo "     - Teams: " . round($avgTeamRating, 2) . "\n";
        echo "     - Players: " . round($avgPlayerRating, 2) . "\n";
        
        // Earnings statistics
        $totalTeamEarnings = DB::table('teams')->sum('earnings');
        $totalPlayerEarnings = DB::table('players')->sum('total_earnings');
        
        echo "   Total earnings:\n";
        echo "     - Teams: $" . number_format($totalTeamEarnings, 2) . "\n";
        echo "     - Players: $" . number_format($totalPlayerEarnings, 2) . "\n";
        
        // Save detailed report
        $this->saveDetailedReport();
        
        echo "\n";
    }
    
    private function saveDetailedReport()
    {
        $report = [
            'validation_date' => date('Y-m-d H:i:s'),
            'database_counts' => [
                'teams' => DB::table('teams')->count(),
                'players' => DB::table('players')->count(),
                'player_team_history' => DB::table('player_team_history')->count()
            ],
            'statistics' => [
                'teams_by_region' => DB::table('teams')
                    ->select('region', DB::raw('COUNT(*) as count'))
                    ->groupBy('region')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->region => $item->count];
                    })
                    ->toArray(),
                'players_by_role' => DB::table('players')
                    ->select('role', DB::raw('COUNT(*) as count'))
                    ->groupBy('role')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->role => $item->count];
                    })
                    ->toArray(),
                'players_by_country' => DB::table('players')
                    ->select('country', DB::raw('COUNT(*) as count'))
                    ->groupBy('country')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->country => $item->count];
                    })
                    ->toArray(),
                'average_ratings' => [
                    'teams' => round(DB::table('teams')->avg('rating'), 2),
                    'players' => round(DB::table('players')->avg('rating'), 2)
                ],
                'total_earnings' => [
                    'teams' => DB::table('teams')->sum('earnings'),
                    'players' => DB::table('players')->sum('total_earnings')
                ]
            ],
            'validation_results' => [
                'orphaned_players' => DB::table('players')
                    ->leftJoin('teams', 'players.team_id', '=', 'teams.id')
                    ->whereNotNull('players.team_id')
                    ->whereNull('teams.id')
                    ->count(),
                'players_with_teams' => DB::table('players')->whereNotNull('team_id')->count(),
                'players_without_teams' => DB::table('players')->whereNull('team_id')->count(),
                'duplicate_team_names' => DB::table('teams')
                    ->select('short_name', DB::raw('COUNT(*) as count'))
                    ->groupBy('short_name')
                    ->having('count', '>', 1)
                    ->count(),
                'duplicate_player_usernames' => DB::table('players')
                    ->select('username', DB::raw('COUNT(*) as count'))
                    ->groupBy('username')
                    ->having('count', '>', 1)
                    ->count()
            ]
        ];
        
        file_put_contents('marvel_rivals_import_validation_report.json', json_encode($report, JSON_PRETTY_PRINT));
        echo "📝 Detailed validation report saved to: marvel_rivals_import_validation_report.json\n";
    }
}

// Execute validation
try {
    $validator = new MarvelRivalsImportValidator();
    $validator->validate();
} catch (Exception $e) {
    echo "❌ Fatal error during validation: " . $e->getMessage() . "\n";
    exit(1);
}