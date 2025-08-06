<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\Event;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameMatch;
use App\Models\MatchMap;
use App\Models\EventStanding;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LiquipediaScraper
{
    private $baseUrl = 'https://liquipedia.net';
    private $tournaments = [
        'north_america_invitational' => [
            'url' => '/marvelrivals/Marvel_Rivals_Invitational/2025/North_America',
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'region' => 'North America',
            'tier' => 'A',
            'prize_pool' => 100000,
            'start_date' => '2025-03-14',
            'end_date' => '2025-03-23',
            'type' => 'invitational'
        ],
        'emea_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/EMEA',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA',
            'region' => 'Europe',
            'tier' => 'A',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament'
        ],
        'asia_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/Asia',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia',
            'region' => 'Asia',
            'tier' => 'A',
            'prize_pool' => 100000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament'
        ],
        'americas_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/Americas',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas',
            'region' => 'Americas',
            'tier' => 'A',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament'
        ],
        'oceania_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/Oceania',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania',
            'region' => 'Oceania',
            'tier' => 'A',
            'prize_pool' => 75000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-22',
            'type' => 'tournament'
        ]
    ];

    public function scrapeAllTournaments()
    {
        $results = [];
        
        foreach ($this->tournaments as $key => $tournament) {
            Log::info("Scraping tournament: {$tournament['name']}");
            
            try {
                $results[$key] = $this->scrapeTournament($tournament);
            } catch (\Exception $e) {
                Log::error("Error scraping {$tournament['name']}: " . $e->getMessage());
                $results[$key] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }

    public function scrapeTournament($tournamentData)
    {
        $url = $this->baseUrl . $tournamentData['url'];
        $response = Http::get($url);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch page: " . $response->status());
        }
        
        $crawler = new Crawler($response->body());
        
        // Create or update event
        $event = $this->createOrUpdateEvent($tournamentData, $crawler);
        
        // Scrape teams and players
        $teams = $this->scrapeTeams($crawler, $event);
        
        // Scrape matches and results
        $matches = $this->scrapeMatches($crawler, $event);
        
        // Scrape standings
        $standings = $this->scrapeStandings($crawler, $event);
        
        return [
            'event' => $event,
            'teams' => $teams,
            'matches' => $matches,
            'standings' => $standings
        ];
    }

    private function createOrUpdateEvent($tournamentData, Crawler $crawler)
    {
        // Extract additional event details from the page
        $description = $this->extractDescription($crawler);
        $format = $this->extractFormat($crawler);
        
        $event = Event::updateOrCreate(
            ['name' => $tournamentData['name']],
            [
                'description' => $description,
                'location' => 'Online',
                'region' => $tournamentData['region'],
                'tier' => $tournamentData['tier'],
                'start_date' => $tournamentData['start_date'],
                'end_date' => $tournamentData['end_date'],
                'prize_pool' => $tournamentData['prize_pool'],
                'format' => $format,
                'type' => $tournamentData['type'],
                'organizer' => 'NetEase',
                'status' => 'completed',
                'game' => 'marvel_rivals'
            ]
        );
        
        return $event;
    }

    private function scrapeTeams(Crawler $crawler, Event $event)
    {
        $teams = [];
        
        // Find team cards or team list
        $crawler->filter('.teamcard, .participant-team')->each(function (Crawler $node) use (&$teams, $event) {
            $teamData = $this->extractTeamData($node);
            
            if ($teamData) {
                $team = $this->createOrUpdateTeam($teamData);
                
                // Attach team to event
                $event->teams()->syncWithoutDetaching([$team->id => [
                    'seed' => $teamData['seed'] ?? null,
                    'registered_at' => now()
                ]]);
                
                // Scrape players for this team
                $players = $this->scrapePlayersForTeam($node, $team);
                $teamData['players'] = $players;
                
                $teams[] = $teamData;
            }
        });
        
        return $teams;
    }

    private function extractTeamData(Crawler $node)
    {
        $data = [];
        
        // Team name
        $data['name'] = $node->filter('.teamname, .team-template-text a')->first()->text('');
        
        if (empty($data['name'])) {
            return null;
        }
        
        // Team logo
        $logoNode = $node->filter('.teamlogo img, .team-template-image img')->first();
        if ($logoNode->count() > 0) {
            $data['logo'] = $this->extractImageUrl($logoNode->attr('src'));
        }
        
        // Country/Region
        $countryNode = $node->filter('.flag img, [class*="flag"]')->first();
        if ($countryNode->count() > 0) {
            $data['country'] = $this->extractCountryFromFlag($countryNode);
        }
        
        // Social media links
        $data['social_media'] = $this->extractSocialMediaLinks($node);
        
        // Seed/Placement
        $seedNode = $node->filter('.seed, .placement')->first();
        if ($seedNode->count() > 0) {
            $data['seed'] = intval(preg_replace('/[^0-9]/', '', $seedNode->text()));
        }
        
        return $data;
    }

    private function createOrUpdateTeam($teamData)
    {
        $team = Team::updateOrCreate(
            ['name' => $teamData['name']],
            [
                'country' => $teamData['country'] ?? null,
                'region' => $this->determineRegionFromCountry($teamData['country'] ?? null),
                'logo' => $teamData['logo'] ?? null,
                'twitter' => $teamData['social_media']['twitter'] ?? null,
                'instagram' => $teamData['social_media']['instagram'] ?? null,
                'youtube' => $teamData['social_media']['youtube'] ?? null,
                'website' => $teamData['social_media']['website'] ?? null,
                'status' => 'active',
                'game' => 'marvel_rivals'
            ]
        );
        
        return $team;
    }

    private function scrapePlayersForTeam(Crawler $node, Team $team)
    {
        $players = [];
        
        // Find player rows within team section
        $node->filter('.player-row, .roster-player, tr[class*="Player"]')->each(function (Crawler $playerNode) use (&$players, $team) {
            $playerData = $this->extractPlayerData($playerNode);
            
            if ($playerData) {
                $player = $this->createOrUpdatePlayer($playerData, $team);
                $players[] = $player;
            }
        });
        
        return $players;
    }

    private function extractPlayerData(Crawler $node)
    {
        $data = [];
        
        // Player IGN
        $data['ign'] = $node->filter('.player-name a, .player a, td:nth-child(2) a')->first()->text('');
        
        if (empty($data['ign'])) {
            return null;
        }
        
        // Real name
        $realNameNode = $node->filter('.player-realname, td:nth-child(3)')->first();
        if ($realNameNode->count() > 0) {
            $data['real_name'] = $realNameNode->text('');
        }
        
        // Country
        $countryNode = $node->filter('.flag img, [class*="flag"]')->first();
        if ($countryNode->count() > 0) {
            $data['country'] = $this->extractCountryFromFlag($countryNode);
        }
        
        // Role
        $roleNode = $node->filter('.player-role, .role, td:nth-child(4)')->first();
        if ($roleNode->count() > 0) {
            $data['role'] = $this->normalizeRole($roleNode->text(''));
        }
        
        // Social media
        $data['social_media'] = $this->extractPlayerSocialMedia($node);
        
        return $data;
    }

    private function createOrUpdatePlayer($playerData, Team $team)
    {
        $player = Player::updateOrCreate(
            ['ign' => $playerData['ign']],
            [
                'real_name' => $playerData['real_name'] ?? null,
                'country' => $playerData['country'] ?? null,
                'country_flag' => $this->getCountryFlag($playerData['country'] ?? null),
                'role' => $playerData['role'] ?? 'flex',
                'team_id' => $team->id,
                'twitter' => $playerData['social_media']['twitter'] ?? null,
                'instagram' => $playerData['social_media']['instagram'] ?? null,
                'twitch' => $playerData['social_media']['twitch'] ?? null,
                'youtube' => $playerData['social_media']['youtube'] ?? null,
                'status' => 'active',
                'joined_team_at' => now()
            ]
        );
        
        return $player;
    }

    private function scrapeMatches(Crawler $crawler, Event $event)
    {
        $matches = [];
        
        // Find match containers
        $crawler->filter('.match-row, .bracket-match, .match-details')->each(function (Crawler $node) use (&$matches, $event) {
            $matchData = $this->extractMatchData($node);
            
            if ($matchData) {
                $match = $this->createOrUpdateMatch($matchData, $event);
                $matches[] = $match;
            }
        });
        
        return $matches;
    }

    private function extractMatchData(Crawler $node)
    {
        $data = [];
        
        // Teams
        $team1Node = $node->filter('.team1, .bracket-team-top, .match-team1')->first();
        $team2Node = $node->filter('.team2, .bracket-team-bottom, .match-team2')->first();
        
        if ($team1Node->count() === 0 || $team2Node->count() === 0) {
            return null;
        }
        
        $data['team1_name'] = $team1Node->filter('.teamname, .name, a')->first()->text('');
        $data['team2_name'] = $team2Node->filter('.teamname, .name, a')->first()->text('');
        
        // Scores
        $data['team1_score'] = intval($team1Node->filter('.score, .bracket-score')->first()->text('0'));
        $data['team2_score'] = intval($team2Node->filter('.score, .bracket-score')->first()->text('0'));
        
        // Match details
        $data['match_date'] = $this->extractMatchDate($node);
        $data['round'] = $this->extractRound($node);
        $data['status'] = $data['team1_score'] > 0 || $data['team2_score'] > 0 ? 'completed' : 'upcoming';
        
        // Maps
        $data['maps'] = $this->extractMapResults($node);
        
        return $data;
    }

    private function createOrUpdateMatch($matchData, Event $event)
    {
        // Find teams
        $team1 = Team::where('name', $matchData['team1_name'])->first();
        $team2 = Team::where('name', $matchData['team2_name'])->first();
        
        if (!$team1 || !$team2) {
            return null;
        }
        
        $match = GameMatch::create([
            'event_id' => $event->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => $matchData['team1_score'],
            'team2_score' => $matchData['team2_score'],
            'match_date' => $matchData['match_date'],
            'round' => $matchData['round'],
            'status' => $matchData['status'],
            'best_of' => $this->determineBestOf($matchData),
            'winner_id' => $this->determineWinner($team1->id, $team2->id, $matchData['team1_score'], $matchData['team2_score'])
        ]);
        
        // Create map results
        foreach ($matchData['maps'] as $mapData) {
            MatchMap::create([
                'match_id' => $match->id,
                'map_name' => $mapData['name'],
                'map_number' => $mapData['number'],
                'team1_score' => $mapData['team1_score'],
                'team2_score' => $mapData['team2_score'],
                'winner_id' => $this->determineWinner($team1->id, $team2->id, $mapData['team1_score'], $mapData['team2_score'])
            ]);
        }
        
        return $match;
    }

    private function scrapeStandings(Crawler $crawler, Event $event)
    {
        $standings = [];
        
        // Find standings table or placement info
        $crawler->filter('.placement-row, .standings-row, .prizepool-row')->each(function (Crawler $node, $index) use (&$standings, $event) {
            $standingData = $this->extractStandingData($node, $index + 1);
            
            if ($standingData) {
                $standing = $this->createOrUpdateStanding($standingData, $event);
                $standings[] = $standing;
            }
        });
        
        return $standings;
    }

    private function extractStandingData(Crawler $node, $position)
    {
        $data = [];
        
        // Team name
        $data['team_name'] = $node->filter('.teamname, .team a, td:nth-child(2) a')->first()->text('');
        
        if (empty($data['team_name'])) {
            return null;
        }
        
        // Position
        $positionNode = $node->filter('.placement, .position, td:first-child')->first();
        if ($positionNode->count() > 0) {
            $data['position'] = intval(preg_replace('/[^0-9]/', '', $positionNode->text()));
        } else {
            $data['position'] = $position;
        }
        
        // Prize money
        $prizeNode = $node->filter('.prizemoney, .prize, td:last-child')->first();
        if ($prizeNode->count() > 0) {
            $prizeText = $prizeNode->text('');
            $data['prize_money'] = $this->extractPrizeMoney($prizeText);
        }
        
        // Points (if applicable)
        $pointsNode = $node->filter('.points, td:nth-child(3)')->first();
        if ($pointsNode->count() > 0) {
            $data['points'] = intval($pointsNode->text('0'));
        }
        
        return $data;
    }

    private function createOrUpdateStanding($standingData, Event $event)
    {
        $team = Team::where('name', $standingData['team_name'])->first();
        
        if (!$team) {
            return null;
        }
        
        $standing = EventStanding::updateOrCreate(
            [
                'event_id' => $event->id,
                'team_id' => $team->id
            ],
            [
                'position' => $standingData['position'],
                'prize_money' => $standingData['prize_money'] ?? 0,
                'points' => $standingData['points'] ?? 0
            ]
        );
        
        return $standing;
    }

    // Helper methods
    private function extractImageUrl($src)
    {
        if (!$src) return null;
        
        // Handle Liquipedia image URLs
        if (strpos($src, '//') === 0) {
            return 'https:' . $src;
        }
        
        if (strpos($src, 'http') !== 0) {
            return $this->baseUrl . $src;
        }
        
        return $src;
    }

    private function extractCountryFromFlag($flagNode)
    {
        // Try to get country from title or alt attribute
        $country = $flagNode->attr('title') ?: $flagNode->attr('alt');
        
        // Try to extract from image filename
        if (!$country && $flagNode->attr('src')) {
            preg_match('/([A-Z]{2,3})\./', basename($flagNode->attr('src')), $matches);
            if (isset($matches[1])) {
                $country = $this->countryCodeToName($matches[1]);
            }
        }
        
        return $country;
    }

    private function countryCodeToName($code)
    {
        $countries = [
            'US' => 'United States',
            'USA' => 'United States',
            'CA' => 'Canada',
            'UK' => 'United Kingdom',
            'GB' => 'United Kingdom',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'NO' => 'Norway',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'RU' => 'Russia',
            'KR' => 'South Korea',
            'JP' => 'Japan',
            'CN' => 'China',
            'TW' => 'Taiwan',
            'HK' => 'Hong Kong',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'PH' => 'Philippines',
            'ID' => 'Indonesia',
            'VN' => 'Vietnam',
            'IN' => 'India',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'MX' => 'Mexico',
            'CO' => 'Colombia',
            'PE' => 'Peru'
        ];
        
        return $countries[$code] ?? $code;
    }

    private function getCountryFlag($country)
    {
        if (!$country) return null;
        
        // Map country names to flag emojis or image paths
        $flags = [
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'United Kingdom' => '🇬🇧',
            'France' => '🇫🇷',
            'Germany' => '🇩🇪',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Sweden' => '🇸🇪',
            'Denmark' => '🇩🇰',
            'Norway' => '🇳🇴',
            'Finland' => '🇫🇮',
            'Poland' => '🇵🇱',
            'Russia' => '🇷🇺',
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Singapore' => '🇸🇬',
            'Malaysia' => '🇲🇾',
            'Thailand' => '🇹🇭',
            'Philippines' => '🇵🇭',
            'Indonesia' => '🇮🇩',
            'Vietnam' => '🇻🇳',
            'India' => '🇮🇳',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Mexico' => '🇲🇽',
            'Colombia' => '🇨🇴',
            'Peru' => '🇵🇪'
        ];
        
        return $flags[$country] ?? null;
    }

    private function determineRegionFromCountry($country)
    {
        $regions = [
            'North America' => ['United States', 'Canada', 'Mexico'],
            'Europe' => ['United Kingdom', 'France', 'Germany', 'Spain', 'Italy', 'Netherlands', 'Sweden', 'Denmark', 'Norway', 'Finland', 'Poland', 'Russia'],
            'Asia' => ['South Korea', 'Japan', 'China', 'Taiwan', 'Hong Kong', 'Singapore', 'Malaysia', 'Thailand', 'Philippines', 'Indonesia', 'Vietnam', 'India'],
            'Oceania' => ['Australia', 'New Zealand'],
            'South America' => ['Brazil', 'Argentina', 'Chile', 'Colombia', 'Peru']
        ];
        
        foreach ($regions as $region => $countries) {
            if (in_array($country, $countries)) {
                return $region;
            }
        }
        
        return 'International';
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

    private function extractSocialMediaLinks(Crawler $node)
    {
        $links = [];
        
        // Find social media links
        $node->filter('a[href*="twitter.com"], a[href*="x.com"]')->each(function ($link) use (&$links) {
            $links['twitter'] = $link->attr('href');
        });
        
        $node->filter('a[href*="instagram.com"]')->each(function ($link) use (&$links) {
            $links['instagram'] = $link->attr('href');
        });
        
        $node->filter('a[href*="youtube.com"]')->each(function ($link) use (&$links) {
            $links['youtube'] = $link->attr('href');
        });
        
        $node->filter('a[href*="twitch.tv"]')->each(function ($link) use (&$links) {
            $links['twitch'] = $link->attr('href');
        });
        
        return $links;
    }

    private function extractPlayerSocialMedia(Crawler $node)
    {
        // Similar to team social media extraction but for player profiles
        return $this->extractSocialMediaLinks($node);
    }

    private function extractDescription(Crawler $crawler)
    {
        // Extract tournament description from infobox or first paragraph
        $descNode = $crawler->filter('.infobox-description, .tournament-description, #mw-content-text > p')->first();
        
        if ($descNode->count() > 0) {
            return trim($descNode->text());
        }
        
        return null;
    }

    private function extractFormat(Crawler $crawler)
    {
        // Extract tournament format
        $formatNode = $crawler->filter('.infobox-cell-2:contains("Format") + .infobox-cell-2')->first();
        
        if ($formatNode->count() > 0) {
            return $formatNode->text();
        }
        
        return 'tournament';
    }

    private function extractMatchDate(Crawler $node)
    {
        $dateNode = $node->filter('.match-date, .date, .timer-object')->first();
        
        if ($dateNode->count() > 0) {
            try {
                return Carbon::parse($dateNode->text())->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }
        
        return null;
    }

    private function extractRound(Crawler $node)
    {
        $roundNode = $node->filter('.match-round, .round, .bracket-header')->first();
        
        if ($roundNode->count() > 0) {
            return $roundNode->text();
        }
        
        // Try to determine from bracket position
        $bracketClass = $node->attr('class');
        if (preg_match('/round-(\d+)/', $bracketClass, $matches)) {
            return "Round " . $matches[1];
        }
        
        return 'Group Stage';
    }

    private function extractMapResults(Crawler $node)
    {
        $maps = [];
        
        $node->filter('.map-result, .game-details, .match-game')->each(function (Crawler $mapNode, $index) use (&$maps) {
            $mapData = [
                'number' => $index + 1,
                'name' => $mapNode->filter('.map-name, .game-map')->first()->text('Unknown'),
                'team1_score' => intval($mapNode->filter('.team1-score, .score-left')->first()->text('0')),
                'team2_score' => intval($mapNode->filter('.team2-score, .score-right')->first()->text('0'))
            ];
            
            $maps[] = $mapData;
        });
        
        return $maps;
    }

    private function determineBestOf($matchData)
    {
        $totalMaps = count($matchData['maps']);
        
        if ($totalMaps >= 5) return 5;
        if ($totalMaps >= 3) return 3;
        
        // Try to determine from scores
        $maxScore = max($matchData['team1_score'], $matchData['team2_score']);
        
        if ($maxScore >= 3) return 5;
        if ($maxScore >= 2) return 3;
        
        return 1;
    }

    private function determineWinner($team1Id, $team2Id, $score1, $score2)
    {
        if ($score1 > $score2) return $team1Id;
        if ($score2 > $score1) return $team2Id;
        return null;
    }

    private function extractPrizeMoney($text)
    {
        // Remove currency symbols and extract number
        $text = preg_replace('/[^0-9,.]/', '', $text);
        $text = str_replace(',', '', $text);
        
        return floatval($text);
    }
}