<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== FIXING ALL REGIONAL FLAGS ===\n\n";

// Define proper country assignments for teams by region
$regionDefaults = [
    'EU' => [
        'Virtus.pro' => ['country' => 'Russia', 'flag' => '🇷🇺'],
        'Fnatic' => ['country' => 'United Kingdom', 'flag' => '🇬🇧'],
        'OG' => ['country' => 'Denmark', 'flag' => '🇩🇰'],
        'Astronic Esports' => ['country' => 'Turkey', 'flag' => '🇹🇷'],
        'Ex Oblivione' => ['country' => 'Germany', 'flag' => '🇩🇪'],
        'Brr Brr Patapim' => ['country' => 'France', 'flag' => '🇫🇷'],
        'Zero Tenacity' => ['country' => 'United Kingdom', 'flag' => '🇬🇧'],
        'ZERO.PERCENT' => ['country' => 'Germany', 'flag' => '🇩🇪'],
        'Twisted Minds' => ['country' => 'Poland', 'flag' => '🇵🇱'],
        'TEAM1' => ['country' => 'Sweden', 'flag' => '🇸🇪'],
        'Team Peps' => ['country' => 'France', 'flag' => '🇫🇷'],
        'Rad EU' => ['country' => 'Germany', 'flag' => '🇩🇪'],
        'OG Seed' => ['country' => 'Denmark', 'flag' => '🇩🇰'],
        'Luminosity Gaming EU' => ['country' => 'United Kingdom', 'flag' => '🇬🇧'],
        'Project EVERSIO' => ['country' => 'Malta', 'flag' => '🇲🇹'],
        // Default for others
        'default' => ['country' => 'Germany', 'flag' => '🇩🇪']
    ],
    'CN' => [
        'EHOME' => ['country' => 'China', 'flag' => '🇨🇳'],
        'LGD Gaming' => ['country' => 'China', 'flag' => '🇨🇳'],
        'Nova Esports' => ['country' => 'China', 'flag' => '🇨🇳'],
        'default' => ['country' => 'China', 'flag' => '🇨🇳']
    ],
    'KR' => [
        'default' => ['country' => 'South Korea', 'flag' => '🇰🇷']
    ],
    'JP' => [
        'default' => ['country' => 'Japan', 'flag' => '🇯🇵']
    ],
    'SEA' => [
        'Paper Rex' => ['country' => 'Singapore', 'flag' => '🇸🇬'],
        'Team Secret' => ['country' => 'Philippines', 'flag' => '🇵🇭'],
        'Bleed Esports' => ['country' => 'Singapore', 'flag' => '🇸🇬'],
        'XERXIA Esports' => ['country' => 'Thailand', 'flag' => '🇹🇭'],
        'BOOM Esports' => ['country' => 'Indonesia', 'flag' => '🇮🇩'],
        'default' => ['country' => 'Singapore', 'flag' => '🇸🇬']
    ],
    'OCE' => [
        'Ground Zero Gaming' => ['country' => 'Australia', 'flag' => '🇦🇺'],
        'FURY' => ['country' => 'Australia', 'flag' => '🇦🇺'],
        'Kanga Esports' => ['country' => 'Australia', 'flag' => '🇦🇺'],
        'The Vicious' => ['country' => 'New Zealand', 'flag' => '🇳🇿'],
        'default' => ['country' => 'Australia', 'flag' => '🇦🇺']
    ],
    'SA' => [
        'LOUD' => ['country' => 'Brazil', 'flag' => '🇧🇷'],
        'FURIA Esports' => ['country' => 'Brazil', 'flag' => '🇧🇷'],
        'paiN Gaming' => ['country' => 'Brazil', 'flag' => '🇧🇷'],
        'KRÜ Esports' => ['country' => 'Argentina', 'flag' => '🇦🇷'],
        'Leviatán Esports' => ['country' => 'Chile', 'flag' => '🇨🇱'],
        'default' => ['country' => 'Brazil', 'flag' => '🇧🇷']
    ],
    'MENA' => [
        '3BL Esports' => ['country' => 'Saudi Arabia', 'flag' => '🇸🇦'],
        'Al Qadsiah' => ['country' => 'Saudi Arabia', 'flag' => '🇸🇦'],
        'Falcons Esports' => ['country' => 'Saudi Arabia', 'flag' => '🇸🇦'],
        'NASR Esports' => ['country' => 'United Arab Emirates', 'flag' => '🇦🇪'],
        'Geekay Esports' => ['country' => 'United Arab Emirates', 'flag' => '🇦🇪'],
        'default' => ['country' => 'Saudi Arabia', 'flag' => '🇸🇦']
    ],
    'CIS' => [
        'Virtus.pro' => ['country' => 'Russia', 'flag' => '🇷🇺'],
        'Gambit Esports' => ['country' => 'Russia', 'flag' => '🇷🇺'],
        'Natus Vincere' => ['country' => 'Ukraine', 'flag' => '🇺🇦'],
        'Team Spirit' => ['country' => 'Russia', 'flag' => '🇷🇺'],
        'default' => ['country' => 'Russia', 'flag' => '🇷🇺']
    ],
    'ASIA' => [
        'Rival Esports' => ['country' => 'India', 'flag' => '🇮🇳'],
        'default' => ['country' => 'India', 'flag' => '🇮🇳']
    ]
];

// Fix teams by region
foreach ($regionDefaults as $region => $teamMap) {
    echo "Fixing $region teams...\n";
    
    $teams = Team::where('region', $region)->get();
    
    foreach ($teams as $team) {
        $countryData = $teamMap[$team->name] ?? $teamMap['default'] ?? null;
        
        if ($countryData && (empty($team->country) || empty($team->country_flag))) {
            $team->update([
                'country' => $countryData['country'],
                'country_flag' => $countryData['flag']
            ]);
            echo "   ✓ {$team->name}: {$countryData['country']} {$countryData['flag']}\n";
        }
    }
}

// Fix players based on their team's country
echo "\nFixing player flags based on team countries...\n";

$teams = Team::whereNotNull('country')
    ->where('country', '!=', '')
    ->get();

$totalUpdated = 0;
foreach ($teams as $team) {
    $updated = $team->players()
        ->where(function($query) {
            $query->whereNull('country')
                ->orWhere('country', '')
                ->orWhereNull('country_flag')
                ->orWhere('country_flag', '');
        })
        ->update([
            'country' => $team->country,
            'country_flag' => $team->country_flag
        ]);
    
    $totalUpdated += $updated;
}

echo "   ✓ Updated $totalUpdated player flags\n";

// Final verification
echo "\n=== FINAL FLAG DISTRIBUTION ===\n";

echo "\nTeams by Country:\n";
Team::selectRaw('country, country_flag, count(*) as count')
    ->whereNotNull('country')
    ->where('country', '!=', '')
    ->groupBy('country', 'country_flag')
    ->orderBy('count', 'desc')
    ->get()
    ->each(function($group) {
        printf("   %-25s %s : %2d teams\n", $group->country, $group->country_flag, $group->count);
    });

echo "\nRegions with proper flags:\n";
$regions = ['NA', 'EU', 'CN', 'KR', 'JP', 'SEA', 'OCE', 'SA', 'MENA', 'CIS', 'ASIA'];
foreach ($regions as $region) {
    $teamsWithFlags = Team::where('region', $region)
        ->whereNotNull('country_flag')
        ->where('country_flag', '!=', '')
        ->count();
    $totalTeams = Team::where('region', $region)->count();
    
    printf("   %-5s: %2d/%2d teams with flags\n", $region, $teamsWithFlags, $totalTeams);
}

echo "\n✓ All regional flags fixed!\n";