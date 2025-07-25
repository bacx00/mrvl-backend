<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

echo "=== MARVEL RIVALS BACKEND COMPREHENSIVE CHECK ===\n\n";

// 1. Database Connection
echo "1. DATABASE CONNECTION:\n";
try {
    DB::connection()->getPdo();
    echo "✓ Database connected successfully\n";
    $tables = DB::select('SHOW TABLES');
    echo "✓ Total tables: " . count($tables) . "\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

// 2. Critical Tables Check
echo "\n2. CRITICAL TABLES CHECK:\n";
$criticalTables = [
    'users', 'teams', 'players', 'matches', 'events', 
    'news', 'forum_threads', 'match_maps', 'event_teams'
];
foreach ($criticalTables as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "✓ Table '$table' exists (Records: $count)\n";
    } else {
        echo "✗ Table '$table' is MISSING!\n";
    }
}

// 3. Missing Columns Check
echo "\n3. MISSING COLUMNS CHECK:\n";
$columnChecks = [
    'matches' => ['ended_at', 'started_at', 'bracket_round', 'bracket_position'],
    'events' => ['tier', 'format', 'max_teams'],
    'teams' => ['status', 'coach_picture'],
    'users' => ['use_hero_as_avatar', 'hero_flair'],
    'news' => ['score', 'featured_image']
];

foreach ($columnChecks as $table => $columns) {
    if (Schema::hasTable($table)) {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                echo "✓ $table.$column exists\n";
            } else {
                echo "✗ $table.$column is MISSING!\n";
            }
        }
    }
}

// 4. Environment Configuration
echo "\n4. ENVIRONMENT CONFIGURATION:\n";
$envChecks = [
    'APP_ENV' => config('app.env'),
    'APP_DEBUG' => config('app.debug') ? 'true' : 'false',
    'APP_URL' => config('app.url'),
    'DB_CONNECTION' => config('database.default'),
    'CACHE_DRIVER' => config('cache.default'),
    'QUEUE_CONNECTION' => config('queue.default'),
    'SESSION_DRIVER' => config('session.driver'),
    'BROADCAST_DRIVER' => config('broadcasting.default')
];

foreach ($envChecks as $key => $value) {
    echo "$key: $value\n";
}

// 5. Storage Permissions
echo "\n5. STORAGE PERMISSIONS:\n";
$storageDirs = [
    'storage/app/public',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/logs',
    'public/avatars',
    'public/teams',
    'public/events',
    'public/news'
];

foreach ($storageDirs as $dir) {
    $path = base_path($dir);
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? '✓ Writable' : '✗ Not writable';
        echo "$dir: $perms $writable\n";
    } else {
        echo "$dir: ✗ Directory does not exist\n";
    }
}

// 6. Cache Test
echo "\n6. CACHE TEST:\n";
try {
    Cache::put('test_key', 'test_value', 60);
    $value = Cache::get('test_key');
    if ($value === 'test_value') {
        echo "✓ Cache is working\n";
        Cache::forget('test_key');
    } else {
        echo "✗ Cache write/read failed\n";
    }
} catch (\Exception $e) {
    echo "✗ Cache error: " . $e->getMessage() . "\n";
}

// 7. Queue Jobs Check
echo "\n7. QUEUE JOBS CHECK:\n";
try {
    $pendingJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    echo "Pending jobs: $pendingJobs\n";
    echo "Failed jobs: $failedJobs\n";
} catch (\Exception $e) {
    echo "✗ Could not check queue status: " . $e->getMessage() . "\n";
}

// 8. OAuth Keys
echo "\n8. OAUTH KEYS:\n";
$privateKey = storage_path('oauth-private.key');
$publicKey = storage_path('oauth-public.key');

if (file_exists($privateKey)) {
    $perms = substr(sprintf('%o', fileperms($privateKey)), -4);
    echo "✓ Private key exists (permissions: $perms)\n";
} else {
    echo "✗ Private key missing\n";
}

if (file_exists($publicKey)) {
    $perms = substr(sprintf('%o', fileperms($publicKey)), -4);
    echo "✓ Public key exists (permissions: $perms)\n";
} else {
    echo "✗ Public key missing\n";
}

// 9. Model Relationships Test
echo "\n9. MODEL RELATIONSHIPS TEST:\n";
try {
    $user = \App\Models\User::first();
    if ($user) {
        echo "✓ User model loads\n";
        
        // Test eager loading
        $team = \App\Models\Team::with('players')->first();
        if ($team) {
            echo "✓ Team->players relationship works\n";
        }
        
        $event = \App\Models\Event::with('teams')->first();
        if ($event) {
            echo "✓ Event->teams relationship works\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ Model relationship error: " . $e->getMessage() . "\n";
}

// 10. Recent Errors Summary
echo "\n10. RECENT ERRORS SUMMARY:\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentErrors = [];
    $errorPattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|EMERGENCY|ALERT): (.+)/';
    
    foreach (array_reverse($lines) as $line) {
        if (preg_match($errorPattern, $line, $matches)) {
            $timestamp = $matches[1];
            $level = $matches[2];
            $message = substr($matches[3], 0, 100) . '...';
            
            // Only show errors from last 24 hours
            if (strtotime($timestamp) > strtotime('-24 hours')) {
                $recentErrors[] = "[$timestamp] $level: $message";
                if (count($recentErrors) >= 5) break;
            }
        }
    }
    
    if (empty($recentErrors)) {
        echo "✓ No errors in the last 24 hours\n";
    } else {
        echo "Recent errors (last 24h):\n";
        foreach ($recentErrors as $error) {
            echo "  - $error\n";
        }
    }
}

// 11. Critical Issues Summary
echo "\n=== CRITICAL ISSUES THAT NEED IMMEDIATE ATTENTION ===\n";
$criticalIssues = [];

// Check for missing columns from error log
if (!Schema::hasColumn('matches', 'ended_at')) {
    $criticalIssues[] = "Missing 'ended_at' column in matches table - causing match completion errors";
}

// Check storage permissions
if (!is_writable(storage_path('app/public'))) {
    $criticalIssues[] = "Storage directory not writable - file uploads will fail";
}

// Check OAuth keys
if (!file_exists($privateKey) || !file_exists($publicKey)) {
    $criticalIssues[] = "OAuth keys missing - authentication will fail";
}

// Check database connection
try {
    DB::connection()->getPdo();
} catch (\Exception $e) {
    $criticalIssues[] = "Database connection failed - entire application will not work";
}

if (empty($criticalIssues)) {
    echo "✓ No critical issues found - system appears ready for production!\n";
} else {
    echo "✗ CRITICAL ISSUES FOUND:\n";
    foreach ($criticalIssues as $i => $issue) {
        echo ($i + 1) . ". $issue\n";
    }
}

echo "\n=== CHECK COMPLETE ===\n";