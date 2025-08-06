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
use App\Models\PlayerTeamHistory;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ComprehensiveLiquipediaScraper
{
    private $baseUrl = 'https://liquipedia.net';
    private $apiUrl = 'https://liquipedia.net/marvelrivals/api.php';
    
    private $tournaments = [
        'north_america_invitational' => [
            'url' => '/marvelrivals/Marvel_Rivals_Invitational/2025/North_America',
            'name' => 'Marvel Rivals Invitational 2025: North America',
            'region' => 'North America',
            'tier' => 'A',
            'prize_pool' => 100000,
            'start_date' => '2025-03-14',
            'end_date' => '2025-03-23',
            'type' => 'invitational',
            'organizer' => 'NetEase',
            'game_version' => '1.0',
            'expected_teams' => 8
        ],
        'emea_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/EMEA',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA',
            'region' => 'Europe',
            'tier' => 'A',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament',
            'organizer' => 'NetEase',
            'game_version' => '1.0',
            'expected_teams' => 16
        ],
        'asia_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/Asia',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia',
            'region' => 'Asia',
            'tier' => 'A',
            'prize_pool' => 100000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament',
            'organizer' => 'NetEase',
            'game_version' => '1.0',
            'expected_teams' => 12
        ],
        'americas_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/Americas',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas',
            'region' => 'Americas',
            'tier' => 'A',
            'prize_pool' => 250000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'type' => 'tournament',
            'organizer' => 'NetEase',
            'game_version' => '1.0',
            'expected_teams' => 16
        ],
        'oceania_ignite' => [
            'url' => '/marvelrivals/MR_Ignite/2025/Stage_1/Oceania',
            'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania',
            'region' => 'Oceania',
            'tier' => 'A',
            'prize_pool' => 75000,
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-22',
            'type' => 'tournament',
            'organizer' => 'NetEase',
            'game_version' => '1.0',
            'expected_teams' => 8
        ]
    ];

    private $heroRoleMap = [
        // Vanguards (Tanks)
        'captain-america' => 'vanguard',
        'doctor-strange' => 'vanguard',
        'groot' => 'vanguard',
        'hulk' => 'vanguard',
        'magneto' => 'vanguard',
        'peni-parker' => 'vanguard',
        'thor' => 'vanguard',
        'venom' => 'vanguard',
        
        // Duelists (DPS)
        'black-panther' => 'duelist',
        'black-widow' => 'duelist',
        'hawkeye' => 'duelist',
        'hela' => 'duelist',
        'iron-fist' => 'duelist',
        'iron-man' => 'duelist',
        'magik' => 'duelist',
        'moon-knight' => 'duelist',
        'namor' => 'duelist',
        'psylocke' => 'duelist',
        'punisher' => 'duelist',
        'scarlet-witch' => 'duelist',
        'spider-man' => 'duelist',
        'star-lord' => 'duelist',
        'storm' => 'duelist',
        'the-punisher' => 'duelist',
        'winter-soldier' => 'duelist',
        'wolverine' => 'duelist',
        
        // Strategists (Support)
        'adam-warlock' => 'strategist',
        'cloak-and-dagger' => 'strategist',
        'jeff-the-land-shark' => 'strategist',
        'loki' => 'strategist',
        'luna-snow' => 'strategist',
        'mantis' => 'strategist',
        'rocket-raccoon' => 'strategist',
        
        // Fantastic Four
        'human-torch' => 'duelist',
        'invisible-woman' => 'strategist',
        'mister-fantastic' => 'duelist',
        'the-thing' => 'vanguard'
    ];

    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate, br',
        'DNT' => '1',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1'
    ];

    // Country mapping for accurate data
    private $countryMap = [
        'USA' => 'United States',
        'US' => 'United States',
        'UK' => 'United Kingdom',
        'GB' => 'United Kingdom',
        'KR' => 'South Korea',
        'CN' => 'China',
        'JP' => 'Japan',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'FR' => 'France',
        'DE' => 'Germany',
        'ES' => 'Spain',
        'IT' => 'Italy',
        'SE' => 'Sweden',
        'DK' => 'Denmark',
        'NO' => 'Norway',
        'FI' => 'Finland',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
        'PL' => 'Poland',
        'RU' => 'Russia',
        'TR' => 'Turkey',
        'IN' => 'India',
        'SG' => 'Singapore',
        'MY' => 'Malaysia',
        'PH' => 'Philippines',
        'TH' => 'Thailand',
        'ID' => 'Indonesia',
        'VN' => 'Vietnam',
        'TW' => 'Taiwan',
        'HK' => 'Hong Kong'
    ];

    public function scrapeAllTournaments($updateElo = true, $dryRun = false, $force = false)
    {
        $results = [];
        
        if (!$dryRun) {
            DB::beginTransaction();
        }
        
        try {
            foreach ($this->tournaments as $key => $tournament) {
                Log::info("Starting comprehensive scraping for: {$tournament['name']}");
                
                $results[$key] = $this->scrapeTournament($tournament, $dryRun, $force);
                
                // Add delay to avoid rate limiting
                sleep(3);
            }
            
            if (!$dryRun) {
                // Update ELO ratings after all matches are imported
                if ($updateElo) {
                    $this->updateEloRatings();
                }
                
                // Update comprehensive statistics
                $this->updateComprehensiveStatistics();
                
                DB::commit();
            }
            
            Log::info("Successfully completed comprehensive scraping of all tournaments");
            
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            Log::error("Error during comprehensive scraping: " . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }

    public function scrapeSingleTournament($tournamentKey, $updateElo = true, $dryRun = false, $force = false)
    {
        if (!isset($this->tournaments[$tournamentKey])) {
            throw new \Exception("Tournament key not found: {$tournamentKey}");
        }
        
        $tournament = $this->tournaments[$tournamentKey];
        
        if (!$dryRun) {
            DB::beginTransaction();
        }
        
        try {
            $result = $this->scrapeTournament($tournament, $dryRun, $force);
            
            if (!$dryRun) {
                if ($updateElo) {
                    $this->updateEloRatings();
                }
                
                $this->updateComprehensiveStatistics();
                
                DB::commit();
            }
            
            return [$tournamentKey => $result];
            
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    private function scrapeTournament($tournamentData, $dryRun = false, $force = false)
    {
        $url = $this->baseUrl . $tournamentData['url'];
        
        Log::info("Fetching tournament page: {$url}");
        
        // Use the API endpoint for better data extraction
        $apiResponse = $this->fetchPageViaAPI($tournamentData['url']);
        
        if (!$apiResponse) {
            // Fallback to direct HTML scraping
            $response = Http::withHeaders($this->headers)
                ->timeout(30)
                ->get($url);
            
            if (!$response->successful()) {
                throw new \Exception("Failed to fetch page: " . $response->status());
            }
            
            $html = $response->body();
        } else {
            $html = $apiResponse;
        }
        
        $crawler = new Crawler($html);
        
        // Extract all comprehensive data
        $event = null;
        $teams = [];
        $players = [];
        $matches = [];
        $standings = [];
        
        if (!$dryRun) {
            // Create or update event
            $event = $this->createOrUpdateEvent($tournamentData, $crawler, $force);
            
            // Scrape all teams with complete information
            $teams = $this->scrapeAllTeams($crawler, $event);
            
            // Scrape all players with detailed profiles
            $players = $this->scrapeAllPlayers($crawler, $teams, $event);
            
            // Scrape all matches including group stage and playoffs
            $matches = $this->scrapeAllMatches($crawler, $event);
            
            // Scrape final standings with earnings
            $standings = $this->scrapeFinalStandings($crawler, $event);
            
            // Update social media and additional info
            $this->updateAdditionalInformation($crawler, $event, $teams, $players);
        }
        
        // Calculate statistics
        $stats = [
            'total_teams' => count($teams),
            'total_players' => count($players),
            'total_matches' => count($matches),
            'total_prize_pool' => $tournamentData['prize_pool']
        ];
        
        Log::info("Tournament scraping completed: {$tournamentData['name']}");
        Log::info("Stats - Teams: {$stats['total_teams']}, Players: {$stats['total_players']}, Matches: {$stats['total_matches']}");
        
        return [
            'event' => $event,
            'teams' => $teams,
            'players' => $players,
            'matches' => $matches,
            'standings' => $standings,
            'stats' => $stats
        ];
    }

    private function fetchPageViaAPI($pageUrl)
    {
        try {
            // Extract page title from URL
            $pageParts = explode('/', trim($pageUrl, '/'));
            $pageTitle = end($pageParts);
            
            $params = [
                'action' => 'parse',
                'page' => str_replace('_', ' ', $pageTitle),
                'format' => 'json',
                'prop' => 'text|categories|externallinks'
            ];
            
            $response = Http::withHeaders($this->headers)
                ->get($this->apiUrl, $params);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['parse']['text']['*'])) {
                    return $data['parse']['text']['*'];
                }
            }
        } catch (\Exception $e) {
            Log::warning("API fetch failed, falling back to direct scraping: " . $e->getMessage());
        }
        
        return null;
    }

    private function createOrUpdateEvent($tournamentData, Crawler $crawler, $force = false)
    {
        // Extract all event details
        $description = $this->extractEventDescription($crawler);
        $format = $this->extractEventFormat($crawler);
        $streams = $this->extractStreamInformation($crawler);
        $venue = $this->extractVenueInformation($crawler);
        
        $eventData = [
            'name' => $tournamentData['name'],
            'description' => $description,
            'location' => $venue['location'] ?? 'Online',
            'venue' => $venue['name'] ?? null,
            'region' => $tournamentData['region'],
            'tier' => $tournamentData['tier'],
            'start_date' => $tournamentData['start_date'],
            'end_date' => $tournamentData['end_date'],
            'prize_pool' => $tournamentData['prize_pool'],
            'format' => $format,
            'type' => $tournamentData['type'],
            'organizer' => $tournamentData['organizer'],
            'status' => 'completed',
            'game' => 'marvel_rivals',
            'game_version' => $tournamentData['game_version'],
            'stream_urls' => json_encode($streams),
            'liquipedia_url' => $this->baseUrl . $tournamentData['url'],
            'participants' => $tournamentData['expected_teams']
        ];
        
        if ($force) {
            $event = Event::updateOrCreate(
                ['name' => $tournamentData['name']],
                $eventData
            );
        } else {
            $event = Event::firstOrCreate(
                ['name' => $tournamentData['name']],
                $eventData
            );
        }
        
        Log::info("Event created/updated: {$event->name} (ID: {$event->id})");
        
        return $event;
    }

    private function scrapeAllTeams(Crawler $crawler, Event $event)
    {
        $teams = [];
        $processedTeams = [];
        
        // Multiple strategies to find all teams
        
        // 1. From participant tables
        $crawler->filter('.participanttable tr, .wikitable.participanttable tr')->each(function (Crawler $row) use (&$teams, &$processedTeams, $event) {
            if ($row->filter('th')->count() > 0) return; // Skip header
            
            $teamData = $this->extractTeamFromParticipantRow($row);
            if ($teamData && !in_array($teamData['name'], $processedTeams)) {
                $team = $this->createOrUpdateTeam($teamData, $event);
                $teams[] = $team;
                $processedTeams[] = $teamData['name'];
            }
        });
        
        // 2. From group tables
        $crawler->filter('.grouptable .team-template-team-standard, .group-table .team-template-team-standard')->each(function (Crawler $node) use (&$teams, &$processedTeams, $event) {
            $teamData = $this->extractTeamFromTemplate($node);
            if ($teamData && !in_array($teamData['name'], $processedTeams)) {
                $team = $this->createOrUpdateTeam($teamData, $event);
                $teams[] = $team;
                $processedTeams[] = $teamData['name'];
            }
        });
        
        // 3. From bracket
        $crawler->filter('.bracket .team-template-team-standard, .bracket-team')->each(function (Crawler $node) use (&$teams, &$processedTeams, $event) {
            $teamData = $this->extractTeamFromTemplate($node);
            if ($teamData && !in_array($teamData['name'], $processedTeams)) {
                $team = $this->createOrUpdateTeam($teamData, $event);
                $teams[] = $team;
                $processedTeams[] = $teamData['name'];
            }
        });
        
        // 4. From standings/results
        $crawler->filter('.prizepooltable tr')->each(function (Crawler $row) use (&$teams, &$processedTeams, $event) {
            if ($row->filter('th')->count() > 0) return; // Skip header
            
            $teamNode = $row->filter('.team-template-team-standard')->first();
            if ($teamNode->count() > 0) {
                $teamData = $this->extractTeamFromTemplate($teamNode);
                if ($teamData && !in_array($teamData['name'], $processedTeams)) {
                    // Add prize money information
                    $prizeNode = $row->filter('td:last-child')->first();
                    if ($prizeNode->count() > 0) {
                        $teamData['tournament_earnings'] = $this->extractPrizeMoney($prizeNode->text());
                    }
                    
                    $team = $this->createOrUpdateTeam($teamData, $event);
                    $teams[] = $team;
                    $processedTeams[] = $teamData['name'];
                }
            }
        });
        
        Log::info("Total teams found: " . count($teams));
        
        // For each team, get detailed information from team page
        foreach ($teams as $team) {
            $this->enrichTeamData($team);
        }
        
        return $teams;
    }

    private function extractTeamFromParticipantRow(Crawler $row)
    {
        $data = [];
        
        // Team name and link
        $teamNode = $row->filter('.team-template-team-standard, .teamname a, td:nth-child(2) a')->first();
        if ($teamNode->count() === 0) return null;
        
        $data['name'] = trim($teamNode->text());
        if (empty($data['name'])) return null;
        
        // Team page URL
        $linkNode = $row->filter('a[href*="/marvelrivals/"]')->first();
        if ($linkNode->count() > 0) {
            $data['liquipedia_url'] = $linkNode->attr('href');
        }
        
        // Country/Region
        $flagNode = $row->filter('.flag img, img[class*="flag"]')->first();
        if ($flagNode->count() > 0) {
            $data['country'] = $this->extractCountryFromFlag($flagNode);
        }
        
        // Seed
        $seedNode = $row->filter('td:first-child')->first();
        if ($seedNode->count() > 0) {
            $seedText = $seedNode->text();
            if (preg_match('/\d+/', $seedText, $matches)) {
                $data['seed'] = intval($matches[0]);
            }
        }
        
        // Qualification info
        $qualNode = $row->filter('.qualified-from, td:contains("Invited"), td:contains("Qualified")')->first();
        if ($qualNode->count() > 0) {
            $data['qualification'] = trim($qualNode->text());
        }
        
        return $data;
    }

    private function extractTeamFromTemplate(Crawler $node)
    {
        $data = [];
        
        // Team name
        $nameNode = $node->filter('.team-template-text a, .team-template-text')->first();
        if ($nameNode->count() === 0) return null;
        
        $data['name'] = trim($nameNode->text());
        if (empty($data['name']) || $data['name'] === 'TBD') return null;
        
        // Team link
        $linkNode = $node->filter('a[href*="/marvelrivals/"]')->first();
        if ($linkNode->count() > 0) {
            $data['liquipedia_url'] = $linkNode->attr('href');
        }
        
        // Team logo
        $logoNode = $node->filter('.team-template-image img, .team-template-image-icon img')->first();
        if ($logoNode->count() > 0) {
            $data['logo_url'] = $this->extractImageUrl($logoNode->attr('src'));
        }
        
        return $data;
    }

    private function createOrUpdateTeam($teamData, Event $event)
    {
        // Determine region from country or event
        $region = $this->determineRegion($teamData['country'] ?? null, $event->region);
        
        $teamAttributes = [
            'name' => $teamData['name'],
            'short_name' => $this->generateShortName($teamData['name']),
            'country' => $teamData['country'] ?? null,
            'region' => $region,
            'status' => 'active',
            'game' => 'marvel_rivals',
            'platform' => 'PC',
            'rating' => 1500, // Base ELO
            'earnings' => $teamData['tournament_earnings'] ?? 0
        ];
        
        if (isset($teamData['logo_url'])) {
            $teamAttributes['logo'] = $teamData['logo_url'];
        }
        
        $team = Team::updateOrCreate(
            ['name' => $teamData['name']],
            $teamAttributes
        );
        
        // Attach to event
        $pivotData = [
            'registered_at' => now()
        ];
        
        if (isset($teamData['seed'])) {
            $pivotData['seed'] = $teamData['seed'];
        }
        
        if (isset($teamData['qualification'])) {
            $pivotData['qualified_from'] = $teamData['qualification'];
        }
        
        $event->teams()->syncWithoutDetaching([$team->id => $pivotData]);
        
        return $team;
    }

    private function enrichTeamData(Team $team)
    {
        if (!isset($team->liquipedia_url)) return;
        
        try {
            $url = strpos($team->liquipedia_url, 'http') === 0 
                ? $team->liquipedia_url 
                : $this->baseUrl . $team->liquipedia_url;
            
            $response = Http::withHeaders($this->headers)
                ->timeout(20)
                ->get($url);
            
            if ($response->successful()) {
                $crawler = new Crawler($response->body());
                
                // Extract social media
                $socialMedia = $this->extractSocialMediaLinks($crawler);
                
                // Extract coach
                $coachNode = $crawler->filter('.infobox-cell-2:contains("Coach") + .infobox-cell-2')->first();
                if ($coachNode->count() > 0) {
                    $coach = trim($coachNode->text());
                    if (!empty($coach) && $coach !== 'TBD') {
                        $team->coach = $coach;
                    }
                }
                
                // Extract founded date
                $foundedNode = $crawler->filter('.infobox-cell-2:contains("Founded") + .infobox-cell-2')->first();
                if ($foundedNode->count() > 0) {
                    $founded = trim($foundedNode->text());
                    if (!empty($founded)) {
                        $team->founded = $founded;
                    }
                }
                
                // Update team
                $team->social_media = $socialMedia;
                $team->save();
                
                Log::info("Enriched team data for: {$team->name}");
            }
        } catch (\Exception $e) {
            Log::warning("Could not enrich team data for {$team->name}: " . $e->getMessage());
        }
        
        sleep(1); // Rate limiting
    }

    private function scrapeAllPlayers(Crawler $crawler, $teams, Event $event)
    {
        $allPlayers = [];
        $processedPlayers = [];
        
        // Strategy 1: From team rosters in participant table
        $crawler->filter('.participanttable tr')->each(function (Crawler $row) use (&$allPlayers, &$processedPlayers, $teams) {
            $teamNameNode = $row->filter('.team-template-team-standard .team-template-text')->first();
            if ($teamNameNode->count() === 0) return;
            
            $teamName = trim($teamNameNode->text());
            $team = $this->findTeamByName($teams, $teamName);
            if (!$team) return;
            
            // Look for roster in the same row or next rows
            $rosterText = $row->filter('.Roster, td:contains(":")')->text('');
            if (!empty($rosterText)) {
                $players = $this->extractPlayersFromRosterText($rosterText, $team);
                foreach ($players as $player) {
                    if (!in_array($player->ign, $processedPlayers)) {
                        $allPlayers[] = $player;
                        $processedPlayers[] = $player->ign;
                    }
                }
            }
        });
        
        // Strategy 2: From dedicated roster sections
        $crawler->filter('.roster-card, .teamcard').each(function (Crawler $card) use (&$allPlayers, &$processedPlayers, $teams) {
            $teamNameNode = $card->filter('.teamname, .team-template-text')->first();
            if ($teamNameNode->count() === 0) return;
            
            $teamName = trim($teamNameNode->text());
            $team = $this->findTeamByName($teams, $teamName);
            if (!$team) return;
            
            $card->filter('.player-row, .roster-player, tr[class*="Player"]')->each(function (Crawler $playerRow) use (&$allPlayers, &$processedPlayers, $team) {
                $playerData = $this->extractPlayerFromRow($playerRow);
                if ($playerData) {
                    $player = $this->createOrUpdatePlayer($playerData, $team);
                    if (!in_array($player->ign, $processedPlayers)) {
                        $allPlayers[] = $player;
                        $processedPlayers[] = $player->ign;
                    }
                }
            });
        });
        
        // Strategy 3: From match details (to catch substitutes)
        $crawler->filter('.match-details .player-name a, .match-info .player a')->each(function (Crawler $playerNode) use (&$allPlayers, &$processedPlayers, $teams) {
            $playerName = trim($playerNode->text());
            if (!in_array($playerName, $processedPlayers)) {
                // Try to determine team from context
                $teamNode = $playerNode->closest('.team-side, .match-team');
                if ($teamNode->count() > 0) {
                    $teamNameNode = $teamNode->filter('.team-template-text')->first();
                    if ($teamNameNode->count() > 0) {
                        $teamName = trim($teamNameNode->text());
                        $team = $this->findTeamByName($teams, $teamName);
                        if ($team) {
                            $playerData = [
                                'ign' => $playerName,
                                'liquipedia_url' => $playerNode->attr('href')
                            ];
                            $player = $this->createOrUpdatePlayer($playerData, $team);
                            $allPlayers[] = $player;
                            $processedPlayers[] = $player->ign;
                        }
                    }
                }
            }
        });
        
        Log::info("Total players found: " . count($allPlayers));
        
        // Enrich player data from individual pages
        foreach ($allPlayers as $player) {
            $this->enrichPlayerData($player);
        }
        
        return $allPlayers;
    }

    private function extractPlayersFromRosterText($rosterText, Team $team)
    {
        $players = [];
        
        // Remove "Roster:" prefix
        $rosterText = preg_replace('/^Roster:\s*/i', '', $rosterText);
        
        // Split by common delimiters
        $playerNames = preg_split('/[,\s]+/', $rosterText);
        
        foreach ($playerNames as $name) {
            $name = trim($name);
            if (!empty($name) && strlen($name) > 2) {
                $playerData = ['ign' => $name];
                $players[] = $this->createOrUpdatePlayer($playerData, $team);
            }
        }
        
        return $players;
    }

    private function extractPlayerFromRow(Crawler $row)
    {
        $data = [];
        
        // Player IGN
        $ignNode = $row->filter('.player-name a, .ID a, td:nth-child(2) a')->first();
        if ($ignNode->count() === 0) {
            $ignNode = $row->filter('.player-name, .ID, td:nth-child(2)')->first();
        }
        
        if ($ignNode->count() === 0) return null;
        
        $data['ign'] = trim($ignNode->text());
        if (empty($data['ign'])) return null;
        
        // Player page URL
        if ($ignNode->nodeName() === 'a') {
            $data['liquipedia_url'] = $ignNode->attr('href');
        }
        
        // Real name
        $realNameNode = $row->filter('.Name, td:nth-child(3)')->first();
        if ($realNameNode->count() > 0) {
            $realName = trim($realNameNode->text());
            if (!empty($realName) && $realName !== '-' && $realName !== 'TBD') {
                $data['real_name'] = $realName;
            }
        }
        
        // Country
        $flagNode = $row->filter('.flag img, img[class*="flag"]')->first();
        if ($flagNode->count() > 0) {
            $data['country'] = $this->extractCountryFromFlag($flagNode);
        }
        
        // Role
        $roleNode = $row->filter('.Position, .role, td:nth-child(4)')->first();
        if ($roleNode->count() > 0) {
            $roleText = trim($roleNode->text());
            if (!empty($roleText)) {
                $data['role'] = $this->normalizeRole($roleText);
            }
        }
        
        // Join date
        $joinDateNode = $row->filter('.JoinDate, td:contains("-")')->last();
        if ($joinDateNode->count() > 0) {
            $joinText = trim($joinDateNode->text());
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $joinText, $matches)) {
                $data['joined_at'] = $matches[0];
            }
        }
        
        return $data;
    }

    private function createOrUpdatePlayer($playerData, Team $team)
    {
        $playerAttributes = [
            'ign' => $playerData['ign'],
            'username' => $playerData['ign'],
            'team_id' => $team->id,
            'real_name' => $playerData['real_name'] ?? null,
            'country' => $playerData['country'] ?? null,
            'role' => $playerData['role'] ?? 'flex',
            'status' => 'active',
            'rating' => 1000, // Base rating
            'joined_team_at' => $playerData['joined_at'] ?? now()
        ];
        
        if (isset($playerData['country'])) {
            $playerAttributes['country_flag'] = $this->getCountryFlag($playerData['country']);
        }
        
        $player = Player::updateOrCreate(
            ['ign' => $playerData['ign']],
            $playerAttributes
        );
        
        // Create team history entry
        PlayerTeamHistory::firstOrCreate(
            [
                'player_id' => $player->id,
                'team_id' => $team->id,
                'joined_at' => $playerAttributes['joined_team_at']
            ],
            [
                'role' => $playerAttributes['role']
            ]
        );
        
        return $player;
    }

    private function enrichPlayerData(Player $player)
    {
        if (!isset($player->liquipedia_url)) return;
        
        try {
            $url = strpos($player->liquipedia_url, 'http') === 0 
                ? $player->liquipedia_url 
                : $this->baseUrl . $player->liquipedia_url;
            
            $response = Http::withHeaders($this->headers)
                ->timeout(20)
                ->get($url);
            
            if ($response->successful()) {
                $crawler = new Crawler($response->body());
                
                // Extract social media
                $socialMedia = $this->extractSocialMediaLinks($crawler);
                
                // Extract birth date for age
                $birthNode = $crawler->filter('.infobox-cell-2:contains("Born") + .infobox-cell-2')->first();
                if ($birthNode->count() > 0) {
                    if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $birthNode->text(), $matches)) {
                        $player->birth_date = $matches[1];
                        $player->age = Carbon::parse($matches[1])->age;
                    } else if (preg_match('/\(age\s+(\d+)\)/', $birthNode->text(), $matches)) {
                        $player->age = intval($matches[1]);
                    }
                }
                
                // Extract earnings
                $earningsNode = $crawler->filter('.infobox-cell-2:contains("Approx. Total Earnings") + .infobox-cell-2')->first();
                if ($earningsNode->count() > 0) {
                    $player->earnings = $this->extractPrizeMoney($earningsNode->text());
                }
                
                // Extract alternate IDs
                $altNode = $crawler->filter('.infobox-cell-2:contains("Alternate IDs") + .infobox-cell-2')->first();
                if ($altNode->count() > 0) {
                    $altIds = array_map('trim', explode(',', $altNode->text()));
                    $player->alternate_ids = json_encode($altIds);
                }
                
                // Extract romanized name if different
                $romanizedNode = $crawler->filter('.infobox-cell-2:contains("Romanized Name") + .infobox-cell-2')->first();
                if ($romanizedNode->count() > 0) {
                    $romanized = trim($romanizedNode->text());
                    if (!empty($romanized) && $romanized !== $player->real_name) {
                        $player->romanized_name = $romanized;
                    }
                }
                
                // Update player
                $player->social_media = $socialMedia;
                $player->save();
                
                // Extract team history
                $this->extractPlayerTeamHistory($crawler, $player);
                
                Log::info("Enriched player data for: {$player->ign}");
            }
        } catch (\Exception $e) {
            Log::warning("Could not enrich player data for {$player->ign}: " . $e->getMessage());
        }
        
        sleep(1); // Rate limiting
    }

    private function extractPlayerTeamHistory(Crawler $crawler, Player $player)
    {
        $crawler->filter('.team-history tr, .history tr')->each(function (Crawler $row) use ($player) {
            if ($row->filter('th')->count() > 0) return; // Skip header
            
            $teamNode = $row->filter('td:nth-child(1) a')->first();
            $joinNode = $row->filter('td:nth-child(2)')->first();
            $leaveNode = $row->filter('td:nth-child(3)')->first();
            
            if ($teamNode->count() > 0) {
                $teamName = trim($teamNode->text());
                $joinDate = $joinNode->count() > 0 ? $this->parseDate($joinNode->text()) : null;
                $leaveDate = $leaveNode->count() > 0 ? $this->parseDate($leaveNode->text()) : null;
                
                if ($joinDate) {
                    // Find or create team
                    $team = Team::firstOrCreate(
                        ['name' => $teamName],
                        [
                            'short_name' => $this->generateShortName($teamName),
                            'region' => 'International',
                            'status' => 'active',
                            'game' => 'marvel_rivals',
                            'platform' => 'PC',
                            'rating' => 1500
                        ]
                    );
                    
                    PlayerTeamHistory::updateOrCreate(
                        [
                            'player_id' => $player->id,
                            'team_id' => $team->id,
                            'joined_at' => $joinDate
                        ],
                        [
                            'left_at' => $leaveDate,
                            'role' => $player->role
                        ]
                    );
                }
            }
        });
    }

    private function scrapeAllMatches(Crawler $crawler, Event $event)
    {
        $allMatches = [];
        
        // 1. Group stage matches
        $groupMatches = $this->scrapeGroupStageMatches($crawler, $event);
        $allMatches = array_merge($allMatches, $groupMatches);
        
        // 2. Bracket/Playoff matches
        $bracketMatches = $this->scrapeBracketMatches($crawler, $event);
        $allMatches = array_merge($allMatches, $bracketMatches);
        
        // 3. Swiss/Round-robin matches
        $swissMatches = $this->scrapeSwissMatches($crawler, $event);
        $allMatches = array_merge($allMatches, $swissMatches);
        
        Log::info("Total matches found: " . count($allMatches));
        
        return $allMatches;
    }

    private function scrapeGroupStageMatches(Crawler $crawler, Event $event)
    {
        $matches = [];
        
        // Find all group tables
        $crawler->filter('.grouptable, .group-table')->each(function (Crawler $groupTable) use (&$matches, $event) {
            // Extract group name
            $groupName = 'Group Stage';
            $headerNode = $groupTable->prevAll()->filter('h3, h4, .group-header')->first();
            if ($headerNode->count() > 0) {
                if (preg_match('/Group\s+([A-D])/i', $headerNode->text(), $matches)) {
                    $groupName = 'Group ' . strtoupper($matches[1]);
                }
            }
            
            // Extract matches from crosstable
            $groupTable->filter('.crosstable tr')->each(function (Crawler $row, $rowIndex) use (&$matches, $event, $groupName) {
                if ($rowIndex === 0) return; // Skip header
                
                $teamNode = $row->filter('td:first-child .team-template-text')->first();
                if ($teamNode->count() === 0) return;
                
                $team1Name = trim($teamNode->text());
                
                $row->filter('td')->each(function (Crawler $cell, $cellIndex) use (&$matches, $event, $groupName, $team1Name, $row) {
                    if ($cellIndex <= 1) return; // Skip team name and first column
                    
                    $scoreText = trim($cell->text());
                    if (empty($scoreText) || $scoreText === '-') return;
                    
                    // Extract score
                    if (preg_match('/(\d+)\s*-\s*(\d+)/', $scoreText, $scoreMatches)) {
                        // Get team 2 name from header
                        $headerRow = $cell->closest('table')->filter('tr:first-child');
                        $team2Cell = $headerRow->filter('th')->eq($cellIndex);
                        $team2Node = $team2Cell->filter('.team-template-text')->first();
                        
                        if ($team2Node->count() > 0) {
                            $team2Name = trim($team2Node->text());
                            
                            $matchData = [
                                'team1_name' => $team1Name,
                                'team2_name' => $team2Name,
                                'team1_score' => intval($scoreMatches[1]),
                                'team2_score' => intval($scoreMatches[2]),
                                'round' => $groupName,
                                'bracket_type' => 'group',
                                'status' => 'completed'
                            ];
                            
                            $match = $this->createMatch($matchData, $event);
                            if ($match) {
                                $matches[] = $match;
                            }
                        }
                    }
                });
            });
            
            // Also check for match lists within groups
            $groupTable->filter('.matchlist .match-row, .match-list tr')->each(function (Crawler $matchRow) use (&$matches, $event, $groupName) {
                $matchData = $this->extractMatchData($matchRow);
                if ($matchData) {
                    $matchData['round'] = $groupName;
                    $matchData['bracket_type'] = 'group';
                    
                    $match = $this->createMatch($matchData, $event);
                    if ($match) {
                        $matches[] = $match;
                    }
                }
            });
        });
        
        return $matches;
    }

    private function scrapeBracketMatches(Crawler $crawler, Event $event)
    {
        $matches = [];
        
        // Find bracket containers
        $crawler->filter('.bracket, .playoffs').each(function (Crawler $bracket) use (&$matches, $event) {
            // Determine bracket type
            $bracketType = 'upper';
            if (stripos($bracket->attr('class'), 'lower') !== false) {
                $bracketType = 'lower';
            }
            
            // Extract matches
            $bracket->filter('.bracket-match, .bracket-game, .match').each(function (Crawler $matchNode) use (&$matches, $event, $bracketType) {
                $matchData = $this->extractBracketMatchData($matchNode);
                if ($matchData) {
                    $matchData['bracket_position'] = $bracketType;
                    $matchData['bracket_type'] = 'playoff';
                    
                    // Determine round from position
                    $round = $this->determineRoundFromBracketPosition($matchNode);
                    if ($round) {
                        $matchData['round'] = $round;
                    }
                    
                    $match = $this->createMatch($matchData, $event);
                    if ($match) {
                        $matches[] = $match;
                    }
                }
            });
        });
        
        return $matches;
    }

    private function scrapeSwissMatches(Crawler $crawler, Event $event)
    {
        $matches = [];
        
        // Look for Swiss system tables
        $crawler->filter('.swissresults, .swiss-table, .matchlist.swiss')->each(function (Crawler $table) use (&$matches, $event) {
            $table->filter('tr, .match-row')->each(function (Crawler $row) use (&$matches, $event) {
                $matchData = $this->extractMatchData($row);
                if ($matchData) {
                    $matchData['bracket_type'] = 'swiss';
                    
                    // Extract round number
                    $roundNode = $row->filter('.round, td:contains("Round")')->first();
                    if ($roundNode->count() > 0) {
                        if (preg_match('/Round\s+(\d+)/i', $roundNode->text(), $matches)) {
                            $matchData['round'] = 'Round ' . $matches[1];
                        }
                    }
                    
                    $match = $this->createMatch($matchData, $event);
                    if ($match) {
                        $matches[] = $match;
                    }
                }
            });
        });
        
        return $matches;
    }

    private function extractMatchData(Crawler $node)
    {
        $data = [];
        
        // Team 1
        $team1Node = $node->filter('.team1 .team-template-text, .team-left .team-template-text, td:nth-child(1) .team-template-text')->first();
        if ($team1Node->count() > 0) {
            $data['team1_name'] = trim($team1Node->text());
        }
        
        // Team 2
        $team2Node = $node->filter('.team2 .team-template-text, .team-right .team-template-text, td:nth-child(3) .team-template-text')->first();
        if ($team2Node->count() > 0) {
            $data['team2_name'] = trim($team2Node->text());
        }
        
        if (empty($data['team1_name']) || empty($data['team2_name'])) {
            return null;
        }
        
        // Score
        $scoreNode = $node->filter('.score, .bracket-score, td:nth-child(2)')->first();
        if ($scoreNode->count() > 0) {
            $scoreText = $scoreNode->text();
            if (preg_match('/(\d+)\s*[-:]\s*(\d+)/', $scoreText, $matches)) {
                $data['team1_score'] = intval($matches[1]);
                $data['team2_score'] = intval($matches[2]);
                $data['status'] = 'completed';
            }
        }
        
        // Match time
        $timeNode = $node->filter('.timer-object, abbr[data-timestamp]')->first();
        if ($timeNode->count() > 0) {
            $timestamp = $timeNode->attr('data-timestamp');
            if ($timestamp) {
                $data['scheduled_at'] = Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');
            }
        }
        
        // Format
        $formatNode = $node->filter('.format, abbr[title*="Best of"]')->first();
        if ($formatNode->count() > 0) {
            if (preg_match('/Best of (\d+)/i', $formatNode->attr('title') ?? $formatNode->text(), $matches)) {
                $data['best_of'] = intval($matches[1]);
            }
        }
        
        // VODs
        $vodLinks = [];
        $node->filter('a[href*="youtube.com/watch"], a[href*="twitch.tv/videos"]')->each(function ($link) use (&$vodLinks) {
            $vodLinks[] = $link->attr('href');
        });
        if (!empty($vodLinks)) {
            $data['vod_urls'] = $vodLinks;
        }
        
        // Map details
        $data['maps'] = $this->extractMapDetails($node);
        
        return $data;
    }

    private function extractBracketMatchData(Crawler $node)
    {
        $data = $this->extractMatchData($node);
        
        if (!$data) {
            // Try alternative selectors for bracket matches
            $data = [];
            
            // Teams
            $teams = $node->filter('.bracket-team-top, .bracket-team-bottom');
            if ($teams->count() >= 2) {
                $data['team1_name'] = trim($teams->eq(0)->filter('.team-template-text')->text(''));
                $data['team2_name'] = trim($teams->eq(1)->filter('.team-template-text')->text(''));
                
                // Scores
                $score1 = $teams->eq(0)->filter('.bracket-score')->text('');
                $score2 = $teams->eq(1)->filter('.bracket-score')->text('');
                
                if (is_numeric($score1) && is_numeric($score2)) {
                    $data['team1_score'] = intval($score1);
                    $data['team2_score'] = intval($score2);
                    $data['status'] = 'completed';
                }
            }
        }
        
        return $data;
    }

    private function extractMapDetails(Crawler $node)
    {
        $maps = [];
        
        // Look for map containers
        $node->filter('.mapholder, .map-stats, .game-details')->each(function (Crawler $mapNode, $index) use (&$maps) {
            $mapData = [
                'number' => $index + 1
            ];
            
            // Map name
            $mapNameNode = $mapNode->filter('.mapname, .map-name')->first();
            if ($mapNameNode->count() > 0) {
                $mapData['name'] = $this->normalizeMapName($mapNameNode->text());
            }
            
            // Scores
            $scoresNode = $mapNode->filter('.results, .map-score');
            if ($scoresNode->count() > 0) {
                if (preg_match('/(\d+)\s*-\s*(\d+)/', $scoresNode->text(), $matches)) {
                    $mapData['team1_score'] = intval($matches[1]);
                    $mapData['team2_score'] = intval($matches[2]);
                }
            }
            
            // Duration
            $durationNode = $mapNode->filter('.map-duration, .timer');
            if ($durationNode->count() > 0) {
                $mapData['duration'] = $this->parseDuration($durationNode->text());
            }
            
            if (!empty($mapData['name'])) {
                $maps[] = $mapData;
            }
        });
        
        return $maps;
    }

    private function createMatch($matchData, Event $event)
    {
        // Find teams
        $team1 = Team::where('name', $matchData['team1_name'])->first();
        $team2 = Team::where('name', $matchData['team2_name'])->first();
        
        if (!$team1 || !$team2) {
            Log::warning("Could not find teams for match: {$matchData['team1_name']} vs {$matchData['team2_name']}");
            return null;
        }
        
        // Determine winner
        $winnerId = null;
        if (isset($matchData['team1_score']) && isset($matchData['team2_score'])) {
            if ($matchData['team1_score'] > $matchData['team2_score']) {
                $winnerId = $team1->id;
            } else if ($matchData['team2_score'] > $matchData['team1_score']) {
                $winnerId = $team2->id;
            }
        }
        
        $matchAttributes = [
            'event_id' => $event->id,
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'team1_score' => $matchData['team1_score'] ?? 0,
            'team2_score' => $matchData['team2_score'] ?? 0,
            'scheduled_at' => $matchData['scheduled_at'] ?? now(),
            'round' => $matchData['round'] ?? 'Unknown',
            'bracket_position' => $matchData['bracket_position'] ?? null,
            'bracket_type' => $matchData['bracket_type'] ?? null,
            'status' => $matchData['status'] ?? 'upcoming',
            'format' => 'BO' . ($matchData['best_of'] ?? 3),
            'winner_id' => $winnerId,
            'vod_urls' => $matchData['vod_urls'] ?? []
        ];
        
        // Create match
        $match = GameMatch::create($matchAttributes);
        
        // Create map results
        if (isset($matchData['maps'])) {
            foreach ($matchData['maps'] as $mapData) {
                $this->createMap($match, $mapData);
            }
        }
        
        Log::info("Match created: {$team1->name} vs {$team2->name} - {$match->team1_score}:{$match->team2_score}");
        
        return $match;
    }

    private function createMap($match, $mapData)
    {
        $winnerId = null;
        if (isset($mapData['team1_score']) && isset($mapData['team2_score'])) {
            if ($mapData['team1_score'] > $mapData['team2_score']) {
                $winnerId = $match->team1_id;
            } else if ($mapData['team2_score'] > $mapData['team1_score']) {
                $winnerId = $match->team2_id;
            }
        }
        
        MatchMap::create([
            'match_id' => $match->id,
            'map_number' => $mapData['number'],
            'map_name' => $mapData['name'],
            'game_mode' => $this->getMapMode($mapData['name']),
            'team1_score' => $mapData['team1_score'] ?? 0,
            'team2_score' => $mapData['team2_score'] ?? 0,
            'winner_id' => $winnerId,
            'duration_seconds' => $mapData['duration'] ?? null,
            'status' => 'completed'
        ]);
    }

    private function scrapeFinalStandings(Crawler $crawler, Event $event)
    {
        $standings = [];
        
        // Find prize pool table
        $crawler->filter('.prizepooltable tr, table[class*="prize"] tr')->each(function (Crawler $row, $index) use (&$standings, $event) {
            if ($row->filter('th')->count() > 0) return; // Skip header
            
            $standingData = $this->extractStandingData($row);
            if ($standingData) {
                $standing = $this->createStanding($standingData, $event);
                if ($standing) {
                    $standings[] = $standing;
                }
            }
        });
        
        return $standings;
    }

    private function extractStandingData(Crawler $row)
    {
        $data = [];
        
        // Position
        $positionNode = $row->filter('td:first-child')->first();
        if ($positionNode->count() > 0) {
            $positionText = trim($positionNode->text());
            
            // Handle different position formats
            if (preg_match('/^(\d+)(?:st|nd|rd|th)?$/i', $positionText, $matches)) {
                $data['position'] = intval($matches[1]);
                $data['position_start'] = $data['position'];
                $data['position_end'] = $data['position'];
            } else if (preg_match('/(\d+)(?:st|nd|rd|th)?\s*-\s*(\d+)(?:st|nd|rd|th)?/i', $positionText, $matches)) {
                $data['position'] = intval($matches[1]);
                $data['position_start'] = intval($matches[1]);
                $data['position_end'] = intval($matches[2]);
            }
        }
        
        // Team
        $teamNode = $row->filter('.team-template-team-standard .team-template-text')->first();
        if ($teamNode->count() > 0) {
            $data['team_name'] = trim($teamNode->text());
        }
        
        // Prize money
        $prizeNode = $row->filter('td:contains("$"), td:contains("€")')->first();
        if ($prizeNode->count() === 0) {
            $prizeNode = $row->filter('td:last-child')->first();
        }
        
        if ($prizeNode->count() > 0) {
            $data['prize_money'] = $this->extractPrizeMoney($prizeNode->text());
        }
        
        // Points (if applicable)
        $pointsNode = $row->filter('td:contains("points")')->first();
        if ($pointsNode->count() > 0) {
            if (preg_match('/(\d+)\s*points?/i', $pointsNode->text(), $matches)) {
                $data['points'] = intval($matches[1]);
            }
        }
        
        return !empty($data['team_name']) ? $data : null;
    }

    private function createStanding($standingData, Event $event)
    {
        $team = Team::where('name', $standingData['team_name'])->first();
        
        if (!$team) {
            Log::warning("Could not find team for standing: " . $standingData['team_name']);
            return null;
        }
        
        $standing = EventStanding::updateOrCreate(
            [
                'event_id' => $event->id,
                'team_id' => $team->id
            ],
            [
                'position' => $standingData['position'],
                'position_start' => $standingData['position_start'],
                'position_end' => $standingData['position_end'],
                'prize_money' => $standingData['prize_money'] ?? 0,
                'points' => $standingData['points'] ?? 0
            ]
        );
        
        // Update team earnings
        if ($standingData['prize_money'] > 0) {
            $team->increment('earnings', $standingData['prize_money']);
            
            // Also update player earnings (divided equally)
            $players = Player::where('team_id', $team->id)->get();
            if ($players->count() > 0) {
                $perPlayerEarnings = $standingData['prize_money'] / $players->count();
                foreach ($players as $player) {
                    $player->increment('earnings', $perPlayerEarnings);
                }
            }
        }
        
        return $standing;
    }

    private function updateAdditionalInformation(Crawler $crawler, Event $event, $teams, $players)
    {
        // Update event with additional metadata
        $metadata = [
            'total_matches' => GameMatch::where('event_id', $event->id)->count(),
            'total_maps_played' => MatchMap::whereHas('match', function ($query) use ($event) {
                $query->where('event_id', $event->id);
            })->count(),
            'participating_teams' => count($teams),
            'total_players' => count($players),
            'winner' => EventStanding::where('event_id', $event->id)
                ->where('position', 1)
                ->with('team')
                ->first()
                ?->team->name,
            'runner_up' => EventStanding::where('event_id', $event->id)
                ->where('position', 2)
                ->with('team')
                ->first()
                ?->team->name,
            'mvp' => $this->extractMVP($crawler),
            'viewership' => $this->extractViewership($crawler),
            'last_updated' => now()
        ];
        
        $event->metadata = json_encode($metadata);
        $event->save();
    }

    private function updateEloRatings()
    {
        Log::info("Updating ELO ratings based on match results...");
        
        // Get all completed matches ordered by date
        $matches = GameMatch::where('status', 'completed')
            ->whereNotNull('winner_id')
            ->orderBy('scheduled_at')
            ->get();
        
        foreach ($matches as $match) {
            $this->updateMatchElo($match);
        }
        
        Log::info("ELO ratings updated successfully");
    }

    private function updateMatchElo($match)
    {
        $team1 = Team::find($match->team1_id);
        $team2 = Team::find($match->team2_id);
        
        if (!$team1 || !$team2) {
            return;
        }
        
        // K-factor based on tournament tier
        $kFactor = 32;
        if ($match->event && $match->event->tier === 'S') {
            $kFactor = 48;
        } else if ($match->event && $match->event->tier === 'A') {
            $kFactor = 40;
        }
        
        // Expected scores
        $expectedScore1 = 1 / (1 + pow(10, ($team2->rating - $team1->rating) / 400));
        $expectedScore2 = 1 - $expectedScore1;
        
        // Actual scores
        $actualScore1 = $match->winner_id == $team1->id ? 1 : 0;
        $actualScore2 = $match->winner_id == $team2->id ? 1 : 0;
        
        // Update ratings
        $newRating1 = $team1->rating + $kFactor * ($actualScore1 - $expectedScore1);
        $newRating2 = $team2->rating + $kFactor * ($actualScore2 - $expectedScore2);
        
        $team1->update(['rating' => max(100, round($newRating1))]);
        $team2->update(['rating' => max(100, round($newRating2))]);
        
        // Also update player ratings proportionally
        $this->updatePlayerRatings($team1, $actualScore1 > 0);
        $this->updatePlayerRatings($team2, $actualScore2 > 0);
    }

    private function updatePlayerRatings(Team $team, $won)
    {
        $players = Player::where('team_id', $team->id)->get();
        
        foreach ($players as $player) {
            $kFactor = 16; // Lower K-factor for individual players
            $adjustment = $won ? $kFactor : -$kFactor;
            
            $newRating = $player->rating + $adjustment;
            $player->update(['rating' => max(100, round($newRating))]);
        }
    }

    private function updateComprehensiveStatistics()
    {
        Log::info("Updating comprehensive statistics...");
        
        // Update team statistics
        $teams = Team::all();
        foreach ($teams as $team) {
            $this->updateTeamStats($team);
        }
        
        // Update player statistics
        $players = Player::all();
        foreach ($players as $player) {
            $this->updatePlayerStats($player);
        }
        
        Log::info("Statistics updated successfully");
    }

    private function updateTeamStats(Team $team)
    {
        $stats = [
            'total_matches' => 0,
            'matches_won' => 0,
            'matches_lost' => 0,
            'maps_played' => 0,
            'maps_won' => 0,
            'maps_lost' => 0,
            'tournaments_played' => 0,
            'tournament_wins' => 0
        ];
        
        // Count matches
        $matches = GameMatch::where('team1_id', $team->id)
            ->orWhere('team2_id', $team->id)
            ->where('status', 'completed')
            ->get();
        
        foreach ($matches as $match) {
            $stats['total_matches']++;
            
            if ($match->winner_id == $team->id) {
                $stats['matches_won']++;
            } else if ($match->winner_id) {
                $stats['matches_lost']++;
            }
            
            // Count maps
            $maps = MatchMap::where('match_id', $match->id)->get();
            foreach ($maps as $map) {
                $stats['maps_played']++;
                
                if ($map->winner_id == $team->id) {
                    $stats['maps_won']++;
                } else if ($map->winner_id) {
                    $stats['maps_lost']++;
                }
            }
        }
        
        // Count tournaments
        $stats['tournaments_played'] = DB::table('event_teams')
            ->where('team_id', $team->id)
            ->count();
        
        $stats['tournament_wins'] = EventStanding::where('team_id', $team->id)
            ->where('position', 1)
            ->count();
        
        // Calculate win rates
        $matchWinRate = $stats['total_matches'] > 0 
            ? ($stats['matches_won'] / $stats['total_matches']) * 100 
            : 0;
        
        $mapWinRate = $stats['maps_played'] > 0 
            ? ($stats['maps_won'] / $stats['maps_played']) * 100 
            : 0;
        
        // Update team
        $team->update([
            'wins' => $stats['matches_won'],
            'losses' => $stats['matches_lost'],
            'win_rate' => round($matchWinRate, 2),
            'map_win_rate' => round($mapWinRate, 2),
            'tournaments_won' => $stats['tournament_wins']
        ]);
    }

    private function updatePlayerStats(Player $player)
    {
        // Count matches played while on current team
        $matchCount = 0;
        $tournamentsPlayed = 0;
        
        if ($player->team_id) {
            $matchCount = GameMatch::where(function($query) use ($player) {
                $query->where('team1_id', $player->team_id)
                    ->orWhere('team2_id', $player->team_id);
            })
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', $player->joined_team_at ?? '2000-01-01')
            ->count();
            
            $tournamentsPlayed = DB::table('event_teams')
                ->join('events', 'events.id', '=', 'event_teams.event_id')
                ->where('event_teams.team_id', $player->team_id)
                ->where('events.start_date', '>=', $player->joined_team_at ?? '2000-01-01')
                ->count();
        }
        
        $player->update([
            'total_matches' => $matchCount,
            'tournaments_played' => $tournamentsPlayed
        ]);
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
        
        // Extract actual image URL from thumb URL
        if (strpos($src, '/thumb/') !== false) {
            // Remove thumb parameters
            $src = preg_replace('/\/thumb\/(.+?)\/\d+px-.+$/', '/$1', $src);
        }
        
        return $src;
    }

    private function extractCountryFromFlag($flagNode)
    {
        // Try to get country from title or alt attribute
        $country = $flagNode->attr('title') ?: $flagNode->attr('alt');
        
        if ($country) {
            $country = str_replace(['Flag of ', 'flag'], '', $country);
            $country = trim($country);
            
            if (!empty($country)) {
                return $country;
            }
        }
        
        // Try to extract from image filename
        $src = $flagNode->attr('src');
        if ($src && preg_match('/([A-Z]{2,3})(?:\.png|\.jpg|\.svg)/i', basename($src), $matches)) {
            $code = strtoupper($matches[1]);
            return $this->countryMap[$code] ?? $code;
        }
        
        return null;
    }

    private function extractSocialMediaLinks(Crawler $crawler)
    {
        $socialMedia = [];
        
        $infobox = $crawler->filter('.infobox, .fo-nttax-infobox');
        
        // Twitter/X
        $twitterLink = $infobox->filter('a[href*="twitter.com"], a[href*="x.com"]')->first();
        if ($twitterLink->count() > 0) {
            $socialMedia['twitter'] = $twitterLink->attr('href');
        }
        
        // Twitch
        $twitchLink = $infobox->filter('a[href*="twitch.tv"]')->first();
        if ($twitchLink->count() > 0) {
            $socialMedia['twitch'] = $twitchLink->attr('href');
        }
        
        // YouTube
        $youtubeLink = $infobox->filter('a[href*="youtube.com"]')->first();
        if ($youtubeLink->count() > 0) {
            $socialMedia['youtube'] = $youtubeLink->attr('href');
        }
        
        // Instagram
        $instagramLink = $infobox->filter('a[href*="instagram.com"]')->first();
        if ($instagramLink->count() > 0) {
            $socialMedia['instagram'] = $instagramLink->attr('href');
        }
        
        // Discord
        $discordLink = $infobox->filter('a[href*="discord.gg"], a[href*="discord.com"]')->first();
        if ($discordLink->count() > 0) {
            $socialMedia['discord'] = $discordLink->attr('href');
        }
        
        // TikTok
        $tiktokLink = $infobox->filter('a[href*="tiktok.com"]')->first();
        if ($tiktokLink->count() > 0) {
            $socialMedia['tiktok'] = $tiktokLink->attr('href');
        }
        
        // Website
        $websiteNode = $crawler->filter('.infobox-cell-2:contains("Website") + .infobox-cell-2 a')->first();
        if ($websiteNode->count() > 0) {
            $socialMedia['website'] = $websiteNode->attr('href');
        }
        
        return $socialMedia;
    }

    private function extractPrizeMoney($text)
    {
        // Remove formatting and extract numeric value
        $text = str_replace(['$', '€', ',', ' ', 'USD', 'EUR'], '', $text);
        
        if (preg_match('/(\d+(?:\.\d+)?)/', $text, $matches)) {
            return floatval($matches[1]);
        }
        
        return 0;
    }

    private function normalizeMapName($mapName)
    {
        $mapName = strtolower(trim($mapName));
        $mapName = str_replace([' ', "'", "-"], ['_', '', '_'], $mapName);
        
        $mapCorrections = [
            'hells_heaven' => 'hells-heaven',
            'shin_shibuya' => 'shin-shibuya',
            'spider_islands' => 'spider-islands',
            'asgards_throne_room' => 'asgard-throne-room',
            'sanctum_sanctorum' => 'sanctum-sanctorum',
            'tokyo_2099' => 'tokyo-2099-convoy',
            'yggsgard' => 'yggsgard-convoy',
            'wakanda' => 'intergalactic-empire-of-wakanda'
        ];
        
        return $mapCorrections[$mapName] ?? $mapName;
    }

    private function getMapMode($mapName)
    {
        $mapModes = [
            'midtown' => 'convoy',
            'tokyo-2099-convoy' => 'convoy',
            'yggsgard-convoy' => 'convoy',
            'hells-heaven' => 'domination',
            'shin-shibuya' => 'domination',
            'klyntar' => 'domination',
            'intergalactic-empire-of-wakanda' => 'domination',
            'asgard-throne-room' => 'convergence',
            'sanctum-sanctorum' => 'convergence',
            'spider-islands' => 'convergence'
        ];
        
        return $mapModes[$mapName] ?? 'unknown';
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
            'sub' => 'substitute',
            'coach' => 'coach',
            'manager' => 'manager'
        ];
        
        return $roleMap[$role] ?? 'flex';
    }

    private function generateShortName($teamName)
    {
        // Remove common suffixes
        $teamName = preg_replace('/\s+(Gaming|Esports|Team|Club|eSports|GG)$/i', '', $teamName);
        
        // If it's already short, use it
        if (strlen($teamName) <= 5) {
            return strtoupper($teamName);
        }
        
        // Try to use existing abbreviation
        if (preg_match('/\b([A-Z]{2,5})\b/', $teamName, $matches)) {
            return $matches[1];
        }
        
        // Generate from initials
        $words = explode(' ', $teamName);
        if (count($words) >= 2) {
            $shortName = '';
            foreach ($words as $word) {
                if (!empty($word)) {
                    $shortName .= strtoupper(substr($word, 0, 1));
                }
            }
            return substr($shortName, 0, 5);
        }
        
        // Use first 3-4 characters
        return strtoupper(substr($teamName, 0, 4));
    }

    private function getCountryFlag($country)
    {
        $flags = [
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'United Kingdom' => '🇬🇧',
            'France' => '🇫🇷',
            'Germany' => '🇩🇪',
            'Spain' => '🇪🇸',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Belgium' => '🇧🇪',
            'Sweden' => '🇸🇪',
            'Denmark' => '🇩🇰',
            'Norway' => '🇳🇴',
            'Finland' => '🇫🇮',
            'Poland' => '🇵🇱',
            'Czech Republic' => '🇨🇿',
            'Russia' => '🇷🇺',
            'Ukraine' => '🇺🇦',
            'Turkey' => '🇹🇷',
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Singapore' => '🇸🇬',
            'Malaysia' => '🇲🇾',
            'Philippines' => '🇵🇭',
            'Thailand' => '🇹🇭',
            'Indonesia' => '🇮🇩',
            'Vietnam' => '🇻🇳',
            'India' => '🇮🇳',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Mexico' => '🇲🇽',
            'Peru' => '🇵🇪',
            'Colombia' => '🇨🇴'
        ];
        
        return $flags[$country] ?? null;
    }

    private function determineRegion($country, $eventRegion = null)
    {
        if (!$country) {
            return $eventRegion ?? 'International';
        }
        
        $regions = [
            'North America' => ['United States', 'Canada'],
            'Europe' => [
                'United Kingdom', 'France', 'Germany', 'Spain', 'Italy', 'Netherlands',
                'Belgium', 'Sweden', 'Denmark', 'Norway', 'Finland', 'Poland',
                'Czech Republic', 'Slovakia', 'Hungary', 'Romania', 'Bulgaria',
                'Greece', 'Turkey', 'Russia', 'Ukraine', 'Belarus'
            ],
            'Asia' => [
                'South Korea', 'Japan', 'China', 'Taiwan', 'Hong Kong', 'Macau',
                'Singapore', 'Malaysia', 'Thailand', 'Philippines', 'Indonesia',
                'Vietnam', 'India', 'Pakistan', 'Bangladesh'
            ],
            'Oceania' => ['Australia', 'New Zealand'],
            'South America' => [
                'Brazil', 'Argentina', 'Chile', 'Peru', 'Colombia', 'Venezuela',
                'Ecuador', 'Uruguay', 'Paraguay', 'Bolivia'
            ],
            'Central America' => ['Mexico', 'Costa Rica', 'Panama']
        ];
        
        foreach ($regions as $region => $countries) {
            if (in_array($country, $countries)) {
                return $region;
            }
        }
        
        return $eventRegion ?? 'International';
    }

    private function parseDuration($durationText)
    {
        if (preg_match('/(\d+):(\d+)/', $durationText, $matches)) {
            return (intval($matches[1]) * 60) + intval($matches[2]);
        }
        
        if (preg_match('/(\d+)m/', $durationText, $matches)) {
            return intval($matches[1]) * 60;
        }
        
        return null;
    }

    private function parseDate($dateText)
    {
        if (empty($dateText) || $dateText === '-' || strtolower($dateText) === 'present') {
            return null;
        }
        
        try {
            return Carbon::parse($dateText)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractEventDescription(Crawler $crawler)
    {
        // Get first paragraph
        $firstParagraph = $crawler->filter('#mw-content-text > .mw-parser-output > p')->first();
        if ($firstParagraph->count() > 0) {
            $text = $firstParagraph->text();
            $text = preg_replace('/\[[^\]]+\]/', '', $text); // Remove [edit] links
            $text = trim($text);
            
            if (strlen($text) > 50) {
                return $text;
            }
        }
        
        return null;
    }

    private function extractEventFormat(Crawler $crawler)
    {
        $formatNode = $crawler->filter('.infobox-cell-2:contains("Format") + .infobox-cell-2')->first();
        if ($formatNode->count() > 0) {
            return trim($formatNode->text());
        }
        
        // Try to determine from page structure
        if ($crawler->filter('.bracket')->count() > 0 && $crawler->filter('.grouptable')->count() > 0) {
            return 'Group Stage + Playoffs';
        } else if ($crawler->filter('.bracket')->count() > 0) {
            return 'Single Elimination';
        } else if ($crawler->filter('.crosstable')->count() > 0) {
            return 'Round Robin';
        } else if ($crawler->filter('.swissresults')->count() > 0) {
            return 'Swiss System';
        }
        
        return 'Tournament';
    }

    private function extractStreamInformation(Crawler $crawler)
    {
        $streams = [];
        
        $crawler->filter('a[href*="twitch.tv"], a[href*="youtube.com/watch"], a[href*="youtube.com/live"]')->each(function ($link) use (&$streams) {
            $url = $link->attr('href');
            $platform = '';
            
            if (strpos($url, 'twitch.tv') !== false) {
                $platform = 'twitch';
            } else if (strpos($url, 'youtube.com') !== false) {
                $platform = 'youtube';
            }
            
            if ($platform) {
                $streams[] = [
                    'platform' => $platform,
                    'url' => $url,
                    'title' => $link->text() ?: $platform
                ];
            }
        });
        
        return $streams;
    }

    private function extractVenueInformation(Crawler $crawler)
    {
        $venue = [];
        
        $venueNode = $crawler->filter('.infobox-cell-2:contains("Venue") + .infobox-cell-2')->first();
        if ($venueNode->count() > 0) {
            $venue['name'] = trim($venueNode->text());
        }
        
        $locationNode = $crawler->filter('.infobox-cell-2:contains("Location") + .infobox-cell-2')->first();
        if ($locationNode->count() > 0) {
            $venue['location'] = trim($locationNode->text());
        }
        
        return $venue;
    }

    private function findTeamByName($teams, $teamName)
    {
        foreach ($teams as $team) {
            if ($team->name === $teamName) {
                return $team;
            }
        }
        return null;
    }

    private function determineRoundFromBracketPosition(Crawler $node)
    {
        // Check parent containers for round information
        $roundContainer = $node->closest('.bracket-column');
        if ($roundContainer->count() > 0) {
            $headerNode = $roundContainer->filter('.bracket-header, .bracket-column-header')->first();
            if ($headerNode->count() > 0) {
                return trim($headerNode->text());
            }
        }
        
        // Check for round class names
        $classes = $node->attr('class') ?? '';
        
        if (stripos($classes, 'grand-final') !== false) {
            return 'Grand Final';
        } else if (stripos($classes, 'final') !== false) {
            return 'Final';
        } else if (stripos($classes, 'semifinal') !== false) {
            return 'Semifinal';
        } else if (stripos($classes, 'quarterfinal') !== false) {
            return 'Quarterfinal';
        } else if (preg_match('/round-of-(\d+)/i', $classes, $matches)) {
            return 'Round of ' . $matches[1];
        }
        
        return 'Playoff';
    }

    private function extractMVP(Crawler $crawler)
    {
        $mvpNode = $crawler->filter('.infobox-cell-2:contains("MVP") + .infobox-cell-2 a')->first();
        if ($mvpNode->count() > 0) {
            return trim($mvpNode->text());
        }
        
        return null;
    }

    private function extractViewership(Crawler $crawler)
    {
        $viewershipNode = $crawler->filter('.infobox-cell-2:contains("Peak Viewers") + .infobox-cell-2')->first();
        if ($viewershipNode->count() > 0) {
            $text = $viewershipNode->text();
            if (preg_match('/(\d+(?:,\d+)*)/', $text, $matches)) {
                return intval(str_replace(',', '', $matches[1]));
            }
        }
        
        return null;
    }
}