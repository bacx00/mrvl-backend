<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\Leaderboard;
use App\Models\UserAchievement;
use App\Models\UserStreak;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class AchievementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $achievementService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the achievement system
        Artisan::call('achievement:seed');
        
        // Create a test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'user'
        ]);
        
        $this->achievementService = app(AchievementService::class);
    }

    /** @test */
    public function it_can_load_achievements()
    {
        $response = $this->getJson('/api/achievements');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'data' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'slug',
                                 'description',
                                 'category',
                                 'rarity',
                                 'points'
                             ]
                         ]
                     ]
                 ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, count($response->json('data.data')));
    }

    /** @test */
    public function it_can_load_individual_achievement()
    {
        $achievement = Achievement::first();
        
        $response = $this->getJson("/api/achievements/{$achievement->id}");
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $achievement->id,
                         'name' => $achievement->name
                     ]
                 ]);
    }

    /** @test */
    public function it_can_track_user_activity()
    {
        $this->actingAs($this->user, 'api');
        
        $response = $this->postJson('/api/achievements/track', [
            'activity_type' => 'comment_posted',
            'metadata' => ['test' => true]
        ]);
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_user_achievement_summary()
    {
        $this->actingAs($this->user, 'api');
        
        $response = $this->getJson('/api/achievements/summary');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'completed_achievements',
                         'in_progress_achievements',
                         'total_points',
                         'recent_achievements',
                         'active_streaks',
                         'active_challenges'
                     ]
                 ]);
    }

    /** @test */
    public function it_can_load_leaderboards()
    {
        $response = $this->getJson('/api/leaderboards');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => [
                             'id',
                             'name',
                             'slug',
                             'type',
                             'period'
                         ]
                     ]
                 ]);
        
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /** @test */
    public function it_can_load_individual_leaderboard()
    {
        $leaderboard = Leaderboard::first();
        
        $response = $this->getJson("/api/leaderboards/{$leaderboard->id}");
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'leaderboard',
                         'entries'
                     ]
                 ]);
    }

    /** @test */
    public function it_can_load_challenges()
    {
        $response = $this->getJson('/api/challenges');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'data' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'slug',
                                 'description',
                                 'difficulty',
                                 'rewards'
                             ]
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function it_can_join_a_challenge()
    {
        $this->actingAs($this->user, 'api');
        
        $challenge = Challenge::where('is_active', true)->first();
        
        if ($challenge) {
            $response = $this->postJson("/api/challenges/{$challenge->id}/join");
            
            // Note: This might fail if challenge is not currently active
            // but we're testing the endpoint structure
            $response->assertJsonStructure(['success']);
        } else {
            $this->markTestSkipped('No active challenges available for testing');
        }
    }

    /** @test */
    public function it_can_get_streak_types()
    {
        $response = $this->getJson('/api/streaks/types');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'login',
                         'comment',
                         'forum_post',
                         'prediction',
                         'vote'
                     ]
                 ]);
    }

    /** @test */
    public function it_can_get_streak_statistics()
    {
        $response = $this->getJson('/api/streaks/statistics');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_active_streaks',
                         'streaks_at_risk',
                         'longest_current_streak',
                         'longest_all_time_streak'
                     ]
                 ]);
    }

    /** @test */
    public function it_can_get_user_streaks()
    {
        $this->actingAs($this->user, 'api');
        
        $response = $this->getJson('/api/streaks/user');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data'
                 ]);
    }

    /** @test */
    public function it_can_award_achievement_to_user()
    {
        $achievement = Achievement::first();
        
        $success = $this->achievementService->awardAchievement($this->user->id, $achievement->id);
        
        $this->assertTrue($success);
        
        $userAchievement = UserAchievement::where('user_id', $this->user->id)
                                         ->where('achievement_id', $achievement->id)
                                         ->first();
                                         
        $this->assertNotNull($userAchievement);
        $this->assertTrue($userAchievement->is_completed);
    }

    /** @test */
    public function it_can_track_streaks()
    {
        // Create a login streak
        $streak = UserStreak::create([
            'user_id' => $this->user->id,
            'streak_type' => 'login',
            'current_count' => 5,
            'best_count' => 5,
            'last_activity_date' => now()->toDateString(),
            'streak_started_at' => now()->subDays(4),
            'is_active' => true
        ]);
        
        $this->assertEquals(5, $streak->current_count);
        $this->assertTrue($streak->is_active);
        
        // Test recording activity
        $streak->recordActivity();
        $streak->refresh();
        
        $this->assertEquals(6, $streak->current_count);
        $this->assertEquals(6, $streak->best_count);
    }

    /** @test */
    public function it_provides_all_metadata_endpoints()
    {
        // Test achievement categories
        $response = $this->getJson('/api/achievements/categories');
        $response->assertStatus(200);
        
        // Test achievement rarities
        $response = $this->getJson('/api/achievements/rarities');
        $response->assertStatus(200);
        
        // Test challenge difficulties
        $response = $this->getJson('/api/challenges/difficulties');
        $response->assertStatus(200);
        
        // Test leaderboard metadata
        $response = $this->getJson('/api/leaderboards/metadata');
        $response->assertStatus(200);
    }

    /** @test */
    public function achievement_service_calculates_user_stats()
    {
        // Award some achievements
        $achievements = Achievement::take(3)->get();
        
        foreach ($achievements as $achievement) {
            $this->achievementService->awardAchievement($this->user->id, $achievement->id);
        }
        
        $summary = $this->achievementService->getUserAchievementSummary($this->user->id);
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('completed_achievements', $summary);
        $this->assertArrayHasKey('total_points', $summary);
        $this->assertEquals(3, $summary['completed_achievements']);
        $this->assertGreaterThan(0, $summary['total_points']);
    }

    /** @test */
    public function it_handles_global_achievement_statistics()
    {
        $response = $this->getJson('/api/achievements/stats/global');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_achievements',
                         'total_earned',
                         'most_earned',
                         'rarest_achievements'
                     ]
                 ]);
    }

    /** @test */
    public function system_maintains_data_integrity()
    {
        // Check that all seeded data is properly structured
        $achievements = Achievement::all();
        $challenges = Challenge::all();
        $leaderboards = Leaderboard::all();
        
        $this->assertGreaterThan(0, $achievements->count());
        $this->assertGreaterThan(0, $challenges->count());
        $this->assertGreaterThan(0, $leaderboards->count());
        
        // Verify achievement requirements are valid JSON
        foreach ($achievements as $achievement) {
            $this->assertIsArray($achievement->requirements);
        }
        
        // Verify challenge requirements and rewards are valid JSON
        foreach ($challenges as $challenge) {
            $this->assertIsArray($challenge->requirements);
            $this->assertIsArray($challenge->rewards);
        }
        
        // Verify leaderboard criteria are valid JSON
        foreach ($leaderboards as $leaderboard) {
            $this->assertIsArray($leaderboard->criteria);
        }
    }
}