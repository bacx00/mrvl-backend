<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Event;
use App\Models\Team;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Services\TournamentIntegrationService;
use App\Services\BracketService;
use App\Services\RankingService;
use Mockery;

class TournamentIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TournamentIntegrationService $service;
    protected Event $event;
    protected $teams;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $bracketService = Mockery::mock(BracketService::class);
        $progressionService = Mockery::mock(\App\Services\BracketProgressionService::class);
        $rankingService = Mockery::mock(RankingService::class);
        
        $this->service = new TournamentIntegrationService(
            $bracketService,
            $progressionService,
            $rankingService
        );
        
        // Create test data
        $this->event = Event::factory()->create();
        $this->teams = Team::factory(8)->create();
        
        foreach ($this->teams as $index => $team) {
            $this->event->teams()->attach($team->id, ['seed' => $index + 1]);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_correct_liquipedia_notation_format()
    {
        // Create test bracket structure
        $stage = BracketStage::factory()->create([
            'event_id' => $this->event->id,
            'type' => 'upper_bracket',
            'name' => 'Upper Bracket'
        ]);
        
        $matches = collect();
        for ($round = 1; $round <= 3; $round++) {
            for ($match = 1; $match <= pow(2, 4-$round); $match++) {
                $bracketMatch = BracketMatch::factory()->create([
                    'event_id' => $this->event->id,
                    'bracket_stage_id' => $stage->id,
                    'round_number' => $round,
                    'match_number' => $match,
                    'round_name' => "Upper Bracket Round {$round}",
                    'team1_id' => $this->teams[0]->id,
                    'team2_id' => $this->teams[1]->id
                ]);
                $matches->push($bracketMatch);
            }
        }
        
        $brackets = ['Upper Bracket' => ['matches' => $matches]];
        $notation = $this->service->generateLiquipediaNotation($brackets);
        
        $this->assertIsArray($notation);
        $this->assertArrayHasKey('Upper Bracket', $notation);
        
        // Check R#M# format
        foreach ($notation['Upper Bracket'] as $roundName => $roundMatches) {
            foreach ($roundMatches as $match) {
                $this->assertMatchesPattern('/^R\d+M\d+$/', $match['liquipedia_id']);
                $this->assertArrayHasKey('teams', $match);
                $this->assertArrayHasKey('score', $match);
                $this->assertArrayHasKey('status', $match);
                $this->assertArrayHasKey('advancement', $match);
            }
        }
    }

    /** @test */
    public function it_calculates_swiss_standings_correctly()
    {
        $stage = BracketStage::factory()->create([
            'event_id' => $this->event->id,
            'type' => 'swiss',
            'name' => 'Swiss Stage'
        ]);
        
        // Create seedings
        foreach ($this->teams as $index => $team) {
            $stage->seedings()->create([
                'event_id' => $this->event->id,
                'team_id' => $team->id,
                'seed' => $index + 1
            ]);
        }
        
        // Create completed matches with different results
        $matches = [
            // Round 1
            ['team1_id' => $this->teams[0]->id, 'team2_id' => $this->teams[1]->id, 'winner_id' => $this->teams[0]->id],
            ['team1_id' => $this->teams[2]->id, 'team2_id' => $this->teams[3]->id, 'winner_id' => $this->teams[2]->id],
            ['team1_id' => $this->teams[4]->id, 'team2_id' => $this->teams[5]->id, 'winner_id' => $this->teams[4]->id],
            ['team1_id' => $this->teams[6]->id, 'team2_id' => $this->teams[7]->id, 'winner_id' => $this->teams[6]->id],
        ];
        
        foreach ($matches as $matchData) {
            BracketMatch::factory()->create([
                'event_id' => $this->event->id,
                'bracket_stage_id' => $stage->id,
                'team1_id' => $matchData['team1_id'],
                'team2_id' => $matchData['team2_id'],
                'winner_id' => $matchData['winner_id'],
                'team1_score' => $matchData['winner_id'] === $matchData['team1_id'] ? 2 : 1,
                'team2_score' => $matchData['winner_id'] === $matchData['team1_id'] ? 1 : 2,
                'status' => 'completed'
            ]);
        }
        
        $standings = $this->service->calculateSwissStandings($stage);
        
        $this->assertCount(8, $standings);
        
        // Check that winners are ranked higher
        $winners = [$this->teams[0]->id, $this->teams[2]->id, $this->teams[4]->id, $this->teams[6]->id];
        $topFour = $standings->take(4)->pluck('team_id')->toArray();
        
        $this->assertEquals(4, count(array_intersect($winners, $topFour)));
        
        // Check scoring system
        foreach ($standings as $standing) {
            if (in_array($standing['team_id'], $winners)) {
                $this->assertEquals(1, $standing['wins']);
                $this->assertEquals(0, $standing['losses']);
                $this->assertEquals(3, $standing['swiss_score']);
            } else {
                $this->assertEquals(0, $standing['wins']);
                $this->assertEquals(1, $standing['losses']);
                $this->assertEquals(0, $standing['swiss_score']);
            }
        }
    }

    /** @test */
    public function it_generates_optimal_swiss_pairings()
    {
        $stage = BracketStage::factory()->create([
            'event_id' => $this->event->id,
            'type' => 'swiss'
        ]);
        
        // Create seedings
        foreach ($this->teams as $index => $team) {
            $stage->seedings()->create([
                'event_id' => $this->event->id,
                'team_id' => $team->id,
                'seed' => $index + 1
            ]);
        }
        
        // Create standings with varied records
        $standings = collect([
            ['team_id' => $this->teams[0]->id, 'wins' => 2, 'losses' => 0, 'opponents' => [$this->teams[2]->id, $this->teams[4]->id]],
            ['team_id' => $this->teams[1]->id, 'wins' => 2, 'losses' => 0, 'opponents' => [$this->teams[3]->id, $this->teams[5]->id]],
            ['team_id' => $this->teams[2]->id, 'wins' => 1, 'losses' => 1, 'opponents' => [$this->teams[0]->id, $this->teams[6]->id]],
            ['team_id' => $this->teams[3]->id, 'wins' => 1, 'losses' => 1, 'opponents' => [$this->teams[1]->id, $this->teams[7]->id]],
            ['team_id' => $this->teams[4]->id, 'wins' => 1, 'losses' => 1, 'opponents' => [$this->teams[0]->id, $this->teams[6]->id]],
            ['team_id' => $this->teams[5]->id, 'wins' => 1, 'losses' => 1, 'opponents' => [$this->teams[1]->id, $this->teams[7]->id]],
            ['team_id' => $this->teams[6]->id, 'wins' => 0, 'losses' => 2, 'opponents' => [$this->teams[2]->id, $this->teams[4]->id]],
            ['team_id' => $this->teams[7]->id, 'wins' => 0, 'losses' => 2, 'opponents' => [$this->teams[3]->id, $this->teams[5]->id]]
        ]);
        
        $pairings = $this->service->generateSwissPairings($standings, 3);
        
        $this->assertCount(4, $pairings); // 8 teams = 4 matches
        
        // Verify teams with similar records are paired together
        $pairedTeams = [];
        foreach ($pairings as $pairing) {
            $pairedTeams[] = $pairing['team1_id'];
            $pairedTeams[] = $pairing['team2_id'];
        }
        
        $this->assertCount(8, $pairedTeams);
        $this->assertEquals(8, count(array_unique($pairedTeams)));
        
        // Check that teams with similar records are paired
        foreach ($pairings as $pairing) {
            $team1Record = $standings->where('team_id', $pairing['team1_id'])->first();
            $team2Record = $standings->where('team_id', $pairing['team2_id'])->first();
            
            // Teams should have similar records (within 1 win difference)
            $this->assertLessThanOrEqual(1, abs($team1Record['wins'] - $team2Record['wins']));
        }
    }

    /** @test */
    public function it_handles_match_completion_processing()
    {
        $stage = BracketStage::factory()->create([
            'event_id' => $this->event->id,
            'type' => 'upper_bracket'
        ]);
        
        $match = BracketMatch::factory()->create([
            'event_id' => $this->event->id,
            'bracket_stage_id' => $stage->id,
            'team1_id' => $this->teams[0]->id,
            'team2_id' => $this->teams[1]->id,
            'status' => 'completed',
            'winner_id' => $this->teams[0]->id,
            'team1_score' => 2,
            'team2_score' => 1
        ]);
        
        $matchData = [
            'team1_score' => 2,
            'team2_score' => 1,
            'games' => [
                ['game_number' => 1, 'winner_id' => $this->teams[0]->id],
                ['game_number' => 2, 'winner_id' => $this->teams[0]->id],
                ['game_number' => 3, 'winner_id' => $this->teams[1]->id]
            ]
        ];
        
        $result = $this->service->processMatchCompletion($match, $matchData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('match_updated', $result);
        $this->assertArrayHasKey('advancement', $result);
        $this->assertArrayHasKey('tournament_status', $result);
        $this->assertTrue($result['match_updated']);
    }

    /** @test */
    public function it_calculates_buchholz_scores_correctly()
    {
        $stage = BracketStage::factory()->create([
            'event_id' => $this->event->id,
            'type' => 'swiss'
        ]);
        
        // Create matches where team performance affects Buchholz calculation
        $opponents = [$this->teams[1]->id, $this->teams[2]->id, $this->teams[3]->id];
        $opponentWins = [3, 2, 1]; // Different win counts for opponents
        
        $expectedBuchholz = (3 * 3 + 2 * 3 + 1 * 3) / 3; // Average opponent score
        
        $actualBuchholz = $this->service->calculateBuchholzScore($opponents, $stage, $opponentWins);
        
        $this->assertEquals($expectedBuchholz, $actualBuchholz);
    }

    /** @test */
    public function it_validates_tournament_configuration()
    {
        $invalidConfig = [
            'format' => 'invalid_format',
            'phases' => []
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateTournamentConfig($invalidConfig);
        
        $validConfig = [
            'format' => 'double_elimination',
            'phases' => [
                [
                    'name' => 'Main Event',
                    'type' => 'double_elimination'
                ]
            ],
            'seeding_method' => 'seed'
        ];
        
        // Should not throw exception
        $this->service->validateTournamentConfig($validConfig);
        $this->assertTrue(true); // Assertion to indicate test passed
    }

    /** @test */
    public function it_handles_edge_cases_in_swiss_pairings()
    {
        $stage = BracketStage::factory()->create(['event_id' => $this->event->id, 'type' => 'swiss']);
        
        // Edge case: Odd number of teams (should handle bye)
        $oddTeams = Team::factory(7)->create();
        foreach ($oddTeams as $index => $team) {
            $stage->seedings()->create([
                'event_id' => $this->event->id,
                'team_id' => $team->id,
                'seed' => $index + 1
            ]);
        }
        
        $standings = collect($oddTeams)->map(function ($team, $index) {
            return [
                'team_id' => $team->id,
                'wins' => 0,
                'losses' => 0,
                'opponents' => []
            ];
        });
        
        $pairings = $this->service->generateSwissPairings($standings, 1);
        
        // Should create 3 matches with 6 teams, leaving 1 team with bye
        $this->assertCount(3, $pairings);
        
        $pairedTeams = [];
        foreach ($pairings as $pairing) {
            $pairedTeams[] = $pairing['team1_id'];
            $pairedTeams[] = $pairing['team2_id'];
        }
        
        $this->assertCount(6, $pairedTeams);
        $this->assertCount(1, array_diff($oddTeams->pluck('id')->toArray(), $pairedTeams));
    }

    /** @test */
    public function it_maintains_data_integrity_across_operations()
    {
        $stage = BracketStage::factory()->create([
            'event_id' => $this->event->id,
            'type' => 'swiss'
        ]);
        
        // Create seedings
        foreach ($this->teams as $index => $team) {
            $stage->seedings()->create([
                'event_id' => $this->event->id,
                'team_id' => $team->id,
                'seed' => $index + 1
            ]);
        }
        
        // Generate multiple rounds and verify integrity
        for ($round = 1; $round <= 3; $round++) {
            $matches = $this->service->generateSwissRound($stage, $round);
            
            // Verify all teams are paired exactly once per round
            $teamsInRound = [];
            foreach ($matches as $match) {
                $teamsInRound[] = $match->team1_id;
                $teamsInRound[] = $match->team2_id;
            }
            
            $this->assertCount(8, $teamsInRound);
            $this->assertCount(8, array_unique($teamsInRound));
            
            // Complete matches with random results for next round
            foreach ($matches as $match) {
                $winner = rand(0, 1) ? $match->team1_id : $match->team2_id;
                $match->update([
                    'winner_id' => $winner,
                    'team1_score' => $winner === $match->team1_id ? 2 : 1,
                    'team2_score' => $winner === $match->team1_id ? 1 : 2,
                    'status' => 'completed'
                ]);
            }
        }
        
        // Verify final standings integrity
        $standings = $this->service->calculateSwissStandings($stage);
        
        $this->assertCount(8, $standings);
        
        // Verify total games played
        $totalWins = $standings->sum('wins');
        $totalLosses = $standings->sum('losses');
        
        $this->assertEquals($totalWins, $totalLosses); // Wins should equal losses
        $this->assertEquals(12, $totalWins); // 3 rounds * 4 matches per round = 12 total games
    }

    // Helper method for pattern matching in older PHP versions
    protected function assertMatchesPattern(string $pattern, string $value): void
    {
        $this->assertTrue(
            (bool) preg_match($pattern, $value),
            "Failed asserting that '{$value}' matches pattern '{$pattern}'"
        );
    }
}