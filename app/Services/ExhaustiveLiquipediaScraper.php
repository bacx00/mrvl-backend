<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ExhaustiveLiquipediaScraper
{
    private $baseUrl = 'https://liquipedia.net';
    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate',
        'Connection' => 'keep-alive',
    ];
    
    private $teamsToScrape = [];
    private $scrapedTeams = [];
    private $scrapedPlayers = [];

    public function scrapeAllTeamsAndPlayers()
    {
        echo "Starting exhaustive Liquipedia scraping for Marvel Rivals...\n\n";
        
        // First, get all teams from the teams portal
        $this->scrapeTeamsPortal();
        
        // Then scrape each team's page for detailed info
        $this->scrapeAllTeamPages();
        
        // Import all data
        $this->importAllData();
        
        echo "\nScraping completed!\n";
    }

    private function scrapeTeamsPortal()
    {
        echo "Fetching teams from Portal:Teams...\n";
        
        $response = Http::withHeaders($this->headers)->get($this->baseUrl . '/marvelrivals/Portal:Teams');
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch teams portal");
        }
        
        $crawler = new Crawler($response->body());
        
        // Find all team links
        $crawler->filter('.teamcard, .wikitable td a[href*="/marvelrivals/"]')->each(function (Crawler $node) {
            $href = $node->attr('href');
            $teamName = trim($node->text());
            
            if ($href && !empty($teamName) && strpos($href, '/marvelrivals/') !== false) {
                // Skip category and portal pages
                if (strpos($href, 'Category:') === false && 
                    strpos($href, 'Portal:') === false &&
                    strpos($href, 'Template:') === false) {
                    
                    $this->teamsToScrape[$teamName] = $href;
                }
            }
        });
        
        echo "Found " . count($this->teamsToScrape) . " teams to scrape\n";
    }

    private function scrapeAllTeamPages()
    {
        $count = 0;
        foreach ($this->teamsToScrape as $teamName => $teamUrl) {
            $count++;
            echo "\n[$count/" . count($this->teamsToScrape) . "] Scraping: $teamName\n";
            
            try {
                $this->scrapeTeamPage($teamName, $teamUrl);
                sleep(1); // Rate limiting
            } catch (\Exception $e) {
                echo "  Error scraping $teamName: " . $e->getMessage() . "\n";
            }
        }
    }

    private function scrapeTeamPage($teamName, $teamUrl)
    {
        $fullUrl = strpos($teamUrl, 'http') === 0 ? $teamUrl : $this->baseUrl . $teamUrl;
        $response = Http::withHeaders($this->headers)->get($fullUrl);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch team page");
        }
        
        $crawler = new Crawler($response->body());
        
        $teamData = [
            'name' => $teamName,
            'url' => $fullUrl,
            'region' => $this->extractRegion($crawler),
            'country' => $this->extractCountry($crawler),
            'social_media' => $this->extractSocialMedia($crawler),
            'earnings' => $this->extractEarnings($crawler),
            'logo' => $this->extractLogo($crawler),
            'founded' => $this->extractFounded($crawler),
            'coach' => $this->extractCoach($crawler),
            'manager' => $this->extractManager($crawler),
            'players' => [],
            'achievements' => $this->extractAchievements($crawler)
        ];
        
        // Extract roster
        $teamData['players'] = $this->extractRoster($crawler, $teamName);
        
        $this->scrapedTeams[] = $teamData;
        
        echo "  ✓ Found " . count($teamData['players']) . " players\n";
        echo "  ✓ Social: " . implode(', ', array_keys($teamData['social_media'])) . "\n";
    }

    private function extractRegion($crawler)
    {
        // Look for region in various places
        $regionNode = $crawler->filter('.infobox-cell-2:contains("Region") + .infobox-cell-2')->first();
        if ($regionNode->count() > 0) {
            $region = trim($regionNode->text());
            return $this->normalizeRegion($region);
        }
        
        // Try to find it in categories
        $crawler->filter('#mw-normal-catlinks a')->each(function ($node) use (&$region) {
            $text = $node->text();
            if (strpos($text, 'North American Teams') !== false) $region = 'NA';
            elseif (strpos($text, 'European Teams') !== false) $region = 'EU';
            elseif (strpos($text, 'Asian Teams') !== false) $region = 'ASIA';
            elseif (strpos($text, 'Chinese Teams') !== false) $region = 'CN';
            elseif (strpos($text, 'Oceanian Teams') !== false) $region = 'OCE';
            elseif (strpos($text, 'South American Teams') !== false) $region = 'SA';
            elseif (strpos($text, 'Middle Eastern Teams') !== false) $region = 'MENA';
        });
        
        return $region ?? 'INT';
    }

    private function extractCountry($crawler)
    {
        $countryNode = $crawler->filter('.infobox-cell-2:contains("Location") + .infobox-cell-2')->first();
        if ($countryNode->count() > 0) {
            // Extract country from flag image or text
            $flagImg = $countryNode->filter('img[alt]')->first();
            if ($flagImg->count() > 0) {
                return trim($flagImg->attr('alt'));
            }
            return trim($countryNode->text());
        }
        return null;
    }

    private function extractSocialMedia($crawler)
    {
        $social = [];
        
        // Check infobox links section
        $crawler->filter('.infobox-icons a, .infobox-cell-2 a[href]')->each(function ($node) use (&$social) {
            $href = $node->attr('href');
            $title = $node->attr('title') ?? '';
            
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
        
        // Also check for website
        $websiteNode = $crawler->filter('.infobox-cell-2:contains("Website") + .infobox-cell-2 a')->first();
        if ($websiteNode->count() > 0) {
            $social['website'] = $websiteNode->attr('href');
        }
        
        return $social;
    }

    private function extractEarnings($crawler)
    {
        $earningsNode = $crawler->filter('.infobox-cell-2:contains("Approx. Total Winnings") + .infobox-cell-2')->first();
        if ($earningsNode->count() > 0) {
            $text = $earningsNode->text();
            if (preg_match('/\$?([\d,]+)/', $text, $matches)) {
                return intval(str_replace(',', '', $matches[1]));
            }
        }
        return 0;
    }

    private function extractLogo($crawler)
    {
        $logoNode = $crawler->filter('.infobox-image img')->first();
        if ($logoNode->count() > 0) {
            $src = $logoNode->attr('src');
            if (strpos($src, '//') === 0) {
                return 'https:' . $src;
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

    private function extractCoach($crawler)
    {
        $coachInfo = null;
        
        // Look for coach in roster table
        $crawler->filter('table.roster-card tr, .wikitable tr')->each(function ($row) use (&$coachInfo) {
            $positionCell = $row->filter('td:nth-child(3), th:contains("Position") + td')->first();
            if ($positionCell->count() > 0 && stripos($positionCell->text(), 'coach') !== false) {
                $nameCell = $row->filter('td:first-child a, td:nth-child(2) a')->first();
                if ($nameCell->count() > 0) {
                    $coachInfo = [
                        'name' => trim($nameCell->text()),
                        'url' => $nameCell->attr('href')
                    ];
                }
            }
        });
        
        return $coachInfo;
    }

    private function extractManager($crawler)
    {
        $managerInfo = null;
        
        // Look for manager in roster table
        $crawler->filter('table.roster-card tr, .wikitable tr')->each(function ($row) use (&$managerInfo) {
            $positionCell = $row->filter('td:nth-child(3), th:contains("Position") + td')->first();
            if ($positionCell->count() > 0 && stripos($positionCell->text(), 'manager') !== false) {
                $nameCell = $row->filter('td:first-child a, td:nth-child(2) a')->first();
                if ($nameCell->count() > 0) {
                    $managerInfo = [
                        'name' => trim($nameCell->text()),
                        'url' => $nameCell->attr('href')
                    ];
                }
            }
        });
        
        return $managerInfo;
    }

    private function extractRoster($crawler, $teamName)
    {
        $players = [];
        
        // Find roster table
        $crawler->filter('table.roster-card tr, .wikitable.roster tr, h2:contains("Player Roster") + .table-responsive table tr')->each(function ($row) use (&$players, $teamName) {
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
            
            // Extract player info
            $playerData = $this->extractPlayerFromRow($row, $teamName);
            if ($playerData && !empty($playerData['ign'])) {
                $players[] = $playerData;
            }
        });
        
        return $players;
    }

    private function extractPlayerFromRow($row, $teamName)
    {
        $playerData = [
            'team' => $teamName,
            'joined_date' => null,
            'left_date' => null
        ];
        
        // Player IGN and link
        $ignCell = $row->filter('td:first-child a, td:nth-child(2) a')->first();
        if ($ignCell->count() > 0) {
            $playerData['ign'] = trim($ignCell->text());
            $playerData['liquipedia_url'] = $ignCell->attr('href');
        } else {
            // Try without link
            $ignCell = $row->filter('td:first-child, td:nth-child(2)')->first();
            if ($ignCell->count() > 0) {
                $playerData['ign'] = trim($ignCell->text());
            }
        }
        
        // Real name
        $nameCell = $row->filter('td:nth-child(2)')->first();
        if ($nameCell->count() === 0) {
            $nameCell = $row->filter('td:nth-child(3)')->first();
        }
        if ($nameCell->count() > 0) {
            $realName = trim($nameCell->text());
            if (!empty($realName) && $realName !== $playerData['ign']) {
                $playerData['real_name'] = $realName;
            }
        }
        
        // Position/Role
        $positionCell = $row->filter('td:nth-child(3), td:nth-child(4)')->first();
        if ($positionCell->count() > 0) {
            $playerData['role'] = $this->normalizeRole(trim($positionCell->text()));
        }
        
        // Country
        $countryImg = $row->filter('img[alt*="flag"], .flag img')->first();
        if ($countryImg->count() > 0) {
            $playerData['country'] = trim($countryImg->attr('alt'));
        }
        
        // Join date
        $joinCell = $row->filter('td:contains("-")')->last();
        if ($joinCell->count() > 0) {
            $dateText = trim($joinCell->text());
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $dateText, $matches)) {
                $playerData['joined_date'] = $matches[1];
            }
        }
        
        // Add to global players list for detailed scraping later
        if (!empty($playerData['ign']) && !empty($playerData['liquipedia_url'])) {
            $this->scrapedPlayers[$playerData['ign']] = $playerData;
        }
        
        return $playerData;
    }

    private function extractAchievements($crawler)
    {
        $achievements = [];
        
        // Look for achievements section
        $crawler->filter('.achievements-table tr, .wikitable.achievements tr')->each(function ($row) use (&$achievements) {
            $dateCell = $row->filter('td:first-child')->first();
            $placeCell = $row->filter('td:nth-child(2)')->first();
            $eventCell = $row->filter('td:nth-child(3) a')->first();
            
            if ($dateCell->count() > 0 && $placeCell->count() > 0 && $eventCell->count() > 0) {
                $achievements[] = [
                    'date' => trim($dateCell->text()),
                    'place' => trim($placeCell->text()),
                    'event' => trim($eventCell->text()),
                    'event_url' => $eventCell->attr('href')
                ];
            }
        });
        
        return $achievements;
    }

    private function normalizeRegion($region)
    {
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
            'Americas' => 'AMERICAS'
        ];
        
        foreach ($regionMap as $long => $short) {
            if (stripos($region, $long) !== false) {
                return $short;
            }
        }
        
        return strlen($region) <= 10 ? strtoupper($region) : 'INT';
    }

    private function normalizeRole($role)
    {
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

    private function importAllData()
    {
        echo "\n\nImporting data to database...\n";
        
        DB::beginTransaction();
        
        try {
            $teamCount = 0;
            $playerCount = 0;
            
            foreach ($this->scrapedTeams as $teamData) {
                // Create or update team
                $team = Team::updateOrCreate(
                    ['name' => $teamData['name']],
                    [
                        'short_name' => $this->generateShortName($teamData['name']),
                        'country' => $teamData['country'],
                        'region' => $teamData['region'],
                        'logo' => $teamData['logo'],
                        'founded' => $teamData['founded'],
                        'website' => $teamData['social_media']['website'] ?? null,
                        'twitter' => $teamData['social_media']['twitter'] ?? null,
                        'instagram' => $teamData['social_media']['instagram'] ?? null,
                        'youtube' => $teamData['social_media']['youtube'] ?? null,
                        'twitch' => $teamData['social_media']['twitch'] ?? null,
                        'discord' => $teamData['social_media']['discord'] ?? null,
                        'facebook' => $teamData['social_media']['facebook'] ?? null,
                        'earnings' => $teamData['earnings'],
                        'status' => 'active',
                        'game' => 'marvel_rivals',
                        'platform' => 'PC',
                        'rating' => $this->calculateInitialRating($teamData),
                        'coach' => $teamData['coach']['name'] ?? null,
                        'manager' => $teamData['manager']['name'] ?? null
                    ]
                );
                
                $teamCount++;
                
                // Import players
                foreach ($teamData['players'] as $playerData) {
                    $player = Player::updateOrCreate(
                        [
                            'name' => $playerData['ign'],
                            'team_id' => $team->id
                        ],
                        [
                            'username' => $playerData['ign'],
                            'real_name' => $playerData['real_name'] ?? null,
                            'country' => $playerData['country'] ?? $teamData['country'],
                            'country_flag' => $this->getCountryFlag($playerData['country'] ?? $teamData['country']),
                            'role' => $playerData['role'] ?? 'flex',
                            'region' => $teamData['region'],
                            'status' => 'active',
                            'rating' => $team->rating - rand(100, 200),
                            'liquipedia_url' => isset($playerData['liquipedia_url']) ? $this->baseUrl . $playerData['liquipedia_url'] : null,
                            'main_hero' => '',
                            'skill_rating' => 0
                        ]
                    );
                    
                    $playerCount++;
                }
            }
            
            DB::commit();
            
            echo "\n✓ Imported $teamCount teams and $playerCount players\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function generateShortName($teamName)
    {
        // Remove common suffixes
        $name = str_replace([' Esports', ' Gaming', ' Esports Club', ' Team'], '', $teamName);
        
        // Generate short name
        $short = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        
        // Ensure uniqueness
        $counter = 1;
        $baseName = substr($short, 0, 8);
        while (Team::where('short_name', $short)->exists()) {
            $short = $baseName . $counter;
            $counter++;
        }
        
        return substr($short, 0, 10);
    }

    private function calculateInitialRating($teamData)
    {
        $baseRating = 1500;
        
        // Adjust based on achievements
        if (!empty($teamData['achievements'])) {
            foreach ($teamData['achievements'] as $achievement) {
                if (strpos($achievement['place'], '1st') !== false) {
                    $baseRating += 100;
                } elseif (strpos($achievement['place'], '2nd') !== false) {
                    $baseRating += 50;
                } elseif (strpos($achievement['place'], '3rd') !== false) {
                    $baseRating += 25;
                }
            }
        }
        
        // Adjust based on earnings
        if ($teamData['earnings'] > 50000) {
            $baseRating += 200;
        } elseif ($teamData['earnings'] > 20000) {
            $baseRating += 100;
        } elseif ($teamData['earnings'] > 5000) {
            $baseRating += 50;
        }
        
        return min($baseRating, 2000); // Cap at 2000
    }

    private function getCountryFlag($country)
    {
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
            'Malta' => '🇲🇹'
        ];
        
        return $flags[$country] ?? '🌍';
    }

    public function scrapePlayerDetails($playerUrl)
    {
        // This would scrape individual player pages for social links
        // To be implemented if needed
    }
}