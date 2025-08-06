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

class EnhancedLiquipediaScraper
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
            'game_version' => '1.0'
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
            'game_version' => '1.0'
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
            'game_version' => '1.0'
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
            'game_version' => '1.0'
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
            'game_version' => '1.0'
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

    private $mapModes = [
        // Convoy Maps
        'midtown' => 'convoy',
        'tokyo-2099-convoy' => 'convoy',
        'yggsgard-convoy' => 'convoy',
        
        // Domination Maps
        'hells-heaven' => 'domination',
        'shin-shibuya' => 'domination', 
        'klyntar' => 'domination',
        'intergalactic-empire-of-wakanda' => 'domination',
        
        // Convergence Maps
        'asgard-throne-room' => 'convergence',
        'sanctum-sanctorum' => 'convergence',
        'spider-islands' => 'convergence'
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

    public function getTournamentConfig()
    {
        return $this->tournaments;
    }

    public function scrapeAllTournaments($updateElo = true)
    {
        $results = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($this->tournaments as $key => $tournament) {
                Log::info("Starting enhanced scraping for: {$tournament['name']}");
                
                $results[$key] = $this->scrapeTournament($tournament);
                
                // Add delay to avoid rate limiting
                sleep(2);
            }
            
            // Update ELO ratings after all matches are imported
            if ($updateElo) {
                $this->updateEloRatings();
            }
            
            // Update team statistics
            $this->updateTeamStatistics();
            
            // Update player statistics
            $this->updatePlayerStatistics();
            
            DB::commit();
            
            Log::info("Successfully completed enhanced scraping of all tournaments");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error during enhanced scraping: " . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }

    public function scrapeTournament($tournamentData)
    {
        $url = $this->baseUrl . $tournamentData['url'];
        
        // Use proper headers to avoid blocking
        $response = Http::withHeaders($this->headers)
            ->timeout(30)
            ->get($url);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch page: " . $response->status());
        }
        
        $crawler = new Crawler($response->body());
        
        // Create or update event with comprehensive data
        $event = $this->createOrUpdateEvent($tournamentData, $crawler);
        
        // Scrape prize pool distribution
        $prizeDistribution = $this->scrapePrizeDistribution($crawler);
        
        // Scrape teams with detailed information
        $teams = $this->scrapeTeamsEnhanced($crawler, $event);
        
        // Scrape group stage results if applicable
        $groupStageResults = $this->scrapeGroupStage($crawler, $event);
        
        // Scrape bracket/playoff matches
        $bracketMatches = $this->scrapeBracketMatches($crawler, $event);
        
        // Scrape final standings with prize money
        $standings = $this->scrapeDetailedStandings($crawler, $event, $prizeDistribution);
        
        // Scrape notable matches and highlights
        $highlights = $this->scrapeHighlights($crawler);
        
        // Update event with additional metadata
        $this->updateEventMetadata($event, $crawler);
        
        return [
            'event' => $event,
            'teams' => $teams,
            'group_stage' => $groupStageResults,
            'bracket_matches' => $bracketMatches,
            'standings' => $standings,
            'highlights' => $highlights,
            'stats' => [
                'total_teams' => count($teams),
                'total_matches' => count($groupStageResults) + count($bracketMatches),
                'total_prize_pool' => $tournamentData['prize_pool']
            ]
        ];
    }

    private function createOrUpdateEvent($tournamentData, Crawler $crawler)
    {
        // Extract comprehensive event details
        $description = $this->extractDetailedDescription($crawler);
        $format = $this->extractTournamentFormat($crawler);
        $participants = $this->extractParticipantCount($crawler);
        $streams = $this->extractStreamLinks($crawler);
        $sponsors = $this->extractSponsors($crawler);
        
        $eventData = [
            'name' => $tournamentData['name'],
            'description' => $description,
            'location' => 'Online',
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
            'participants' => $participants,
            'game_version' => $tournamentData['game_version'],
            'stream_urls' => json_encode($streams),
            'sponsors' => json_encode($sponsors),
            'liquipedia_url' => $this->baseUrl . $tournamentData['url']
        ];
        
        $event = Event::updateOrCreate(
            ['name' => $tournamentData['name']],
            $eventData
        );
        
        Log::info("Event created/updated: {$event->name} (ID: {$event->id})");
        
        return $event;
    }

    private function scrapeTeamsEnhanced(Crawler $crawler, Event $event)
    {
        $teams = [];
        
        // Multiple selectors for different tournament formats
        $teamSelectors = [
            '.teamcard',
            '.participant-team',
            '.bracket-team',
            '.grouptable .team-template-team-standard',
            '.participanttable .team-template-team-standard',
            'div[class*="team-template"]'
        ];
        
        foreach ($teamSelectors as $selector) {
            $crawler->filter($selector)->each(function (Crawler $node) use (&$teams, $event) {
                $teamData = $this->extractEnhancedTeamData($node);
                
                if ($teamData && !empty($teamData['name'])) {
                    // Create or update team with all data
                    $team = $this->createOrUpdateTeamEnhanced($teamData);
                    
                    // Attach to event with additional metadata
                    $event->teams()->syncWithoutDetaching([
                        $team->id => [
                            'seed' => $teamData['seed'] ?? null,
                            'registered_at' => now(),
                            'group_name' => $teamData['group'] ?? null,
                            'qualified_from' => $teamData['qualified_from'] ?? null
                        ]
                    ]);
                    
                    // Scrape roster with detailed player info
                    $roster = $this->scrapeEnhancedRoster($node, $team);
                    $teamData['roster'] = $roster;
                    
                    $teams[$team->name] = $teamData;
                    
                    Log::info("Team scraped: {$team->name} with " . count($roster) . " players");
                }
            });
        }
        
        // Also check for teams in match results that might not be in participant list
        $this->scrapeAdditionalTeamsFromMatches($crawler, $event, $teams);
        
        return $teams;
    }

    private function extractEnhancedTeamData(Crawler $node)
    {
        $data = [];
        
        // Team name - try multiple selectors
        $nameSelectors = [
            '.teamname',
            '.team-template-text a',
            '.team-template-text',
            'a[title]',
            '.name'
        ];
        
        foreach ($nameSelectors as $selector) {
            $nameNode = $node->filter($selector)->first();
            if ($nameNode->count() > 0) {
                $data['name'] = trim($nameNode->text());
                if (!empty($data['name'])) break;
            }
        }
        
        if (empty($data['name'])) {
            return null;
        }
        
        // Team page URL for detailed scraping
        $teamLinkNode = $node->filter('a[href*="/marvelrivals/"]')->first();
        if ($teamLinkNode->count() > 0) {
            $data['team_page_url'] = $teamLinkNode->attr('href');
        }
        
        // Logo
        $logoSelectors = [
            '.teamlogo img',
            '.team-template-image img',
            'img[alt*="' . $data['name'] . '"]'
        ];
        
        foreach ($logoSelectors as $selector) {
            $logoNode = $node->filter($selector)->first();
            if ($logoNode->count() > 0) {
                $data['logo'] = $this->extractImageUrl($logoNode->attr('src'));
                break;
            }
        }
        
        // Country/Region
        $countryNode = $node->filter('.flag img, [class*="flag"] img')->first();
        if ($countryNode->count() > 0) {
            $data['country'] = $this->extractCountryFromFlag($countryNode);
            $data['country_code'] = $this->extractCountryCode($countryNode);
        }
        
        // Seed/Placement
        $seedNode = $node->filter('.seed, .placement, td:first-child')->first();
        if ($seedNode->count() > 0) {
            $seedText = $seedNode->text();
            if (preg_match('/\d+/', $seedText, $matches)) {
                $data['seed'] = intval($matches[0]);
            }
        }
        
        // Group (for group stage tournaments)
        if (preg_match('/group[- ]?([a-d])/i', $node->html(), $matches)) {
            $data['group'] = strtoupper($matches[1]);
        }
        
        // Qualified from
        $qualifiedNode = $node->filter('.qualified-from, .note')->first();
        if ($qualifiedNode->count() > 0) {
            $data['qualified_from'] = trim($qualifiedNode->text());
        }
        
        // If we have a team page URL, scrape additional details
        if (isset($data['team_page_url'])) {
            $additionalData = $this->scrapeTeamPage($data['team_page_url']);
            $data = array_merge($data, $additionalData);
        }
        
        return $data;
    }

    private function scrapeTeamPage($teamPageUrl)
    {
        $additionalData = [];
        
        try {
            $fullUrl = strpos($teamPageUrl, 'http') === 0 ? $teamPageUrl : $this->baseUrl . $teamPageUrl;
            
            $response = Http::withHeaders($this->headers)
                ->timeout(20)
                ->get($fullUrl);
            
            if ($response->successful()) {
                $crawler = new Crawler($response->body());
                
                // Social media links
                $additionalData['social_media'] = $this->extractTeamSocialMedia($crawler);
                
                // Founded date
                $foundedNode = $crawler->filter('.infobox-cell-2:contains("Founded") + .infobox-cell-2')->first();
                if ($foundedNode->count() > 0) {
                    $additionalData['founded'] = $foundedNode->text();
                }
                
                // Coach
                $coachNode = $crawler->filter('.infobox-cell-2:contains("Coach") + .infobox-cell-2 a')->first();
                if ($coachNode->count() > 0) {
                    $additionalData['coach'] = trim($coachNode->text());
                }
                
                // Total earnings
                $earningsNode = $crawler->filter('.infobox-cell-2:contains("Total Earnings") + .infobox-cell-2')->first();
                if ($earningsNode->count() > 0) {
                    $additionalData['total_earnings'] = $this->extractPrizeMoney($earningsNode->text());
                }
                
                // Recent results
                $additionalData['recent_results'] = $this->extractRecentResults($crawler);
            }
        } catch (\Exception $e) {
            Log::warning("Could not scrape team page: " . $teamPageUrl . " - " . $e->getMessage());
        }
        
        return $additionalData;
    }

    private function extractTeamSocialMedia(Crawler $crawler)
    {
        $socialMedia = [];
        
        // Check infobox for social links
        $infobox = $crawler->filter('.infobox, .fo-nttax-infobox');
        
        // Twitter/X
        $twitterSelectors = [
            'a[href*="twitter.com"]',
            'a[href*="x.com"]'
        ];
        
        foreach ($twitterSelectors as $selector) {
            $link = $infobox->filter($selector)->first();
            if ($link->count() > 0) {
                $socialMedia['twitter'] = $link->attr('href');
                break;
            }
        }
        
        // Instagram
        $instagramLink = $infobox->filter('a[href*="instagram.com"]')->first();
        if ($instagramLink->count() > 0) {
            $socialMedia['instagram'] = $instagramLink->attr('href');
        }
        
        // YouTube
        $youtubeLink = $infobox->filter('a[href*="youtube.com"]')->first();
        if ($youtubeLink->count() > 0) {
            $socialMedia['youtube'] = $youtubeLink->attr('href');
        }
        
        // Twitch
        $twitchLink = $infobox->filter('a[href*="twitch.tv"]')->first();
        if ($twitchLink->count() > 0) {
            $socialMedia['twitch'] = $twitchLink->attr('href');
        }
        
        // TikTok
        $tiktokLink = $infobox->filter('a[href*="tiktok.com"]')->first();
        if ($tiktokLink->count() > 0) {
            $socialMedia['tiktok'] = $tiktokLink->attr('href');
        }
        
        // Discord
        $discordLink = $infobox->filter('a[href*="discord.gg"], a[href*="discord.com"]')->first();
        if ($discordLink->count() > 0) {
            $socialMedia['discord'] = $discordLink->attr('href');
        }
        
        // Website
        $websiteNode = $crawler->filter('.infobox-cell-2:contains("Website") + .infobox-cell-2 a')->first();
        if ($websiteNode->count() > 0) {
            $socialMedia['website'] = $websiteNode->attr('href');
        }
        
        return $socialMedia;
    }

    private function createOrUpdateTeamEnhanced($teamData)
    {
        // Calculate initial ELO rating if not set
        $initialElo = $this->calculateInitialElo($teamData);
        
        $teamAttributes = [
            'name' => $teamData['name'],
            'short_name' => $this->generateShortName($teamData['name']),
            'country' => $teamData['country'] ?? null,
            'region' => $this->determineRegionFromCountry($teamData['country'] ?? null),
            'logo' => $teamData['logo'] ?? null,
            'founded' => $teamData['founded'] ?? null,
            'coach' => $teamData['coach'] ?? null,
            'website' => $teamData['social_media']['website'] ?? null,
            'social_media' => $teamData['social_media'] ?? [],
            'status' => 'active',
            'game' => 'marvel_rivals',
            'platform' => 'PC',
            'rating' => $initialElo,
            'earnings' => $teamData['total_earnings'] ?? 0
        ];
        
        $team = Team::updateOrCreate(
            ['name' => $teamData['name']],
            $teamAttributes
        );
        
        return $team;
    }

    private function scrapeEnhancedRoster(Crawler $node, Team $team)
    {
        $players = [];
        
        // Try to find roster section
        $rosterSelectors = [
            '.roster-card',
            '.player-row',
            '.roster-player',
            'tr[class*="Player"]',
            '.teamcard-players tr',
            '.table-responsive tr'
        ];
        
        foreach ($rosterSelectors as $selector) {
            $playerNodes = $node->filter($selector);
            if ($playerNodes->count() > 0) {
                $playerNodes->each(function (Crawler $playerNode) use (&$players, $team) {
                    $playerData = $this->extractEnhancedPlayerData($playerNode);
                    
                    if ($playerData && !empty($playerData['ign'])) {
                        $player = $this->createOrUpdatePlayerEnhanced($playerData, $team);
                        if ($player) {
                            $players[] = $player;
                        }
                    }
                });
                
                if (count($players) > 0) break;
            }
        }
        
        // If no players found in team card, try to get from team page
        if (count($players) === 0 && isset($team->liquipedia_url)) {
            $players = $this->scrapePlayersFromTeamPage($team);
        }
        
        return $players;
    }

    private function extractEnhancedPlayerData(Crawler $node)
    {
        $data = [];
        
        // IGN/Username
        $ignSelectors = [
            '.player-name a',
            '.player a',
            'td:nth-child(2) a',
            '.ID a',
            'a[title]'
        ];
        
        foreach ($ignSelectors as $selector) {
            $ignNode = $node->filter($selector)->first();
            if ($ignNode->count() > 0) {
                $data['ign'] = trim($ignNode->text());
                $data['player_page_url'] = $ignNode->attr('href');
                break;
            }
        }
        
        if (empty($data['ign'])) {
            return null;
        }
        
        // Real name
        $realNameSelectors = [
            '.player-realname',
            '.Name',
            'td:nth-child(3)',
            '.realname'
        ];
        
        foreach ($realNameSelectors as $selector) {
            $realNameNode = $node->filter($selector)->first();
            if ($realNameNode->count() > 0) {
                $realName = trim($realNameNode->text());
                if (!empty($realName) && $realName !== '-' && $realName !== 'TBD') {
                    $data['real_name'] = $realName;
                    break;
                }
            }
        }
        
        // Country
        $countryNode = $node->filter('.flag img, [class*="flag"] img')->first();
        if ($countryNode->count() > 0) {
            $data['country'] = $this->extractCountryFromFlag($countryNode);
            $data['country_flag'] = $this->getCountryFlag($data['country']);
        }
        
        // Role
        $roleSelectors = [
            '.player-role',
            '.role',
            '.Position',
            'td:nth-child(4)',
            'td:contains("Vanguard"), td:contains("Duelist"), td:contains("Strategist")'
        ];
        
        foreach ($roleSelectors as $selector) {
            $roleNode = $node->filter($selector)->first();
            if ($roleNode->count() > 0) {
                $roleText = trim($roleNode->text());
                if (!empty($roleText)) {
                    $data['role'] = $this->normalizeRole($roleText);
                    break;
                }
            }
        }
        
        // If no role found, default to flex
        if (!isset($data['role'])) {
            $data['role'] = 'flex';
        }
        
        // Age
        $ageNode = $node->filter('.Age, td:contains("years old")')->first();
        if ($ageNode->count() > 0) {
            if (preg_match('/(\d+)/', $ageNode->text(), $matches)) {
                $data['age'] = intval($matches[1]);
            }
        }
        
        // Join date
        $joinDateNode = $node->filter('.JoinDate, .join-date')->first();
        if ($joinDateNode->count() > 0) {
            try {
                $data['joined_team_at'] = Carbon::parse($joinDateNode->text())->format('Y-m-d');
            } catch (\Exception $e) {
                // Ignore parse errors
            }
        }
        
        // If we have a player page URL, scrape additional details
        if (isset($data['player_page_url'])) {
            $additionalData = $this->scrapePlayerPage($data['player_page_url']);
            $data = array_merge($data, $additionalData);
        }
        
        return $data;
    }

    private function scrapePlayerPage($playerPageUrl)
    {
        $additionalData = [];
        
        try {
            $fullUrl = strpos($playerPageUrl, 'http') === 0 ? $playerPageUrl : $this->baseUrl . $playerPageUrl;
            
            $response = Http::withHeaders($this->headers)
                ->timeout(20)
                ->get($fullUrl);
            
            if ($response->successful()) {
                $crawler = new Crawler($response->body());
                
                // Social media
                $additionalData['social_media'] = $this->extractPlayerSocialMedia($crawler);
                
                // Birth date for accurate age
                $birthDateNode = $crawler->filter('.infobox-cell-2:contains("Born") + .infobox-cell-2')->first();
                if ($birthDateNode->count() > 0) {
                    if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $birthDateNode->text(), $matches)) {
                        $additionalData['birth_date'] = $matches[1];
                        $additionalData['age'] = Carbon::parse($matches[1])->age;
                    }
                }
                
                // Total earnings
                $earningsNode = $crawler->filter('.infobox-cell-2:contains("Total Earnings") + .infobox-cell-2')->first();
                if ($earningsNode->count() > 0) {
                    $additionalData['earnings'] = $this->extractPrizeMoney($earningsNode->text());
                }
                
                // Alternative IDs
                $altIdNode = $crawler->filter('.infobox-cell-2:contains("Alternate IDs") + .infobox-cell-2')->first();
                if ($altIdNode->count() > 0) {
                    $additionalData['alternate_ids'] = array_map('trim', explode(',', $altIdNode->text()));
                }
                
                // Hero pool
                $additionalData['hero_pool'] = $this->extractHeroPool($crawler);
                
                // Career history
                $additionalData['career_history'] = $this->extractCareerHistory($crawler);
            }
        } catch (\Exception $e) {
            Log::warning("Could not scrape player page: " . $playerPageUrl . " - " . $e->getMessage());
        }
        
        return $additionalData;
    }

    private function extractPlayerSocialMedia(Crawler $crawler)
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
        
        return $socialMedia;
    }

    private function extractHeroPool(Crawler $crawler)
    {
        $heroPool = [];
        
        // Look for hero statistics section
        $crawler->filter('.hero-stats tr, .player-heroes tr')->each(function (Crawler $node) use (&$heroPool) {
            $hero = $node->filter('.hero-name, td:first-child')->text('');
            $playTime = $node->filter('.play-time, td:nth-child(2)')->text('');
            
            if (!empty($hero) && !empty($playTime)) {
                $heroPool[] = [
                    'hero' => $this->normalizeHeroName($hero),
                    'play_time' => $playTime,
                    'role' => $this->heroRoleMap[$this->normalizeHeroName($hero)] ?? 'flex'
                ];
            }
        });
        
        return $heroPool;
    }

    private function extractCareerHistory(Crawler $crawler)
    {
        $history = [];
        
        $crawler->filter('.team-history tr, .history-table tr')->each(function (Crawler $node) use (&$history) {
            $team = $node->filter('.team-name a, td:nth-child(1) a')->text('');
            $joinDate = $node->filter('.join-date, td:nth-child(2)')->text('');
            $leaveDate = $node->filter('.leave-date, td:nth-child(3)')->text('');
            
            if (!empty($team)) {
                $history[] = [
                    'team' => $team,
                    'join_date' => $this->parseDate($joinDate),
                    'leave_date' => $this->parseDate($leaveDate)
                ];
            }
        });
        
        return $history;
    }

    private function createOrUpdatePlayerEnhanced($playerData, Team $team)
    {
        // Calculate initial rating
        $initialRating = $this->calculateInitialPlayerRating($playerData);
        
        $playerAttributes = [
            'ign' => $playerData['ign'],
            'username' => $playerData['ign'], // Same as IGN for now
            'real_name' => $playerData['real_name'] ?? null,
            'country' => $playerData['country'] ?? null,
            'country_flag' => $playerData['country_flag'] ?? null,
            'role' => $playerData['role'] ?? 'flex',
            'team_id' => $team->id,
            'age' => $playerData['age'] ?? null,
            'social_media' => $playerData['social_media'] ?? [],
            'rating' => $initialRating,
            'earnings' => $playerData['earnings'] ?? 0,
            'hero_pool' => $playerData['hero_pool'] ?? [],
            'status' => 'active',
            'joined_team_at' => $playerData['joined_team_at'] ?? now()
        ];
        
        $player = Player::updateOrCreate(
            ['ign' => $playerData['ign']],
            $playerAttributes
        );
        
        // Track team history
        if (isset($playerData['career_history'])) {
            foreach ($playerData['career_history'] as $history) {
                PlayerTeamHistory::updateOrCreate(
                    [
                        'player_id' => $player->id,
                        'team_name' => $history['team'],
                        'joined_at' => $history['join_date']
                    ],
                    [
                        'left_at' => $history['leave_date'],
                        'role' => $playerData['role'] ?? 'flex'
                    ]
                );
            }
        }
        
        return $player;
    }

    private function scrapeGroupStage(Crawler $crawler, Event $event)
    {
        $matches = [];
        
        // Find group stage tables
        $crawler->filter('.grouptable, .group-stage, .matchlist')->each(function (Crawler $groupNode) use (&$matches, $event) {
            $groupName = $this->extractGroupName($groupNode);
            
            // Find matches within the group
            $groupNode->filter('.match-row, .matchlist-match, tr[class*="match"]')->each(function (Crawler $matchNode) use (&$matches, $event, $groupName) {
                $matchData = $this->extractDetailedMatchData($matchNode);
                
                if ($matchData) {
                    $matchData['round'] = $groupName ?: 'Group Stage';
                    $matchData['bracket_type'] = 'group';
                    
                    $match = $this->createOrUpdateMatchEnhanced($matchData, $event);
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
        
        // Find bracket matches
        $bracketSelectors = [
            '.bracket-match',
            '.bracket-game',
            '.playoff-match',
            '.bracket .match',
            'div[class*="bracket"] .match'
        ];
        
        foreach ($bracketSelectors as $selector) {
            $crawler->filter($selector)->each(function (Crawler $matchNode) use (&$matches, $event) {
                $matchData = $this->extractBracketMatchData($matchNode);
                
                if ($matchData) {
                    $match = $this->createOrUpdateMatchEnhanced($matchData, $event);
                    if ($match) {
                        $matches[] = $match;
                    }
                }
            });
        }
        
        return $matches;
    }

    private function extractDetailedMatchData(Crawler $node)
    {
        $data = [];
        
        // Extract team names
        $team1Selectors = [
            '.team1',
            '.team-left',
            '.match-team1',
            'td:nth-child(1) .team-template-text'
        ];
        
        $team2Selectors = [
            '.team2',
            '.team-right', 
            '.match-team2',
            'td:nth-child(3) .team-template-text'
        ];
        
        foreach ($team1Selectors as $selector) {
            $team1Node = $node->filter($selector)->first();
            if ($team1Node->count() > 0) {
                $data['team1_name'] = trim($team1Node->text());
                break;
            }
        }
        
        foreach ($team2Selectors as $selector) {
            $team2Node = $node->filter($selector)->first();
            if ($team2Node->count() > 0) {
                $data['team2_name'] = trim($team2Node->text());
                break;
            }
        }
        
        if (empty($data['team1_name']) || empty($data['team2_name'])) {
            return null;
        }
        
        // Extract scores
        $scoreSelectors = [
            '.score',
            '.bracket-score',
            'td:nth-child(2)'
        ];
        
        foreach ($scoreSelectors as $selector) {
            $scoreNode = $node->filter($selector)->first();
            if ($scoreNode->count() > 0) {
                $scoreText = $scoreNode->text();
                if (preg_match('/(\d+)\s*[-:]\s*(\d+)/', $scoreText, $matches)) {
                    $data['team1_score'] = intval($matches[1]);
                    $data['team2_score'] = intval($matches[2]);
                    break;
                }
            }
        }
        
        // Default scores if not found
        $data['team1_score'] = $data['team1_score'] ?? 0;
        $data['team2_score'] = $data['team2_score'] ?? 0;
        
        // Match date/time
        $data['match_date'] = $this->extractMatchDateTime($node);
        
        // Match format
        $formatNode = $node->filter('.format, .best-of')->first();
        if ($formatNode->count() > 0) {
            $formatText = $formatNode->text();
            if (preg_match('/bo(\d+)/i', $formatText, $matches)) {
                $data['best_of'] = intval($matches[1]);
            }
        }
        
        // Stream/VOD links
        $data['streams'] = $this->extractStreamLinks($node);
        $data['vods'] = $this->extractVodLinks($node);
        
        // Map results
        $data['maps'] = $this->extractDetailedMapResults($node);
        
        // Match ID/Number
        $matchIdNode = $node->filter('.match-id, .match-number')->first();
        if ($matchIdNode->count() > 0) {
            $data['match_number'] = $matchIdNode->text();
        }
        
        // Status
        if ($data['team1_score'] > 0 || $data['team2_score'] > 0 || count($data['maps']) > 0) {
            $data['status'] = 'completed';
        } else if ($data['match_date'] && Carbon::parse($data['match_date'])->isPast()) {
            $data['status'] = 'completed';
        } else {
            $data['status'] = 'upcoming';
        }
        
        return $data;
    }

    private function extractBracketMatchData(Crawler $node)
    {
        $data = $this->extractDetailedMatchData($node);
        
        if (!$data) {
            return null;
        }
        
        // Extract bracket-specific information
        
        // Round name
        $roundNode = $node->closest('.bracket-column-header, .bracket-header');
        if ($roundNode->count() > 0) {
            $data['round'] = trim($roundNode->text());
        } else {
            // Try to determine from class names
            $classes = $node->attr('class') ?? '';
            if (preg_match('/(final|semifinal|quarterfinal|round-of-\d+)/i', $classes, $matches)) {
                $data['round'] = ucfirst(str_replace('-', ' ', $matches[1]));
            }
        }
        
        // Upper/Lower bracket
        if (stripos($node->html(), 'lower') !== false || stripos($node->attr('class') ?? '', 'lower') !== false) {
            $data['bracket_position'] = 'lower';
        } else if (stripos($node->html(), 'upper') !== false || stripos($node->attr('class') ?? '', 'upper') !== false) {
            $data['bracket_position'] = 'upper';
        }
        
        $data['bracket_type'] = 'playoff';
        
        return $data;
    }

    private function extractDetailedMapResults(Crawler $node)
    {
        $maps = [];
        
        // Find map containers
        $mapSelectors = [
            '.map-result',
            '.game-details',
            '.match-game',
            '.mapholder',
            'div[class*="map"]'
        ];
        
        foreach ($mapSelectors as $selector) {
            $node->filter($selector)->each(function (Crawler $mapNode, $index) use (&$maps) {
                $mapData = [
                    'number' => $index + 1
                ];
                
                // Map name
                $mapNameNode = $mapNode->filter('.map-name, .game-map, .mapname')->first();
                if ($mapNameNode->count() > 0) {
                    $mapData['name'] = $this->normalizeMapName($mapNameNode->text());
                    $mapData['mode'] = $this->mapModes[$mapData['name']] ?? 'unknown';
                }
                
                // Scores
                $team1ScoreNode = $mapNode->filter('.team1-score, .score-left, .score1')->first();
                $team2ScoreNode = $mapNode->filter('.team2-score, .score-right, .score2')->first();
                
                if ($team1ScoreNode->count() > 0) {
                    $mapData['team1_score'] = intval($team1ScoreNode->text());
                }
                
                if ($team2ScoreNode->count() > 0) {
                    $mapData['team2_score'] = intval($team2ScoreNode->text());
                }
                
                // Duration
                $durationNode = $mapNode->filter('.duration, .map-duration')->first();
                if ($durationNode->count() > 0) {
                    $mapData['duration'] = $this->parseDuration($durationNode->text());
                }
                
                // Winner (if explicitly shown)
                if (isset($mapData['team1_score']) && isset($mapData['team2_score'])) {
                    if ($mapData['team1_score'] > $mapData['team2_score']) {
                        $mapData['winner'] = 'team1';
                    } else if ($mapData['team2_score'] > $mapData['team1_score']) {
                        $mapData['winner'] = 'team2';
                    }
                }
                
                if (!empty($mapData['name'])) {
                    $maps[] = $mapData;
                }
            });
        }
        
        return $maps;
    }

    private function createOrUpdateMatchEnhanced($matchData, Event $event)
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
        if ($matchData['status'] === 'completed') {
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
            'team1_score' => $matchData['team1_score'],
            'team2_score' => $matchData['team2_score'],
            'scheduled_at' => $matchData['match_date'] ?? now(),
            'round' => $matchData['round'] ?? 'Unknown',
            'bracket_position' => $matchData['bracket_position'] ?? null,
            'bracket_type' => $matchData['bracket_type'] ?? null,
            'match_number' => $matchData['match_number'] ?? null,
            'status' => $matchData['status'],
            'format' => 'BO' . ($matchData['best_of'] ?? 3),
            'winner_id' => $winnerId,
            'stream_urls' => $matchData['streams'] ?? [],
            'vod_urls' => $matchData['vods'] ?? []
        ];
        
        // Use a composite key to avoid duplicates
        $match = GameMatch::updateOrCreate(
            [
                'event_id' => $event->id,
                'team1_id' => $team1->id,
                'team2_id' => $team2->id,
                'round' => $matchData['round'] ?? 'Unknown'
            ],
            $matchAttributes
        );
        
        // Create map results
        foreach ($matchData['maps'] as $mapData) {
            $this->createOrUpdateMap($match, $mapData);
        }
        
        Log::info("Match created/updated: {$team1->name} vs {$team2->name} - Score: {$match->team1_score}-{$match->team2_score}");
        
        return $match;
    }

    private function createOrUpdateMap($match, $mapData)
    {
        $winnerId = null;
        
        if (isset($mapData['winner'])) {
            if ($mapData['winner'] === 'team1') {
                $winnerId = $match->team1_id;
            } else if ($mapData['winner'] === 'team2') {
                $winnerId = $match->team2_id;
            }
        }
        
        MatchMap::updateOrCreate(
            [
                'match_id' => $match->id,
                'map_number' => $mapData['number']
            ],
            [
                'map_name' => $mapData['name'],
                'game_mode' => $mapData['mode'] ?? 'unknown',
                'team1_score' => $mapData['team1_score'] ?? 0,
                'team2_score' => $mapData['team2_score'] ?? 0,
                'winner_id' => $winnerId,
                'duration_seconds' => $mapData['duration'] ?? null,
                'status' => 'completed'
            ]
        );
    }

    private function scrapeDetailedStandings(Crawler $crawler, Event $event, $prizeDistribution)
    {
        $standings = [];
        
        // Find standings/results table
        $standingSelectors = [
            '.placement-row',
            '.standings-row',
            '.prizepool-row',
            '.results-table tr',
            'table[class*="prize"] tr'
        ];
        
        foreach ($standingSelectors as $selector) {
            $crawler->filter($selector)->each(function (Crawler $node, $index) use (&$standings, $event, $prizeDistribution) {
                // Skip header rows
                if ($node->filter('th')->count() > 0) {
                    return;
                }
                
                $standingData = $this->extractStandingData($node, $index + 1);
                
                if ($standingData && !empty($standingData['team_name'])) {
                    // Add prize money from distribution if available
                    if (isset($prizeDistribution[$standingData['position']])) {
                        $standingData['prize_money'] = $prizeDistribution[$standingData['position']];
                    }
                    
                    $standing = $this->createOrUpdateStandingEnhanced($standingData, $event);
                    if ($standing) {
                        $standings[] = $standing;
                    }
                }
            });
        }
        
        return $standings;
    }

    private function extractStandingData(Crawler $node, $defaultPosition)
    {
        $data = [];
        
        // Position
        $positionNode = $node->filter('.placement, .position, td:first-child')->first();
        if ($positionNode->count() > 0) {
            $positionText = $positionNode->text();
            
            // Handle ranges like "5th-8th"
            if (preg_match('/(\d+)/', $positionText, $matches)) {
                $data['position'] = intval($matches[1]);
            }
            
            // Extract position range if exists
            if (preg_match('/(\d+)[^\d]+(\d+)/', $positionText, $matches)) {
                $data['position_start'] = intval($matches[1]);
                $data['position_end'] = intval($matches[2]);
            }
        } else {
            $data['position'] = $defaultPosition;
        }
        
        // Team name
        $teamNode = $node->filter('.teamname a, .team a, td:nth-child(2) a')->first();
        if ($teamNode->count() > 0) {
            $data['team_name'] = trim($teamNode->text());
        }
        
        // Prize money
        $prizeNode = $node->filter('.prizemoney, .prize, td:last-child')->first();
        if ($prizeNode->count() > 0) {
            $data['prize_money'] = $this->extractPrizeMoney($prizeNode->text());
        }
        
        // Points (for league/circuit standings)
        $pointsNode = $node->filter('.points, td:contains("points")')->first();
        if ($pointsNode->count() > 0) {
            if (preg_match('/(\d+)/', $pointsNode->text(), $matches)) {
                $data['points'] = intval($matches[1]);
            }
        }
        
        // Match score (W-L)
        $scoreNode = $node->filter('.score, .match-score')->first();
        if ($scoreNode->count() > 0) {
            if (preg_match('/(\d+)\s*-\s*(\d+)/', $scoreNode->text(), $matches)) {
                $data['matches_won'] = intval($matches[1]);
                $data['matches_lost'] = intval($matches[2]);
            }
        }
        
        return $data;
    }

    private function createOrUpdateStandingEnhanced($standingData, Event $event)
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
                'position_start' => $standingData['position_start'] ?? $standingData['position'],
                'position_end' => $standingData['position_end'] ?? $standingData['position'],
                'prize_money' => $standingData['prize_money'] ?? 0,
                'points' => $standingData['points'] ?? 0,
                'matches_won' => $standingData['matches_won'] ?? 0,
                'matches_lost' => $standingData['matches_lost'] ?? 0
            ]
        );
        
        // Update team earnings
        if ($standingData['prize_money'] > 0) {
            $team->increment('earnings', $standingData['prize_money']);
        }
        
        return $standing;
    }

    private function scrapePrizeDistribution(Crawler $crawler)
    {
        $distribution = [];
        
        // Look for prize pool breakdown
        $crawler->filter('.prizepooltable tr, table[class*="prize"] tr')->each(function (Crawler $node) use (&$distribution) {
            $positionNode = $node->filter('td:first-child')->first();
            $prizeNode = $node->filter('td:contains("$"), td:contains("€")')->first();
            
            if ($positionNode->count() > 0 && $prizeNode->count() > 0) {
                $positionText = $positionNode->text();
                $prize = $this->extractPrizeMoney($prizeNode->text());
                
                if (preg_match('/(\d+)/', $positionText, $matches)) {
                    $position = intval($matches[1]);
                    $distribution[$position] = $prize;
                    
                    // Handle position ranges
                    if (preg_match('/(\d+)[^\d]+(\d+)/', $positionText, $rangeMatches)) {
                        $start = intval($rangeMatches[1]);
                        $end = intval($rangeMatches[2]);
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $distribution[$i] = $prize;
                        }
                    }
                }
            }
        });
        
        return $distribution;
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
        
        // K-factor (higher for newer teams)
        $kFactor = 32;
        
        // Expected scores
        $expectedScore1 = 1 / (1 + pow(10, ($team2->rating - $team1->rating) / 400));
        $expectedScore2 = 1 - $expectedScore1;
        
        // Actual scores
        $actualScore1 = $match->winner_id == $team1->id ? 1 : 0;
        $actualScore2 = $match->winner_id == $team2->id ? 1 : 0;
        
        // Update ratings
        $newRating1 = $team1->rating + $kFactor * ($actualScore1 - $expectedScore1);
        $newRating2 = $team2->rating + $kFactor * ($actualScore2 - $expectedScore2);
        
        $team1->update(['rating' => round($newRating1)]);
        $team2->update(['rating' => round($newRating2)]);
    }

    private function updateTeamStatistics()
    {
        Log::info("Updating team statistics...");
        
        $teams = Team::all();
        
        foreach ($teams as $team) {
            $stats = [
                'total_matches' => 0,
                'matches_won' => 0,
                'matches_lost' => 0,
                'maps_played' => 0,
                'maps_won' => 0,
                'maps_lost' => 0
            ];
            
            // Get all matches for this team
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
            
            // Calculate win rate
            $winRate = $stats['total_matches'] > 0 
                ? ($stats['matches_won'] / $stats['total_matches']) * 100 
                : 0;
            
            // Update team
            $team->update([
                'win_rate' => round($winRate, 2),
                'wins' => $stats['matches_won'],
                'losses' => $stats['matches_lost']
            ]);
        }
        
        Log::info("Team statistics updated successfully");
    }

    private function updatePlayerStatistics()
    {
        Log::info("Updating player statistics...");
        
        // This would require match-specific player data which Liquipedia might not have
        // For now, we'll just update basic stats
        
        $players = Player::all();
        
        foreach ($players as $player) {
            // Count matches played by their team while they were on it
            $team = $player->team;
            
            if ($team) {
                $matchCount = GameMatch::where(function($query) use ($team) {
                    $query->where('team1_id', $team->id)
                        ->orWhere('team2_id', $team->id);
                })
                ->where('status', 'completed')
                ->where('scheduled_at', '>=', $player->joined_team_at ?? '2000-01-01')
                ->count();
                
                $player->update([
                    'total_matches' => $matchCount
                ]);
            }
        }
        
        Log::info("Player statistics updated successfully");
    }

    // Helper methods continue...

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

    private function normalizeMapName($mapName)
    {
        $mapName = strtolower(trim($mapName));
        $mapName = str_replace([' ', "'", "-"], ['_', '', '_'], $mapName);
        
        // Map name corrections
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

    private function normalizeHeroName($heroName)
    {
        $heroName = strtolower(trim($heroName));
        $heroName = str_replace([' ', "'", ".", "-"], ['_', '', '', '_'], $heroName);
        
        // Hero name corrections
        $heroCorrections = [
            'jeff' => 'jeff-the-land-shark',
            'cloak_dagger' => 'cloak-and-dagger',
            'mr_fantastic' => 'mister-fantastic',
            'the_punisher' => 'punisher',
            'emma_frost' => 'emma-frost'
        ];
        
        return $heroCorrections[$heroName] ?? str_replace('_', '-', $heroName);
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
        // Common abbreviations
        $abbreviations = [
            'Gaming' => '',
            'Esports' => '',
            'Team' => '',
            'Club' => ''
        ];
        
        $shortName = $teamName;
        
        foreach ($abbreviations as $word => $replacement) {
            $shortName = str_ireplace($word, $replacement, $shortName);
        }
        
        $shortName = trim($shortName);
        
        // If still too long, use initials
        if (strlen($shortName) > 5) {
            $words = explode(' ', $teamName);
            if (count($words) > 1) {
                $shortName = '';
                foreach ($words as $word) {
                    $shortName .= strtoupper(substr($word, 0, 1));
                }
            } else {
                $shortName = strtoupper(substr($teamName, 0, 3));
            }
        }
        
        return $shortName;
    }

    private function calculateInitialElo($teamData)
    {
        // Base ELO
        $elo = 1500;
        
        // Adjust based on region
        $regionBonus = [
            'North America' => 50,
            'Europe' => 50,
            'Asia' => 40,
            'Americas' => 30,
            'Oceania' => 20,
            'South America' => 10
        ];
        
        $region = $teamData['region'] ?? $this->determineRegionFromCountry($teamData['country'] ?? null);
        $elo += $regionBonus[$region] ?? 0;
        
        // Adjust based on seed/qualification
        if (isset($teamData['seed'])) {
            $elo += max(0, (9 - $teamData['seed']) * 10);
        }
        
        return $elo;
    }

    private function calculateInitialPlayerRating($playerData)
    {
        // Base rating
        $rating = 1000;
        
        // Adjust based on role
        $roleBonus = [
            'duelist' => 50,
            'vanguard' => 30,
            'strategist' => 40,
            'flex' => 20
        ];
        
        $rating += $roleBonus[$playerData['role']] ?? 0;
        
        // Adjust based on previous earnings
        if (isset($playerData['earnings']) && $playerData['earnings'] > 0) {
            $rating += min(200, sqrt($playerData['earnings']) / 10);
        }
        
        return round($rating);
    }

    private function extractCountryFromFlag($flagNode)
    {
        // Try to get country from title or alt attribute
        $country = $flagNode->attr('title') ?: $flagNode->attr('alt');
        
        if ($country) {
            // Clean up country name
            $country = str_replace(['Flag of ', 'flag'], '', $country);
            $country = trim($country);
            
            if (!empty($country)) {
                return $country;
            }
        }
        
        // Try to extract from image filename
        if ($flagNode->nodeName() === 'img' && $flagNode->attr('src')) {
            $src = $flagNode->attr('src');
            
            // Look for country codes in filename
            if (preg_match('/([A-Z]{2,3})(?:\.png|\.jpg|\.svg)/i', basename($src), $matches)) {
                return $this->countryCodeToName($matches[1]);
            }
        }
        
        return null;
    }

    private function extractCountryCode($flagNode)
    {
        if ($flagNode->nodeName() === 'img' && $flagNode->attr('src')) {
            $src = $flagNode->attr('src');
            
            if (preg_match('/([A-Z]{2,3})(?:\.png|\.jpg|\.svg)/i', basename($src), $matches)) {
                return strtoupper($matches[1]);
            }
        }
        
        return null;
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
            'BE' => 'Belgium',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'NO' => 'Norway',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'SK' => 'Slovakia',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'GR' => 'Greece',
            'TR' => 'Turkey',
            'RU' => 'Russia',
            'UA' => 'Ukraine',
            'BY' => 'Belarus',
            'KZ' => 'Kazakhstan',
            'IL' => 'Israel',
            'SA' => 'Saudi Arabia',
            'AE' => 'United Arab Emirates',
            'KR' => 'South Korea',
            'JP' => 'Japan',
            'CN' => 'China',
            'TW' => 'Taiwan',
            'HK' => 'Hong Kong',
            'MO' => 'Macau',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'PH' => 'Philippines',
            'ID' => 'Indonesia',
            'VN' => 'Vietnam',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'PE' => 'Peru',
            'CO' => 'Colombia',
            'VE' => 'Venezuela',
            'MX' => 'Mexico',
            'CR' => 'Costa Rica',
            'PA' => 'Panama',
            'EC' => 'Ecuador',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'BO' => 'Bolivia',
            'ZA' => 'South Africa',
            'EG' => 'Egypt',
            'MA' => 'Morocco',
            'TN' => 'Tunisia',
            'DZ' => 'Algeria',
            'NG' => 'Nigeria',
            'KE' => 'Kenya'
        ];
        
        return $countries[strtoupper($code)] ?? $code;
    }

    private function getCountryFlag($country)
    {
        if (!$country) return null;
        
        // Map country names to flag emojis
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
            'Slovakia' => '🇸🇰',
            'Hungary' => '🇭🇺',
            'Romania' => '🇷🇴',
            'Bulgaria' => '🇧🇬',
            'Greece' => '🇬🇷',
            'Turkey' => '🇹🇷',
            'Russia' => '🇷🇺',
            'Ukraine' => '🇺🇦',
            'Belarus' => '🇧🇾',
            'Kazakhstan' => '🇰🇿',
            'Israel' => '🇮🇱',
            'Saudi Arabia' => '🇸🇦',
            'United Arab Emirates' => '🇦🇪',
            'South Korea' => '🇰🇷',
            'Japan' => '🇯🇵',
            'China' => '🇨🇳',
            'Taiwan' => '🇹🇼',
            'Hong Kong' => '🇭🇰',
            'Macau' => '🇲🇴',
            'Singapore' => '🇸🇬',
            'Malaysia' => '🇲🇾',
            'Thailand' => '🇹🇭',
            'Philippines' => '🇵🇭',
            'Indonesia' => '🇮🇩',
            'Vietnam' => '🇻🇳',
            'India' => '🇮🇳',
            'Pakistan' => '🇵🇰',
            'Bangladesh' => '🇧🇩',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Chile' => '🇨🇱',
            'Peru' => '🇵🇪',
            'Colombia' => '🇨🇴',
            'Venezuela' => '🇻🇪',
            'Mexico' => '🇲🇽',
            'Costa Rica' => '🇨🇷',
            'Panama' => '🇵🇦',
            'Ecuador' => '🇪🇨',
            'Uruguay' => '🇺🇾',
            'Paraguay' => '🇵🇾',
            'Bolivia' => '🇧🇴',
            'South Africa' => '🇿🇦',
            'Egypt' => '🇪🇬',
            'Morocco' => '🇲🇦',
            'Tunisia' => '🇹🇳',
            'Algeria' => '🇩🇿',
            'Nigeria' => '🇳🇬',
            'Kenya' => '🇰🇪'
        ];
        
        return $flags[$country] ?? null;
    }

    private function determineRegionFromCountry($country)
    {
        if (!$country) return 'International';
        
        $regions = [
            'North America' => ['United States', 'Canada', 'Mexico'],
            'Europe' => [
                'United Kingdom', 'France', 'Germany', 'Spain', 'Italy', 'Netherlands',
                'Belgium', 'Sweden', 'Denmark', 'Norway', 'Finland', 'Poland',
                'Czech Republic', 'Slovakia', 'Hungary', 'Romania', 'Bulgaria',
                'Greece', 'Turkey', 'Russia', 'Ukraine', 'Belarus', 'Kazakhstan'
            ],
            'Asia' => [
                'South Korea', 'Japan', 'China', 'Taiwan', 'Hong Kong', 'Macau',
                'Singapore', 'Malaysia', 'Thailand', 'Philippines', 'Indonesia',
                'Vietnam', 'India', 'Pakistan', 'Bangladesh'
            ],
            'Oceania' => ['Australia', 'New Zealand'],
            'Middle East' => ['Israel', 'Saudi Arabia', 'United Arab Emirates'],
            'South America' => [
                'Brazil', 'Argentina', 'Chile', 'Peru', 'Colombia', 'Venezuela',
                'Ecuador', 'Uruguay', 'Paraguay', 'Bolivia'
            ],
            'Central America' => ['Costa Rica', 'Panama'],
            'Africa' => ['South Africa', 'Egypt', 'Morocco', 'Tunisia', 'Algeria', 'Nigeria', 'Kenya']
        ];
        
        foreach ($regions as $region => $countries) {
            if (in_array($country, $countries)) {
                return $region;
            }
        }
        
        return 'International';
    }

    private function extractStreamLinks(Crawler $node)
    {
        $streams = [];
        
        // Look for stream links
        $node->filter('a[href*="twitch.tv"], a[href*="youtube.com/watch"], a[href*="youtube.com/live"]')->each(function ($link) use (&$streams) {
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

    private function extractVodLinks(Crawler $node)
    {
        $vods = [];
        
        // Look for VOD links
        $node->filter('a[href*="youtube.com/watch"], a[href*="twitch.tv/videos"]')->each(function ($link) use (&$vods) {
            $url = $link->attr('href');
            $text = strtolower($link->text());
            
            // Check if it's likely a VOD link
            if (strpos($text, 'vod') !== false || strpos($text, 'replay') !== false || strpos($text, 'watch') !== false) {
                $platform = '';
                
                if (strpos($url, 'youtube.com') !== false) {
                    $platform = 'youtube';
                } else if (strpos($url, 'twitch.tv') !== false) {
                    $platform = 'twitch';
                }
                
                if ($platform) {
                    $vods[] = [
                        'platform' => $platform,
                        'url' => $url,
                        'title' => $link->text()
                    ];
                }
            }
        });
        
        return $vods;
    }

    private function extractMatchDateTime(Crawler $node)
    {
        // Try multiple selectors for date/time
        $dateSelectors = [
            '.match-date',
            '.date',
            '.timer-object',
            '.datetime',
            'abbr[data-timestamp]',
            'span[data-timestamp]'
        ];
        
        foreach ($dateSelectors as $selector) {
            $dateNode = $node->filter($selector)->first();
            if ($dateNode->count() > 0) {
                // Check for timestamp attribute
                $timestamp = $dateNode->attr('data-timestamp');
                if ($timestamp) {
                    try {
                        return Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        // Continue to text parsing
                    }
                }
                
                // Parse text
                $dateText = $dateNode->text();
                if (!empty($dateText)) {
                    try {
                        return Carbon::parse($dateText)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        Log::warning("Could not parse date: " . $dateText);
                    }
                }
            }
        }
        
        return null;
    }

    private function extractGroupName(Crawler $node)
    {
        // Check for group header
        $headerNode = $node->prevAll()->filter('.group-header, h3, h4')->first();
        if ($headerNode->count() > 0) {
            $headerText = $headerNode->text();
            if (preg_match('/group\s+([a-d])/i', $headerText, $matches)) {
                return 'Group ' . strtoupper($matches[1]);
            }
        }
        
        // Check node classes
        $classes = $node->attr('class') ?? '';
        if (preg_match('/group-([a-d])/i', $classes, $matches)) {
            return 'Group ' . strtoupper($matches[1]);
        }
        
        return null;
    }

    private function extractSponsors(Crawler $crawler)
    {
        $sponsors = [];
        
        // Look for sponsor section
        $crawler->filter('.sponsors img, .sponsor-logo img')->each(function ($img) use (&$sponsors) {
            $sponsors[] = [
                'name' => $img->attr('alt') ?: 'Sponsor',
                'logo' => $this->extractImageUrl($img->attr('src'))
            ];
        });
        
        return $sponsors;
    }

    private function extractParticipantCount(Crawler $crawler)
    {
        // Look for participant count in infobox
        $participantNode = $crawler->filter('.infobox-cell-2:contains("Number of teams") + .infobox-cell-2')->first();
        
        if ($participantNode->count() > 0) {
            if (preg_match('/(\d+)/', $participantNode->text(), $matches)) {
                return intval($matches[1]);
            }
        }
        
        return null;
    }

    private function extractTournamentFormat(Crawler $crawler)
    {
        // Look for format in infobox
        $formatNode = $crawler->filter('.infobox-cell-2:contains("Format") + .infobox-cell-2')->first();
        
        if ($formatNode->count() > 0) {
            return trim($formatNode->text());
        }
        
        // Try to determine from page content
        if ($crawler->filter('.bracket').count() > 0 && $crawler->filter('.grouptable')->count() > 0) {
            return 'Group Stage + Playoffs';
        } else if ($crawler->filter('.bracket')->count() > 0) {
            return 'Single Elimination';
        } else if ($crawler->filter('.crosstable')->count() > 0) {
            return 'Round Robin';
        }
        
        return 'Tournament';
    }

    private function extractDetailedDescription(Crawler $crawler)
    {
        // Get first paragraph of article
        $firstParagraph = $crawler->filter('#mw-content-text > .mw-parser-output > p')->first();
        
        if ($firstParagraph->count() > 0) {
            $text = $firstParagraph->text();
            
            // Clean up the text
            $text = preg_replace('/\[[^\]]+\]/', '', $text); // Remove [edit] links
            $text = trim($text);
            
            if (strlen($text) > 50) { // Make sure it's substantial
                return $text;
            }
        }
        
        // Fallback to infobox description
        $descNode = $crawler->filter('.infobox-description')->first();
        if ($descNode->count() > 0) {
            return trim($descNode->text());
        }
        
        return null;
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
        if (empty($dateText) || $dateText === '-' || $dateText === 'Present') {
            return null;
        }
        
        try {
            return Carbon::parse($dateText)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function scrapeHighlights(Crawler $crawler)
    {
        $highlights = [];
        
        // Look for notable matches or highlights section
        $crawler->filter('.highlights li, .notable-matches li')->each(function ($node) use (&$highlights) {
            $highlights[] = trim($node->text());
        });
        
        return $highlights;
    }

    private function updateEventMetadata(Event $event, Crawler $crawler)
    {
        // Count total matches
        $totalMatches = GameMatch::where('event_id', $event->id)->count();
        
        // Count participating teams
        $participatingTeams = DB::table('event_teams')
            ->where('event_id', $event->id)
            ->count();
        
        // Get winner
        $winner = EventStanding::where('event_id', $event->id)
            ->where('position', 1)
            ->with('team')
            ->first();
        
        $metadata = [
            'total_matches' => $totalMatches,
            'participating_teams' => $participatingTeams,
            'winner' => $winner ? $winner->team->name : null,
            'last_updated' => now()
        ];
        
        $event->update([
            'metadata' => json_encode($metadata)
        ]);
    }

    private function scrapeAdditionalTeamsFromMatches(Crawler $crawler, Event $event, &$teams)
    {
        // Look for teams mentioned in matches that weren't in participant list
        $crawler->filter('.match-team, .team-template-text')->each(function ($node) use ($event, &$teams) {
            $teamName = trim($node->text());
            
            if (!empty($teamName) && !isset($teams[$teamName])) {
                // Try to get basic team info
                $teamData = [
                    'name' => $teamName,
                    'region' => $event->region
                ];
                
                $team = $this->createOrUpdateTeamEnhanced($teamData);
                
                $event->teams()->syncWithoutDetaching([$team->id => [
                    'registered_at' => now()
                ]]);
                
                $teams[$teamName] = $teamData;
            }
        });
    }

    private function scrapePlayersFromTeamPage(Team $team)
    {
        // This would require fetching the team's Liquipedia page
        // For now, return empty array
        return [];
    }

    private function extractRecentResults(Crawler $crawler)
    {
        $results = [];
        
        // Look for recent results section
        $crawler->filter('.recent-matches tr, .achievements li')->each(function ($node) use (&$results) {
            $text = trim($node->text());
            if (!empty($text) && strlen($text) > 10) {
                $results[] = $text;
            }
        });
        
        return array_slice($results, 0, 5); // Keep only 5 most recent
    }
}