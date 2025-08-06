<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== MARVEL RIVALS TOURNAMENT DATA VERIFICATION ===\n\n";

// 1. Check for duplicates
echo "1. CHECKING FOR DUPLICATES\n";
$dupTeams = DB::table('teams')
    ->select('name', DB::raw('COUNT(*) as count'))
    ->groupBy('name')
    ->having('count', '>', 1)
    ->get();

$dupPlayers = DB::table('players')
    ->select('name', 'team_id', DB::raw('COUNT(*) as count'))
    ->groupBy('name', 'team_id')
    ->having('count', '>', 1)
    ->get();

echo "   Duplicate teams: " . ($dupTeams->count() > 0 ? $dupTeams->count() . " FOUND!" : "None ✓") . "\n";
echo "   Duplicate players: " . ($dupPlayers->count() > 0 ? $dupPlayers->count() . " FOUND!" : "None ✓") . "\n\n";

// 2. Virtus.pro check
echo "2. VIRTUS.PRO CHECK\n";
$vp = Team::where('name', 'Virtus.pro')->first();
if ($vp) {
    echo "   ✓ Found Virtus.pro\n";
    echo "   - Players: " . $vp->players()->count() . "\n";
    echo "   - Region: " . $vp->region . "\n";
    echo "   - Social: ";
    $socials = [];
    if ($vp->twitter) $socials[] = 'Twitter';
    if ($vp->instagram) $socials[] = 'Instagram';
    if ($vp->youtube) $socials[] = 'YouTube';
    if ($vp->facebook) $socials[] = 'Facebook';
    echo implode(', ', $socials) . "\n";
} else {
    echo "   ✗ Virtus.pro NOT FOUND!\n";
}
echo "\n";

// 3. Data completeness
echo "3. DATA COMPLETENESS\n";
$totalTeams = Team::count();
$totalPlayers = Player::count();

echo "   Teams: $totalTeams\n";
echo "   - With social media: " . Team::where(function($q) {
    $q->whereNotNull('twitter')
      ->orWhereNotNull('instagram')
      ->orWhereNotNull('youtube')
      ->orWhereNotNull('discord');
})->count() . " (" . round(Team::where(function($q) {
    $q->whereNotNull('twitter')
      ->orWhereNotNull('instagram')
      ->orWhereNotNull('youtube')
      ->orWhereNotNull('discord');
})->count() / $totalTeams * 100) . "%)\n";
echo "   - With earnings > 0: " . Team::where('earnings', '>', 0)->count() . "\n";
echo "   - With proper region: " . Team::where('region', '!=', 'INT')->count() . "\n";

echo "\n   Players: $totalPlayers\n";
echo "   - With usernames: " . Player::whereNotNull('username')->where('username', '!=', '')->count() . " (100% required)\n";
echo "   - With real names: " . Player::whereNotNull('real_name')->where('real_name', '!=', '')->count() . "\n";
echo "   - With country flags: " . Player::whereNotNull('country_flag')->where('country_flag', '!=', '')->count() . "\n";
echo "   - With earnings > 0: " . Player::where('earnings', '>', 0)->count() . "\n";
echo "   - With ELO rating: " . Player::where('rating', '>', 0)->count() . " (100% required)\n";
echo "   - With social media: " . Player::where(function($q) {
    $q->whereNotNull('twitter')
      ->orWhereNotNull('instagram')
      ->orWhereNotNull('twitch')
      ->orWhereNotNull('youtube');
})->count() . "\n";
echo "   - With age data: " . Player::whereNotNull('age')->count() . "\n\n";

// 4. Regional distribution
echo "4. REGIONAL DISTRIBUTION\n";
Team::selectRaw('region, count(*) as count')
    ->groupBy('region')
    ->orderBy('count', 'desc')
    ->get()
    ->each(function($r) {
        printf("   %-10s: %2d teams\n", $r->region, $r->count);
    });

// Check China teams specifically
$chinaTeams = Team::where('region', 'CN')->count();
$asiaTeams = Team::whereIn('region', ['CN', 'KR', 'JP', 'SEA', 'ASIA'])->count();
$oceTeams = Team::where('region', 'OCE')->count();

echo "\n   China region: $chinaTeams teams " . ($chinaTeams > 0 ? "✓" : "✗ MISSING!") . "\n";
echo "   Asia-Pacific total: $asiaTeams teams\n";
echo "   Oceania: $oceTeams teams " . ($oceTeams > 0 ? "✓" : "✗ MISSING!") . "\n\n";

// 5. Sample teams from each region
echo "5. SAMPLE TEAMS BY REGION\n";
$regions = ['NA', 'EU', 'CN', 'KR', 'OCE', 'SA', 'MENA', 'SEA'];
foreach ($regions as $region) {
    $teams = Team::where('region', $region)->take(3)->pluck('name');
    if ($teams->count() > 0) {
        echo "   $region: " . $teams->implode(', ') . "\n";
    }
}

// 6. Top rated teams
echo "\n6. TOP RATED TEAMS (FOR RANKINGS)\n";
Team::orderBy('rating', 'desc')
    ->take(10)
    ->get(['name', 'region', 'rating', 'earnings'])
    ->each(function($team, $index) {
        printf("   %2d. %-25s (%s) - Rating: %d, Earnings: $%s\n", 
            $index + 1, 
            $team->name, 
            $team->region, 
            $team->rating,
            number_format($team->earnings)
        );
    });

// 7. Data quality issues
echo "\n7. DATA QUALITY ISSUES\n";
$missingUsernames = Player::whereNull('username')->orWhere('username', '')->count();
$missingRatings = Player::where('rating', 0)->orWhereNull('rating')->count();
$intRegionTeams = Team::where('region', 'INT')->count();

echo "   Players without usernames: " . ($missingUsernames > 0 ? "$missingUsernames ✗" : "None ✓") . "\n";
echo "   Players without ratings: " . ($missingRatings > 0 ? "$missingRatings ✗" : "None ✓") . "\n";
echo "   Teams with INT region: " . ($intRegionTeams > 0 ? "$intRegionTeams (needs fixing)" : "None ✓") . "\n";

// 8. Final tournament readiness
echo "\n8. TOURNAMENT READINESS CHECK\n";
$ready = true;

if ($dupTeams->count() > 0 || $dupPlayers->count() > 0) {
    echo "   ✗ Duplicates found - needs fixing\n";
    $ready = false;
}

if ($missingUsernames > 0) {
    echo "   ✗ Players missing usernames - needs fixing\n";
    $ready = false;
}

if ($chinaTeams == 0) {
    echo "   ✗ No China teams - needs fixing\n";
    $ready = false;
}

if (!$vp) {
    echo "   ✗ Virtus.pro missing - needs fixing\n";
    $ready = false;
}

if ($ready) {
    echo "   ✓ ALL CHECKS PASSED - READY FOR TOURNAMENT!\n";
}

echo "\n=== END OF VERIFICATION ===\n";