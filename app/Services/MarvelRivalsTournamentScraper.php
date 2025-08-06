<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class MarvelRivalsTournamentScraper
{
    private $baseUrl = 'https://liquipedia.net';
    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate',
        'Connection' => 'keep-alive',
    ];
    
    private $tournaments = [
        'NA_Invitational' => '/marvelrivals/Marvel_Rivals_Invitational/2025/North_America',
        'EMEA_Ignite' => '/marvelrivals/MR_Ignite/2025/Stage_1/EMEA',
        'Asia_Ignite' => '/marvelrivals/MR_Ignite/2025/Stage_1/Asia',
        'Americas_Ignite' => '/marvelrivals/MR_Ignite/2025/Stage_1/Americas',
        'OCE_Ignite' => '/marvelrivals/MR_Ignite/2025/Stage_1/Oceania'
    ];
    
    private $countryFlags = [];
    private $scrapedTeams = [];
    private $scrapedPlayers = [];

    public function scrapeAllTournaments()
    {
        echo "Starting Marvel Rivals tournament data scraping...\n\n";
        
        // Load country flags mapping
        $this->loadCountryFlags();
        
        foreach ($this->tournaments as $name => $path) {
            echo "Scraping tournament: $name\n";
            try {
                $this->scrapeTournament($name, $path);
                sleep(2); // Rate limiting
            } catch (\Exception $e) {
                echo "Error scraping $name: " . $e->getMessage() . "\n";
                Log::error("Tournament scraping error", ['tournament' => $name, 'error' => $e->getMessage()]);
            }
        }
        
        // Import all collected data
        $this->importAllData();
        
        echo "\nTournament scraping completed!\n";
    }

    private function scrapeTournament($tournamentName, $path)
    {
        $url = $this->baseUrl . $path;
        $response = Http::withHeaders($this->headers)->get($url);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch tournament page: $url");
        }
        
        $crawler = new Crawler($response->body());
        
        // Extract tournament info
        $tournamentData = $this->extractTournamentInfo($crawler, $tournamentName);
        
        // Extract participating teams
        $teams = $this->extractParticipatingTeams($crawler);
        
        echo "  Found " . count($teams) . " teams\n";
        
        // For each team, get detailed roster
        foreach ($teams as $teamName => $teamData) {
            echo "  Scraping team: $teamName\n";
            try {
                $this->scrapeTeamDetails($teamName, $teamData['url'] ?? null);
                sleep(1); // Rate limiting
            } catch (\Exception $e) {
                echo "    Error: " . $e->getMessage() . "\n";
            }
        }
    }

    private function extractParticipatingTeams($crawler)
    {
        $teams = [];
        
        // Look for team cards in various formats
        $selectors = [
            '.teamcard',
            '.grouptable .teamname',
            '.bracket-team',
            '.participant-team',
            '.wikitable td a[href*="/marvelrivals/"]'
        ];
        
        foreach ($selectors as $selector) {
            $crawler->filter($selector)->each(function (Crawler $node) use (&$teams) {
                $teamName = trim($node->text());
                $href = null;
                
                // Try to get the link
                if ($node->nodeName() === 'a') {
                    $href = $node->attr('href');
                } else {
                    $link = $node->filter('a')->first();
                    if ($link->count()) {
                        $href = $link->attr('href');
                    }
                }
                
                if (!empty($teamName) && $teamName !== 'TBD' && strlen($teamName) > 1) {
                    $teams[$teamName] = [
                        'name' => $teamName,
                        'url' => $href
                    ];
                }
            });
        }
        
        return $teams;
    }

    private function scrapeTeamDetails($teamName, $teamUrl)
    {
        if (!$teamUrl) {
            echo "    No URL for team $teamName\n";
            return;
        }
        
        $fullUrl = strpos($teamUrl, 'http') === 0 ? $teamUrl : $this->baseUrl . $teamUrl;
        $response = Http::withHeaders($this->headers)->get($fullUrl);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch team page");
        }
        
        $crawler = new Crawler($response->body());
        
        $teamData = [
            'name' => $teamName,
            'liquipedia_url' => $fullUrl,
            'region' => $this->extractRegion($crawler),
            'country' => $this->extractCountry($crawler),
            'country_flag' => null,
            'social_media' => $this->extractSocialMedia($crawler),
            'earnings' => $this->extractEarnings($crawler),
            'logo_url' => $this->extractLogo($crawler),
            'founded' => $this->extractFounded($crawler),
            'roster' => []
        ];
        
        // Set country flag
        if ($teamData['country']) {
            $teamData['country_flag'] = $this->getCountryFlag($teamData['country']);
        }
        
        // Extract full roster including coaches and staff
        $teamData['roster'] = $this->extractFullRoster($crawler, $teamName);
        
        $this->scrapedTeams[$teamName] = $teamData;
        
        echo "    ✓ Region: {$teamData['region']}, Country: {$teamData['country']}\n";
        echo "    ✓ Roster: " . count($teamData['roster']) . " members\n";
    }

    private function extractFullRoster($crawler, $teamName)
    {
        $roster = [];
        $positionOrder = 1;
        
        // Extract active roster
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Active Squad', 'player');
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Active Roster', 'player');
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Current Roster', 'player');
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Player Roster', 'player');
        
        // Extract coaches and staff
        $this->extractStaffSection($crawler, $roster, $positionOrder, 'Coaching Staff', 'coach');
        $this->extractStaffSection($crawler, $roster, $positionOrder, 'Organization', 'staff');
        
        // Extract bench players
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Inactive', 'inactive');
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Benched', 'bench');
        $this->extractRosterSection($crawler, $roster, $positionOrder, 'Substitute', 'substitute');
        
        // Fallback: try to find any player tables
        if (empty($roster)) {
            $crawler->filter('.roster-card, .teamcard-inner')->each(function (Crawler $card) use (&$roster, &$positionOrder, $teamName) {
                $this->extractPlayerFromCard($card, $roster, $positionOrder, $teamName, 'player');
            });
        }
        
        return $roster;
    }

    private function extractRosterSection($crawler, &$roster, &$positionOrder, $sectionName, $defaultPosition)
    {
        // Look for section headers
        $crawler->filter('h3, h4, .mw-headline')->each(function (Crawler $header) use (&$roster, &$positionOrder, $sectionName, $defaultPosition) {
            if (stripos($header->text(), $sectionName) !== false) {
                // Find the next table or roster cards
                $nextElement = $header->parents()->first()->nextAll();
                
                $nextElement->filter('table, .roster-card')->first()->filter('tr, .roster-card')->each(function (Crawler $row) use (&$roster, &$positionOrder, $defaultPosition) {
                    $this->extractPlayerFromRow($row, $roster, $positionOrder, $defaultPosition);
                });
            }
        });
    }

    private function extractStaffSection($crawler, &$roster, &$positionOrder, $sectionName, $defaultRole)
    {
        $crawler->filter('.infobox-cell-2')->each(function (Crawler $cell) use (&$roster, &$positionOrder, $defaultRole) {
            $label = trim($cell->text());
            
            $positions = [
                'Coach' => 'coach',
                'Head Coach' => 'coach',
                'Assistant Coach' => 'assistant_coach',
                'Manager' => 'manager',
                'General Manager' => 'manager',
                'Analyst' => 'analyst',
                'Strategic Coach' => 'analyst'
            ];
            
            foreach ($positions as $searchTerm => $position) {
                if (stripos($label, $searchTerm) !== false) {
                    $valueCell = $cell->nextAll()->first();
                    if ($valueCell->count()) {
                        $this->extractStaffFromCell($valueCell, $roster, $positionOrder, $position);
                    }
                }
            }
        });
    }

    private function extractPlayerFromRow($row, &$roster, &$positionOrder, $position)
    {
        $username = $realName = $country = $role = $jerseyNumber = '';
        
        // Extract player link and username
        $playerLink = $row->filter('a[href*="/marvelrivals/"]')->first();
        if ($playerLink->count()) {
            $username = trim($playerLink->text());
            
            // Skip if it's not a player
            if (empty($username) || strlen($username) < 2 || 
                in_array(strtolower($username), ['tbd', 'tba', '-', 'none'])) {
                return;
            }
        }
        
        // Extract real name (usually in parentheses or separate column)
        $row->filter('td')->each(function (Crawler $cell) use (&$realName, &$country, &$role, &$jerseyNumber) {
            $text = trim($cell->text());
            
            // Real name in parentheses
            if (preg_match('/\(([^)]+)\)/', $text, $matches)) {
                $realName = $matches[1];
            }
            
            // Country flag
            $flag = $cell->filter('img[alt], .flag img')->first();
            if ($flag->count()) {
                $country = $flag->attr('alt') ?: $flag->attr('title');
            }
            
            // Jersey number
            if (preg_match('/^#?(\d+)$/', $text, $matches)) {
                $jerseyNumber = $matches[1];
            }
            
            // Role (Vanguard, Duelist, Strategist)
            if (in_array($text, ['Vanguard', 'Duelist', 'Strategist', 'Flex', 'Support', 'Tank'])) {
                $role = $text;
            }
        });
        
        if ($username) {
            $playerKey = strtolower($username);
            $roster[$playerKey] = [
                'username' => $username,
                'real_name' => $realName,
                'country' => $country,
                'country_flag' => $this->getCountryFlag($country),
                'role' => $this->normalizeRole($role),
                'team_position' => $position,
                'position_order' => $positionOrder++,
                'jersey_number' => $jerseyNumber
            ];
            
            $this->scrapedPlayers[$playerKey] = $roster[$playerKey];
        }
    }

    private function extractStaffFromCell($cell, &$roster, &$positionOrder, $position)
    {
        $links = $cell->filter('a');
        
        if ($links->count()) {
            $links->each(function (Crawler $link) use (&$roster, &$positionOrder, $position) {
                $name = trim($link->text());
                if (!empty($name) && strlen($name) > 2) {
                    $staffKey = strtolower($name);
                    $roster[$staffKey] = [
                        'username' => $name,
                        'real_name' => $name, // For staff, username often is real name
                        'country' => '',
                        'country_flag' => '',
                        'role' => 'Support', // Default role for non-players
                        'team_position' => $position,
                        'position_order' => $positionOrder++,
                        'jersey_number' => null
                    ];
                }
            });
        } else {
            // Plain text
            $name = trim($cell->text());
            if (!empty($name) && strlen($name) > 2 && !in_array(strtolower($name), ['tbd', 'tba', '-'])) {
                $staffKey = strtolower($name);
                $roster[$staffKey] = [
                    'username' => $name,
                    'real_name' => $name,
                    'country' => '',
                    'country_flag' => '',
                    'role' => 'Support',
                    'team_position' => $position,
                    'position_order' => $positionOrder++,
                    'jersey_number' => null
                ];
            }
        }
    }

    private function extractPlayerFromCard($card, &$roster, &$positionOrder, $teamName, $position)
    {
        $username = trim($card->filter('.player-name, .name, a')->first()->text());
        if (empty($username) || strlen($username) < 2) return;
        
        $playerData = [
            'username' => $username,
            'real_name' => '',
            'country' => '',
            'country_flag' => '',
            'role' => 'Flex',
            'team_position' => $position,
            'position_order' => $positionOrder++,
            'jersey_number' => null
        ];
        
        // Try to extract country from flag
        $flag = $card->filter('.flag img, img[alt]')->first();
        if ($flag->count()) {
            $playerData['country'] = $flag->attr('alt') ?: $flag->attr('title');
            $playerData['country_flag'] = $this->getCountryFlag($playerData['country']);
        }
        
        $playerKey = strtolower($username);
        $roster[$playerKey] = $playerData;
        $this->scrapedPlayers[$playerKey] = $playerData;
    }

    private function normalizeRole($role)
    {
        $roleMap = [
            'tank' => 'Vanguard',
            'support' => 'Strategist',
            'dps' => 'Duelist',
            'damage' => 'Duelist',
            'flex' => 'Flex',
            'sub' => 'Sub'
        ];
        
        $normalized = $roleMap[strtolower($role)] ?? $role;
        
        if (!in_array($normalized, ['Vanguard', 'Duelist', 'Strategist', 'Flex', 'Sub'])) {
            return 'Flex';
        }
        
        return $normalized;
    }

    private function extractRegion($crawler)
    {
        $regionNode = $crawler->filter('.infobox-cell-2:contains("Region") + .infobox-cell-2')->first();
        if ($regionNode->count() > 0) {
            return $this->normalizeRegion(trim($regionNode->text()));
        }
        
        return 'INT';
    }

    private function normalizeRegion($region)
    {
        $regionMap = [
            'north america' => 'NA',
            'europe' => 'EU',
            'asia' => 'ASIA',
            'china' => 'CN',
            'oceania' => 'OCE',
            'south america' => 'SA',
            'middle east' => 'MENA',
            'americas' => 'NA',
            'emea' => 'EU',
            'apac' => 'ASIA'
        ];
        
        $normalized = $regionMap[strtolower($region)] ?? $region;
        
        return in_array($normalized, ['NA', 'EU', 'ASIA', 'CN', 'OCE', 'SA', 'MENA']) ? $normalized : 'INT';
    }

    private function extractCountry($crawler)
    {
        $countryNode = $crawler->filter('.infobox-cell-2:contains("Location") + .infobox-cell-2')->first();
        if ($countryNode->count() > 0) {
            return trim($countryNode->text());
        }
        
        return '';
    }

    private function extractSocialMedia($crawler)
    {
        $social = [];
        
        $crawler->filter('.infobox-icons a, .external-links a')->each(function (Crawler $link) use (&$social) {
            $href = $link->attr('href');
            $title = $link->attr('title') ?: '';
            
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
            } elseif (strpos($href, 'tiktok.com') !== false) {
                $social['tiktok'] = $href;
            }
        });
        
        return $social;
    }

    private function extractEarnings($crawler)
    {
        $earningsNode = $crawler->filter('.infobox-cell-2:contains("Approx. Total Winnings") + .infobox-cell-2')->first();
        if ($earningsNode->count() > 0) {
            $earnings = trim($earningsNode->text());
            // Extract numeric value
            if (preg_match('/\$?([\d,]+)/', $earnings, $matches)) {
                return (int)str_replace(',', '', $matches[1]);
            }
        }
        
        return 0;
    }

    private function extractLogo($crawler)
    {
        $logo = $crawler->filter('.infobox-image img')->first();
        if ($logo->count() > 0) {
            $src = $logo->attr('src');
            if (strpos($src, '//') === 0) {
                return 'https:' . $src;
            } elseif (strpos($src, '/') === 0) {
                return $this->baseUrl . $src;
            }
            return $src;
        }
        
        return null;
    }

    private function extractFounded($crawler)
    {
        $foundedNode = $crawler->filter('.infobox-cell-2:contains("Created") + .infobox-cell-2')->first();
        if ($foundedNode->count() > 0) {
            return trim($foundedNode->text());
        }
        
        return null;
    }

    private function extractTournamentInfo($crawler, $name)
    {
        return [
            'name' => $name,
            'prize_pool' => $this->extractPrizePool($crawler),
            'date' => $this->extractDate($crawler),
            'teams_count' => $this->extractTeamsCount($crawler)
        ];
    }

    private function extractPrizePool($crawler)
    {
        $prizeNode = $crawler->filter('.infobox-cell-2:contains("Prize Pool") + .infobox-cell-2')->first();
        if ($prizeNode->count() > 0) {
            $prize = trim($prizeNode->text());
            if (preg_match('/\$?([\d,]+)/', $prize, $matches)) {
                return (int)str_replace(',', '', $matches[1]);
            }
        }
        
        return 0;
    }

    private function extractDate($crawler)
    {
        $dateNode = $crawler->filter('.infobox-cell-2:contains("Date") + .infobox-cell-2')->first();
        if ($dateNode->count() > 0) {
            return trim($dateNode->text());
        }
        
        return null;
    }

    private function extractTeamsCount($crawler)
    {
        $teamsNode = $crawler->filter('.infobox-cell-2:contains("Number of Teams") + .infobox-cell-2')->first();
        if ($teamsNode->count() > 0) {
            $count = trim($teamsNode->text());
            if (preg_match('/(\d+)/', $count, $matches)) {
                return (int)$matches[1];
            }
        }
        
        return 0;
    }

    private function loadCountryFlags()
    {
        // Country to flag emoji mapping
        $this->countryFlags = [
            'United States' => '🇺🇸',
            'USA' => '🇺🇸',
            'Canada' => '🇨🇦',
            'Mexico' => '🇲🇽',
            'United Kingdom' => '🇬🇧',
            'UK' => '🇬🇧',
            'Germany' => '🇩🇪',
            'France' => '🇫🇷',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Sweden' => '🇸🇪',
            'Denmark' => '🇩🇰',
            'Norway' => '🇳🇴',
            'Finland' => '🇫🇮',
            'Netherlands' => '🇳🇱',
            'Belgium' => '🇧🇪',
            'Poland' => '🇵🇱',
            'Russia' => '🇷🇺',
            'Ukraine' => '🇺🇦',
            'Turkey' => '🇹🇷',
            'Greece' => '🇬🇷',
            'Portugal' => '🇵🇹',
            'Czech Republic' => '🇨🇿',
            'Austria' => '🇦🇹',
            'Switzerland' => '🇨🇭',
            'South Korea' => '🇰🇷',
            'Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Singapore' => '🇸🇬',
            'Thailand' => '🇹🇭',
            'Vietnam' => '🇻🇳',
            'Philippines' => '🇵🇭',
            'Indonesia' => '🇮🇩',
            'Malaysia' => '🇲🇾',
            'India' => '🇮🇳',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Peru' => '🇵🇪',
            'Colombia' => '🇨🇴',
            'Venezuela' => '🇻🇪',
            'Uruguay' => '🇺🇾',
            'Saudi Arabia' => '🇸🇦',
            'United Arab Emirates' => '🇦🇪',
            'UAE' => '🇦🇪',
            'Kuwait' => '🇰🇼',
            'Qatar' => '🇶🇦',
            'Egypt' => '🇪🇬',
            'Jordan' => '🇯🇴',
            'Lebanon' => '🇱🇧',
            'Israel' => '🇮🇱',
            'Ireland' => '🇮🇪',
            'Scotland' => '🏴󐁧󐁢󐁳󐁣󐁴󐁿',
            'Romania' => '🇷🇴',
            'Bulgaria' => '🇧🇬',
            'Hungary' => '🇭🇺',
            'Slovakia' => '🇸🇰',
            'Slovenia' => '🇸🇮',
            'Croatia' => '🇭🇷',
            'Serbia' => '🇷🇸',
            'Bosnia and Herzegovina' => '🇧🇦',
            'Albania' => '🇦🇱',
            'North Macedonia' => '🇲🇰',
            'Estonia' => '🇪🇪',
            'Latvia' => '🇱🇻',
            'Lithuania' => '🇱🇹',
            'Belarus' => '🇧🇾',
            'Moldova' => '🇲🇩',
            'Armenia' => '🇦🇲',
            'Georgia' => '🇬🇪',
            'Azerbaijan' => '🇦🇿',
            'Kazakhstan' => '🇰🇿',
            'Morocco' => '🇲🇦',
            'Tunisia' => '🇹🇳',
            'Algeria' => '🇩🇿',
            'South Africa' => '🇿🇦',
            'Pakistan' => '🇵🇰',
            'Bangladesh' => '🇧🇩',
            'Mongolia' => '🇲🇳',
            'Myanmar' => '🇲🇲',
            'Cambodia' => '🇰🇭',
            'Laos' => '🇱🇦'
        ];
    }

    private function getCountryFlag($country)
    {
        if (empty($country)) return '';
        
        return $this->countryFlags[$country] ?? 
               $this->countryFlags[ucfirst(strtolower($country))] ?? 
               '';
    }

    private function importAllData()
    {
        echo "\nImporting " . count($this->scrapedTeams) . " teams and " . count($this->scrapedPlayers) . " players...\n";
        
        DB::beginTransaction();
        
        try {
            // Import teams
            foreach ($this->scrapedTeams as $teamData) {
                $team = Team::updateOrCreate(
                    ['name' => $teamData['name']],
                    [
                        'region' => $teamData['region'],
                        'country' => $teamData['country'],
                        'country_flag' => $teamData['country_flag'],
                        'liquipedia_url' => $teamData['liquipedia_url'],
                        'logo_url' => $teamData['logo_url'],
                        'earnings' => $teamData['earnings'],
                        'founded' => $teamData['founded'],
                        'twitter' => $teamData['social_media']['twitter'] ?? null,
                        'instagram' => $teamData['social_media']['instagram'] ?? null,
                        'youtube' => $teamData['social_media']['youtube'] ?? null,
                        'twitch' => $teamData['social_media']['twitch'] ?? null,
                        'discord' => $teamData['social_media']['discord'] ?? null,
                        'facebook' => $teamData['social_media']['facebook'] ?? null,
                        'tiktok' => $teamData['social_media']['tiktok'] ?? null,
                        'social_media' => json_encode($teamData['social_media']),
                        'status' => 'active'
                    ]
                );
                
                // Import roster
                foreach ($teamData['roster'] as $playerData) {
                    Player::updateOrCreate(
                        [
                            'username' => $playerData['username'],
                            'team_id' => $team->id
                        ],
                        [
                            'real_name' => $playerData['real_name'] ?: $playerData['username'],
                            'country' => $playerData['country'],
                            'country_flag' => $playerData['country_flag'],
                            'flag' => $playerData['country_flag'],
                            'role' => $playerData['role'],
                            'team_position' => $playerData['team_position'],
                            'position_order' => $playerData['position_order'],
                            'jersey_number' => $playerData['jersey_number'],
                            'region' => $team->region,
                            'status' => $playerData['team_position'] === 'inactive' ? 'inactive' : 'active'
                        ]
                    );
                }
                
                echo "  ✓ Imported team: {$team->name} with " . count($teamData['roster']) . " members\n";
            }
            
            DB::commit();
            echo "\nData import completed successfully!\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}