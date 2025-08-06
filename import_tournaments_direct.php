<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DirectTournamentImporter
{
    private $tournaments = [
        [
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'region' => 'NA',
            'teams' => [
                [
                    'name' => 'Luminosity Gaming',
                    'coach' => 'Gunba',
                    'players' => [
                        ['username' => 'Danteh', 'real_name' => 'Dante Cruz', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'nero', 'real_name' => 'Charlie Zwarg', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'Punk', 'real_name' => 'Jun Young Park', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'Poko', 'real_name' => 'Gael Gouzerch', 'country' => 'FR', 'role' => 'Tank'],
                        ['username' => 'UltraViolet', 'real_name' => 'Eui-seok Lee', 'country' => 'KR', 'role' => 'Support'],
                        ['username' => 'Lukemino', 'real_name' => 'Lucas Håkansson', 'country' => 'SE', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'NRG Esports',
                    'coach' => 'LegitRc',
                    'players' => [
                        ['username' => 'Surefour', 'real_name' => 'Lane Roberts', 'country' => 'CA', 'role' => 'Duelist'],
                        ['username' => 'Kevster', 'real_name' => 'Kevin Persson', 'country' => 'SE', 'role' => 'Duelist'],
                        ['username' => 'Coluge', 'real_name' => 'Austin Gillis', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'Vestola', 'real_name' => 'Vestola', 'country' => 'FI', 'role' => 'Tank'],
                        ['username' => 'Ojee', 'real_name' => 'Christian Han', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'Rakattack', 'real_name' => 'Kyle Rakauskas', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'TSM',
                    'coach' => 'Hayes',
                    'players' => [
                        ['username' => 'Sugarfree', 'real_name' => 'Gil Seong-jun', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'sHockWave', 'real_name' => 'Cody Corona', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'Gator', 'real_name' => 'Blake Scott', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'Numlocked', 'real_name' => 'Seb Barton', 'country' => 'GB', 'role' => 'Tank'],
                        ['username' => 'Shu', 'real_name' => 'Jin-seo Kim', 'country' => 'KR', 'role' => 'Support'],
                        ['username' => 'squid', 'real_name' => 'Squid', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Oxygen Esports',
                    'coach' => 'Mineral',
                    'players' => [
                        ['username' => 'Kraandop', 'real_name' => 'Daan Schellen', 'country' => 'NL', 'role' => 'Duelist'],
                        ['username' => 'icy', 'real_name' => 'Eugen Kozlov', 'country' => 'RU', 'role' => 'Duelist'],
                        ['username' => 'GiG', 'real_name' => 'Han-gil Jung', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'False', 'real_name' => 'Phillip Bowen', 'country' => 'CA', 'role' => 'Tank'],
                        ['username' => 'Rupal', 'real_name' => 'Rupal Zaman', 'country' => 'GB', 'role' => 'Support'],
                        ['username' => 'Cjay', 'real_name' => 'Justin Caldwell', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Cloud9',
                    'coach' => 'Junkbuck',
                    'players' => [
                        ['username' => 'Wub', 'real_name' => 'Cameron Johnson', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'k1ng', 'real_name' => 'Lee Dong-wook', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Reiner', 'real_name' => 'Aren Syntrowitz', 'country' => 'CA', 'role' => 'Tank'],
                        ['username' => 'Jkaru', 'real_name' => 'Jesse Karu', 'country' => 'US', 'role' => 'Tank'],
                        ['username' => 'Lep', 'real_name' => 'Lepton', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'Yaki', 'real_name' => 'Kim Jun-ki', 'country' => 'KR', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Evil Geniuses',
                    'coach' => 'Casores',
                    'players' => [
                        ['username' => 'Happy', 'real_name' => 'Lee Jung-woo', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Seeker', 'real_name' => 'Je-hwan Shin', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'Kellan', 'real_name' => 'Kellan Taylor', 'country' => 'CA', 'role' => 'Tank'],
                        ['username' => 'MirroR', 'real_name' => 'Miro Toivanen', 'country' => 'FI', 'role' => 'Tank'],
                        ['username' => 'NOS', 'real_name' => 'Devin Harrison', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'Rakattack', 'real_name' => 'Kyle Rakauskas', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'FaZe Clan',
                    'coach' => 'Rawkus',
                    'players' => [
                        ['username' => 'Hydron', 'real_name' => 'Nolan Edwards', 'country' => 'US', 'role' => 'Duelist'],
                        ['username' => 'Checkmate', 'real_name' => 'Lee Do-hyeong', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'LhCloudy', 'real_name' => 'Finley Adisi', 'country' => 'DK', 'role' => 'Tank'],
                        ['username' => 'KSAA', 'real_name' => 'Salman Al-Malki', 'country' => 'SA', 'role' => 'Tank'],
                        ['username' => 'Vega', 'real_name' => 'Adam Pechacek', 'country' => 'CZ', 'role' => 'Support'],
                        ['username' => 'Lyar', 'real_name' => 'Samuel Portillo', 'country' => 'US', 'role' => 'Support']
                    ]
                ],
                [
                    'name' => 'Toronto Defiant',
                    'coach' => 'Mobydik',
                    'players' => [
                        ['username' => 'Dove', 'real_name' => 'Jae-hyeok Kim', 'country' => 'KR', 'role' => 'Duelist'],
                        ['username' => 'ALTHOUGH', 'real_name' => 'Dylan Bignet', 'country' => 'CA', 'role' => 'Duelist'],
                        ['username' => 'Someone', 'real_name' => 'Chan-dong Sin', 'country' => 'KR', 'role' => 'Tank'],
                        ['username' => 'Prime', 'real_name' => 'Prime', 'country' => 'CA', 'role' => 'Tank'],
                        ['username' => 'Landon', 'real_name' => 'Landon Spurlock', 'country' => 'US', 'role' => 'Support'],
                        ['username' => 'Crimzo', 'real_name' => 'William Hernandez', 'country' => 'US', 'role' => 'Support']
                    ]
                ]
            ]
        ]
    ];

    public function import()
    {
        DB::beginTransaction();

        try {
            foreach ($this->tournaments as $tournament) {
                echo "Importing tournament: {$tournament['name']}\n";
                echo "Region: {$tournament['region']}\n";
                echo "Teams: " . count($tournament['teams']) . "\n\n";

                foreach ($tournament['teams'] as $teamData) {
                    // Create team
                    $shortName = $this->generateShortName($teamData['name']);
                    $team = Team::create([
                        'name' => $teamData['name'],
                        'short_name' => $shortName,
                        'slug' => \Illuminate\Support\Str::slug($teamData['name']),
                        'region' => $tournament['region'],
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
                        'player_count' => 6,
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

                    echo "Created team: {$team->name}\n";

                    // Create players
                    foreach ($teamData['players'] as $playerData) {
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
                            'region' => $tournament['region'],
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

                        echo "  - Added player: {$player->username} ({$player->real_name}) - {$player->role}\n";
                    }

                    echo "\n";
                }
            }

            DB::commit();
            echo "Import completed successfully!\n";
            
            // Show summary
            $teamCount = Team::count();
            $playerCount = Player::count();
            echo "\nSummary:\n";
            echo "- Total teams: $teamCount\n";
            echo "- Total players: $playerCount\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "Error: " . $e->getMessage() . "\n";
            throw $e;
        }
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
        // Generate short name from team name
        $shortNames = [
            'Luminosity Gaming' => 'LG',
            'NRG Esports' => 'NRG',
            'TSM' => 'TSM',
            'Oxygen Esports' => 'OXG',
            'Cloud9' => 'C9',
            'Evil Geniuses' => 'EG',
            'FaZe Clan' => 'FAZE',
            'Toronto Defiant' => 'TD'
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
            'Support' => 'luna-snow'
        ];

        return $heroMap[$role] ?? 'spider-man';
    }
}

// Run the importer
$importer = new DirectTournamentImporter();
$importer->import();