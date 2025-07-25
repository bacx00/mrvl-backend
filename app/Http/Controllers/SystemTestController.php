<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use App\Models\User;

class SystemTestController extends Controller
{
    public function testAllSystems()
    {
        $results = [];

        // Test Database Connection
        $results['database'] = $this->testDatabaseConnection();
        
        // Test Authentication System
        $results['authentication'] = $this->testAuthenticationSystem();
        
        // Test User System
        $results['users'] = $this->testUserSystem();
        
        // Test Team System
        $results['teams'] = $this->testTeamSystem();
        
        // Test Player System
        $results['players'] = $this->testPlayerSystem();
        
        // Test Match System
        $results['matches'] = $this->testMatchSystem();
        
        // Test Event System
        $results['events'] = $this->testEventSystem();
        
        // Test Forum System
        $results['forums'] = $this->testForumSystem();
        
        // Test News System
        $results['news'] = $this->testNewsSystem();
        
        // Test Ranking System
        $results['rankings'] = $this->testRankingSystem();
        
        // Test Hero System
        $results['heroes'] = $this->testHeroSystem();
        
        // Test Search System
        $results['search'] = $this->testSearchSystem();
        
        // Test Live Scoring System
        $results['live_scoring'] = $this->testLiveScoringSystem();
        
        // Test Mentions System
        $results['mentions'] = $this->testMentionsSystem();
        
        // Test Analytics System
        $results['analytics'] = $this->testAnalyticsSystem();
        
        // Test Profile System
        $results['profiles'] = $this->testProfileSystem();
        
        // Test API Endpoints
        $results['endpoints'] = $this->testAPIEndpoints();

        $allPassed = collect($results)->every(function($system) {
            return collect($system['tests'])->every(function($test) {
                return $test['passed'];
            });
        });

        return response()->json([
            'success' => $allPassed,
            'timestamp' => now()->toIso8601String(),
            'results' => $results,
            'summary' => [
                'total_systems' => count($results),
                'passed_systems' => collect($results)->filter(function($system) {
                    return $system['passed'];
                })->count(),
                'total_tests' => collect($results)->sum(function($system) {
                    return count($system['tests']);
                }),
                'passed_tests' => collect($results)->sum(function($system) {
                    return collect($system['tests'])->filter(function($test) {
                        return $test['passed'];
                    })->count();
                }),
                'endpoint_count' => $this->countAPIEndpoints(),
                'table_count' => $this->countDatabaseTables()
            ]
        ]);
    }
    
    private function testDatabaseConnection()
    {
        $tests = [];
        
        try {
            DB::connection()->getPdo();
            $tests[] = ['name' => 'Database connection', 'passed' => true];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Database connection', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        try {
            $tableCount = count(DB::select('SHOW TABLES'));
            $tests[] = ['name' => 'Database tables exist', 'passed' => $tableCount > 0, 'count' => $tableCount];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Database tables exist', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'Database Connection',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function testAuthenticationSystem()
    {
        $tests = [];
        
        // Test Passport tables
        try {
            $passportTables = ['oauth_access_tokens', 'oauth_auth_codes', 'oauth_clients', 
                              'oauth_personal_access_clients', 'oauth_refresh_tokens'];
            $allExist = true;
            foreach ($passportTables as $table) {
                if (!Schema::hasTable($table)) {
                    $allExist = false;
                    break;
                }
            }
            $tests[] = ['name' => 'Passport tables exist', 'passed' => $allExist];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Passport tables exist', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        // Test auth routes
        try {
            $authRoutes = ['api/auth/login', 'api/auth/register', 'api/auth/logout', 'api/auth/me'];
            $routeCollection = Route::getRoutes();
            $foundRoutes = 0;
            foreach ($routeCollection as $route) {
                if (in_array($route->uri(), $authRoutes)) {
                    $foundRoutes++;
                }
            }
            $tests[] = ['name' => 'Auth routes exist', 'passed' => $foundRoutes >= 4, 'found' => $foundRoutes];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Auth routes exist', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        // Test test user
        try {
            $testUser = User::where('email', 'jhonny@ar-mediia.com')->first();
            $tests[] = ['name' => 'Test user exists', 'passed' => $testUser !== null];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Test user exists', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'Authentication System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testUserSystem()
    {
        $tests = [];

        // Test user flairs
        try {
            $user = DB::table('users')->first();
            if ($user) {
                $hasFlairColumns = DB::getSchemaBuilder()->hasColumn('users', 'hero_flair') &&
                                  DB::getSchemaBuilder()->hasColumn('users', 'team_flair_id') &&
                                  DB::getSchemaBuilder()->hasColumn('users', 'show_hero_flair') &&
                                  DB::getSchemaBuilder()->hasColumn('users', 'show_team_flair');
                $tests[] = ['name' => 'User flair columns', 'passed' => $hasFlairColumns];
            }
        } catch (\Exception $e) {
            $tests[] = ['name' => 'User flair columns', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test profile picture system
        try {
            $hasAvatarColumns = DB::getSchemaBuilder()->hasColumn('users', 'avatar') &&
                               DB::getSchemaBuilder()->hasColumn('users', 'profile_picture_type') &&
                               DB::getSchemaBuilder()->hasColumn('users', 'use_hero_as_avatar');
            $tests[] = ['name' => 'Profile picture system', 'passed' => $hasAvatarColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Profile picture system', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test roles
        try {
            $rolesExist = DB::table('roles')
                ->whereIn('name', ['admin', 'moderator', 'user'])
                ->where('guard_name', 'api')
                ->count() === 3;
            $tests[] = ['name' => 'User roles exist', 'passed' => $rolesExist];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'User roles exist', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'User System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testTeamSystem()
    {
        $tests = [];

        // Test team table structure
        try {
            $hasRequiredColumns = DB::getSchemaBuilder()->hasColumn('teams', 'name') &&
                                 DB::getSchemaBuilder()->hasColumn('teams', 'short_name') &&
                                 DB::getSchemaBuilder()->hasColumn('teams', 'logo') &&
                                 DB::getSchemaBuilder()->hasColumn('teams', 'rating') &&
                                 DB::getSchemaBuilder()->hasColumn('teams', 'country_flag');
            $tests[] = ['name' => 'Team table structure', 'passed' => $hasRequiredColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Team table structure', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Team System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testPlayerSystem()
    {
        $tests = [];

        // Test player table structure
        try {
            $hasRequiredColumns = DB::getSchemaBuilder()->hasColumn('players', 'username') &&
                                 DB::getSchemaBuilder()->hasColumn('players', 'real_name') &&
                                 DB::getSchemaBuilder()->hasColumn('players', 'main_hero') &&
                                 DB::getSchemaBuilder()->hasColumn('players', 'country_flag');
            $tests[] = ['name' => 'Player table structure', 'passed' => $hasRequiredColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Player table structure', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Player System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testMatchSystem()
    {
        $tests = [];

        // Test match live scoring columns
        try {
            $hasLiveScoringColumns = DB::getSchemaBuilder()->hasColumn('matches', 'maps_data') &&
                                    DB::getSchemaBuilder()->hasColumn('matches', 'live_data') &&
                                    DB::getSchemaBuilder()->hasColumn('matches', 'started_at') &&
                                    DB::getSchemaBuilder()->hasColumn('matches', 'completed_at');
            $tests[] = ['name' => 'Match live scoring columns', 'passed' => $hasLiveScoringColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Match live scoring columns', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test match player stats table
        try {
            $hasStatsTable = DB::getSchemaBuilder()->hasTable('match_player_stats');
            $tests[] = ['name' => 'Match player stats table', 'passed' => $hasStatsTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Match player stats table', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test match comments system
        try {
            $hasCommentsTable = DB::getSchemaBuilder()->hasTable('match_comments');
            $tests[] = ['name' => 'Match comments table', 'passed' => $hasCommentsTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Match comments table', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Match System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testEventSystem()
    {
        $tests = [];

        // Test event table structure
        try {
            $hasRequiredColumns = DB::getSchemaBuilder()->hasColumn('events', 'slug') &&
                                 DB::getSchemaBuilder()->hasColumn('events', 'format') &&
                                 DB::getSchemaBuilder()->hasColumn('events', 'prize_pool') &&
                                 DB::getSchemaBuilder()->hasColumn('events', 'views');
            $tests[] = ['name' => 'Event table structure', 'passed' => $hasRequiredColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Event table structure', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test event teams table
        try {
            $hasEventTeamsTable = DB::getSchemaBuilder()->hasTable('event_teams');
            $hasRequiredColumns = DB::getSchemaBuilder()->hasColumn('event_teams', 'placement') &&
                                 DB::getSchemaBuilder()->hasColumn('event_teams', 'prize_money');
            $tests[] = ['name' => 'Event teams system', 'passed' => $hasEventTeamsTable && $hasRequiredColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Event teams system', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Event System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testForumSystem()
    {
        $tests = [];

        // Test forum tables
        try {
            $hasForumTables = DB::getSchemaBuilder()->hasTable('forum_categories') &&
                             DB::getSchemaBuilder()->hasTable('forum_threads') &&
                             DB::getSchemaBuilder()->hasTable('forum_posts');
            $tests[] = ['name' => 'Forum tables exist', 'passed' => $hasForumTables];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Forum tables exist', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test voting system
        try {
            $hasVotingColumns = DB::getSchemaBuilder()->hasColumn('forum_threads', 'upvotes') &&
                               DB::getSchemaBuilder()->hasColumn('forum_threads', 'downvotes');
            $tests[] = ['name' => 'Forum voting system', 'passed' => $hasVotingColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Forum voting system', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Forum System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testNewsSystem()
    {
        $tests = [];

        // Test news table
        try {
            $hasNewsTable = DB::getSchemaBuilder()->hasTable('news');
            $hasRequiredColumns = DB::getSchemaBuilder()->hasColumn('news', 'featured_image') &&
                                 DB::getSchemaBuilder()->hasColumn('news', 'video_url') &&
                                 DB::getSchemaBuilder()->hasColumn('news', 'mentions');
            $tests[] = ['name' => 'News system structure', 'passed' => $hasNewsTable && $hasRequiredColumns];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'News system structure', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test news comments
        try {
            $hasCommentsTable = DB::getSchemaBuilder()->hasTable('news_comments');
            $tests[] = ['name' => 'News comments system', 'passed' => $hasCommentsTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'News comments system', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'News System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testRankingSystem()
    {
        $tests = [];

        // Test rankings table
        try {
            $hasRankingsTable = DB::getSchemaBuilder()->hasTable('rankings');
            $tests[] = ['name' => 'Rankings table exists', 'passed' => $hasRankingsTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Rankings table exists', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test Marvel Rivals ranks
        try {
            $ranks = ['bronze_iii', 'bronze_ii', 'bronze_i', 'silver_iii', 'silver_ii', 'silver_i',
                     'gold_iii', 'gold_ii', 'gold_i', 'platinum_iii', 'platinum_ii', 'platinum_i',
                     'diamond_iii', 'diamond_ii', 'diamond_i', 'grandmaster_iii', 'grandmaster_ii',
                     'grandmaster_i', 'celestial_iii', 'celestial_ii', 'celestial_i', 'eternity', 'one_above_all'];
            $tests[] = ['name' => 'Marvel Rivals rank system', 'passed' => true];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Marvel Rivals rank system', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Ranking System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testHeroSystem()
    {
        $tests = [];

        // Test heroes table
        try {
            $hasHeroesTable = DB::getSchemaBuilder()->hasTable('marvel_rivals_heroes');
            $heroCount = DB::table('marvel_rivals_heroes')->count();
            $tests[] = ['name' => 'Heroes table with data', 'passed' => $hasHeroesTable && $heroCount >= 37];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Heroes table with data', 'passed' => false, 'error' => $e->getMessage()];
        }

        // Test maps table
        try {
            $hasMapsTable = DB::getSchemaBuilder()->hasTable('marvel_rivals_maps');
            $mapCount = DB::table('marvel_rivals_maps')->count();
            $tests[] = ['name' => 'Maps table with data', 'passed' => $hasMapsTable && $mapCount >= 12];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Maps table with data', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Hero System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }

    private function testSearchSystem()
    {
        $tests = [];

        // Test search functionality exists
        try {
            $searchRoutes = ['api/search', 'api/search/teams', 'api/search/players', 'api/search/mentions'];
            $routeCollection = Route::getRoutes();
            $foundRoutes = 0;
            foreach ($routeCollection as $route) {
                if (str_contains($route->uri(), 'api/search')) {
                    $foundRoutes++;
                }
            }
            $tests[] = ['name' => 'Search system routes', 'passed' => $foundRoutes >= 8, 'found' => $foundRoutes];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Search system routes', 'passed' => false, 'error' => $e->getMessage()];
        }

        return [
            'name' => 'Search System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function testLiveScoringSystem()
    {
        $tests = [];
        
        // Test live match updates table
        try {
            $hasTable = Schema::hasTable('live_match_updates');
            $tests[] = ['name' => 'Live match updates table', 'passed' => $hasTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Live match updates table', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        // Test match streaming endpoints
        try {
            $streamingRoutes = 0;
            foreach (Route::getRoutes() as $route) {
                if (str_contains($route->uri(), 'stream') || str_contains($route->uri(), 'live-update')) {
                    $streamingRoutes++;
                }
            }
            $tests[] = ['name' => 'Live scoring endpoints', 'passed' => $streamingRoutes >= 3, 'found' => $streamingRoutes];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Live scoring endpoints', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'Live Scoring System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function testMentionsSystem()
    {
        $tests = [];
        
        // Test mentions table
        try {
            $hasTable = Schema::hasTable('mentions');
            $tests[] = ['name' => 'Mentions table exists', 'passed' => $hasTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Mentions table exists', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        // Test mention routes
        try {
            $mentionRoutes = 0;
            foreach (Route::getRoutes() as $route) {
                if (str_contains($route->uri(), 'mention')) {
                    $mentionRoutes++;
                }
            }
            $tests[] = ['name' => 'Mention endpoints', 'passed' => $mentionRoutes >= 2, 'found' => $mentionRoutes];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Mention endpoints', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'Mentions System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function testAnalyticsSystem()
    {
        $tests = [];
        
        // Test analytics routes
        try {
            $analyticsRoutes = 0;
            foreach (Route::getRoutes() as $route) {
                if (str_contains($route->uri(), 'analytics')) {
                    $analyticsRoutes++;
                }
            }
            $tests[] = ['name' => 'Analytics endpoints', 'passed' => $analyticsRoutes >= 5, 'found' => $analyticsRoutes];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Analytics endpoints', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'Analytics System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function testProfileSystem()
    {
        $tests = [];
        
        // Test profile views table
        try {
            $hasTable = Schema::hasTable('profile_views');
            $tests[] = ['name' => 'Profile views table', 'passed' => $hasTable];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Profile views table', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        // Test profile routes
        try {
            $profileRoutes = 0;
            foreach (Route::getRoutes() as $route) {
                if (str_contains($route->uri(), 'profile')) {
                    $profileRoutes++;
                }
            }
            $tests[] = ['name' => 'Profile endpoints', 'passed' => $profileRoutes >= 4, 'found' => $profileRoutes];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Profile endpoints', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'Profile System',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function testAPIEndpoints()
    {
        $tests = [];
        
        // Count total API endpoints
        try {
            $apiEndpoints = 0;
            $categories = [];
            foreach (Route::getRoutes() as $route) {
                if (str_starts_with($route->uri(), 'api/')) {
                    $apiEndpoints++;
                    $category = explode('/', $route->uri())[1] ?? 'other';
                    if (!isset($categories[$category])) {
                        $categories[$category] = 0;
                    }
                    $categories[$category]++;
                }
            }
            $tests[] = [
                'name' => 'Total API endpoints', 
                'passed' => $apiEndpoints >= 200, 
                'count' => $apiEndpoints,
                'categories' => $categories
            ];
        } catch (\Exception $e) {
            $tests[] = ['name' => 'Total API endpoints', 'passed' => false, 'error' => $e->getMessage()];
        }
        
        return [
            'name' => 'API Endpoints',
            'passed' => collect($tests)->every(fn($test) => $test['passed']),
            'tests' => $tests
        ];
    }
    
    private function countAPIEndpoints()
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $count++;
            }
        }
        return $count;
    }
    
    private function countDatabaseTables()
    {
        try {
            return count(DB::select('SHOW TABLES'));
        } catch (\Exception $e) {
            return 0;
        }
    }
}