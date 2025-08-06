<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;
use App\Models\Team;
use App\Models\Player;
use App\Models\GameMatch;
use App\Models\EventStanding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

echo "Starting manual Liquipedia import...\n\n";

$tournaments = [
    [
        'url' => 'https://liquipedia.net/marvelrivals/Marvel_Rivals_Invitational/2025/North_America',
        'name' => 'Marvel Rivals Invitational 2025: North America',
        'region' => 'North America',
        'tier' => 'A',
        'prize_pool' => 100000,
        'start_date' => '2025-03-14',
        'end_date' => '2025-03-23',
        'type' => 'invitational',
        'expected_teams' => 8
    ],
    [
        'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/EMEA',
        'name' => 'Marvel Rivals Ignite 2025 Stage 1 - EMEA',
        'region' => 'Europe',
        'tier' => 'A',
        'prize_pool' => 250000,
        'start_date' => '2025-06-12',
        'end_date' => '2025-06-29',
        'type' => 'tournament',
        'expected_teams' => 16
    ],
    [
        'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/Asia',
        'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Asia',
        'region' => 'Asia', 
        'tier' => 'A',
        'prize_pool' => 100000,
        'start_date' => '2025-06-12',
        'end_date' => '2025-06-29',
        'type' => 'tournament',
        'expected_teams' => 12
    ],
    [
        'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/Americas',
        'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Americas',
        'region' => 'Americas',
        'tier' => 'A',
        'prize_pool' => 250000,
        'start_date' => '2025-06-12',
        'end_date' => '2025-06-29',
        'type' => 'tournament',
        'expected_teams' => 16
    ],
    [
        'url' => 'https://liquipedia.net/marvelrivals/MR_Ignite/2025/Stage_1/Oceania',
        'name' => 'Marvel Rivals Ignite 2025 Stage 1 - Oceania',
        'region' => 'Oceania',
        'tier' => 'A',
        'prize_pool' => 75000,
        'start_date' => '2025-06-12',
        'end_date' => '2025-06-22',
        'type' => 'tournament',
        'expected_teams' => 8
    ]
];

$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
];

DB::beginTransaction();

try {
    $totalTeams = 0;
    $totalPlayers = 0;
    $totalMatches = 0;

    foreach ($tournaments as $tournamentData) {
        echo "Processing: {$tournamentData['name']}\n";
        
        // Create event
        $event = Event::updateOrCreate(
            ['name' => $tournamentData['name']],
            [
                'description' => "Official Marvel Rivals {$tournamentData['type']} tournament",
                'location' => 'Online',
                'region' => $tournamentData['region'],
                'tier' => $tournamentData['tier'],
                'start_date' => $tournamentData['start_date'],
                'end_date' => $tournamentData['end_date'],
                'prize_pool' => $tournamentData['prize_pool'],
                'type' => $tournamentData['type'],
                'status' => 'completed',
                'game' => 'marvel_rivals',
                'organizer' => 'NetEase',
                'participants' => $tournamentData['expected_teams']
            ]
        );
        
        echo "  ✓ Event created/updated\n";
        
        // Fetch page
        $response = Http::withHeaders($headers)->get($tournamentData['url']);
        
        if ($response->successful()) {
            $html = $response->body();
            $crawler = new Crawler($html);
            
            // Extract teams
            $teamsFound = 0;
            $processedTeams = [];
            
            // Find teams from participant table
            $crawler->filter('.participanttable tr, .wikitable tr')->each(function (Crawler $row) use (&$teamsFound, &$processedTeams, $event, &$totalPlayers) {
                $teamNode = $row->filter('.team-template-text, .teamname a')->first();
                if ($teamNode->count() > 0) {
                    $teamName = trim($teamNode->text());
                    
                    if (!empty($teamName) && $teamName !== 'TBD' && !in_array($teamName, $processedTeams)) {
                        // Create team
                        $team = Team::firstOrCreate(
                            ['name' => $teamName],
                            [
                                'short_name' => substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $teamName)), 0, 5),
                                'region' => $event->region,
                                'status' => 'active',
                                'game' => 'marvel_rivals',
                                'platform' => 'PC',
                                'rating' => 1500,
                                'earnings' => 0
                            ]
                        );
                        
                        // Attach to event
                        $event->teams()->syncWithoutDetaching([$team->id => ['registered_at' => now()]]);
                        
                        $teamsFound++;
                        $processedTeams[] = $teamName;
                        
                        // Extract players if available
                        $row->filter('.player a, .roster a')->each(function (Crawler $playerNode) use ($team, &$totalPlayers) {
                            $playerName = trim($playerNode->text());
                            if (!empty($playerName)) {
                                Player::firstOrCreate(
                                    ['ign' => $playerName],
                                    [
                                        'username' => $playerName,
                                        'team_id' => $team->id,
                                        'role' => 'flex',
                                        'status' => 'active',
                                        'rating' => 1000,
                                        'earnings' => 0
                                    ]
                                );
                                $totalPlayers++;
                            }
                        });
                    }
                }
            });
            
            // Also check standings for teams
            $crawler->filter('.prizepooltable tr')->each(function (Crawler $row) use (&$teamsFound, &$processedTeams, $event) {
                $teamNode = $row->filter('.team-template-text')->first();
                $positionNode = $row->filter('td:first-child')->first();
                $prizeNode = $row->filter('td:last-child')->first();
                
                if ($teamNode->count() > 0 && $positionNode->count() > 0) {
                    $teamName = trim($teamNode->text());
                    
                    if (!empty($teamName) && $teamName !== 'TBD') {
                        // Create team if not exists
                        if (!in_array($teamName, $processedTeams)) {
                            $team = Team::firstOrCreate(
                                ['name' => $teamName],
                                [
                                    'short_name' => substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $teamName)), 0, 5),
                                    'region' => $event->region,
                                    'status' => 'active',
                                    'game' => 'marvel_rivals',
                                    'platform' => 'PC',
                                    'rating' => 1500,
                                    'earnings' => 0
                                ]
                            );
                            
                            $event->teams()->syncWithoutDetaching([$team->id => ['registered_at' => now()]]);
                            $teamsFound++;
                            $processedTeams[] = $teamName;
                        } else {
                            $team = Team::where('name', $teamName)->first();
                        }
                        
                        // Create standing
                        if ($team && $positionNode->count() > 0) {
                            $positionText = trim($positionNode->text());
                            $position = 1;
                            
                            if (preg_match('/(\d+)/', $positionText, $matches)) {
                                $position = intval($matches[1]);
                            }
                            
                            $prizeMoney = 0;
                            if ($prizeNode->count() > 0) {
                                $prizeText = $prizeNode->text();
                                if (preg_match('/[\$€]?([\d,]+)/', $prizeText, $matches)) {
                                    $prizeMoney = intval(str_replace(',', '', $matches[1]));
                                }
                            }
                            
                            EventStanding::updateOrCreate(
                                [
                                    'event_id' => $event->id,
                                    'team_id' => $team->id
                                ],
                                [
                                    'position' => $position,
                                    'position_start' => $position,
                                    'position_end' => $position,
                                    'prize_money' => $prizeMoney
                                ]
                            );
                            
                            // Update team earnings
                            if ($prizeMoney > 0) {
                                $team->increment('earnings', $prizeMoney);
                            }
                        }
                    }
                }
            });
            
            $totalTeams += $teamsFound;
            echo "  ✓ Teams found: $teamsFound\n";
            
            // Extract matches
            $matchesFound = 0;
            $crawler->filter('.match-row, .bracket-match, .grouptable .match')->each(function (Crawler $matchNode) use ($event, &$matchesFound) {
                $team1Node = $matchNode->filter('.team1 .team-template-text, .team-left .team-template-text')->first();
                $team2Node = $matchNode->filter('.team2 .team-template-text, .team-right .team-template-text')->first();
                $scoreNode = $matchNode->filter('.score, .bracket-score')->first();
                
                if ($team1Node->count() > 0 && $team2Node->count() > 0) {
                    $team1Name = trim($team1Node->text());
                    $team2Name = trim($team2Node->text());
                    
                    if (!empty($team1Name) && !empty($team2Name) && $team1Name !== 'TBD' && $team2Name !== 'TBD') {
                        $team1 = Team::where('name', $team1Name)->first();
                        $team2 = Team::where('name', $team2Name)->first();
                        
                        if ($team1 && $team2 && $scoreNode->count() > 0) {
                            $scoreText = $scoreNode->text();
                            if (preg_match('/(\d+)\s*[-:]\s*(\d+)/', $scoreText, $matches)) {
                                $team1Score = intval($matches[1]);
                                $team2Score = intval($matches[2]);
                                
                                $winnerId = null;
                                if ($team1Score > $team2Score) {
                                    $winnerId = $team1->id;
                                } else if ($team2Score > $team1Score) {
                                    $winnerId = $team2->id;
                                }
                                
                                GameMatch::create([
                                    'event_id' => $event->id,
                                    'team1_id' => $team1->id,
                                    'team2_id' => $team2->id,
                                    'team1_score' => $team1Score,
                                    'team2_score' => $team2Score,
                                    'winner_id' => $winnerId,
                                    'scheduled_at' => now(),
                                    'status' => 'completed',
                                    'round' => 'Unknown',
                                    'format' => 'BO3'
                                ]);
                                
                                $matchesFound++;
                            }
                        }
                    }
                }
            });
            
            $totalMatches += $matchesFound;
            echo "  ✓ Matches found: $matchesFound\n";
            
        } else {
            echo "  ✗ Failed to fetch page\n";
        }
        
        echo "\n";
        sleep(2); // Rate limiting
    }
    
    DB::commit();
    
    echo "\n=== IMPORT SUMMARY ===\n";
    echo "Total Tournaments: " . count($tournaments) . "\n";
    echo "Total Teams: $totalTeams\n";
    echo "Total Players: $totalPlayers\n";
    echo "Total Matches: $totalMatches\n";
    echo "Total Prize Pool: $" . number_format(array_sum(array_column($tournaments, 'prize_pool'))) . "\n";
    echo "\n✓ Import completed successfully!\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}