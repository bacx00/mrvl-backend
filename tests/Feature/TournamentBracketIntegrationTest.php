<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Services\TournamentIntegrationService;
use App\Services\BracketService;
use App\Jobs\GenerateTournamentBracket;
use App\Jobs\ProcessMatchCompletion;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

class TournamentBracketIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;
    protected Event $event;
    protected $teams;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        // Create test event
        $this->event = Event::factory()->create([
            'name' => 'Test Tournament',
            'format' => 'double_elimination',
            'status' => 'upcoming',
            'max_teams' => 8,
            'organizer_id' => $this->admin->id
        ]);
        
        // Create 8 teams for tournament
        $this->teams = Team::factory(8)->create();
        
        // Attach teams to event with seeding
        foreach ($this->teams as $index => $team) {
            $this->event->teams()->attach($team->id, [
                'seed' => $index + 1,
                'status' => 'confirmed',
                'registered_at' => now()
            ]);
        }
    }

    /** @test */
    public function it_can_get_tournament_bracket_via_api()
    {
        $this->actingAs($this->admin);
        
        // Generate bracket first
        $tournamentService = app(TournamentIntegrationService::class);
        $tournamentService->createLiquipediaTournament($this->event, [
            'format' => 'double_elimination',
            'phases' => [
                [
                    'name' => 'Main Event',
                    'type' => 'double_elimination',
                    'advancement' => ['top_8' => 'playoffs']
                ]
            ]
        ]);
        
        // Test API endpoint
        $response = $this->getJson("/api/tournaments/events/{$this->event->id}/bracket");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'event' => [
                            'id',
                            'name',
                            'format',
                            'status',
                            'current_round',
                            'total_rounds'
                        ],
                        'brackets' => [
                            '*' => [
                                'id',
                                'name',
                                'type',
                                'status',
                                'matches'
                            ]
                        ],
                        'liquipedia_notation',
                        'metadata' => [
                            'teams_count',
                            'total_matches',
                            'completed_matches'
                        ]
                    ]
                ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->event->id, $data['event']['id']);
        $this->assertEquals('double_elimination', $data['event']['format']);
        $this->assertEquals(8, $data['metadata']['teams_count']);
    }

    /** @test */
    public function it_can_update_match_score_and_process_advancement()
    {
        $this->actingAs($this->admin);
        
        // Generate bracket
        $bracketService = app(BracketService::class);
        $result = $bracketService->generateDoubleEliminationBracket($this->event, $this->teams);
        
        // Get first match
        $firstMatch = $result['upper_matches']->first();
        
        // Update match score
        $response = $this->putJson("/api/tournaments/events/{$this->event->id}/match/{$firstMatch->id}/score", [
            'team1_score' => 2,
            'team2_score' => 1,
            'status' => 'completed',
            'games' => [
                [
                    'game_number' => 1,
                    'map_name' => 'Birnin Zana',
                    'team1_score' => 1,
                    'team2_score' => 0,
                    'winner_id' => $firstMatch->team1_id
                ],
                [
                    'game_number' => 2,
                    'map_name' => 'New Tokyo',
                    'team1_score' => 1,
                    'team2_score' => 0,
                    'winner_id' => $firstMatch->team1_id
                ],
                [
                    'game_number' => 3,
                    'map_name' => 'Stark Tower',
                    'team1_score' => 0,
                    'team2_score' => 1,
                    'winner_id' => $firstMatch->team2_id
                ]
            ]
        ]);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Match updated and advancement processed'
                ]);
        
        // Verify match was updated
        $firstMatch->refresh();
        $this->assertEquals(2, $firstMatch->team1_score);
        $this->assertEquals(1, $firstMatch->team2_score);
        $this->assertEquals('completed', $firstMatch->status);
        $this->assertEquals($firstMatch->team1_id, $firstMatch->winner_id);
        
        // Verify games were created
        $this->assertEquals(3, $firstMatch->games()->count());
        
        // Check if winner advanced to next match
        $nextMatch = BracketMatch::where('team1_source', "Winner of {$firstMatch->match_id}")
                                ->orWhere('team2_source', "Winner of {$firstMatch->match_id}")
                                ->first();
        
        $this->assertNotNull($nextMatch);
        $this->assertTrue(
            $nextMatch->team1_id === $firstMatch->winner_id || 
            $nextMatch->team2_id === $firstMatch->winner_id
        );
    }

    /** @test */
    public function it_can_generate_swiss_system_tournament()
    {
        $this->actingAs($this->admin);
        
        // Update event to use Swiss format
        $this->event->update(['format' => 'swiss']);
        
        $bracketService = app(BracketService::class);
        $result = $bracketService->generateSwissBracket($this->event, $this->teams, [
            'rounds' => 3,
            'seeding_method' => 'random'
        ]);
        
        $swissStage = $result['swiss_stage'];
        
        $this->assertEquals('swiss', $swissStage->type);
        $this->assertEquals(3, $swissStage->total_rounds);
        
        // Check first round matches
        $firstRoundMatches = $swissStage->matches()->where('round_number', 1)->get();
        $this->assertEquals(4, $firstRoundMatches->count()); // 8 teams = 4 matches per round
        
        // Verify all teams are in first round
        $teamsInFirstRound = [];
        foreach ($firstRoundMatches as $match) {
            $teamsInFirstRound[] = $match->team1_id;
            $teamsInFirstRound[] = $match->team2_id;
        }
        $this->assertEquals(8, count(array_unique($teamsInFirstRound)));
    }

    /** @test */
    public function it_generates_correct_liquipedia_notation()
    {
        $this->actingAs($this->admin);
        
        $bracketService = app(BracketService::class);
        $result = $bracketService->generateDoubleEliminationBracket($this->event, $this->teams);
        
        $tournamentService = app(TournamentIntegrationService::class);
        $notation = $tournamentService->generateLiquipediaNotation([
            'upper_bracket' => ['matches' => $result['upper_matches']],
            'lower_bracket' => ['matches' => $result['lower_matches']]
        ]);
        
        $this->assertIsArray($notation);
        $this->assertArrayHasKey('Upper Bracket', $notation);
        $this->assertArrayHasKey('Lower Bracket', $notation);
        
        // Check R#M# notation format
        foreach ($notation['Upper Bracket'] as $roundName => $roundMatches) {
            foreach ($roundMatches as $match) {
                $this->assertMatchesPattern('/^R\d+M\d+$/', $match['liquipedia_id']);
                $this->assertArrayHasKey('teams', $match);
                $this->assertArrayHasKey('score', $match);
                $this->assertArrayHasKey('advancement', $match);
            }
        }
    }

    /** @test */
    public function it_handles_swiss_round_generation_correctly()
    {
        $this->actingAs($this->admin);
        
        // Create Swiss tournament
        $this->event->update(['format' => 'swiss']);
        
        $bracketService = app(BracketService::class);
        $result = $bracketService->generateSwissBracket($this->event, $this->teams, [
            'rounds' => 3,
            'seeding_method' => 'rating'
        ]);
        
        $swissStage = $result['swiss_stage'];
        
        // Complete first round matches with different results
        $firstRoundMatches = $swissStage->matches()->where('round_number', 1)->get();
        
        foreach ($firstRoundMatches as $index => $match) {
            // Alternate winners to create different records
            $winnerId = $index % 2 === 0 ? $match->team1_id : $match->team2_id;
            $loserId = $winnerId === $match->team1_id ? $match->team2_id : $match->team1_id;
            
            $match->update([
                'team1_score' => $winnerId === $match->team1_id ? 2 : 1,
                'team2_score' => $winnerId === $match->team1_id ? 1 : 2,
                'winner_id' => $winnerId,
                'loser_id' => $loserId,
                'status' => 'completed',
                'completed_at' => now()
            ]);
        }
        
        // Generate second round
        $tournamentService = app(TournamentIntegrationService::class);
        $secondRoundMatches = $tournamentService->generateSwissRound($swissStage, 2);
        
        $this->assertEquals(4, $secondRoundMatches->count());
        
        // Verify Swiss pairing logic (teams with similar records should be paired)
        $winnerTeamIds = $firstRoundMatches->pluck('winner_id')->toArray();
        $loserTeamIds = $firstRoundMatches->pluck('loser_id')->toArray();
        
        $secondRoundTeams = [];
        foreach ($secondRoundMatches as $match) {
            $secondRoundTeams[] = $match->team1_id;
            $secondRoundTeams[] = $match->team2_id;
        }
        
        // All teams should still be in the tournament
        $this->assertCount(8, array_unique($secondRoundTeams));
    }

    /** @test */
    public function it_processes_bracket_reset_in_grand_finals()
    {
        $this->actingAs($this->admin);
        
        $bracketService = app(BracketService::class);
        $result = $bracketService->generateDoubleEliminationBracket($this->event, $this->teams);
        
        // Create a scenario where lower bracket team wins grand finals
        $grandFinal = BracketMatch::create([
            'match_id' => 'GF',
            'liquipedia_id' => 'GF',
            'event_id' => $this->event->id,
            'bracket_stage_id' => $result['final_stage']->id,
            'round_name' => 'Grand Final',
            'round_number' => 1,
            'match_number' => 1,
            'team1_id' => $this->teams[0]->id, // Upper bracket winner
            'team2_id' => $this->teams[1]->id, // Lower bracket winner
            'team1_source' => 'Upper Bracket Champion',
            'team2_source' => 'Lower Bracket Champion',
            'status' => 'ready',
            'best_of' => 5
        ]);
        
        // Lower bracket team wins
        $response = $this->putJson("/api/tournaments/events/{$this->event->id}/match/{$grandFinal->id}/score", [
            'team1_score' => 2,
            'team2_score' => 3,
            'status' => 'completed'
        ]);
        
        $response->assertStatus(200);
        
        $grandFinal->refresh();
        $this->assertEquals($this->teams[1]->id, $grandFinal->winner_id);
        
        // Check if bracket reset match was created
        $bracketReset = BracketMatch::where('bracket_reset', true)
                                   ->where('event_id', $this->event->id)
                                   ->first();
        
        $this->assertNotNull($bracketReset);
        $this->assertEquals('Grand Final - Bracket Reset', $bracketReset->round_name);
    }

    /** @test */
    public function it_handles_tournament_queue_jobs_correctly()
    {
        Queue::fake();
        
        $this->actingAs($this->admin);
        
        // Dispatch bracket generation job
        GenerateTournamentBracket::dispatch($this->event, [
            'format' => 'double_elimination',
            'seeding_method' => 'seed'
        ]);
        
        Queue::assertPushed(GenerateTournamentBracket::class, function ($job) {
            return $job->tournament->id === $this->event->id;
        });
        
        // Create a match and test match completion job
        $match = BracketMatch::factory()->create([
            'event_id' => $this->event->id,
            'team1_id' => $this->teams[0]->id,
            'team2_id' => $this->teams[1]->id,
            'winner_id' => $this->teams[0]->id,
            'status' => 'completed'
        ]);
        
        ProcessMatchCompletion::dispatch($match, [
            'team1_score' => 2,
            'team2_score' => 1
        ]);
        
        Queue::assertPushed(ProcessMatchCompletion::class, function ($job) use ($match) {
            return $job->match->id === $match->id;
        });
    }

    /** @test */
    public function it_caches_tournament_data_effectively()
    {
        $this->actingAs($this->admin);
        
        // Generate bracket
        $bracketService = app(BracketService::class);
        $bracketService->generateDoubleEliminationBracket($this->event, $this->teams);
        
        // First request should cache the data
        $response1 = $this->getJson("/api/tournaments/events/{$this->event->id}/bracket");
        $response1->assertStatus(200);
        
        // Check if data was cached
        $cacheKey = "event_bracket_{$this->event->id}";
        $this->assertTrue(Cache::has($cacheKey));
        
        // Second request should use cached data
        $response2 = $this->getJson("/api/tournaments/events/{$this->event->id}/bracket");
        $response2->assertStatus(200);
        
        // Verify responses are identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    /** @test */
    public function it_validates_tournament_format_requirements()
    {
        $this->actingAs($this->admin);
        
        // Test double elimination with non-power-of-2 teams
        $this->teams = Team::factory(6)->create(); // Non-power of 2
        
        $this->event->teams()->detach();
        foreach ($this->teams as $index => $team) {
            $this->event->teams()->attach($team->id, ['seed' => $index + 1]);
        }
        
        $bracketService = app(BracketService::class);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Double elimination requires a power of 2 teams');
        
        $bracketService->generateDoubleEliminationBracket($this->event, $this->teams);
    }

    /** @test */
    public function it_handles_live_tournament_state_updates()
    {
        $this->actingAs($this->admin);
        
        // Generate bracket and create live matches
        $bracketService = app(BracketService::class);
        $result = $bracketService->generateDoubleEliminationBracket($this->event, $this->teams);
        
        // Set some matches as live
        $liveMatches = $result['upper_matches']->take(2);
        foreach ($liveMatches as $match) {
            $match->update([
                'status' => 'live',
                'started_at' => now()
            ]);
        }
        
        // Test live state endpoint
        $response = $this->getJson("/api/tournaments/events/{$this->event->id}/live-state");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'event_status',
                        'current_round',
                        'total_rounds',
                        'live_matches',
                        'upcoming_matches',
                        'statistics'
                    ]
                ]);
        
        $data = $response->json('data');
        $this->assertCount(2, $data['live_matches']);
        $this->assertEquals('ongoing', $data['event_status']);
    }

    /** @test */
    public function it_handles_unauthorized_access_properly()
    {
        // Test without authentication
        $response = $this->getJson("/api/tournaments/events/{$this->event->id}/bracket");
        $response->assertStatus(200); // Bracket viewing should be public
        
        // Test admin-only actions without authentication
        $response = $this->putJson("/api/tournaments/events/{$this->event->id}/match/1/score", [
            'team1_score' => 2,
            'team2_score' => 1
        ]);
        $response->assertStatus(401);
        
        // Test with non-admin user
        $regularUser = User::factory()->create(['role' => 'user']);
        $this->actingAs($regularUser);
        
        $response = $this->putJson("/api/tournaments/events/{$this->event->id}/match/1/score", [
            'team1_score' => 2,
            'team2_score' => 1
        ]);
        $response->assertStatus(403);
    }
}