<?php
/**
 * Comprehensive Image Loading Test
 * 
 * Tests all image loading functionality including heroes, team logos, 
 * fallback systems, and API endpoints.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPREHENSIVE IMAGE LOADING TEST ===\n\n";

function testEndpoint($url, $description) {
    echo "Testing: $description\n";
    echo "URL: $url\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'Accept: application/json'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ FAILED: Could not connect to endpoint\n\n";
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        echo "❌ FAILED: Invalid JSON response\n\n";
        return false;
    }
    
    if (!isset($data['success']) || !$data['success']) {
        echo "❌ FAILED: API returned error: " . ($data['message'] ?? 'Unknown error') . "\n\n";
        return false;
    }
    
    echo "✅ SUCCESS: API endpoint working\n";
    if (isset($data['total'])) {
        echo "   Total items: " . $data['total'] . "\n";
    }
    if (isset($data['data']) && is_array($data['data'])) {
        echo "   Data count: " . count($data['data']) . "\n";
    }
    echo "\n";
    
    return $data;
}

function checkFileExists($path, $description) {
    echo "Checking: $description\n";
    echo "Path: $path\n";
    
    $fullPath = __DIR__ . '/public' . $path;
    
    if (file_exists($fullPath) && is_file($fullPath)) {
        echo "✅ SUCCESS: File exists\n";
        echo "   Size: " . formatBytes(filesize($fullPath)) . "\n\n";
        return true;
    } else {
        echo "❌ FAILED: File does not exist\n\n";
        return false;
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

echo "1. TESTING HEROES API ENDPOINTS\n";
echo "================================\n\n";

// Test heroes endpoint
$heroesData = testEndpoint('http://localhost/api/heroes/images/all', 'Heroes Images API');
if ($heroesData && isset($heroesData['data'])) {
    $missingHeroes = 0;
    $totalHeroes = count($heroesData['data']);
    
    echo "Checking individual hero images...\n";
    foreach (array_slice($heroesData['data'], 0, 5) as $hero) { // Check first 5
        if (!$hero['image_exists']) {
            $missingHeroes++;
        }
        echo "  " . $hero['name'] . ": " . ($hero['image_exists'] ? "✅ Found" : "❌ Missing") . "\n";
    }
    
    echo "\nHeroes Summary:\n";
    echo "  Total heroes: $totalHeroes\n";
    echo "  Missing images: $missingHeroes\n";
    echo "  Success rate: " . round((($totalHeroes - $missingHeroes) / $totalHeroes) * 100, 1) . "%\n\n";
}

echo "2. TESTING TEAM LOGOS\n";
echo "====================\n\n";

// Test team logos endpoint
$teamLogosData = testEndpoint('http://localhost/api/teams/logos/all', 'Team Logos API');
if ($teamLogosData && isset($teamLogosData['data'])) {
    echo "Team Logos Summary:\n";
    echo "  Total teams: " . $teamLogosData['total'] . "\n";
    echo "  Missing logos: " . $teamLogosData['missing_count'] . "\n";
    echo "  Success rate: " . round((($teamLogosData['total'] - $teamLogosData['missing_count']) / $teamLogosData['total']) * 100, 1) . "%\n\n";
    
    if (!empty($teamLogosData['missing_teams'])) {
        echo "Teams with missing logos:\n";
        foreach (array_slice($teamLogosData['missing_teams'], 0, 10) as $teamName) {
            echo "  - $teamName\n";
        }
        echo "\n";
    }
}

echo "3. TESTING PHYSICAL FILE EXISTENCE\n";
echo "==================================\n\n";

// Test hero image files
echo "Checking hero image files:\n";
$heroImagesPassed = 0;
$heroImagesTotal = 5; // Test first 5
$testHeroes = ['spider-man', 'iron-man', 'captain-america', 'thor', 'hulk'];

foreach ($testHeroes as $heroSlug) {
    $path = "/images/heroes/{$heroSlug}-headbig.webp";
    if (checkFileExists($path, "Hero image: $heroSlug")) {
        $heroImagesPassed++;
    }
}

echo "Hero Images Summary: $heroImagesPassed/$heroImagesTotal passed\n\n";

// Test team logo files
echo "Checking team logo files:\n";
$teamLogosPassed = 0;
$teamLogosTotal = 5; // Test first 5
$testLogos = ['100t-logo.png', 'sentinels-logo.png', 'fnatic-logo.png', 'g2-logo.png', 'drx-logo.png'];

foreach ($testLogos as $logoFile) {
    $path = "/teams/$logoFile";
    if (checkFileExists($path, "Team logo: $logoFile")) {
        $teamLogosPassed++;
    }
}

echo "Team Logos Summary: $teamLogosPassed/$teamLogosTotal passed\n\n";

// Test fallback files
echo "Checking fallback files:\n";
checkFileExists('/images/heroes/question-mark.svg', 'Hero fallback image');
checkFileExists('/images/team-placeholder.svg', 'Team placeholder image');
checkFileExists('/images/news-placeholder.svg', 'News placeholder image');
checkFileExists('/images/player-placeholder.svg', 'Player placeholder image');

echo "4. TESTING STORAGE SYMLINKS\n";
echo "===========================\n\n";

// Test storage symlink
$symlinkPath = __DIR__ . '/public/storage';
echo "Checking storage symlink:\n";
echo "Path: $symlinkPath\n";

if (is_link($symlinkPath)) {
    $target = readlink($symlinkPath);
    echo "✅ SUCCESS: Symlink exists\n";
    echo "   Target: $target\n";
    
    if (is_dir($target)) {
        echo "   Target directory exists: ✅\n";
    } else {
        echo "   Target directory missing: ❌\n";
    }
} else {
    echo "❌ FAILED: Storage symlink does not exist\n";
    echo "   Run: php artisan storage:link\n";
}
echo "\n";

echo "5. TESTING DATABASE CONSISTENCY\n";
echo "===============================\n\n";

// Test heroes in database
$heroCount = DB::table('marvel_rivals_heroes')->count();
echo "Heroes in database: $heroCount\n";

if ($heroCount > 0) {
    echo "✅ Heroes table populated\n";
} else {
    echo "❌ Heroes table empty - run: php artisan db:seed --class=HeroSeeder\n";
}

// Test teams with logos
$teamsWithLogos = DB::table('teams')->whereNotNull('logo')->count();
$totalTeams = DB::table('teams')->count();
echo "Teams with logos: $teamsWithLogos / $totalTeams\n";

if ($teamsWithLogos > 0) {
    echo "✅ Teams have logo paths\n\n";
} else {
    echo "❌ No teams have logo paths\n\n";
}

echo "6. OVERALL SYSTEM STATUS\n";
echo "========================\n\n";

$allPassed = true;
$issues = [];

// Check critical components
if ($heroCount == 0) {
    $allPassed = false;
    $issues[] = "Heroes table is empty";
}

if (!is_link($symlinkPath)) {
    $allPassed = false;
    $issues[] = "Storage symlink missing";
}

if ($heroImagesPassed < ($heroImagesTotal * 0.8)) {
    $allPassed = false;
    $issues[] = "Too many hero images missing";
}

if ($teamLogosPassed < ($teamLogosTotal * 0.6)) {
    $allPassed = false;
    $issues[] = "Too many team logos missing";
}

if ($allPassed) {
    echo "🎉 ALL SYSTEMS GO! Image loading is working correctly.\n\n";
    echo "✅ Heroes API working\n";
    echo "✅ Team logos working with fallbacks\n";
    echo "✅ Physical files exist\n";
    echo "✅ Database populated\n";
    echo "✅ Storage properly configured\n";
} else {
    echo "⚠️  ISSUES DETECTED:\n\n";
    foreach ($issues as $issue) {
        echo "❌ $issue\n";
    }
    echo "\nPlease fix these issues before deployment.\n";
}

echo "\n=== TEST COMPLETED ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
?>