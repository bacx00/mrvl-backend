<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\TournamentBracket;
use App\Models\BracketMatch;
use App\Services\BracketGenerationService;
use App\Services\BracketProgressionService;
use App\Services\SeedingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OptimizedBracketSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $bracketService;
    protected $progressionService;
    protected $seedingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->bracketService = new BracketGenerationService();
        $this->progressionService = new BracketProgressionService();
        $this->seedingService = new SeedingService();
    }

    /** @test */
    public function it_generates_optimized_single_elimination_bracket()
    {
        // Create tournament with 8 teams
        $tournament = Tournament::factory()->create([
            'format' => 'single_elimination',
            'max_teams' => 8,
            'match_format_settings' => ['playoffs' => 'bo3']
        ]);

        $teams = Team::factory()->count(8)->create();
        
        // Attach teams with seeds
        foreach ($teams as $index => $team) {
            $tournament->teams()->attach($team->id, [
                'status' => 'checked_in',
                'seed' => $index + 1
            ]);
        }

        // Generate bracket
        $startTime = microtime(true);
        $brackets = $this->bracketService->generateTournamentBrackets($tournament);
        $generationTime = microtime(true) - $startTime;

        // Assertions
        $this->assertNotNull($brackets);
        $this->assertCount(1, $brackets);
        
        $bracket = $brackets->first();
        $this->assertEquals('single_elimination', $bracket->bracket_type);
        $this->assertEquals(8, $bracket->team_count);
        $this->assertEquals(3, $bracket->round_count); // log2(8) = 3

        // Verify bracket data structure
        $this->assertNotNull($bracket->bracket_data);
        $this->assertIsArray($bracket->bracket_data);
        
        // Check first round has 4 matches
        $firstRoundMatches = array_filter($bracket->bracket_data, function($match) {
            return $match['round'] === 1;
        });
        $this->assertCount(4, $firstRoundMatches);

        // Performance assertion - should generate in under 1 second
        $this->assertLessThan(1.0, $generationTime);

        // Verify caching
        $this->assertTrue(Cache::has("tournament_bracket_{$tournament->id}"));
    }

    /** @test */
    public function it_generates_optimized_double_elimination_bracket()
    {
        $tournament = Tournament::factory()->create([
            'format' => 'double_elimination',
            'max_teams' => 8
        ]);

        $teams = Team::factory()->count(8)->create();
        foreach ($teams as $index => $team) {
            $tournament->teams()->attach($team->id, [
                'status' => 'checked_in',
                'seed' => $index + 1
            ]);
        }

        $brackets = $this->bracketService->generateTournamentBrackets($tournament);

        $this->assertCount(2, $brackets); // Upper and Lower bracket
        
        $upperBracket = $brackets->firstWhere('bracket_type', 'double_elimination_upper');
        $lowerBracket = $brackets->firstWhere('bracket_type', 'double_elimination_lower');
        
        $this->assertNotNull($upperBracket);
        $this->assertNotNull($lowerBracket);
        $this->assertEquals($upperBracket->id, $lowerBracket->parent_bracket_id);
    }

    /** @test */
    public function it_handles_optimized_seeding_algorithms()
    {
        $teams = Team::factory()->count(8)->create()->map(function($team, $index) {
            $team->rating = 1000 + ($index * 100); // Ratings from 1000 to 1700
            $team->seed = $index + 1;
            return $team;
        })->toArray();

        // Test rating-based seeding
        $ratingSeeded = $this->seedingService->applySeedingMethod($teams, 'rating');
        $this->assertEquals($teams[7]['id'], $ratingSeeded[0]['id']); // Highest rated first

        // Test balanced seeding
        $balancedSeeded = $this->seedingService->applySeedingMethod($teams, 'balanced');
        $this->assertNotNull($balancedSeeded);

        // Test regional seeding
        foreach ($teams as &$team) {
            $team['region'] = $team['rating'] > 1400 ? 'NA' : 'EU';
        }
        $regionalSeeded = $this->seedingService->applySeedingMethod($teams, 'regional');
        $this->assertNotNull($regionalSeeded);

        // Test performance seeding
        $performanceSeeded = $this->seedingService->applySeedingMethod($teams, 'performance');
        $this->assertNotNull($performanceSeeded);
    }

    /** @test */
    public function it_validates_seeding_integrity()
    {
        $teams = Team::factory()->count(8)->create()->map(function($team, $index) {
            $team->seed = $index + 1;
            return $team->toArray();
        })->toArray();

        $validation = $this->seedingService->validateSeeding($teams, 'single_elimination');
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['issues']);

        // Test with invalid seeding (duplicate seeds)
        $teams[1]['seed'] = 1; // Duplicate seed
        $validation = $this->seedingService->validateSeeding($teams, 'single_elimination');
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Duplicate seed found: 1', $validation['issues']);
    }

    /** @test */
    public function it_processes_optimized_match_completion()
    {
        // Create tournament with matches
        $tournament = Tournament::factory()->create(['format' => 'single_elimination']);
        $teams = Team::factory()->count(2)->create();
        
        $match = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'match_identifier' => 'R1M1',
            'round' => 1,
            'match_number' => 1,
            'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id,
            'status' => 'live',
            'bracket_type' => 'main',
            'best_of' => '3'
        ]);

        $scoreData = [
            'team1_score' => 2,
            'team2_score' => 1,
            'forfeit' => false
        ];

        $startTime = microtime(true);
        $result = $this->progressionService->processMatchCompletion($match, $scoreData);
        $processingTime = microtime(true) - $startTime;

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals($teams[0]->id, $result['winner_id']);
        $this->assertEquals($teams[1]->id, $result['loser_id']);
        $this->assertArrayHasKey('advancement_processed', $result);
        $this->assertArrayHasKey('next_matches', $result);

        // Performance assertion - should process in under 500ms
        $this->assertLessThan(0.5, $processingTime);

        // Verify match state updated
        $match->refresh();
        $this->assertEquals('completed', $match->status);
        $this->assertEquals($teams[0]->id, $match->winner_id);
    }

    /** @test */
    public function it_handles_swiss_system_pairing_optimization()
    {
        $standings = [
            ['team_id' => 1, 'wins' => 2, 'losses' => 0, 'rating' => 1500, 'buchholz' => 6],
            ['team_id' => 2, 'wins' => 2, 'losses' => 0, 'rating' => 1400, 'buchholz' => 5],
            ['team_id' => 3, 'wins' => 1, 'losses' => 1, 'rating' => 1300, 'buchholz' => 4],
            ['team_id' => 4, 'wins' => 1, 'losses' => 1, 'rating' => 1200, 'buchholz' => 3],
            ['team_id' => 5, 'wins' => 0, 'losses' => 2, 'rating' => 1100, 'buchholz' => 2],
            ['team_id' => 6, 'wins' => 0, 'losses' => 2, 'rating' => 1000, 'buchholz' => 1],
        ];

        $pairings = $this->bracketService->generateOptimizedSwissPairing($standings, 3);

        $this->assertCount(3, $pairings); // 6 teams = 3 pairings
        
        // Verify top teams are paired together
        $firstPairing = $pairings[0];
        $this->assertContains($firstPairing['team1']['team_id'], [1, 2]);
        $this->assertContains($firstPairing['team2']['team_id'], [1, 2]);
    }

    /** @test */
    public function it_optimizes_database_queries_with_caching()
    {
        $tournament = Tournament::factory()->create();
        
        // Clear cache
        Cache::flush();
        
        // First call should hit database
        DB::enableQueryLog();
        $result1 = $this->bracketService->getCachedBracketData($tournament->id);
        $queriesFirst = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Second call should hit cache
        DB::enableQueryLog();
        $result2 = $this->bracketService->getCachedBracketData($tournament->id);
        $queriesSecond = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Cache should reduce queries
        $this->assertGreaterThan($queriesSecond, $queriesFirst);
        $this->assertEquals($result1['cached_at']->format('Y-m-d H:i:s'), $result2['cached_at']->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_bracket_progression_edge_cases()
    {
        // Test forfeit handling
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();
        
        $match = BracketMatch::create([
            'tournament_id' => $tournament->id,
            'match_identifier' => 'R1M1',
            'round' => 1,
            'match_number' => 1,
            'team1_id' => $teams[0]->id,
            'team2_id' => $teams[1]->id,
            'status' => 'live',
            'bracket_type' => 'main'
        ]);

        $forfeitData = [
            'team1_score' => 0,
            'team2_score' => 0,
            'forfeit' => true,
            'winner_by_forfeit' => 2
        ];

        $result = $this->progressionService->processMatchCompletion($match, $forfeitData);

        $this->assertTrue($result['success']);
        $this->assertEquals($teams[1]->id, $result['winner_id']);
    }

    /** @test */
    public function it_validates_bracket_integrity_after_updates()
    {
        $tournament = Tournament::factory()->create(['format' => 'single_elimination']);
        $teams = Team::factory()->count(4)->create();
        
        foreach ($teams as $index => $team) {
            $tournament->teams()->attach($team->id, [
                'status' => 'checked_in',
                'seed' => $index + 1
            ]);
        }

        $brackets = $this->bracketService->generateTournamentBrackets($tournament);
        $bracket = $brackets->first();

        // Verify bracket structure integrity
        $bracketData = $bracket->bracket_data;
        
        // Check that all first round matches have teams
        $firstRoundMatches = array_filter($bracketData, fn($m) => $m['round'] === 1);
        foreach ($firstRoundMatches as $match) {
            $this->assertNotNull($match['team1_id']);
            $this->assertNotNull($match['team2_id']);
        }

        // Check advancement paths are valid
        foreach ($firstRoundMatches as $match) {
            if (isset($match['winner_advances_to'])) {
                $nextMatchExists = array_key_exists($match['winner_advances_to'], $bracketData);
                $this->assertTrue($nextMatchExists);
            }
        }
    }

    /** @test */
    public function it_measures_performance_for_large_tournaments()
    {
        // Test with 64-team tournament
        $tournament = Tournament::factory()->create([
            'format' => 'single_elimination',
            'max_teams' => 64
        ]);

        $teams = Team::factory()->count(64)->create();
        foreach ($teams as $index => $team) {
            $tournament->teams()->attach($team->id, [
                'status' => 'checked_in',
                'seed' => $index + 1
            ]);
        }

        $startTime = microtime(true);
        $memory_start = memory_get_usage();
        
        $brackets = $this->bracketService->generateTournamentBrackets($tournament);
        
        $endTime = microtime(true);
        $memory_end = memory_get_usage();
        
        $executionTime = $endTime - $startTime;
        $memoryUsed = $memory_end - $memory_start;

        // Performance assertions
        $this->assertLessThan(5.0, $executionTime); // Should complete in under 5 seconds
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed); // Should use less than 50MB

        // Verify bracket correctness
        $bracket = $brackets->first();
        $this->assertEquals(64, $bracket->team_count);
        $this->assertEquals(6, $bracket->round_count); // log2(64) = 6
    }

    /** @test */
    public function it_handles_concurrent_bracket_updates()
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(4)->create();
        
        $matches = [];
        for ($i = 0; $i < 2; $i++) {
            $matches[] = BracketMatch::create([
                'tournament_id' => $tournament->id,
                'match_identifier' => "R1M" . ($i + 1),
                'round' => 1,
                'match_number' => $i + 1,
                'team1_id' => $teams[$i * 2]->id,
                'team2_id' => $teams[$i * 2 + 1]->id,
                'status' => 'live',
                'bracket_type' => 'main'
            ]);
        }

        // Simulate concurrent match completions
        $results = [];
        foreach ($matches as $index => $match) {
            $scoreData = [
                'team1_score' => 2,
                'team2_score' => $index, // Different scores
                'forfeit' => false
            ];
            
            $results[] = $this->progressionService->processMatchCompletion($match, $scoreData);
        }

        // All should succeed
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
        }
    }

    /** @test */
    public function it_optimizes_cache_invalidation()
    {
        $tournament = Tournament::factory()->create();
        
        // Prime cache
        $this->bracketService->getCachedBracketData($tournament->id);
        $this->assertTrue(Cache::has("tournament_bracket_{$tournament->id}"));

        // Invalidate cache
        $this->bracketService->invalidateBracketCache($tournament->id);
        $this->assertFalse(Cache::has("tournament_bracket_{$tournament->id}"));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}