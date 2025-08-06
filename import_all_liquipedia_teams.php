<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ComprehensiveLiquipediaImporter
{
    private $baseUrl = 'https://liquipedia.net';
    private $apiUrl = 'https://liquipedia.net/marvelrivals/api.php';
    
    private $allTeams = [
        '100 Thieves', '3BL Esports', 'Al Qadsiah', 'All Business', 'Arrival Seven',
        'Astronic Esports', 'Brr Brr Patapim', 'Cafe Noir', 'Citadel Gaming', 
        'Crazy Raccoon', 'Cumberland University', 'DarkZero', 'DUSTY', 'EHOME',
        'ENVY', 'Ex Oblivione', 'FL eSports Club', 'FlyQuest', 'Fnatic', 'FURY',
        'Gen.G Esports', 'Ground Zero Gaming', 'InControl', 'Kanga Esports', 
        'LGD Gaming', 'Luminosity Gaming EU', 'Luminosity Gaming NA', 'Nova Esports',
        'NTMR', 'OG', 'OG Seed', 'OUG', 'Project EVERSIO', 'Rad Esports', 'Rad EU',
        'REJECT', 'Rival Esports', 'RIZON', 'Sentinels', 'Shikigami', 'SHROUD-X',
        'SLZZ', 'Solaris', 'St. Clair College', 'Steam Engines', 'Supernova',
        'Tayun Gaming', 'Team Nemesis', 'Team Peps', 'TEAM1', 'The Vicious',
        'Twisted Minds', 'UwUfps', 'Virtus.pro', 'YFP', 'Zero Tenacity', 'ZERO.PERCENT'
    ];

    private $countryMap = [
        'usa' => 'US', 'united states' => 'US', 'us' => 'US',
        'canada' => 'CA', 'ca' => 'CA',
        'brazil' => 'BR', 'br' => 'BR',
        'argentina' => 'AR', 'ar' => 'AR',
        'chile' => 'CL', 'cl' => 'CL',
        'mexico' => 'MX', 'mx' => 'MX',
        'colombia' => 'CO', 'co' => 'CO',
        'peru' => 'PE', 'pe' => 'PE',
        'korea' => 'KR', 'south korea' => 'KR', 'kr' => 'KR',
        'japan' => 'JP', 'jp' => 'JP',
        'china' => 'CN', 'cn' => 'CN',
        'taiwan' => 'TW', 'tw' => 'TW',
        'hong kong' => 'HK', 'hk' => 'HK',
        'thailand' => 'TH', 'th' => 'TH',
        'vietnam' => 'VN', 'vn' => 'VN',
        'philippines' => 'PH', 'ph' => 'PH',
        'singapore' => 'SG', 'sg' => 'SG',
        'malaysia' => 'MY', 'my' => 'MY',
        'indonesia' => 'ID', 'id' => 'ID',
        'india' => 'IN', 'in' => 'IN',
        'australia' => 'AU', 'au' => 'AU',
        'new zealand' => 'NZ', 'nz' => 'NZ',
        'united kingdom' => 'GB', 'uk' => 'GB', 'england' => 'GB', 'gb' => 'GB',
        'france' => 'FR', 'fr' => 'FR',
        'germany' => 'DE', 'de' => 'DE',
        'spain' => 'ES', 'es' => 'ES',
        'italy' => 'IT', 'it' => 'IT',
        'poland' => 'PL', 'pl' => 'PL',
        'sweden' => 'SE', 'se' => 'SE',
        'norway' => 'NO', 'no' => 'NO',
        'denmark' => 'DK', 'dk' => 'DK',
        'finland' => 'FI', 'fi' => 'FI',
        'netherlands' => 'NL', 'nl' => 'NL',
        'belgium' => 'BE', 'be' => 'BE',
        'russia' => 'RU', 'ru' => 'RU',
        'ukraine' => 'UA', 'ua' => 'UA',
        'turkey' => 'TR', 'tr' => 'TR',
        'greece' => 'GR', 'gr' => 'GR',
        'portugal' => 'PT', 'pt' => 'PT',
        'czech republic' => 'CZ', 'czechia' => 'CZ', 'cz' => 'CZ',
        'romania' => 'RO', 'ro' => 'RO',
        'hungary' => 'HU', 'hu' => 'HU',
        'austria' => 'AT', 'at' => 'AT',
        'switzerland' => 'CH', 'ch' => 'CH',
        'ireland' => 'IE', 'ie' => 'IE',
        'world' => 'WORLD', 'international' => 'WORLD'
    ];

    public function importAllTeams()
    {
        DB::beginTransaction();
        
        try {
            $totalTeams = 0;
            $totalPlayers = 0;
            $newTeams = 0;
            $newPlayers = 0;
            
            echo "Starting comprehensive import of all Marvel Rivals teams from Liquipedia...\n";
            echo "Found " . count($this->allTeams) . " teams to process\n";
            echo str_repeat("=", 80) . "\n\n";
            
            foreach ($this->allTeams as $teamName) {
                echo "Processing team: $teamName\n";
                
                // Check if team already exists
                $existingTeam = Team::where('name', $teamName)->first();
                if ($existingTeam) {
                    echo "  - Team already exists, skipping...\n\n";
                    continue;
                }
                
                // Scrape team data from Liquipedia
                $teamData = $this->scrapeTeamData($teamName);
                
                if ($teamData) {
                    $team = $this->createTeam($teamData);
                    if ($team) {
                        $totalTeams++;
                        $newTeams++;
                        echo "  ✓ Created team: {$team->name}\n";
                        
                        // Import players
                        $playerCount = $this->importTeamPlayers($team, $teamData['players'] ?? []);
                        $totalPlayers += $playerCount;
                        $newPlayers += $playerCount;
                        
                        echo "  ✓ Imported $playerCount players\n";
                    }
                } else {
                    echo "  - Could not scrape data for $teamName\n";
                }
                
                echo "\n";
                
                // Small delay to avoid rate limiting
                sleep(1);
            }
            
            DB::commit();
            
            echo str_repeat("=", 80) . "\n";
            echo "IMPORT COMPLETED SUCCESSFULLY!\n";
            echo str_repeat("=", 80) . "\n";
            echo "New teams imported: $newTeams\n";
            echo "New players imported: $newPlayers\n";
            echo "Total teams processed: $totalTeams\n";
            echo "Total players processed: $totalPlayers\n";
            
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

    private function scrapeTeamData($teamName)
    {
        try {
            $pageTitle = str_replace(' ', '_', $teamName);
            
            $response = Http::timeout(30)->get($this->apiUrl, [
                'action' => 'parse',
                'page' => $pageTitle,
                'format' => 'json',
                'prop' => 'text'
            ]);

            if (!$response->successful()) {
                echo "    - Failed to fetch page for $teamName\n";
                return null;
            }

            $data = $response->json();
            $html = $data['parse']['text']['*'] ?? '';
            
            if (empty($html)) {
                echo "    - No content found for $teamName\n";
                return null;
            }

            return $this->parseTeamHtml($html, $teamName);
            
        } catch (\Exception $e) {
            echo "    - Error scraping $teamName: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function parseTeamHtml($html, $teamName)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        $teamData = [
            'name' => $teamName,
            'region' => $this->determineRegion($teamName),
            'country' => 'WORLD',
            'players' => [],
            'coach' => null,
            'founded' => null,
            'earnings' => 0
        ];

        // Try to find roster/players section
        $rosterNodes = $xpath->query("//h2[contains(text(), 'Roster')] | //h3[contains(text(), 'Roster')] | //h2[contains(text(), 'Players')] | //h3[contains(text(), 'Players')]");
        
        if ($rosterNodes->length > 0) {
            // Look for player links after roster heading
            $rosterNode = $rosterNodes->item(0);
            $nextElements = $xpath->query("following-sibling::*[position() <= 5]", $rosterNode);
            
            foreach ($nextElements as $element) {
                $playerLinks = $xpath->query(".//a[contains(@href, '/marvelrivals/') and not(contains(@href, ':'))]", $element);
                
                foreach ($playerLinks as $link) {
                    $href = $link->getAttribute('href');
                    $playerName = trim($link->textContent);
                    
                    if (!empty($playerName) && strlen($playerName) > 1 && $playerName !== $teamName) {
                        $playerData = $this->scrapePlayerData($playerName, $href);
                        if ($playerData) {
                            $teamData['players'][] = $playerData;
                        }
                    }
                }
            }
        }

        // Look for infobox data
        $infoboxRows = $xpath->query("//div[@class='infobox']//tr | //table[contains(@class, 'infobox')]//tr");
        
        foreach ($infoboxRows as $row) {
            $cells = $xpath->query(".//td", $row);
            if ($cells->length >= 2) {
                $label = trim($cells->item(0)->textContent);
                $value = trim($cells->item(1)->textContent);
                
                switch (strtolower($label)) {
                    case 'location:':
                    case 'country:':
                        $teamData['country'] = $this->normalizeCountry($value);
                        break;
                    case 'founded:':
                        $teamData['founded'] = $value;
                        break;
                    case 'earnings:':
                    case 'total earnings:':
                        $teamData['earnings'] = $this->parseEarnings($value);
                        break;
                    case 'coach:':
                        $teamData['coach'] = $value;
                        break;
                }
            }
        }

        // If no players found, try alternative parsing
        if (empty($teamData['players'])) {
            $allLinks = $xpath->query("//a[contains(@href, '/marvelrivals/') and not(contains(@href, ':')) and not(contains(@href, '/tournaments/')) and not(contains(@href, '/events/'))]");
            
            foreach ($allLinks as $link) {
                $href = $link->getAttribute('href');
                $playerName = trim($link->textContent);
                
                if (!empty($playerName) && strlen($playerName) > 1 && $playerName !== $teamName) {
                    // Check if this looks like a player name (not a tournament/event)
                    if (!preg_match('/\d{4}|tournament|championship|cup|series|stage|playoffs|finals/i', $playerName)) {
                        $playerData = $this->scrapePlayerData($playerName, $href);
                        if ($playerData && count($teamData['players']) < 10) { // Limit to 10 to avoid spam
                            $teamData['players'][] = $playerData;
                        }
                    }
                }
            }
        }

        return $teamData;
    }

    private function scrapePlayerData($playerName, $href)
    {
        try {
            $playerPath = str_replace('/marvelrivals/', '', parse_url($href, PHP_URL_PATH));
            
            $response = Http::timeout(20)->get($this->apiUrl, [
                'action' => 'parse',
                'page' => $playerPath,
                'format' => 'json',
                'prop' => 'text'
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $html = $data['parse']['text']['*'] ?? '';
            
            if (empty($html)) {
                return null;
            }

            return $this->parsePlayerHtml($html, $playerName);
            
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parsePlayerHtml($html, $playerName)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        $playerData = [
            'username' => $playerName,
            'real_name' => $playerName,
            'country' => 'WORLD',
            'role' => 'Flex',
            'earnings' => 0,
            'age' => null
        ];

        // Look for infobox
        $infoboxRows = $xpath->query("//div[@class='infobox']//tr | //table[contains(@class, 'infobox')]//tr");
        
        foreach ($infoboxRows as $row) {
            $cells = $xpath->query(".//td", $row);
            if ($cells->length >= 2) {
                $label = trim($cells->item(0)->textContent);
                $value = trim($cells->item(1)->textContent);
                
                switch (strtolower($label)) {
                    case 'name:':
                    case 'real name:':
                        if (!empty($value)) {
                            $playerData['real_name'] = $value;
                        }
                        break;
                    case 'nationality:':
                    case 'country:':
                        $countryImg = $xpath->query(".//img", $cells->item(1))->item(0);
                        if ($countryImg) {
                            $alt = $countryImg->getAttribute('alt');
                            $playerData['country'] = $this->normalizeCountry($alt);
                        } else {
                            $playerData['country'] = $this->normalizeCountry($value);
                        }
                        break;
                    case 'role:':
                    case 'position:':
                        $playerData['role'] = $this->normalizeRole($value);
                        break;
                    case 'earnings:':
                    case 'total earnings:':
                        $playerData['earnings'] = $this->parseEarnings($value);
                        break;
                    case 'age:':
                        $playerData['age'] = $this->parseAge($value);
                        break;
                }
            }
        }

        return $playerData;
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
                'country' => $teamData['country'],
                'country_code' => $teamData['country'],
                'flag' => $teamData['country'],
                'country_flag' => $teamData['country'],
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
                'earnings' => $teamData['earnings'],
                'founded' => $teamData['founded'],
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
                // Check if player already exists
                $existingPlayer = Player::where('username', $playerData['username'])->first();
                if ($existingPlayer) {
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
                    'earnings' => $playerData['earnings'],
                    'rating' => 1000,
                    'rank' => 0,
                    'peak_rating' => 1000,
                    'region' => $team->region,
                    'age' => $playerData['age'],
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

    private function determineRegion($teamName)
    {
        $regionMap = [
            // North America
            '100 Thieves' => 'NA',
            'Cafe Noir' => 'NA',
            'Cumberland University' => 'NA',
            'DarkZero' => 'NA',
            'ENVY' => 'NA',
            'FlyQuest' => 'NA',
            'InControl' => 'NA',
            'Luminosity Gaming NA' => 'NA',
            'NTMR' => 'NA',
            'Rad Esports' => 'NA',
            'Sentinels' => 'NA',
            'Shikigami' => 'NA',
            'SHROUD-X' => 'NA',
            'Solaris' => 'NA',
            'St. Clair College' => 'NA',
            'Steam Engines' => 'NA',
            'Team Nemesis' => 'NA',
            'YFP' => 'NA',
            
            // EMEA
            'All Business' => 'EMEA',
            'Citadel Gaming' => 'EMEA',
            'DUSTY' => 'EMEA',
            'Ex Oblivione' => 'EMEA',
            'Fnatic' => 'EMEA',
            'Luminosity Gaming EU' => 'EMEA',
            'OG' => 'EMEA',
            'OG Seed' => 'EMEA',
            'Project EVERSIO' => 'EMEA',
            'Rad EU' => 'EMEA',
            'RIZON' => 'EMEA',
            'Supernova' => 'EMEA',
            'Team Peps' => 'EMEA',
            'Virtus.pro' => 'EMEA',
            'Zero Tenacity' => 'EMEA',
            'ZERO.PERCENT' => 'EMEA',
            'Astronic Esports' => 'EMEA',
            '3BL Esports' => 'EMEA',
            'Al Qadsiah' => 'EMEA',
            
            // Asia
            'Crazy Raccoon' => 'ASIA',
            'Gen.G Esports' => 'ASIA',
            'REJECT' => 'ASIA',
            'Rival Esports' => 'ASIA',
            'EHOME' => 'ASIA',
            'LGD Gaming' => 'ASIA',
            'Nova Esports' => 'ASIA',
            'Tayun Gaming' => 'ASIA',
            'TEAM1' => 'ASIA',
            'FL eSports Club' => 'ASIA',
            'FURY' => 'ASIA',
            'SLZZ' => 'ASIA',
            'Twisted Minds' => 'ASIA',
            'UwUfps' => 'ASIA',
            'OUG' => 'ASIA',
            
            // Oceania
            'Kanga Esports' => 'OCE',
            'Ground Zero Gaming' => 'OCE',
            
            // Multi-region/Unknown
            'Arrival Seven' => 'WORLD',
            'Brr Brr Patapim' => 'WORLD',
            'The Vicious' => 'WORLD'
        ];
        
        return $regionMap[$teamName] ?? 'WORLD';
    }

    private function normalizeCountry($country)
    {
        $country = strtolower(trim($country));
        return $this->countryMap[$country] ?? 'WORLD';
    }

    private function normalizeRole($role)
    {
        $role = strtolower(trim($role));
        $roleMap = [
            'duelist' => 'Duelist',
            'dps' => 'Duelist',
            'damage' => 'Duelist',
            'tank' => 'Tank',
            'vanguard' => 'Tank',
            'support' => 'Support',
            'strategist' => 'Support',
            'healer' => 'Support',
            'flex' => 'Flex'
        ];
        
        return $roleMap[$role] ?? 'Flex';
    }

    private function parseEarnings($earningsText)
    {
        $earnings = preg_replace('/[^0-9.]/', '', $earningsText);
        return floatval($earnings);
    }

    private function parseAge($ageText)
    {
        $age = preg_replace('/[^0-9]/', '', $ageText);
        return !empty($age) ? intval($age) : null;
    }

    private function generateShortName($teamName)
    {
        $shortNames = [
            '100 Thieves' => '100T',
            '3BL Esports' => '3BL',
            'Al Qadsiah' => 'AQ',
            'All Business' => 'AB',
            'Arrival Seven' => 'AS',
            'Astronic Esports' => 'AST',
            'Brr Brr Patapim' => 'BRP',
            'Cafe Noir' => 'CN',
            'Citadel Gaming' => 'CDL',
            'Crazy Raccoon' => 'CR',
            'Cumberland University' => 'CU',
            'DarkZero' => 'DZ',
            'DUSTY' => 'DST',
            'EHOME' => 'EH',
            'ENVY' => 'NV',
            'Ex Oblivione' => 'EO',
            'FL eSports Club' => 'FL',
            'FlyQuest' => 'FQ',
            'Fnatic' => 'FNC',
            'FURY' => 'FRY',
            'Gen.G Esports' => 'GEN',
            'Ground Zero Gaming' => 'GZ',
            'InControl' => 'IC',
            'Kanga Esports' => 'KNG',
            'LGD Gaming' => 'LGD',
            'Luminosity Gaming EU' => 'LG-EU',
            'Luminosity Gaming NA' => 'LG-NA',
            'Nova Esports' => 'NVA',
            'NTMR' => 'NTMR',
            'OG' => 'OG',
            'OG Seed' => 'OGS',
            'OUG' => 'OUG',
            'Project EVERSIO' => 'EVR',
            'Rad Esports' => 'RAD',
            'Rad EU' => 'RAD-EU',
            'REJECT' => 'RJT',
            'Rival Esports' => 'RVL',
            'RIZON' => 'RZN',
            'Sentinels' => 'SEN',
            'Shikigami' => 'SKG',
            'SHROUD-X' => 'SHX',
            'SLZZ' => 'SLZ',
            'Solaris' => 'SOL',
            'St. Clair College' => 'SCC',
            'Steam Engines' => 'SE',
            'Supernova' => 'SNV',
            'Tayun Gaming' => 'TYG',
            'Team Nemesis' => 'NMS',
            'Team Peps' => 'PPS',
            'TEAM1' => 'T1',
            'The Vicious' => 'VIC',
            'Twisted Minds' => 'TM',
            'UwUfps' => 'UWU',
            'Virtus.pro' => 'VP',
            'YFP' => 'YFP',
            'Zero Tenacity' => 'ZT',
            'ZERO.PERCENT' => 'Z%'
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
$importer = new ComprehensiveLiquipediaImporter();
$importer->importAllTeams();