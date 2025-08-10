<?php

/**
 * Comprehensive Marvel Rivals Data Generator
 * Creates realistic test data for 57 teams and 358 players
 */

class MarvelRivalsDataGenerator
{
    private $teams = [];
    private $players = [];
    
    private $regions = ['North America', 'Europe', 'Asia', 'China', 'Korea', 'Japan', 'South America', 'Oceania'];
    private $countries = [
        'North America' => ['United States', 'Canada', 'Mexico'],
        'Europe' => ['United Kingdom', 'Germany', 'France', 'Spain', 'Sweden', 'Denmark', 'Netherlands', 'Poland', 'Russia', 'Turkey'],
        'Asia' => ['Thailand', 'Singapore', 'Malaysia', 'Philippines', 'Indonesia', 'Vietnam', 'India'],
        'China' => ['China'],
        'Korea' => ['South Korea'],
        'Japan' => ['Japan'],
        'South America' => ['Brazil', 'Argentina', 'Chile', 'Colombia'],
        'Oceania' => ['Australia', 'New Zealand']
    ];
    
    private $roles = ['Duelist', 'Strategist', 'Vanguard'];
    private $heroes = [
        'Duelist' => ['Spider-Man', 'Iron Man', 'Hawkeye', 'Black Panther', 'Winter Soldier', 'Psylocke', 'Star-Lord', 'Punisher', 'Squirrel Girl'],
        'Strategist' => ['Mantis', 'Luna Snow', 'Rocket Raccoon', 'Cloak & Dagger', 'Jeff the Land Shark', 'Adam Warlock', 'Invisible Woman'],
        'Vanguard' => ['Hulk', 'Captain America', 'Thor', 'Groot', 'Peni Parker', 'Venom', 'Magneto', 'Doctor Strange', 'Storm']
    ];
    
    private $teamNames = [
        'North America' => [
            'Sentinels', 'NRG Esports', 'Cloud9', '100 Thieves', 'TSM', 'FaZe Clan', 'OpTic Gaming', 'Team Liquid NA',
            'Evil Geniuses', 'Gen.G', 'Version1', 'Dignitas', 'XSET', 'Rise', 'Immortals', 'Envy'
        ],
        'Europe' => [
            'G2 Esports', 'Team Liquid', 'FNATIC', 'Vitality', 'BIG', 'MAD Lions', 'Guild Esports', 'Alliance',
            'OG', 'Ninjas in Pyjamas', 'Astralis', 'FunPlus Phoenix', 'Excel Esports', 'LDLC'
        ],
        'Asia' => [
            'Paper Rex', 'XERXIA', 'FULL SENSE', 'BOOM Esports', 'Team Secret', 'Rex Regum Qeon', 'Alter Ego',
            'BLEED', 'ORDER', 'Soniqs', 'Global Esports', 'Velocity Gaming'
        ],
        'China' => [
            'EDward Gaming', 'FunPlus Phoenix', 'Bilibili Gaming', 'JD Gaming', 'ThunderTalk Gaming', 'Rare Atom',
            'Weibo Gaming', 'LNG Esports'
        ],
        'Korea' => [
            'DRX', 'T1', 'Gen.G', 'KT Rolster', 'Hanwha Life Esports', 'DAMWON KIA', 'Liiv SANDBOX'
        ],
        'Japan' => [
            'ZETA DIVISION', 'Crazy Raccoon', 'FENNEL', 'Northeption', 'REJECT'
        ],
        'South America' => [
            'LOUD', 'NIP', 'Keyd Stars', 'Sharks Esports', 'Vivo Keyd'
        ],
        'Oceania' => [
            'ORDER', 'Soniqs', 'Legacy Esports'
        ]
    ];

    private $playerNames = [
        'first_names' => [
            'Tyler', 'Kyle', 'Michael', 'David', 'James', 'Robert', 'John', 'William', 'Richard', 'Joseph',
            'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Donald', 'Steven', 'Andrew', 'Kenneth', 'Paul',
            'Lucas', 'Emma', 'Olivia', 'Ava', 'Isabella', 'Sophia', 'Mia', 'Charlotte', 'Amelia', 'Harper',
            'Noah', 'Liam', 'Oliver', 'Alexander', 'Ethan', 'Henry', 'Mason', 'Sebastian', 'Logan', 'Jackson',
            'Gabriel', 'Benjamin', 'Samuel', 'Jacob', 'Elijah', 'Aaron', 'Christian', 'Nathan', 'Adam', 'Thomas'
        ],
        'last_names' => [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
            'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
            'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
            'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts'
        ],
        'usernames' => [
            'TenZ', 's0m', 'leaf', 'yay', 'FNS', 'crashies', 'Victor', 'Marved', 'ShahZaM', 'SicK',
            'dapr', 'zombs', 'Asuna', 'bang', 'stellar', 'ethan', 'Hiko', 'nitr0', 'steel', 'AZK',
            'ScreaM', 'jamppi', 'Nivera', 'soulcas', 'Liquid', 'nukkye', 'mixwell', 'AvovA', 'hoody', 'pipson',
            'Derke', 'Alfajer', 'Enzo', 'Mistic', 'Boaster', 'Chronicle', 'nAts', 'Redgar', 'sheydos', 'd3ffo',
            'f0rsakeN', 'Jinggg', 'mindfreak', 'Benkai', 'd4v41', 'Rb', 'stax', 'Zest', 'BuZz', 'MaKo',
            'Lakia', 'k1Ng', 'Suggest', 'Foxy9', 'allow', 'Meteor', 'BeYN', 'exy', 'zunba', 'Sylvan',
            'saadhak', 'Less', 'aspas', 'Sacy', 'pANcada', 'heat', 'mwzera', 'Mazin', 'v1xen', 'Khalil'
        ]
    ];

    public function generate()
    {
        echo "🎮 Marvel Rivals Comprehensive Data Generator\n";
        echo "============================================\n\n";
        
        $this->generateTeams();
        $this->generatePlayers();
        $this->saveToFiles();
        
        echo "\n✅ Data generation completed successfully!\n";
        echo "📊 Generated: " . count($this->teams) . " teams and " . count($this->players) . " players\n";
    }

    private function generateTeams()
    {
        echo "🏆 Generating teams...\n";
        
        $teamId = 1;
        
        foreach ($this->teamNames as $region => $regionTeams) {
            foreach ($regionTeams as $teamName) {
                $countries = $this->countries[$region];
                $country = $countries[array_rand($countries)];
                
                $team = [
                    'id' => $teamId,
                    'name' => $teamName,
                    'short_name' => $this->generateShortName($teamName),
                    'logo_url' => "https://liquipedia.net/commons/images/thumb/" . urlencode($teamName) . ".svg/400px-" . urlencode($teamName) . ".svg.png",
                    'region' => $region,
                    'country' => $country,
                    'country_code' => $this->getCountryCode($country),
                    'platform' => 'PC',
                    'tier' => $this->randomChoice(['S', 'A', 'B', 'C'], [0.1, 0.3, 0.4, 0.2]),
                    'rating' => rand(1000, 2500),
                    'rank' => $teamId,
                    'total_earnings' => (float)rand(5000, 200000),
                    'founded' => $this->randomDate('2019-01-01', '2023-12-31'),
                    'status' => $this->randomChoice(['Active', 'Inactive'], [0.9, 0.1]),
                    'website' => "https://www." . strtolower(str_replace(' ', '', $teamName)) . ".gg/",
                    'social_media' => [
                        'twitter' => "https://twitter.com/" . strtolower(str_replace(' ', '', $teamName)),
                        'instagram' => "https://instagram.com/" . strtolower(str_replace(' ', '', $teamName)),
                        'youtube' => "https://youtube.com/c/" . str_replace(' ', '', $teamName),
                        'twitch' => "https://twitch.tv/" . strtolower(str_replace(' ', '', $teamName))
                    ],
                    'roster' => [],
                    'player_count' => 0
                ];
                
                $this->teams[] = $team;
                $teamId++;
            }
        }
        
        echo "✅ Generated " . count($this->teams) . " teams\n";
    }

    private function generatePlayers()
    {
        echo "👥 Generating players...\n";
        
        $playerId = 1;
        $totalPlayers = 358;
        $playersPerTeam = intval($totalPlayers / count($this->teams));
        $extraPlayers = $totalPlayers % count($this->teams);
        
        foreach ($this->teams as $teamIndex => &$team) {
            $teamPlayerCount = $playersPerTeam + ($teamIndex < $extraPlayers ? 1 : 0);
            $teamRoster = [];
            
            for ($i = 0; $i < $teamPlayerCount; $i++) {
                $role = $this->roles[array_rand($this->roles)];
                $mainHeroes = $this->heroes[$role];
                $signatureHero = $mainHeroes[array_rand($mainHeroes)];
                
                // Select random heroes for this player
                $playerMainHeroes = array_slice($mainHeroes, 0, rand(2, 4));
                $secondaryHeroes = array_diff($mainHeroes, $playerMainHeroes);
                $playerSecondaryHeroes = array_slice($secondaryHeroes, 0, rand(1, 3));
                
                $username = $this->playerNames['usernames'][array_rand($this->playerNames['usernames'])];
                // Make username unique
                $username = $username . rand(100, 999);
                
                $firstName = $this->playerNames['first_names'][array_rand($this->playerNames['first_names'])];
                $lastName = $this->playerNames['last_names'][array_rand($this->playerNames['last_names'])];
                $realName = $firstName . ' ' . $lastName;
                
                $countries = $this->countries[$team['region']];
                $country = $countries[array_rand($countries)];
                
                $player = [
                    'id' => $playerId,
                    'username' => $username,
                    'real_name' => $realName,
                    'birth_date' => $this->randomDate('1995-01-01', '2006-12-31'),
                    'age' => rand(18, 29),
                    'nationality' => $this->getNationality($country),
                    'country' => $country,
                    'country_code' => $this->getCountryCode($country),
                    'current_team' => [
                        'name' => $team['name'],
                        'short_name' => $team['short_name'],
                        'join_date' => $this->randomDate('2024-01-01', '2024-12-31'),
                        'role' => $role,
                        'is_captain' => $i === 0,
                        'is_active' => true
                    ],
                    'roles' => [$role],
                    'main_role' => $role,
                    'main_heroes' => $playerMainHeroes,
                    'secondary_heroes' => $playerSecondaryHeroes,
                    'signature_hero' => $signatureHero,
                    'total_earnings' => (float)rand(1000, 100000),
                    'current_rating' => rand(1000, 2500),
                    'peak_rating' => rand(1000, 2600),
                    'rank' => $playerId,
                    'kda_ratio' => round(rand(50, 350) / 100, 2),
                    'win_rate' => round(rand(40, 90) / 100, 2),
                    'games_played' => rand(50, 500),
                    'games_won' => rand(20, 400),
                    'hours_played' => rand(200, 2000),
                    'social_media' => [
                        'twitter' => "https://twitter.com/" . strtolower($username),
                        'instagram' => "https://instagram.com/" . strtolower($username),
                        'youtube' => "https://youtube.com/c/" . $username,
                        'twitch' => "https://twitch.tv/" . strtolower($username),
                        'tiktok' => "https://tiktok.com/@" . strtolower($username)
                    ],
                    'streaming' => [
                        'primary_platform' => $this->randomChoice(['Twitch', 'YouTube', 'None'], [0.6, 0.2, 0.2]),
                        'followers' => rand(1000, 1000000),
                        'average_viewers' => rand(100, 50000),
                        'is_active_streamer' => rand(0, 1) === 1
                    ],
                    'biography' => $this->generatePlayerBiography($role, $team['region']),
                    'career_highlights' => $this->generateCareerHighlights(),
                    'team_history' => $this->generateTeamHistory($playerId)
                ];
                
                // Add to team roster
                $teamRoster[] = [
                    'username' => $username,
                    'real_name' => $realName,
                    'role' => $role,
                    'country' => $country,
                    'is_captain' => $i === 0
                ];
                
                $this->players[] = $player;
                $playerId++;
                
                if ($playerId % 50 == 0) {
                    echo "   - Generated {$playerId} players...\n";
                }
            }
            
            $team['roster'] = $teamRoster;
            $team['player_count'] = count($teamRoster);
        }
        
        echo "✅ Generated " . count($this->players) . " players\n";
    }

    private function saveToFiles()
    {
        echo "💾 Saving data to JSON files...\n";
        
        // Save teams
        $teamsJson = json_encode($this->teams, JSON_PRETTY_PRINT);
        file_put_contents('liquipedia_comprehensive_57_teams_generated.json', $teamsJson);
        
        // Save players
        $playersJson = json_encode($this->players, JSON_PRETTY_PRINT);
        file_put_contents('liquipedia_comprehensive_358_players_generated.json', $playersJson);
        
        // Generate summary
        $summary = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_teams' => count($this->teams),
            'total_players' => count($this->players),
            'teams_by_region' => [],
            'players_by_role' => []
        ];
        
        // Count teams by region
        foreach ($this->teams as $team) {
            $region = $team['region'];
            if (!isset($summary['teams_by_region'][$region])) {
                $summary['teams_by_region'][$region] = 0;
            }
            $summary['teams_by_region'][$region]++;
        }
        
        // Count players by role
        foreach ($this->players as $player) {
            $role = $player['main_role'];
            if (!isset($summary['players_by_role'][$role])) {
                $summary['players_by_role'][$role] = 0;
            }
            $summary['players_by_role'][$role]++;
        }
        
        $summaryJson = json_encode($summary, JSON_PRETTY_PRINT);
        file_put_contents('marvel_rivals_generated_data_summary.json', $summaryJson);
        
        echo "✅ Data saved to files:\n";
        echo "  - liquipedia_comprehensive_57_teams_generated.json\n";
        echo "  - liquipedia_comprehensive_358_players_generated.json\n";
        echo "  - marvel_rivals_generated_data_summary.json\n";
    }

    // Helper methods
    private function generateShortName($fullName)
    {
        $words = explode(' ', $fullName);
        if (count($words) == 1) {
            return strtoupper(substr($fullName, 0, 3));
        }
        
        $shortName = '';
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $shortName .= strtoupper($word[0]);
            }
        }
        
        return $shortName ?: strtoupper(substr($fullName, 0, 3));
    }

    private function getCountryCode($country)
    {
        $codes = [
            'United States' => 'US', 'Canada' => 'CA', 'Mexico' => 'MX',
            'Brazil' => 'BR', 'Argentina' => 'AR', 'Chile' => 'CL', 'Colombia' => 'CO',
            'United Kingdom' => 'GB', 'Germany' => 'DE', 'France' => 'FR', 'Spain' => 'ES',
            'Sweden' => 'SE', 'Denmark' => 'DK', 'Netherlands' => 'NL', 'Poland' => 'PL',
            'Russia' => 'RU', 'Turkey' => 'TR', 'China' => 'CN', 'South Korea' => 'KR',
            'Japan' => 'JP', 'Thailand' => 'TH', 'Singapore' => 'SG', 'Malaysia' => 'MY',
            'Philippines' => 'PH', 'Indonesia' => 'ID', 'Vietnam' => 'VN', 'India' => 'IN',
            'Australia' => 'AU', 'New Zealand' => 'NZ'
        ];
        
        return $codes[$country] ?? 'US';
    }

    private function getNationality($country)
    {
        $nationalities = [
            'United States' => 'American', 'Canada' => 'Canadian', 'Mexico' => 'Mexican',
            'Brazil' => 'Brazilian', 'Argentina' => 'Argentinian', 'Chile' => 'Chilean',
            'United Kingdom' => 'British', 'Germany' => 'German', 'France' => 'French',
            'Spain' => 'Spanish', 'Sweden' => 'Swedish', 'Denmark' => 'Danish',
            'Netherlands' => 'Dutch', 'Poland' => 'Polish', 'Russia' => 'Russian',
            'Turkey' => 'Turkish', 'China' => 'Chinese', 'South Korea' => 'South Korean',
            'Japan' => 'Japanese', 'Thailand' => 'Thai', 'Singapore' => 'Singaporean',
            'Malaysia' => 'Malaysian', 'Philippines' => 'Filipino', 'Indonesia' => 'Indonesian',
            'Vietnam' => 'Vietnamese', 'India' => 'Indian', 'Australia' => 'Australian',
            'New Zealand' => 'New Zealander'
        ];
        
        return $nationalities[$country] ?? 'Unknown';
    }

    private function randomChoice($options, $weights)
    {
        $totalWeight = array_sum($weights);
        $rand = mt_rand(1, $totalWeight * 100) / 100;
        
        $runningWeight = 0;
        for ($i = 0; $i < count($options); $i++) {
            $runningWeight += $weights[$i];
            if ($rand <= $runningWeight) {
                return $options[$i];
            }
        }
        
        return $options[0];
    }

    private function randomDate($start, $end)
    {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $randomTs = rand($startTs, $endTs);
        return date('Y-m-d', $randomTs);
    }

    private function generatePlayerBiography($role, $region)
    {
        $bios = [
            'Duelist' => [
                'Exceptional DPS player known for incredible mechanical skill and game sense.',
                'Rising star in the Marvel Rivals scene with outstanding fragging ability.',
                'Veteran player with years of competitive experience and clutch potential.',
                'Aggressive entry fragger with consistent performance in high-pressure situations.'
            ],
            'Strategist' => [
                'Support specialist with excellent game awareness and team coordination.',
                'Veteran strategist known for clutch plays and exceptional positioning.',
                'Team player with strong communication skills and tactical understanding.',
                'Flexible support player capable of adapting to any team composition.'
            ],
            'Vanguard' => [
                'Solid tank player with excellent space creation and team leadership.',
                'Defensive anchor known for consistent performance and shot-calling.',
                'Aggressive tank player with strong initiation and playmaking ability.',
                'Experienced front-line player with exceptional game sense and positioning.'
            ]
        ];
        
        $roleBios = $bios[$role];
        return $roleBios[array_rand($roleBios)] . " Competing in the {$region} region.";
    }

    private function generateCareerHighlights()
    {
        $achievements = [
            'Marvel Rivals World Champion 2024',
            'Marvel Rivals World Championship Runner-up 2024',
            'Marvel Rivals Spring Major Champion',
            'Marvel Rivals Summer Championship Winner',
            'Marvel Rivals Winter Invitational MVP',
            'Regional Championship Winner 2024',
            'First player to reach 2400+ rating in competitive',
            'Youngest player to reach top 10 global ranking',
            'Most eliminations in a single tournament',
            'Consistent top 3 finisher in major tournaments',
            'Team captain for multiple championship wins',
            'Rookie of the Year 2024'
        ];
        
        $numAchievements = rand(1, 4);
        $selected = array_rand(array_flip($achievements), $numAchievements);
        
        return is_array($selected) ? $selected : [$selected];
    }

    private function generateTeamHistory($playerId)
    {
        if (rand(0, 100) < 70) { // 70% chance of having team history
            return [];
        }
        
        return [
            [
                'team_name' => 'Previous Team ' . rand(1, 100),
                'join_date' => $this->randomDate('2023-01-01', '2023-12-31'),
                'leave_date' => $this->randomDate('2023-06-01', '2024-01-01'),
                'notes' => 'Transferred to current team for better opportunities'
            ]
        ];
    }
}

// Execute the generator
$generator = new MarvelRivalsDataGenerator();
$generator->generate();