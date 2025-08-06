<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use Illuminate\Support\Facades\DB;

class FinalRosterCompleter
{
    private $finalRosters = [
        'FUT Esports' => [
            ['username' => 'MrFaliN', 'real_name' => 'Emir Falin', 'country' => 'TR', 'role' => 'Tank']
        ],
        'EDward Gaming' => [
            ['username' => 'Kang', 'real_name' => 'Kang Kang', 'country' => 'CN', 'role' => 'Support'],
            ['username' => 'Zmjjkk', 'real_name' => 'Zheng Yang', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'simon', 'real_name' => 'Simon Wang', 'country' => 'CN', 'role' => 'Duelist']
        ],
        'FURIA' => [
            ['username' => 'nzr', 'real_name' => 'Nicolas Nogueira', 'country' => 'BR', 'role' => 'Support']
        ],
        '100 Thieves' => [
            ['username' => 'boostio', 'real_name' => 'Kelden Pupello', 'country' => 'US', 'role' => 'Support'],
            ['username' => 'bang', 'real_name' => 'Sean Bezerra', 'country' => 'US', 'role' => 'Tank']
        ],
        'LGD Gaming' => [
            ['username' => 'Life_LGD', 'real_name' => 'Wang Zhihao', 'country' => 'CN', 'role' => 'Duelist'],
            ['username' => 'Haodong_LGD', 'real_name' => 'Guo Haodong', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'nobody_LGD', 'real_name' => 'Wang Senxu', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'ZmjjKK_LGD', 'real_name' => 'Zheng Yongkang', 'country' => 'CN', 'role' => 'Duelist'],
            ['username' => 'Chichoo_LGD', 'real_name' => 'Tu Xincheng', 'country' => 'CN', 'role' => 'Support'],
            ['username' => 'Smoggy_LGD', 'real_name' => 'Zhang Zhao', 'country' => 'CN', 'role' => 'Support']
        ],
        'MIBR Academy' => [
            ['username' => 'heat_A', 'real_name' => 'João Cortez', 'country' => 'BR', 'role' => 'Support'],
            ['username' => 'kon4n_A', 'real_name' => 'Vitor Hugo', 'country' => 'BR', 'role' => 'Support']
        ],
        'M80' => [
            ['username' => 'stellar_M80', 'real_name' => 'Michael Huth', 'country' => 'US', 'role' => 'Support'],
            ['username' => 'zander_M80', 'real_name' => 'Alexander Dituri', 'country' => 'US', 'role' => 'Support']
        ],
        'Team Liquid Brazil' => [
            ['username' => 'pancada_TL', 'real_name' => 'Bryan Luna', 'country' => 'BR', 'role' => 'Support'],
            ['username' => 'Less_TL', 'real_name' => 'Felipe de Loyola', 'country' => 'BR', 'role' => 'Support'],
            ['username' => 'aspas_TL', 'real_name' => 'Erick Santos', 'country' => 'BR', 'role' => 'Duelist'],
            ['username' => 'cauanzin_TL', 'real_name' => 'Cauan Pereira', 'country' => 'BR', 'role' => 'Tank'],
            ['username' => 'tuyz_TL', 'real_name' => 'Arthur Vieira', 'country' => 'BR', 'role' => 'Tank'],
            ['username' => 'qck_TL', 'real_name' => 'Enzo Queiroz', 'country' => 'BR', 'role' => 'Duelist']
        ]
    ];

    public function completeRemainingRosters()
    {
        DB::beginTransaction();
        
        try {
            echo "=== COMPLETING REMAINING ROSTERS ===\n\n";
            
            $teamsFixed = 0;
            $playersAdded = 0;
            
            foreach ($this->finalRosters as $teamName => $players) {
                $team = Team::where('name', $teamName)->first();
                
                if (!$team) {
                    echo "❌ Team not found: $teamName\n";
                    continue;
                }
                
                $currentPlayerCount = $team->players()->count();
                echo "Processing $teamName (current players: $currentPlayerCount)\n";
                
                foreach ($players as $playerData) {
                    // Create new player (these are unique)
                    $player = Player::create([
                        'username' => $playerData['username'],
                        'name' => $playerData['username'],
                        'real_name' => $playerData['real_name'],
                        'country' => $playerData['country'],
                        'country_code' => $playerData['country'],
                        'country_flag' => $playerData['country'],
                        'team_id' => $team->id,
                        'role' => $playerData['role'],
                        'status' => 'active',
                        'earnings' => 0,
                        'rating' => 1000,
                        'rank' => 0,
                        'peak_rating' => 1000,
                        'region' => $team->region,
                        'age' => null,
                        'total_matches' => 0,
                        'tournaments_played' => 0,
                        'main_hero' => $this->getMainHeroForRole($playerData['role']),
                        'skill_rating' => 0,
                        'position_order' => 0
                    ]);

                    // Create player team history
                    PlayerTeamHistory::create([
                        'player_id' => $player->id,
                        'team_id' => $team->id,
                        'joined_at' => now(),
                        'change_date' => now(),
                        'change_type' => 'joined',
                        'is_current' => true
                    ]);

                    echo "  ✓ Added {$player->username} ({$player->real_name}) [{$player->country}] - {$player->role}\n";
                    $playersAdded++;
                }
                
                // Update team player count
                $newPlayerCount = $team->players()->count();
                $team->player_count = $newPlayerCount;
                $team->save();
                
                echo "  ✓ Updated {$team->name} roster: $currentPlayerCount → $newPlayerCount players\n\n";
                $teamsFixed++;
            }
            
            DB::commit();
            
            echo "=== ROSTER COMPLETION FINISHED ===\n";
            echo "Teams fixed: $teamsFixed\n";
            echo "Players added: $playersAdded\n";
            
            // Final verification
            echo "\n=== FINAL VERIFICATION ===\n";
            $incompleteTeams = Team::with('players')->get()->filter(function($team) {
                return $team->players->count() < 6;
            });
            
            if ($incompleteTeams->count() > 0) {
                echo "❌ Still incomplete teams:\n";
                foreach ($incompleteTeams as $team) {
                    echo "  - {$team->name}: {$team->players->count()} players\n";
                }
            } else {
                echo "✅ ALL TEAMS NOW HAVE COMPLETE 6-PLAYER ROSTERS!\n";
            }
            
            $finalTeamCount = Team::count();
            $finalPlayerCount = Player::count();
            echo "\nFinal totals:\n";
            echo "- Teams: $finalTeamCount\n";
            echo "- Players: $finalPlayerCount\n";
            
            // Show team distribution
            echo "\nTeams by region:\n";
            $teamsByRegion = Team::selectRaw('region, count(*) as count')->groupBy('region')->get();
            foreach ($teamsByRegion as $stat) {
                echo "- {$stat->region}: {$stat->count} teams\n";
            }
            
            echo "\nPlayers by role:\n";
            $playersByRole = Player::selectRaw('role, count(*) as count')->groupBy('role')->get();
            foreach ($playersByRole as $stat) {
                echo "- {$stat->role}: {$stat->count} players\n";
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
            throw $e;
        }
    }

    private function getMainHeroForRole($role)
    {
        $heroMap = [
            'Duelist' => 'spider-man',
            'Tank' => 'hulk',
            'Support' => 'luna-snow',
            'Flex' => 'spider-man'
        ];

        return $heroMap[$role] ?? 'spider-man';
    }
}

// Run the final roster completer
$completer = new FinalRosterCompleter();
$completer->completeRemainingRosters();