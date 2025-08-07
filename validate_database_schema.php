<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Database Schema Validation Report\n";
echo "=================================\n\n";

$validationResults = [];

// 1. Validate News Table
echo "1. NEWS TABLE VALIDATION\n";
echo "--------------------------\n";
$newsExpectedColumns = [
    'id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'gallery', 'videos',
    'category', 'category_id', 'tags', 'author_id', 'status', 'published_at', 
    'featured', 'featured_at', 'breaking', 'sort_order', 'meta_data', 'score',
    'upvotes', 'downvotes', 'comments_count', 'views', 'region', 'created_at', 'updated_at'
];

$newsResult = validateTable('news', $newsExpectedColumns);
$validationResults['news'] = $newsResult;

// 2. Validate News Comments Table
echo "\n2. NEWS COMMENTS TABLE VALIDATION\n";
echo "-----------------------------------\n";
$commentsExpectedColumns = [
    'id', 'news_id', 'user_id', 'parent_id', 'content', 'status', 
    'is_edited', 'edited_at', 'upvotes', 'downvotes', 'score',
    'created_at', 'updated_at'
];

$commentsResult = validateTable('news_comments', $commentsExpectedColumns);
$validationResults['news_comments'] = $commentsResult;

// 3. Validate Mentions Table
echo "\n3. MENTIONS TABLE VALIDATION\n";
echo "-----------------------------\n";
$mentionsExpectedColumns = [
    'id', 'mentionable_type', 'mentionable_id', 'mentioned_type', 'mentioned_id',
    'context', 'mention_text', 'position_start', 'position_end', 'mentioned_by',
    'mentioned_at', 'is_active', 'metadata', 'created_at', 'updated_at'
];

$mentionsResult = validateTable('mentions', $mentionsExpectedColumns);
$validationResults['mentions'] = $mentionsResult;

// 4. Validate News Video Embeds Table
echo "\n4. NEWS VIDEO EMBEDS TABLE VALIDATION\n";
echo "--------------------------------------\n";
$videoEmbedsExpectedColumns = [
    'id', 'news_id', 'platform', 'video_id', 'embed_url', 'original_url',
    'title', 'thumbnail', 'duration', 'metadata', 'created_at', 'updated_at'
];

$videoEmbedsResult = validateTable('news_video_embeds', $videoEmbedsExpectedColumns);
$validationResults['news_video_embeds'] = $videoEmbedsResult;

// 5. Validate Reports Table
echo "\n5. REPORTS TABLE VALIDATION\n";
echo "----------------------------\n";
$reportsExpectedColumns = [
    'id', 'reportable_type', 'reportable_id', 'reporter_id', 'reason', 
    'status', 'moderator_id', 'moderator_notes', 'created_at', 'updated_at'
];

$reportsResult = validateTable('reports', $reportsExpectedColumns);
$validationResults['reports'] = $reportsResult;

// 6. Validate Moderation Logs Table
echo "\n6. MODERATION LOGS TABLE VALIDATION\n";
echo "------------------------------------\n";
$moderationLogsExpectedColumns = [
    'id', 'moderator_id', 'action', 'target_type', 'target_id', 'reason', 
    'metadata', 'created_at', 'updated_at'
];

$moderationLogsResult = validateTable('moderation_logs', $moderationLogsExpectedColumns);
$validationResults['moderation_logs'] = $moderationLogsResult;

// Summary
echo "\n\nVALIDATION SUMMARY\n";
echo "==================\n";
$totalTables = count($validationResults);
$passedTables = 0;
$totalIssues = 0;

foreach ($validationResults as $tableName => $result) {
    $status = $result['missing_columns'] == 0 && $result['exists'] ? 'PASS' : 'ISSUES';
    if ($status === 'PASS') {
        $passedTables++;
    }
    $totalIssues += $result['missing_columns'];
    
    echo "- {$tableName}: {$status}";
    if ($result['missing_columns'] > 0) {
        echo " ({$result['missing_columns']} missing columns)";
    }
    echo "\n";
}

echo "\nOverall Status: {$passedTables}/{$totalTables} tables passed validation\n";
echo "Total Issues: {$totalIssues} missing columns\n";

if ($totalIssues == 0) {
    echo "\n✅ ALL DATABASE SCHEMA VALIDATIONS PASSED!\n";
    echo "The database is ready for production use.\n";
} else {
    echo "\n❌ DATABASE SCHEMA ISSUES FOUND!\n";
    echo "Please review the missing columns above.\n";
}

// Test a sample INSERT to news table with videos column
echo "\n\nTEST: INSERT NEWS WITH VIDEOS\n";
echo "==============================\n";
try {
    $testNewsId = DB::table('news')->insertGetId([
        'title' => 'Test News with Videos - ' . date('Y-m-d H:i:s'),
        'slug' => 'test-news-videos-' . time(),
        'excerpt' => 'Test news article with video embeds',
        'content' => 'This is test content with embedded videos.',
        'author_id' => 1, // Assuming admin user exists
        'status' => 'draft',
        'videos' => json_encode([
            [
                'platform' => 'youtube',
                'video_id' => 'dQw4w9WgXcQ',
                'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'original_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
            ]
        ]),
        'breaking' => false,
        'featured' => false,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "✅ Successfully inserted test news article with ID: {$testNewsId}\n";
    echo "✅ Videos column is working properly!\n";
    
    // Clean up test data
    DB::table('news')->where('id', $testNewsId)->delete();
    echo "✅ Test data cleaned up.\n";
    
} catch (\Exception $e) {
    echo "❌ Failed to insert test news: " . $e->getMessage() . "\n";
}

echo "\nValidation completed!\n";

// Helper function
function validateTable($tableName, $expectedColumns) {
    $result = [
        'exists' => false,
        'missing_columns' => 0,
        'extra_columns' => 0,
        'details' => []
    ];
    
    if (!Schema::hasTable($tableName)) {
        echo "❌ Table '{$tableName}' does not exist!\n";
        $result['missing_columns'] = count($expectedColumns);
        return $result;
    }
    
    $result['exists'] = true;
    echo "✅ Table '{$tableName}' exists\n";
    
    // Get actual columns
    $actualColumns = collect(DB::select("SHOW COLUMNS FROM {$tableName}"))->pluck('Field')->toArray();
    
    // Check for missing columns
    $missingColumns = array_diff($expectedColumns, $actualColumns);
    if (!empty($missingColumns)) {
        echo "❌ Missing columns in '{$tableName}': " . implode(', ', $missingColumns) . "\n";
        $result['missing_columns'] = count($missingColumns);
        $result['details']['missing'] = $missingColumns;
    } else {
        echo "✅ All expected columns present in '{$tableName}'\n";
    }
    
    // Check for extra columns (not necessarily a problem)
    $extraColumns = array_diff($actualColumns, $expectedColumns);
    if (!empty($extraColumns)) {
        echo "ℹ️  Extra columns in '{$tableName}': " . implode(', ', $extraColumns) . "\n";
        $result['extra_columns'] = count($extraColumns);
        $result['details']['extra'] = $extraColumns;
    }
    
    return $result;
}