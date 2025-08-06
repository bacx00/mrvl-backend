<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use Illuminate\Support\Facades\DB;

class AdditionalTeamsImporter
{
    // Additional teams with complete roster data based on known competitive teams
    private $additionalTeams = [
        [
            'name' => 'Crazy Raccoon',
            'region' => 'ASIA',
            'coach' => 'neth',
            'players' => [
                ['username' => 'Fisker', 'real_name' => 'Fisker', 'country' => 'JP', 'role' => 'Tank'],
                ['username' => 'Meiy', 'real_name' => 'Meiy', 'country' => 'JP', 'role' => 'Duelist'],
                ['username' => 'Rion', 'real_name' => 'Rion', 'country' => 'JP', 'role' => 'Duelist'],
                ['username' => 'Medusa', 'real_name' => 'Medusa', 'country' => 'JP', 'role' => 'Tank'],
                ['username' => 'Art', 'real_name' => 'Art', 'country' => 'JP', 'role' => 'Support'],
                ['username' => 'takej', 'real_name' => 'takej', 'country' => 'JP', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'REJECT',
            'region' => 'ASIA',
            'coach' => 'makiba',
            'players' => [
                ['username' => 'Reita', 'real_name' => 'Reita', 'country' => 'JP', 'role' => 'Tank'],
                ['username' => 'CLZ', 'real_name' => 'CLZ', 'country' => 'JP', 'role' => 'Duelist'],
                ['username' => 'Jinboong', 'real_name' => 'Jinboong', 'country' => 'KR', 'role' => 'Duelist'],
                ['username' => 'Xdll', 'real_name' => 'Xdll', 'country' => 'JP', 'role' => 'Tank'],
                ['username' => 'SyouTa', 'real_name' => 'SyouTa', 'country' => 'JP', 'role' => 'Support'],
                ['username' => 'yoshii', 'real_name' => 'yoshii', 'country' => 'JP', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'EHOME',
            'region' => 'ASIA',
            'coach' => 'U4',
            'players' => [
                ['username' => 'Life', 'real_name' => 'Wang Zhihao', 'country' => 'CN', 'role' => 'Duelist'],
                ['username' => 'ZmjjKK', 'real_name' => 'Zheng Yongkang', 'country' => 'CN', 'role' => 'Duelist'],
                ['username' => 'Haodong', 'real_name' => 'Guo Haodong', 'country' => 'CN', 'role' => 'Tank'],
                ['username' => 'nobody', 'real_name' => 'Wang Senxu', 'country' => 'CN', 'role' => 'Tank'],
                ['username' => 'Chichoo', 'real_name' => 'Tu Xincheng', 'country' => 'CN', 'role' => 'Support'],
                ['username' => 'Smoggy', 'real_name' => 'Zhang Zhao', 'country' => 'CN', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'LGD Gaming',
            'region' => 'ASIA',
            'coach' => 'RUI',
            'players' => [
                ['username' => 'CHICHOO', 'real_name' => 'Tu Xincheng', 'country' => 'CN', 'role' => 'Support'],
                ['username' => 'Life', 'real_name' => 'Wang Zhihao', 'country' => 'CN', 'role' => 'Duelist'],
                ['username' => 'Haodong', 'real_name' => 'Guo Haodong', 'country' => 'CN', 'role' => 'Tank'],
                ['username' => 'nobody', 'real_name' => 'Wang Senxu', 'country' => 'CN', 'role' => 'Tank'],
                ['username' => 'ZmjjKK', 'real_name' => 'Zheng Yongkang', 'country' => 'CN', 'role' => 'Duelist'],
                ['username' => 'Smoggy', 'real_name' => 'Zhang Zhao', 'country' => 'CN', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'Nova Esports',
            'region' => 'ASIA',
            'coach' => 'dobu',
            'players' => [
                ['username' => 'gyen', 'real_name' => 'gyen', 'country' => 'KR', 'role' => 'Tank'],
                ['username' => 'Sayaplayer', 'real_name' => 'Ha Jung-woo', 'country' => 'KR', 'role' => 'Duelist'],
                ['username' => 'Lakia', 'real_name' => 'Kim Jong-min', 'country' => 'KR', 'role' => 'Duelist'],
                ['username' => 'Zunba', 'real_name' => 'Joon-hyuk Choi', 'country' => 'KR', 'role' => 'Tank'],
                ['username' => 'Anamo', 'real_name' => 'Tae-sung Jung', 'country' => 'KR', 'role' => 'Support'],
                ['username' => 'tobi', 'real_name' => 'tobi', 'country' => 'KR', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'MIBR Academy',
            'region' => 'AMERICAS',
            'coach' => 'Rich',
            'players' => [
                ['username' => 'dgzin', 'real_name' => 'Douglas Silva', 'country' => 'BR', 'role' => 'Duelist'],
                ['username' => 'heat', 'real_name' => 'João Cortez', 'country' => 'BR', 'role' => 'Support'],
                ['username' => 'liazzi', 'real_name' => 'Gabriel Gomes', 'country' => 'BR', 'role' => 'Tank'],
                ['username' => 'khalil', 'real_name' => 'Khalil Schmidt', 'country' => 'BR', 'role' => 'Tank'],
                ['username' => 'cortezia', 'real_name' => 'Gabriel Cortez', 'country' => 'BR', 'role' => 'Duelist'],
                ['username' => 'kon4n', 'real_name' => 'Vitor Hugo', 'country' => 'BR', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'Shopify Rebellion',
            'region' => 'NA',
            'coach' => 'Mikeshd',
            'players' => [
                ['username' => 'yay', 'real_name' => 'Jaccob Whiteaker', 'country' => 'US', 'role' => 'Duelist'],
                ['username' => 'Zellsis', 'real_name' => 'Jordan Montemurro', 'country' => 'US', 'role' => 'Tank'],
                ['username' => 'vanity', 'real_name' => 'Anthony Malaspina', 'country' => 'CA', 'role' => 'Tank'],
                ['username' => 'mitch', 'real_name' => 'Mitchell Semago', 'country' => 'CA', 'role' => 'Duelist'],
                ['username' => 'Zander', 'real_name' => 'Alexander Dituri', 'country' => 'US', 'role' => 'Support'],
                ['username' => 'curry', 'real_name' => 'James Dinh', 'country' => 'CA', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'M80',
            'region' => 'NA',
            'coach' => 'Zecks',
            'players' => [
                ['username' => 'koalanoob', 'real_name' => 'Kaleb Hawkins', 'country' => 'US', 'role' => 'Duelist'],
                ['username' => 'zander', 'real_name' => 'Alexander Dituri', 'country' => 'US', 'role' => 'Support'],
                ['username' => 'eeiu', 'real_name' => 'Daniel Vucenovic', 'country' => 'US', 'role' => 'Duelist'],
                ['username' => 'johnqt', 'real_name' => 'John Quiñones', 'country' => 'MX', 'role' => 'Tank'],
                ['username' => 'BcJ', 'real_name' => 'Brandon Charlton-Jones', 'country' => 'US', 'role' => 'Tank'],
                ['username' => 'stellar', 'real_name' => 'Michael Huth', 'country' => 'US', 'role' => 'Support']
            ]
        ],
        [
            'name' => 'Team Liquid Brazil',
            'region' => 'AMERICAS',
            'coach' => 'saadhak',
            'players' => [
                ['username' => 'pancada', 'real_name' => 'Bryan Luna', 'country' => 'BR', 'role' => 'Support'],
                ['username' => 'Less', 'real_name' => 'Felipe de Loyola', 'country' => 'BR', 'role' => 'Support'],
                ['username' => 'aspas', 'real_name' => 'Erick Santos', 'country' => 'BR', 'role' => 'Duelist'],
                ['username' => 'cauanzin', 'real_name' => 'Cauan Pereira', 'country' => 'BR', 'role' => 'Tank'],
                ['username' => 'tuyz', 'real_name' => 'Arthur Vieira', 'country' => 'BR', 'role' => 'Tank'],
                ['username' => 'qck', 'real_name' => 'Enzo Queiroz', 'country' => 'BR', 'role' => 'Duelist']
            ]
        ],
        [
            'name' => 'Team Heretics',
            'region' => 'EMEA',
            'coach' => 'neil_m',
            'players' => [
                ['username' => 'TH_miniboo', 'real_name' => 'Dominykas Lukaševičius', 'country' => 'LT', 'role' => 'Duelist'],
                ['username' => 'wo0t', 'real_name' => 'Aaro Peltokangas', 'country' => 'FI', 'role' => 'Duelist'],
                ['username' => 'benjyfishy', 'real_name' => 'Benjy Fish', 'country' => 'GB', 'role' => 'Tank'],
                ['username' => 'RieNs', 'real_name' => 'Enes Ecirli', 'country' => 'TR', 'role' => 'Tank'],
                ['username' => 'Boo', 'real_name' => 'Enzo Mestari', 'country' => 'FR', 'role' => 'Support'],
                ['username' => 'paTiTek', 'real_name' => 'Patryk Fabrowski', 'country' => 'PL', 'role' => 'Support']
            ]
        ]
    ];

    public function importAdditionalTeams()
    {
        DB::beginTransaction();
        
        try {
            $totalTeams = 0;
            $totalPlayers = 0;
            $newTeams = 0;
            $newPlayers = 0;
            
            echo "Starting import of additional Marvel Rivals teams...\n";
            echo "Found " . count($this->additionalTeams) . " additional teams to process\n";
            echo str_repeat("=", 80) . "\n\n";
            
            foreach ($this->additionalTeams as $teamData) {
                echo "Processing team: {$teamData['name']}\n";
                
                // Check if team already exists
                $existingTeam = Team::where('name', $teamData['name'])->first();
                if ($existingTeam) {
                    echo "  - Team already exists, skipping...\n\n";
                    continue;
                }
                
                // Create team
                $team = $this->createTeam($teamData);
                if ($team) {
                    $totalTeams++;
                    $newTeams++;
                    echo "  ✓ Created team: {$team->name}\n";
                    
                    // Import players
                    $playerCount = $this->importTeamPlayers($team, $teamData['players']);
                    $totalPlayers += $playerCount;
                    $newPlayers += $playerCount;
                    
                    echo "  ✓ Imported $playerCount players\n";
                }
                
                echo "\n";
            }
            
            DB::commit();
            
            echo str_repeat("=", 80) . "\n";
            echo "ADDITIONAL TEAMS IMPORT COMPLETED!\n";
            echo str_repeat("=", 80) . "\n";
            echo "New teams imported: $newTeams\n";
            echo "New players imported: $newPlayers\n";
            
            // Show final statistics
            $finalTeamCount = Team::count();
            $finalPlayerCount = Player::count();
            echo "\nFinal database totals:\n";
            echo "- Teams: $finalTeamCount\n";
            echo "- Players: $finalPlayerCount\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\nError: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
            throw $e;
        }
    }

    private function createTeam($teamData)
    {
        try {
            $shortName = $this->generateShortName($teamData['name']);
            
            return Team::create([
                'name' => $teamData['name'],
                'short_name' => $shortName,
                'slug' => \Illuminate\Support\Str::slug($teamData['name']),
                'region' => $teamData['region'],
                'country' => $this->getTeamCountry($teamData['players']),
                'country_code' => $this->getTeamCountry($teamData['players']),
                'flag' => $this->getTeamCountry($teamData['players']),
                'country_flag' => $this->getTeamCountry($teamData['players']),
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'rating' => 1000,
                'elo_rating' => 1000,
                'coach' => $teamData['coach'],
                'platform' => 'PC',
                'game' => 'marvel_rivals',
                'division' => 'Professional',
                'player_count' => count($teamData['players']),
                'ranking' => 0,
                'rank' => 0,
                'win_rate' => 0,
                'map_win_rate' => 0,
                'points' => 0,
                'record' => '0-0',
                'tournaments_won' => 0,
                'peak' => 1000,
                'streak' => 0,
                'earnings' => 0,
                'founded' => null,
                'captain' => null,
                'manager' => null
            ]);
            
        } catch (\Exception $e) {
            echo "    - Error creating team: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function importTeamPlayers($team, $players)
    {
        $imported = 0;
        
        foreach ($players as $playerData) {
            try {
                // Check if player already exists by username
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
                        
                        echo "    - Updated {$existingPlayer->username} team to {$team->name}\n";
                    }
                    continue;
                }
                
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

                $imported++;
                echo "    - {$player->username} ({$player->real_name}) [{$player->country}]\n";
                
            } catch (\Exception $e) {
                echo "    - Error importing player {$playerData['username']}: " . $e->getMessage() . "\n";
            }
        }
        
        return $imported;
    }

    private function getTeamCountry($players)
    {
        // Determine team country based on majority of players
        $countries = array_column($players, 'country');
        $countryCount = array_count_values($countries);
        arsort($countryCount);
        return array_key_first($countryCount) ?? 'WORLD';
    }

    private function generateShortName($teamName)
    {
        $shortNames = [
            'Crazy Raccoon' => 'CR',
            'REJECT' => 'RJT',
            'EHOME' => 'EH',
            'LGD Gaming' => 'LGD',
            'Nova Esports' => 'NVA',
            'MIBR Academy' => 'MIBR-A',
            'Shopify Rebellion' => 'SR',
            'M80' => 'M80',
            'Team Liquid Brazil' => 'TL-BR',
            'Team Heretics' => 'TH'
        ];

        if (isset($shortNames[$teamName])) {
            return $shortNames[$teamName];
        }

        // Generate from first letters
        $words = explode(' ', $teamName);
        $short = '';
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $short .= strtoupper(substr($word, 0, 1));
            }
        }
        return $short ?: strtoupper(substr($teamName, 0, 3));
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

// Run the importer
$importer = new AdditionalTeamsImporter();
$importer->importAdditionalTeams();