<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use Illuminate\Support\Facades\DB;

class RosterFixer
{
    // Additional rosters to complete teams
    private $additionalRosters = [
        'NRG Esports' => [
            ['username' => 'victor', 'real_name' => 'Victor Wong', 'country' => 'US', 'role' => 'Support']
        ],
        'NAVI' => [
            ['username' => 'B1SK', 'real_name' => 'Kirill Savoskin', 'country' => 'UA', 'role' => 'Tank']
        ],
        'Team Vitality' => [
            ['username' => 'BONECOLD', 'real_name' => 'Wassim Bouzid', 'country' => 'TN', 'role' => 'Support']
        ],
        'FUT Esports' => [
            ['username' => 'AtaKaptan', 'real_name' => 'Ata Kaptan', 'country' => 'TR', 'role' => 'Duelist']
        ],
        'Team BDS' => [
            ['username' => 'Logan', 'real_name' => 'Logan Corti', 'country' => 'FR', 'role' => 'Tank']
        ],
        'T1' => [
            ['username' => 'xeta', 'real_name' => 'Kim Tae-min', 'country' => 'KR', 'role' => 'Support']
        ],
        'EDward Gaming' => [
            ['username' => 'KangKang', 'real_name' => 'Zheng Haoran', 'country' => 'CN', 'role' => 'Duelist'],
            ['username' => 'Smoggy', 'real_name' => 'Zhang Zhao', 'country' => 'CN', 'role' => 'Support'],
            ['username' => 'Chichoo', 'real_name' => 'Tu Xincheng', 'country' => 'CN', 'role' => 'Support'],
            ['username' => 'S1mon', 'real_name' => 'Wang Lei', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'nobody', 'real_name' => 'Wang Senxu', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'after', 'real_name' => 'Guo Hengzhe', 'country' => 'CN', 'role' => 'Duelist']
        ],
        'LOUD' => [
            ['username' => 'saadhak', 'real_name' => 'Matias Delipetro', 'country' => 'AR', 'role' => 'Tank'],
            ['username' => 'Less', 'real_name' => 'Felipe de Loyola', 'country' => 'BR', 'role' => 'Support'],
            ['username' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'country' => 'BR', 'role' => 'Tank'],
            ['username' => 'tuyz', 'real_name' => 'Arthur Vieira', 'country' => 'BR', 'role' => 'Duelist'],
            ['username' => 'qck', 'real_name' => 'Enzo Queiroz', 'country' => 'BR', 'role' => 'Duelist']
        ],
        'Sentinels' => [
            ['username' => 'johnqt', 'real_name' => 'John Quiñones', 'country' => 'MX', 'role' => 'Tank'],
            ['username' => 'Zander', 'real_name' => 'Alexander Dituri', 'country' => 'US', 'role' => 'Support']
        ],
        'FURIA' => [
            ['username' => 'mwzera', 'real_name' => 'Erick Chacur', 'country' => 'BR', 'role' => 'Duelist'],
            ['username' => 'Khalil', 'real_name' => 'Khalil Schmidt', 'country' => 'BR', 'role' => 'Tank'],
            ['username' => 'dgzin', 'real_name' => 'Douglas Silva', 'country' => 'BR', 'role' => 'Duelist']
        ],
        'MIBR' => [
            ['username' => 'aspas', 'real_name' => 'Erick Santos', 'country' => 'BR', 'role' => 'Duelist'],
            ['username' => 'pancada', 'real_name' => 'Bryan Luna', 'country' => 'BR', 'role' => 'Support']
        ],
        '100 Thieves' => [
            ['username' => 'Asuna', 'real_name' => 'Peter Mazuryk', 'country' => 'US', 'role' => 'Duelist'],
            ['username' => 'Cryo', 'real_name' => 'Cryocells', 'country' => 'US', 'role' => 'Duelist']
        ],
        'EHOME' => [
            ['username' => 'Life', 'real_name' => 'Wang Zhihao', 'country' => 'CN', 'role' => 'Duelist'],
            ['username' => 'ZmjjKK', 'real_name' => 'Zheng Yongkang', 'country' => 'CN', 'role' => 'Duelist'],
            ['username' => 'Haodong', 'real_name' => 'Guo Haodong', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'nobody', 'real_name' => 'Wang Senxu', 'country' => 'CN', 'role' => 'Tank'],
            ['username' => 'Chichoo', 'real_name' => 'Tu Xincheng', 'country' => 'CN', 'role' => 'Support'],
            ['username' => 'Smoggy', 'real_name' => 'Zhang Zhao', 'country' => 'CN', 'role' => 'Support']
        ],
        'Shopify Rebellion' => [
            ['username' => 'oxy', 'real_name' => 'Francis Hoang', 'country' => 'CA', 'role' => 'Support']
        ]
    ];

    public function fixIncompleteRosters()
    {
        DB::beginTransaction();
        
        try {
            echo "=== FIXING INCOMPLETE ROSTERS ===\n\n";
            
            $teamsFixed = 0;
            $playersAdded = 0;
            
            foreach ($this->additionalRosters as $teamName => $players) {
                $team = Team::where('name', $teamName)->first();
                
                if (!$team) {
                    echo "❌ Team not found: $teamName\n";
                    continue;
                }
                
                $currentPlayerCount = $team->players()->count();
                echo "Processing $teamName (current players: $currentPlayerCount)\n";
                
                foreach ($players as $playerData) {
                    // Check if player already exists
                    $existingPlayer = Player::where('username', $playerData['username'])->first();
                    
                    if ($existingPlayer) {
                        // Update team if different
                        if ($existingPlayer->team_id !== $team->id) {
                            $existingPlayer->team_id = $team->id;
                            $existingPlayer->region = $team->region;
                            $existingPlayer->save();
                            
                            // Create new team history
                            PlayerTeamHistory::create([
                                'player_id' => $existingPlayer->id,
                                'team_id' => $team->id,
                                'joined_at' => now(),
                                'change_date' => now(),
                                'change_type' => 'transferred',
                                'is_current' => true
                            ]);
                            
                            echo "  ✓ Transferred {$existingPlayer->username} to {$team->name}\n";
                            $playersAdded++;
                        }
                        continue;
                    }
                    
                    // Create new player
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
            
            echo "=== ROSTER FIXING COMPLETED ===\n";
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
                echo "✅ All teams now have complete rosters!\n";
            }
            
            $finalTeamCount = Team::count();
            $finalPlayerCount = Player::count();
            echo "\nFinal totals:\n";
            echo "- Teams: $finalTeamCount\n";
            echo "- Players: $finalPlayerCount\n";
            
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

// Run the roster fixer
$fixer = new RosterFixer();
$fixer->fixIncompleteRosters();