<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerTeamHistory;
use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompleteLiquipediaScraper
{
    private $baseUrl = 'https://liquipedia.net/marvelrivals/api.php';
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
        'latvia' => 'LV', 'lv' => 'LV',
        'lithuania' => 'LT', 'lt' => 'LT',
        'estonia' => 'EE', 'ee' => 'EE',
        'croatia' => 'HR', 'hr' => 'HR',
        'serbia' => 'RS', 'rs' => 'RS',
        'bosnia' => 'BA', 'bosnia and herzegovina' => 'BA', 'ba' => 'BA',
        'montenegro' => 'ME', 'me' => 'ME',
        'slovenia' => 'SI', 'si' => 'SI',
        'slovakia' => 'SK', 'sk' => 'SK',
        'bulgaria' => 'BG', 'bg' => 'BG',
        'israel' => 'IL', 'il' => 'IL',
        'egypt' => 'EG', 'eg' => 'EG',
        'south africa' => 'ZA', 'za' => 'ZA',
        'morocco' => 'MA', 'ma' => 'MA',
        'tunisia' => 'TN', 'tn' => 'TN',
        'algeria' => 'DZ', 'dz' => 'DZ',
        'saudi arabia' => 'SA', 'sa' => 'SA',
        'united arab emirates' => 'AE', 'uae' => 'AE', 'ae' => 'AE',
        'kuwait' => 'KW', 'kw' => 'KW',
        'qatar' => 'QA', 'qa' => 'QA',
        'bahrain' => 'BH', 'bh' => 'BH',
        'lebanon' => 'LB', 'lb' => 'LB',
        'jordan' => 'JO', 'jo' => 'JO',
        'kazakhstan' => 'KZ', 'kz' => 'KZ',
        'uzbekistan' => 'UZ', 'uz' => 'UZ',
        'kyrgyzstan' => 'KG', 'kg' => 'KG',
        'mongolia' => 'MN', 'mn' => 'MN',
        'pakistan' => 'PK', 'pk' => 'PK',
        'bangladesh' => 'BD', 'bd' => 'BD',
        'sri lanka' => 'LK', 'lk' => 'LK',
        'nepal' => 'NP', 'np' => 'NP',
        'myanmar' => 'MM', 'mm' => 'MM',
        'cambodia' => 'KH', 'kh' => 'KH',
        'laos' => 'LA', 'la' => 'LA',
        'world' => 'WORLD', 'international' => 'WORLD'
    ];

    private $tournaments = [
        'na_invitational' => [
            'url' => 'Marvel_Rivals_Invitational/2025/North_America',
            'region' => 'NA',
            'name' => 'Marvel Rivals Invitational 2025: North America'
        ],
        'emea_ignite' => [
            'url' => 'MR_Ignite/2025/Stage_1/EMEA',
            'region' => 'EMEA',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA'
        ],
        'asia_ignite' => [
            'url' => 'MR_Ignite/2025/Stage_1/Asia',
            'region' => 'ASIA',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia'
        ],
        'americas_ignite' => [
            'url' => 'MR_Ignite/2025/Stage_1/Americas',
            'region' => 'AMERICAS',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas'
        ],
        'oce_ignite' => [
            'url' => 'MR_Ignite/2025/Stage_1/Oceania',
            'region' => 'OCE',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania'
        ]
    ];

    public function scrapeAllTournaments()
    {
        $results = [];
        
        foreach ($this->tournaments as $key => $tournament) {
            echo "Scraping {$tournament['name']}...\n";
            $results[$key] = $this->scrapeTournament($tournament['url'], $tournament['region']);
        }
        
        return $results;
    }

    public function scrapeTournament($tournamentPath, $region)
    {
        try {
            // Get page content
            $response = Http::get($this->baseUrl, [
                'action' => 'parse',
                'page' => $tournamentPath,
                'format' => 'json',
                'prop' => 'text|sections'
            ]);

            if (!$response->successful()) {
                Log::error("Failed to fetch tournament page: $tournamentPath");
                return ['error' => 'Failed to fetch page'];
            }

            $data = $response->json();
            $html = $data['parse']['text']['*'] ?? '';

            // Parse teams from the page
            $teams = $this->parseTeamsFromHtml($html, $region);
            
            return [
                'teams_count' => count($teams),
                'teams' => $teams
            ];

        } catch (\Exception $e) {
            Log::error("Error scraping tournament $tournamentPath: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    private function parseTeamsFromHtml($html, $region)
    {
        $teams = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        // Find team cards or team listings
        $teamNodes = $xpath->query("//div[contains(@class, 'teamcard')]");
        
        if ($teamNodes->length === 0) {
            // Try alternative selectors
            $teamNodes = $xpath->query("//table[contains(@class, 'participanttable')]//tr[position()>1]");
        }

        foreach ($teamNodes as $teamNode) {
            $teamData = $this->extractTeamData($teamNode, $xpath, $region);
            if ($teamData) {
                $teams[] = $teamData;
                $this->saveTeamAndPlayers($teamData);
            }
        }

        return $teams;
    }

    private function extractTeamData($node, $xpath, $region)
    {
        $teamData = [
            'name' => '',
            'region' => $region,
            'players' => [],
            'coach' => null
        ];

        // Extract team name
        $teamNameNode = $xpath->query(".//a[contains(@class, 'team-template-text')]", $node)->item(0);
        if (!$teamNameNode) {
            $teamNameNode = $xpath->query(".//span[contains(@class, 'team-template-text')]", $node)->item(0);
        }
        
        if ($teamNameNode) {
            $teamData['name'] = trim($teamNameNode->textContent);
        }

        // Extract players
        $playerNodes = $xpath->query(".//a[contains(@href, '/marvelrivals/')]", $node);
        
        foreach ($playerNodes as $playerNode) {
            $href = $playerNode->getAttribute('href');
            if (strpos($href, '/marvelrivals/') !== false && strpos($href, ':') === false) {
                $playerName = trim($playerNode->textContent);
                
                // Skip if it's the team link
                if ($playerName === $teamData['name']) continue;
                
                // Check if it's a coach
                $parentText = $playerNode->parentNode->textContent;
                if (stripos($parentText, 'coach') !== false) {
                    $teamData['coach'] = $this->extractPlayerData($playerName, $href);
                } else {
                    $teamData['players'][] = $this->extractPlayerData($playerName, $href);
                }
            }
        }

        // Only return if we have a valid team
        if ($teamData['name'] && count($teamData['players']) > 0) {
            return $teamData;
        }

        return null;
    }

    private function extractPlayerData($playerName, $href)
    {
        // Extract real name and country from player page if possible
        $playerData = [
            'username' => $playerName,
            'real_name' => null,
            'country' => null,
            'flag' => null
        ];

        // We'll need to fetch player details from their page
        $playerPath = str_replace('/marvelrivals/', '', parse_url($href, PHP_URL_PATH));
        
        try {
            $response = Http::get($this->baseUrl, [
                'action' => 'parse',
                'page' => $playerPath,
                'format' => 'json',
                'prop' => 'text'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $html = $data['parse']['text']['*'] ?? '';
                
                // Parse player details
                $playerDetails = $this->parsePlayerDetails($html);
                $playerData = array_merge($playerData, $playerDetails);
            }
        } catch (\Exception $e) {
            Log::warning("Could not fetch player details for: $playerName");
        }

        return $playerData;
    }

    private function parsePlayerDetails($html)
    {
        $details = [
            'real_name' => null,
            'country' => null,
            'flag' => null,
            'earnings' => 0
        ];

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        // Find infobox
        $infoboxRows = $xpath->query("//div[@class='infobox']//div[@class='infobox-cell-2']");
        
        foreach ($infoboxRows as $row) {
            $label = $xpath->query(".//div[@class='infobox-label']", $row)->item(0);
            $value = $xpath->query(".//div[@class='infobox-data']", $row)->item(0);
            
            if ($label && $value) {
                $labelText = trim($label->textContent);
                $valueText = trim($value->textContent);
                
                switch (strtolower($labelText)) {
                    case 'name:':
                    case 'real name:':
                        $details['real_name'] = $valueText;
                        break;
                    case 'nationality:':
                    case 'country:':
                        $countryImg = $xpath->query(".//img", $value)->item(0);
                        if ($countryImg) {
                            $alt = $countryImg->getAttribute('alt');
                            $details['country'] = $this->normalizeCountry($alt);
                            $details['flag'] = $details['country'];
                        }
                        break;
                    case 'total earnings:':
                    case 'earnings:':
                        $details['earnings'] = $this->parseEarnings($valueText);
                        break;
                }
            }
        }

        return $details;
    }

    private function normalizeCountry($country)
    {
        $country = strtolower(trim($country));
        return $this->countryMap[$country] ?? 'WORLD';
    }

    private function parseEarnings($earningsText)
    {
        // Remove currency symbols and convert to number
        $earnings = preg_replace('/[^0-9.]/', '', $earningsText);
        return floatval($earnings);
    }

    private function saveTeamAndPlayers($teamData)
    {
        try {
            // Create or update team
            $team = Team::updateOrCreate(
                ['name' => $teamData['name']],
                [
                    'region' => $teamData['region'],
                    'status' => 'active',
                    'wins' => 0,
                    'losses' => 0,
                    'rating' => 1000
                ]
            );

            echo "Created/Updated team: {$team->name}\n";

            // Add coach if exists
            if ($teamData['coach']) {
                $team->coach_name = $teamData['coach']['username'];
                $team->save();
            }

            // Add players
            foreach ($teamData['players'] as $index => $playerData) {
                $player = Player::updateOrCreate(
                    ['username' => $playerData['username']],
                    [
                        'real_name' => $playerData['real_name'] ?? $playerData['username'],
                        'country' => $playerData['country'] ?? 'WORLD',
                        'flag' => $playerData['flag'] ?? 'WORLD',
                        'team_id' => $team->id,
                        'role' => $this->determineRole($index),
                        'status' => 'active',
                        'earnings' => $playerData['earnings'] ?? 0,
                        'rating' => 1000
                    ]
                );

                // Add to team history
                PlayerTeamHistory::updateOrCreate(
                    [
                        'player_id' => $player->id,
                        'team_id' => $team->id,
                        'joined_at' => now()
                    ]
                );

                echo "  - Added player: {$player->username} ({$player->real_name})\n";
            }

        } catch (\Exception $e) {
            Log::error("Error saving team {$teamData['name']}: " . $e->getMessage());
            echo "Error saving team {$teamData['name']}: " . $e->getMessage() . "\n";
        }
    }

    private function determineRole($index)
    {
        // Typical role distribution in Marvel Rivals
        $roles = ['Duelist', 'Duelist', 'Tank', 'Tank', 'Support', 'Support'];
        return $roles[$index] ?? 'Flex';
    }
}