<?php

/**
 * Forum Moderation System Test Script
 * 
 * This script tests the comprehensive forum moderation panel implementation
 * Run: php forum_moderation_test.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ForumThread;
use App\Models\ForumCategory;
use App\Models\Post;
use App\Models\Report;
use App\Models\UserWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForumModerationTest
{
    private $results = [];
    private $testUser;
    private $moderatorUser;
    private $testCategory;
    private $testThread;
    private $testPost;

    public function __construct()
    {
        echo "=== MRVL Forum Moderation System Test ===\n\n";
    }

    public function runAllTests()
    {
        try {
            $this->testDatabaseStructure();
            $this->testModelRelationships();
            $this->testUserModerationMethods();
            $this->testReportSystem();
            $this->testWarningSystem();
            $this->testBulkOperations();
            $this->generateReport();
        } catch (\Exception $e) {
            echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    }

    private function testDatabaseStructure()
    {
        echo "Testing database structure...\n";

        // Test table existence
        $requiredTables = [
            'forum_threads',
            'forum_categories', 
            'forum_posts',
            'users',
            'reports',
            'user_warnings'
        ];

        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $this->results['database'][$table] = '✓ EXISTS';
            } else {
                $this->results['database'][$table] = '✗ MISSING';
            }
        }

        // Test required columns
        $columnTests = [
            'forum_threads' => ['is_flagged', 'sticky', 'locked', 'pinned'],
            'forum_posts' => ['is_flagged'],
            'users' => ['banned_at', 'muted_until', 'warning_count'],
            'reports' => ['status', 'reportable_type', 'reportable_id'],
            'user_warnings' => ['severity', 'expires_at']
        ];

        foreach ($columnTests as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $this->results['columns']["{$table}.{$column}"] = '✓ EXISTS';
                    } else {
                        $this->results['columns']["{$table}.{$column}"] = '✗ MISSING';
                    }
                }
            }
        }

        echo "Database structure test completed.\n\n";
    }

    private function testModelRelationships()
    {
        echo "Testing model relationships...\n";

        try {
            // Create test data
            $this->createTestData();

            // Test ForumThread relationships
            $thread = ForumThread::with(['user', 'category', 'posts'])->first();
            if ($thread && $thread->user && $thread->category) {
                $this->results['relationships']['ForumThread.user'] = '✓ WORKING';
                $this->results['relationships']['ForumThread.category'] = '✓ WORKING';
            } else {
                $this->results['relationships']['ForumThread'] = '✗ FAILED';
            }

            // Test User forum relationships
            $user = User::with(['forumThreads', 'forumPosts', 'warnings'])->first();
            if ($user) {
                $this->results['relationships']['User.forumThreads'] = '✓ WORKING';
                $this->results['relationships']['User.forumPosts'] = '✓ WORKING';
                $this->results['relationships']['User.warnings'] = '✓ WORKING';
            }

            // Test Report relationships
            if (class_exists('App\\Models\\Report')) {
                $this->results['relationships']['Report.model'] = '✓ EXISTS';
            } else {
                $this->results['relationships']['Report.model'] = '✗ MISSING';
            }

            echo "Model relationships test completed.\n\n";

        } catch (\Exception $e) {
            $this->results['relationships']['error'] = '✗ ERROR: ' . $e->getMessage();
            echo "Error testing relationships: " . $e->getMessage() . "\n\n";
        }
    }

    private function testUserModerationMethods()
    {
        echo "Testing user moderation methods...\n";

        try {
            if (!$this->testUser) {
                $this->createTestData();
            }

            $user = $this->testUser;

            // Test ban functionality
            $user->ban('Test ban reason', now()->addDays(1));
            if ($user->isBanned()) {
                $this->results['moderation']['user.ban'] = '✓ WORKING';
            } else {
                $this->results['moderation']['user.ban'] = '✗ FAILED';
            }

            // Test unban functionality
            $user->unban();
            if (!$user->fresh()->isBanned()) {
                $this->results['moderation']['user.unban'] = '✓ WORKING';
            } else {
                $this->results['moderation']['user.unban'] = '✗ FAILED';
            }

            // Test mute functionality
            $user->mute(now()->addHours(1));
            if ($user->fresh()->isMuted()) {
                $this->results['moderation']['user.mute'] = '✓ WORKING';
            } else {
                $this->results['moderation']['user.mute'] = '✗ FAILED';
            }

            // Test unmute functionality
            $user->unmute();
            if (!$user->fresh()->isMuted()) {
                $this->results['moderation']['user.unmute'] = '✓ WORKING';
            } else {
                $this->results['moderation']['user.unmute'] = '✗ FAILED';
            }

            // Test warning functionality
            $warning = $user->warn($this->moderatorUser->id, 'Test warning', 'medium', now()->addDays(1));
            if ($warning instanceof UserWarning && $user->fresh()->hasActiveWarnings()) {
                $this->results['moderation']['user.warn'] = '✓ WORKING';
            } else {
                $this->results['moderation']['user.warn'] = '✗ FAILED';
            }

            // Test moderation status
            $status = $user->moderation_status;
            if (is_array($status) && isset($status['is_banned'], $status['is_muted'])) {
                $this->results['moderation']['user.status'] = '✓ WORKING';
            } else {
                $this->results['moderation']['user.status'] = '✗ FAILED';
            }

            echo "User moderation methods test completed.\n\n";

        } catch (\Exception $e) {
            $this->results['moderation']['error'] = '✗ ERROR: ' . $e->getMessage();
            echo "Error testing user moderation: " . $e->getMessage() . "\n\n";
        }
    }

    private function testReportSystem()
    {
        echo "Testing report system...\n";

        try {
            if (!class_exists('App\\Models\\Report')) {
                $this->results['reports']['model'] = '✗ MISSING';
                return;
            }

            // Test report creation
            $report = Report::create([
                'reporter_id' => $this->moderatorUser->id,
                'reportable_type' => 'App\\Models\\ForumThread',
                'reportable_id' => $this->testThread->id,
                'reason' => 'Test report',
                'description' => 'This is a test report',
                'status' => 'pending'
            ]);

            if ($report->exists) {
                $this->results['reports']['creation'] = '✓ WORKING';
            } else {
                $this->results['reports']['creation'] = '✗ FAILED';
            }

            // Test report relationships
            $reportWithRelations = Report::with(['reporter', 'reportable'])->first();
            if ($reportWithRelations && $reportWithRelations->reporter && $reportWithRelations->reportable) {
                $this->results['reports']['relationships'] = '✓ WORKING';
            } else {
                $this->results['reports']['relationships'] = '✗ FAILED';
            }

            // Test report resolution
            $report->markAsResolved($this->moderatorUser->id, 'dismissed', 'Not applicable');
            if ($report->fresh()->status === 'resolved') {
                $this->results['reports']['resolution'] = '✓ WORKING';
            } else {
                $this->results['reports']['resolution'] = '✗ FAILED';
            }

            echo "Report system test completed.\n\n";

        } catch (\Exception $e) {
            $this->results['reports']['error'] = '✗ ERROR: ' . $e->getMessage();
            echo "Error testing report system: " . $e->getMessage() . "\n\n";
        }
    }

    private function testWarningSystem()
    {
        echo "Testing warning system...\n";

        try {
            if (!class_exists('App\\Models\\UserWarning')) {
                $this->results['warnings']['model'] = '✗ MISSING';
                return;
            }

            // Test warning creation
            $warning = UserWarning::create([
                'user_id' => $this->testUser->id,
                'moderator_id' => $this->moderatorUser->id,
                'reason' => 'Test warning system',
                'severity' => 'medium',
                'expires_at' => now()->addDays(7)
            ]);

            if ($warning->exists) {
                $this->results['warnings']['creation'] = '✓ WORKING';
            } else {
                $this->results['warnings']['creation'] = '✗ FAILED';
            }

            // Test warning status methods
            if ($warning->isActive()) {
                $this->results['warnings']['status_check'] = '✓ WORKING';
            } else {
                $this->results['warnings']['status_check'] = '✗ FAILED';
            }

            // Test warning acknowledgment
            $warning->acknowledge();
            if ($warning->fresh()->acknowledged) {
                $this->results['warnings']['acknowledgment'] = '✓ WORKING';
            } else {
                $this->results['warnings']['acknowledgment'] = '✗ FAILED';
            }

            echo "Warning system test completed.\n\n";

        } catch (\Exception $e) {
            $this->results['warnings']['error'] = '✗ ERROR: ' . $e->getMessage();
            echo "Error testing warning system: " . $e->getMessage() . "\n\n";
        }
    }

    private function testBulkOperations()
    {
        echo "Testing bulk operations...\n";

        try {
            // Test thread bulk operations
            $threads = ForumThread::limit(3)->get();
            if ($threads->count() > 0) {
                // Test bulk lock
                $threads->each(function ($thread) {
                    $thread->update(['locked' => true]);
                });

                $lockedCount = ForumThread::whereIn('id', $threads->pluck('id'))->where('locked', true)->count();
                if ($lockedCount === $threads->count()) {
                    $this->results['bulk']['thread_lock'] = '✓ WORKING';
                } else {
                    $this->results['bulk']['thread_lock'] = '✗ FAILED';
                }

                // Test bulk unlock
                $threads->each(function ($thread) {
                    $thread->update(['locked' => false]);
                });

                $unlockedCount = ForumThread::whereIn('id', $threads->pluck('id'))->where('locked', false)->count();
                if ($unlockedCount === $threads->count()) {
                    $this->results['bulk']['thread_unlock'] = '✓ WORKING';
                } else {
                    $this->results['bulk']['thread_unlock'] = '✗ FAILED';
                }
            }

            echo "Bulk operations test completed.\n\n";

        } catch (\Exception $e) {
            $this->results['bulk']['error'] = '✗ ERROR: ' . $e->getMessage();
            echo "Error testing bulk operations: " . $e->getMessage() . "\n\n";
        }
    }

    private function createTestData()
    {
        if ($this->testUser) {
            return; // Already created
        }

        echo "Creating test data...\n";

        DB::beginTransaction();

        try {
            // Create test users
            $this->testUser = User::firstOrCreate(
                ['email' => 'test@forum-moderation.com'],
                [
                    'name' => 'Test User',
                    'password' => 'password',
                    'role' => 'user'
                ]
            );

            $this->moderatorUser = User::firstOrCreate(
                ['email' => 'moderator@forum-moderation.com'],
                [
                    'name' => 'Test Moderator',
                    'password' => 'password',
                    'role' => 'moderator'
                ]
            );

            // Create test category
            $this->testCategory = ForumCategory::firstOrCreate(
                ['name' => 'Test Moderation Category'],
                [
                    'slug' => 'test-moderation',
                    'description' => 'Category for testing moderation features',
                    'color' => '#3B82F6',
                    'is_active' => true,
                    'sort_order' => 999
                ]
            );

            // Create test thread
            $this->testThread = ForumThread::firstOrCreate(
                ['title' => 'Test Moderation Thread'],
                [
                    'content' => 'This is a test thread for moderation testing.',
                    'user_id' => $this->testUser->id,
                    'category_id' => $this->testCategory->id,
                    'views' => 0,
                    'replies' => 0
                ]
            );

            // Create test post
            $this->testPost = Post::firstOrCreate(
                [
                    'thread_id' => $this->testThread->id,
                    'user_id' => $this->testUser->id,
                    'content' => 'This is a test post for moderation testing.'
                ]
            );

            DB::commit();
            echo "Test data created successfully.\n";

        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception("Failed to create test data: " . $e->getMessage());
        }
    }

    private function generateReport()
    {
        echo "\n=== TEST RESULTS REPORT ===\n\n";

        $totalTests = 0;
        $passedTests = 0;

        foreach ($this->results as $category => $tests) {
            echo strtoupper($category) . ":\n";
            foreach ($tests as $test => $result) {
                echo "  {$test}: {$result}\n";
                $totalTests++;
                if (strpos($result, '✓') === 0) {
                    $passedTests++;
                }
            }
            echo "\n";
        }

        $failedTests = $totalTests - $passedTests;
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

        echo "=== SUMMARY ===\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Passed: {$passedTests}\n";
        echo "Failed: {$failedTests}\n";
        echo "Success Rate: {$successRate}%\n\n";

        if ($successRate >= 90) {
            echo "🎉 EXCELLENT: Forum moderation system is working well!\n";
        } elseif ($successRate >= 70) {
            echo "⚠️  WARNING: Some issues found, review failed tests.\n";
        } else {
            echo "❌ CRITICAL: Major issues found, immediate attention required.\n";
        }

        echo "\nNext steps:\n";
        echo "1. Run database migration: php artisan migrate\n";
        echo "2. Test API endpoints using Postman or similar tool\n";
        echo "3. Review any failed tests above\n";
        echo "4. Configure proper permissions and roles\n";
        echo "5. Set up rate limiting for sensitive endpoints\n\n";
    }

    public function cleanup()
    {
        echo "Cleaning up test data...\n";

        try {
            if ($this->testPost) $this->testPost->forceDelete();
            if ($this->testThread) $this->testThread->forceDelete();
            if ($this->testCategory && $this->testCategory->name === 'Test Moderation Category') {
                $this->testCategory->forceDelete();
            }
            
            // Don't delete users as they might be used elsewhere
            
            echo "Cleanup completed.\n";
        } catch (\Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the test
$test = new ForumModerationTest();
$test->runAllTests();

// Uncomment the next line if you want to clean up test data
// $test->cleanup();

echo "\nForum Moderation System test completed.\n";