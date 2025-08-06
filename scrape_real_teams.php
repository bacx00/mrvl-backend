<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;

echo "Starting real team scraping from Liquipedia...\n\n";

// List of actual teams to scrape
$teams = [
    '100 Thieves' => '/marvelrivals/100_Thieves',
    '3BL Esports' => '/marvelrivals/3BL_Esports',
    'Al Qadsiah' => '/marvelrivals/Al_Qadsiah',
    'All Business' => '/marvelrivals/All_Business',
    'Arrival Seven' => '/marvelrivals/Arrival_Seven',
    'Astronic Esports' => '/marvelrivals/Astronic_Esports',
    'Brr Brr Patapim' => '/marvelrivals/Brr_Brr_Patapim',
    'Cafe Noir' => '/marvelrivals/Cafe_Noir',
    'Citadel Gaming' => '/marvelrivals/Citadel_Gaming',
    'Crazy Raccoon' => '/marvelrivals/Crazy_Raccoon',
    'Cumberland University' => '/marvelrivals/Cumberland_University',
    'DarkZero' => '/marvelrivals/DarkZero',
    'DUSTY' => '/marvelrivals/DUSTY',
    'EHOME' => '/marvelrivals/EHOME',
    'ENVY' => '/marvelrivals/ENVY',
    'Ex Oblivione' => '/marvelrivals/Ex_Oblivione',
    'FL eSports Club' => '/marvelrivals/FL_eSports_Club',
    'FlyQuest' => '/marvelrivals/FlyQuest',
    'Fnatic' => '/marvelrivals/Fnatic',
    'FURY' => '/marvelrivals/FURY',
    'Gen.G Esports' => '/marvelrivals/Gen.G_Esports',
    'Ground Zero Gaming' => '/marvelrivals/Ground_Zero_Gaming',
    'InControl' => '/marvelrivals/InControl',
    'Kanga Esports' => '/marvelrivals/Kanga_Esports',
    'LGD Gaming' => '/marvelrivals/LGD_Gaming',
    'Luminosity Gaming EU' => '/marvelrivals/Luminosity_Gaming_EU',
    'Luminosity Gaming NA' => '/marvelrivals/Luminosity_Gaming_NA',
    'Nova Esports' => '/marvelrivals/Nova_Esports',
    'NTMR' => '/marvelrivals/NTMR',
    'OG' => '/marvelrivals/OG',
    'OG Seed' => '/marvelrivals/OG_Seed',
    'OUG' => '/marvelrivals/OUG',
    'Project EVERSIO' => '/marvelrivals/Project_EVERSIO',
    'Rad Esports' => '/marvelrivals/Rad_Esports',
    'Rad EU' => '/marvelrivals/Rad_EU',
    'REJECT' => '/marvelrivals/REJECT',
    'Rival Esports' => '/marvelrivals/Rival_Esports',
    'RIZON' => '/marvelrivals/RIZON',
    'Sentinels' => '/marvelrivals/Sentinels',
    'Shikigami' => '/marvelrivals/Shikigami',
    'SHROUD-X' => '/marvelrivals/SHROUD-X',
    'SLZZ' => '/marvelrivals/SLZZ',
    'Solaris' => '/marvelrivals/Solaris',
    'St. Clair College' => '/marvelrivals/St._Clair_College',
    'Steam Engines' => '/marvelrivals/Steam_Engines',
    'Supernova' => '/marvelrivals/Supernova',
    'Tayun Gaming' => '/marvelrivals/Tayun_Gaming',
    'Team Nemesis' => '/marvelrivals/Team_Nemesis',
    'Team Peps' => '/marvelrivals/Team_Peps',
    'TEAM1' => '/marvelrivals/TEAM1',
    'The Vicious' => '/marvelrivals/The_Vicious',
    'Twisted Minds' => '/marvelrivals/Twisted_Minds',
    'UwUfps' => '/marvelrivals/UwUfps',
    'Virtus.pro' => '/marvelrivals/Virtus.pro',
    'YFP' => '/marvelrivals/YFP',
    'Zero Tenacity' => '/marvelrivals/Zero_Tenacity',
    'ZERO.PERCENT' => '/marvelrivals/ZERO.PERCENT'
];

$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
];

DB::beginTransaction();

try {
    // Clear existing data
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    Player::truncate();
    Team::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    
    $totalTeams = 0;
    $totalPlayers = 0;
    
    foreach ($teams as $teamName => $teamPath) {
        echo "Processing: $teamName\n";
        
        $url = 'https://liquipedia.net' . $teamPath;
        $response = Http::withHeaders($headers)->get($url);
        
        if (!$response->successful()) {
            echo "  ✗ Failed to fetch page\n";
            continue;
        }
        
        $crawler = new Crawler($response->body());
        
        // Extract team info
        $teamData = [
            'name' => $teamName,
            'short_name' => generateShortName($teamName),
            'liquipedia_url' => $url,
            'status' => 'active',
            'game' => 'marvel_rivals',
            'platform' => 'PC',
            'rating' => 1500,
            'earnings' => 0
        ];
        
        // Extract region
        $regionNode = $crawler->filter('.infobox-cell-2:contains("Region") + .infobox-cell-2')->first();
        if ($regionNode->count() > 0) {
            $teamData['region'] = normalizeRegion($regionNode->text());
        } else {
            $teamData['region'] = 'INT';
        }
        
        // Extract country
        $countryNode = $crawler->filter('.infobox-cell-2:contains("Location") + .infobox-cell-2')->first();
        if ($countryNode->count() > 0) {
            $flagImg = $countryNode->filter('img[alt]')->first();
            if ($flagImg->count() > 0) {
                $teamData['country'] = trim($flagImg->attr('alt'));
            } else {
                $teamData['country'] = trim($countryNode->text());
            }
        }
        
        // Extract logo
        $logoNode = $crawler->filter('.infobox-image img')->first();
        if ($logoNode->count() > 0) {
            $src = $logoNode->attr('src');
            if (strpos($src, '//') === 0) {
                $teamData['logo'] = 'https:' . $src;
            } else {
                $teamData['logo'] = $src;
            }
        }
        
        // Extract social media
        $social = [];
        $crawler->filter('.infobox-icons a, .infobox-cell-2 a[href]')->each(function ($node) use (&$social) {
            $href = $node->attr('href');
            
            if (strpos($href, 'twitter.com') !== false || strpos($href, 'x.com') !== false) {
                $social['twitter'] = $href;
            } elseif (strpos($href, 'instagram.com') !== false) {
                $social['instagram'] = $href;
            } elseif (strpos($href, 'youtube.com') !== false) {
                $social['youtube'] = $href;
            } elseif (strpos($href, 'twitch.tv') !== false) {
                $social['twitch'] = $href;
            } elseif (strpos($href, 'discord.gg') !== false || strpos($href, 'discord.com') !== false) {
                $social['discord'] = $href;
            } elseif (strpos($href, 'facebook.com') !== false) {
                $social['facebook'] = $href;
            } elseif (strpos($href, 'weibo.com') !== false) {
                $social['weibo'] = $href;
            } elseif (strpos($href, 'vk.com') !== false) {
                $social['vk'] = $href;
            } elseif (strpos($href, 't.me') !== false || strpos($href, 'telegram') !== false) {
                $social['telegram'] = $href;
            } elseif (strpos($href, 'tiktok.com') !== false) {
                $social['tiktok'] = $href;
            } elseif (strpos($href, 'reddit.com') !== false) {
                $social['reddit'] = $href;
            }
        });
        
        // Website
        $websiteNode = $crawler->filter('.infobox-cell-2:contains("Website") + .infobox-cell-2 a')->first();
        if ($websiteNode->count() > 0) {
            $teamData['website'] = $websiteNode->attr('href');
        }
        
        // Apply social media
        foreach (['twitter', 'instagram', 'youtube', 'twitch', 'discord', 'facebook'] as $platform) {
            if (isset($social[$platform])) {
                $teamData[$platform] = $social[$platform];
            }
        }
        
        // Extract earnings
        $earningsNode = $crawler->filter('.infobox-cell-2:contains("Approx. Total Winnings") + .infobox-cell-2')->first();
        if ($earningsNode->count() > 0) {
            $text = $earningsNode->text();
            if (preg_match('/\$?([\d,]+)/', $text, $matches)) {
                $teamData['earnings'] = intval(str_replace(',', '', $matches[1]));
            }
        }
        
        // Extract founded date
        $foundedNode = $crawler->filter('.infobox-cell-2:contains("Created") + .infobox-cell-2')->first();
        if ($foundedNode->count() > 0) {
            $teamData['founded'] = trim($foundedNode->text());
        }
        
        // Extract coach
        $crawler->filter('table.roster-card tr, .wikitable tr')->each(function ($row) use (&$teamData) {
            $positionCell = $row->filter('td:nth-child(3), th:contains("Position") + td')->first();
            if ($positionCell->count() > 0 && stripos($positionCell->text(), 'coach') !== false) {
                $nameCell = $row->filter('td:first-child a, td:nth-child(2) a')->first();
                if ($nameCell->count() > 0) {
                    $teamData['coach'] = trim($nameCell->text());
                }
            }
        });
        
        // Create team
        $team = Team::create($teamData);
        $totalTeams++;
        
        echo "  ✓ Team created\n";
        echo "  ✓ Social: " . implode(', ', array_keys($social)) . "\n";
        
        // Extract players
        $playersFound = 0;
        $crawler->filter('table.roster-card tr, .wikitable.roster tr, h2:contains("Player Roster") + .table-responsive table tr')->each(function ($row) use ($team, &$playersFound, &$totalPlayers) {
            // Skip header rows
            if ($row->filter('th')->count() > 0) {
                return;
            }
            
            // Check if this is a player row (not coach/manager)
            $positionCell = $row->filter('td:nth-child(3)')->first();
            if ($positionCell->count() > 0) {
                $position = strtolower(trim($positionCell->text()));
                if (strpos($position, 'coach') !== false || strpos($position, 'manager') !== false) {
                    return;
                }
            }
            
            $playerData = [
                'team_id' => $team->id,
                'status' => 'active',
                'rating' => $team->rating - rand(50, 150),
                'earnings' => 0,
                'region' => $team->region,
                'country' => $team->country,
                'main_hero' => '',
                'skill_rating' => 0
            ];
            
            // Player IGN
            $ignCell = $row->filter('td:first-child a, td:nth-child(2) a')->first();
            if ($ignCell->count() > 0) {
                $playerData['name'] = trim($ignCell->text());
                $playerData['username'] = $playerData['name'];
                $playerData['liquipedia_url'] = 'https://liquipedia.net' . $ignCell->attr('href');
            } else {
                // Try without link
                $ignCell = $row->filter('td:first-child, td:nth-child(2)')->first();
                if ($ignCell->count() > 0) {
                    $playerData['name'] = trim($ignCell->text());
                    $playerData['username'] = $playerData['name'];
                }
            }
            
            if (empty($playerData['name'])) {
                return;
            }
            
            // Real name
            $nameCell = $row->filter('td:nth-child(2)')->first();
            if ($nameCell->count() === 0) {
                $nameCell = $row->filter('td:nth-child(3)')->first();
            }
            if ($nameCell->count() > 0) {
                $realName = trim($nameCell->text());
                if (!empty($realName) && $realName !== $playerData['name']) {
                    $playerData['real_name'] = $realName;
                }
            }
            
            // Position/Role
            if ($positionCell->count() > 0) {
                $playerData['role'] = normalizeRole(trim($positionCell->text()));
            } else {
                $playerData['role'] = 'flex';
            }
            
            // Country
            $countryImg = $row->filter('img[alt*="flag"], .flag img')->first();
            if ($countryImg->count() > 0) {
                $playerData['country'] = trim($countryImg->attr('alt'));
            }
            
            // Set country from team if not found
            if (empty($playerData['country'])) {
                $playerData['country'] = $team->country ?? 'International';
            }
            
            $playerData['country_flag'] = getCountryFlag($playerData['country']);
            
            // Create player
            Player::create($playerData);
            $playersFound++;
            $totalPlayers++;
        });
        
        echo "  ✓ Players found: $playersFound\n\n";
        sleep(1); // Rate limiting
    }
    
    DB::commit();
    
    echo "\n=== IMPORT SUMMARY ===\n";
    echo "Total Teams: $totalTeams\n";
    echo "Total Players: $totalPlayers\n";
    
    // Show team distribution
    $distribution = Team::selectRaw('region, count(*) as count')
        ->groupBy('region')
        ->pluck('count', 'region');
    
    echo "\nTeams by Region:\n";
    foreach ($distribution as $region => $count) {
        echo "  $region: $count teams\n";
    }
    
    // Show teams with most social media
    echo "\nTeams with complete social media:\n";
    Team::whereNotNull('twitter')
        ->whereNotNull('instagram')
        ->whereNotNull('youtube')
        ->get(['name', 'twitter', 'instagram', 'youtube'])
        ->each(function($team) {
            echo "  {$team->name}\n";
        });
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

function generateShortName($teamName) {
    // Keep regional identifiers if present
    $hasRegion = false;
    $regionSuffix = '';
    if (preg_match('/\s+(NA|EU|SEA|OCE|MENA|CN|KR|JP|SA|CIS|ASIA)$/i', $teamName, $matches)) {
        $hasRegion = true;
        $regionSuffix = strtoupper($matches[1]);
        // Remove the region from the name for processing
        $teamName = preg_replace('/\s+(NA|EU|SEA|OCE|MENA|CN|KR|JP|SA|CIS|ASIA)$/i', '', $teamName);
    }
    
    // Remove common suffixes
    $name = str_replace([' Esports', ' Gaming', ' Esports Club', ' Team', ' eSports'], '', $teamName);
    
    // Generate short name
    $short = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
    
    // Add region suffix if it was present
    if ($hasRegion) {
        $short = substr($short, 0, 7) . $regionSuffix;
    }
    
    // Ensure uniqueness
    $counter = 1;
    $baseName = substr($short, 0, 8);
    while (Team::where('short_name', $short)->exists()) {
        $short = $baseName . $counter;
        $counter++;
    }
    
    return substr($short, 0, 10);
}

function normalizeRegion($region) {
    $regionMap = [
        'North America' => 'NA',
        'Europe' => 'EU',
        'Asia' => 'ASIA',
        'China' => 'CN',
        'Oceania' => 'OCE',
        'South America' => 'SA',
        'Middle East' => 'MENA',
        'CIS' => 'CIS',
        'Korea' => 'KR',
        'Japan' => 'JP',
        'Southeast Asia' => 'SEA',
        'Americas' => 'AMERICAS',
        'Asia-Pacific' => 'APAC',
        'Middle East & North Africa' => 'MENA'
    ];
    
    foreach ($regionMap as $long => $short) {
        if (stripos($region, $long) !== false) {
            return $short;
        }
    }
    
    return strlen($region) <= 10 ? strtoupper($region) : 'INT';
}

function normalizeRole($role) {
    $role = strtolower(trim($role));
    
    $roleMap = [
        'dps' => 'duelist',
        'damage' => 'duelist',
        'duelist' => 'duelist',
        'tank' => 'vanguard',
        'vanguard' => 'vanguard',
        'support' => 'strategist',
        'healer' => 'strategist',
        'strategist' => 'strategist',
        'flex' => 'flex',
        'substitute' => 'substitute',
        'sub' => 'substitute'
    ];
    
    return $roleMap[$role] ?? 'flex';
}

function getCountryFlag($country) {
    $flags = [
        'United States' => '🇺🇸',
        'Canada' => '🇨🇦',
        'Mexico' => '🇲🇽',
        'Brazil' => '🇧🇷',
        'Argentina' => '🇦🇷',
        'Chile' => '🇨🇱',
        'United Kingdom' => '🇬🇧',
        'Germany' => '🇩🇪',
        'France' => '🇫🇷',
        'Spain' => '🇪🇸',
        'Italy' => '🇮🇹',
        'Netherlands' => '🇳🇱',
        'Belgium' => '🇧🇪',
        'Sweden' => '🇸🇪',
        'Denmark' => '🇩🇰',
        'Norway' => '🇳🇴',
        'Finland' => '🇫🇮',
        'Poland' => '🇵🇱',
        'Russia' => '🇷🇺',
        'Ukraine' => '🇺🇦',
        'Turkey' => '🇹🇷',
        'Armenia' => '🇦🇲',
        'Iceland' => '🇮🇸',
        'South Korea' => '🇰🇷',
        'Japan' => '🇯🇵',
        'China' => '🇨🇳',
        'Taiwan' => '🇹🇼',
        'Singapore' => '🇸🇬',
        'Australia' => '🇦🇺',
        'New Zealand' => '🇳🇿',
        'Saudi Arabia' => '🇸🇦',
        'United Arab Emirates' => '🇦🇪',
        'Egypt' => '🇪🇬',
        'Malta' => '🇲🇹',
        'Malaysia' => '🇲🇾',
        'Philippines' => '🇵🇭',
        'Indonesia' => '🇮🇩',
        'Thailand' => '🇹🇭',
        'Vietnam' => '🇻🇳',
        'India' => '🇮🇳',
        'Qatar' => '🇶🇦',
        'Kuwait' => '🇰🇼',
        'Lebanon' => '🇱🇧',
        'Jordan' => '🇯🇴',
        'Peru' => '🇵🇪',
        'Colombia' => '🇨🇴',
        'Venezuela' => '🇻🇪',
        'Uruguay' => '🇺🇾',
        'Puerto Rico' => '🇵🇷'
    ];
    
    return $flags[$country] ?? '🌍';
}