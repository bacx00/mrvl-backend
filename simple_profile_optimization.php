<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Player;
use App\Models\Team;

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔧 Simple Database Profile Optimization...\n\n";

// Step 1: Fix missing flags
echo "1️⃣ Fixing Missing Flags...\n";

$flagMappings = [
    // North America
    'United States' => '🇺🇸', 'USA' => '🇺🇸', 'US' => '🇺🇸',
    'Canada' => '🇨🇦', 'CA' => '🇨🇦',
    'Mexico' => '🇲🇽', 'MX' => '🇲🇽',
    
    // Europe
    'Sweden' => '🇸🇪', 'SE' => '🇸🇪',
    'Denmark' => '🇩🇰', 'DK' => '🇩🇰',
    'Finland' => '🇫🇮', 'FI' => '🇫🇮',
    'Norway' => '🇳🇴', 'NO' => '🇳🇴',
    'Germany' => '🇩🇪', 'DE' => '🇩🇪',
    'France' => '🇫🇷', 'FR' => '🇫🇷',
    'United Kingdom' => '🇬🇧', 'UK' => '🇬🇧', 'GB' => '🇬🇧',
    'Spain' => '🇪🇸', 'ES' => '🇪🇸',
    'Italy' => '🇮🇹', 'IT' => '🇮🇹',
    'Netherlands' => '🇳🇱', 'NL' => '🇳🇱',
    'Belgium' => '🇧🇪', 'BE' => '🇧🇪',
    'Poland' => '🇵🇱', 'PL' => '🇵🇱',
    'Russia' => '🇷🇺', 'RU' => '🇷🇺',
    'Turkey' => '🇹🇷', 'TR' => '🇹🇷',
    
    // Asia Pacific
    'South Korea' => '🇰🇷', 'Korea' => '🇰🇷', 'KR' => '🇰🇷',
    'Japan' => '🇯🇵', 'JP' => '🇯🇵',
    'China' => '🇨🇳', 'CN' => '🇨🇳',
    'Thailand' => '🇹🇭', 'TH' => '🇹🇭',
    'Philippines' => '🇵🇭', 'PH' => '🇵🇭',
    'Singapore' => '🇸🇬', 'SG' => '🇸🇬',
    'Malaysia' => '🇲🇾', 'MY' => '🇲🇾',
    'Indonesia' => '🇮🇩', 'ID' => '🇮🇩',
    'Vietnam' => '🇻🇳', 'VN' => '🇻🇳',
    'Hong Kong' => '🇭🇰', 'HK' => '🇭🇰',
    'Taiwan' => '🇹🇼', 'TW' => '🇹🇼',
    'Australia' => '🇦🇺', 'AU' => '🇦🇺',
    'New Zealand' => '🇳🇿', 'NZ' => '🇳🇿',
    
    // South America
    'Brazil' => '🇧🇷', 'BR' => '🇧🇷',
    'Argentina' => '🇦🇷', 'AR' => '🇦🇷',
    'Chile' => '🇨🇱', 'CL' => '🇨🇱',
    'Colombia' => '🇨🇴', 'CO' => '🇨🇴',
    'Peru' => '🇵🇪', 'PE' => '🇵🇪',
];

$regionFlags = [
    'North America' => '🇺🇸', 'NA' => '🇺🇸',
    'Europe' => '🇪🇺', 'EU' => '🇪🇺', 'EMEA' => '🇪🇺',
    'Asia Pacific' => '🇰🇷', 'APAC' => '🇰🇷', 'Asia' => '🇰🇷',
    'Pacific' => '🇦🇺',
    'South America' => '🇧🇷', 'SA' => '🇧🇷',
    'China' => '🇨🇳', 'CN' => '🇨🇳'
];

// Fix player flags
$playersNeedingFlags = DB::table('players')->whereNull('flag')->orWhere('flag', '')->get();
$playersFixed = 0;

foreach ($playersNeedingFlags as $player) {
    $flag = $flagMappings[$player->country] ?? $regionFlags[$player->region] ?? '🏳️';
    DB::table('players')->where('id', $player->id)->update(['flag' => $flag]);
    $playersFixed++;
}

echo "   ✅ Fixed flags for {$playersFixed} players\n";

// Fix team flags
$teamsNeedingFlags = DB::table('teams')->whereNull('flag')->orWhere('flag', '')->get();
$teamsFixed = 0;

foreach ($teamsNeedingFlags as $team) {
    $flag = $flagMappings[$team->country] ?? $regionFlags[$team->region] ?? '🏳️';
    DB::table('teams')->where('id', $team->id)->update(['flag' => $flag]);
    $teamsFixed++;
}

echo "   ✅ Fixed flags for {$teamsFixed} teams\n";

// Step 2: Fix missing team logos
echo "\n2️⃣ Fixing Missing Team Logos...\n";

$teamsNeedingLogos = DB::table('teams')->whereNull('logo')->orWhere('logo', '')->get();
$logosFixed = 0;

foreach ($teamsNeedingLogos as $team) {
    $slug = strtolower(str_replace([' ', '_'], '-', $team->name));
    $logo = "/images/teams/{$slug}-logo.png";
    DB::table('teams')->where('id', $team->id)->update(['logo' => $logo]);
    $logosFixed++;
}

echo "   ✅ Fixed logos for {$logosFixed} teams\n";

// Step 3: Fix missing team countries
echo "\n3️⃣ Fixing Missing Team Countries...\n";

$regionCountries = [
    'North America' => 'United States', 'NA' => 'United States',
    'Europe' => 'Germany', 'EU' => 'Germany', 'EMEA' => 'Germany',
    'Asia Pacific' => 'South Korea', 'APAC' => 'South Korea', 'Asia' => 'South Korea',
    'Pacific' => 'Australia',
    'South America' => 'Brazil', 'SA' => 'Brazil',
    'China' => 'China', 'CN' => 'China'
];

$teamsNeedingCountries = DB::table('teams')->whereNull('country')->get();
$countriesFixed = 0;

foreach ($teamsNeedingCountries as $team) {
    $country = $regionCountries[$team->region] ?? 'Unknown';
    DB::table('teams')->where('id', $team->id)->update(['country' => $country]);
    $countriesFixed++;
}

echo "   ✅ Fixed countries for {$countriesFixed} teams\n";

// Step 4: Add performance indexes
echo "\n4️⃣ Adding Performance Indexes...\n";

$indexes = [
    // Player profile optimization indexes
    'CREATE INDEX IF NOT EXISTS idx_players_profile_fast ON players (team_id, role, rating DESC, id)',
    'CREATE INDEX IF NOT EXISTS idx_players_search_fast ON players (name, username)',
    'CREATE INDEX IF NOT EXISTS idx_players_country_region ON players (country, region)',
    
    // Team profile optimization indexes  
    'CREATE INDEX IF NOT EXISTS idx_teams_profile_fast ON teams (region, rating DESC, wins DESC, id)',
    'CREATE INDEX IF NOT EXISTS idx_teams_rankings_fast ON teams (region, elo_rating DESC, wins DESC)',
    'CREATE INDEX IF NOT EXISTS idx_teams_country_region ON teams (country, region)',
    
    // Profile performance indexes
    'CREATE INDEX IF NOT EXISTS idx_player_team_history_fast ON player_team_history (player_id, change_date DESC)',
];

$indexesAdded = 0;
foreach ($indexes as $sql) {
    try {
        DB::statement($sql);
        $indexesAdded++;
        echo "   📈 Added index: " . substr($sql, strpos($sql, 'idx_')) . "\n";
    } catch (Exception $e) {
        echo "   ⚠️ Failed to add index: " . $e->getMessage() . "\n";
    }
}

echo "   ✅ Added {$indexesAdded} performance indexes\n";

// Step 5: Generate final statistics
echo "\n5️⃣ Final Statistics...\n";

$stats = [
    'total_players' => DB::table('players')->count(),
    'total_teams' => DB::table('teams')->count(),
    'players_with_flags' => DB::table('players')->whereNotNull('flag')->where('flag', '!=', '')->count(),
    'teams_with_flags' => DB::table('teams')->whereNotNull('flag')->where('flag', '!=', '')->count(),
    'teams_with_logos' => DB::table('teams')->whereNotNull('logo')->where('logo', '!=', '')->count(),
    'teams_with_countries' => DB::table('teams')->whereNotNull('country')->count(),
];

echo "   📊 Total Players: {$stats['total_players']}\n";
echo "   📊 Total Teams: {$stats['total_teams']}\n";
echo "   🏳️ Players with Flags: {$stats['players_with_flags']}/{$stats['total_players']}\n";
echo "   🏳️ Teams with Flags: {$stats['teams_with_flags']}/{$stats['total_teams']}\n";
echo "   🖼️ Teams with Logos: {$stats['teams_with_logos']}/{$stats['total_teams']}\n";
echo "   🌍 Teams with Countries: {$stats['teams_with_countries']}/{$stats['total_teams']}\n";

// Save optimization report
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'players_flags_fixed' => $playersFixed,
    'teams_flags_fixed' => $teamsFixed,
    'team_logos_fixed' => $logosFixed,
    'team_countries_fixed' => $countriesFixed,
    'indexes_added' => $indexesAdded,
    'final_statistics' => $stats
];

file_put_contents('profile_optimization_report.json', json_encode($report, JSON_PRETTY_PRINT));

echo "\n✅ Database Profile Optimization Complete!\n";
echo "📝 Report saved to profile_optimization_report.json\n";