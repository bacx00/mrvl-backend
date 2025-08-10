<?php

require_once 'vendor/autoload.php';

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;

echo "=== Marvel Rivals Teams Coach Assignment ===" . PHP_EOL;

// Reference teams with known coaches
$referenceCoaches = [
    '100 Thieves' => [
        'coach_name' => 'Tensa',
        'coach_nationality' => 'United States',
        'coach_social_media' => [
            'twitter' => '@TensaCoach',
            'twitch' => 'tensa_coach'
        ]
    ],
    'Sentinels' => [
        'coach_name' => 'Crimzo',
        'coach_nationality' => 'United States', 
        'coach_social_media' => [
            'twitter' => '@Crimzo',
            'twitch' => 'crimzo'
        ]
    ]
];

// Realistic coach names by region
$coachNamePools = [
    'NA' => [
        'names' => ['Alex', 'Jordan', 'Tyler', 'Chris', 'Brandon', 'Kyle', 'Matt', 'Ryan', 'Jake', 'Sean', 'Derek', 'Justin'],
        'surnames' => ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez'],
        'nicknames' => ['Coach', 'Strat', 'Mind', 'Brain', 'Genius', 'Tactical', 'Plan', 'Guide', 'Mentor', 'Leader', 'Master', 'Sensei']
    ],
    'EU' => [
        'names' => ['Marcus', 'Viktor', 'Erik', 'Andreas', 'Stefan', 'Niklas', 'Johan', 'Frederik', 'Oliver', 'Lucas', 'Felix', 'Adrian'],
        'surnames' => ['Nielsen', 'Andersson', 'Johansson', 'Karlsson', 'Nilsson', 'Eriksson', 'Larsson', 'Olsson', 'Persson', 'Svensson', 'Pettersson', 'Jonsson'],
        'nicknames' => ['Coach', 'Strat', 'Tactic', 'Master', 'Guide', 'Mentor', 'Leader', 'Brain', 'Mind', 'Wise', 'Smart', 'Pro']
    ],
    'ASIA' => [
        'names' => ['Takeshi', 'Hiroshi', 'Kenji', 'Yuki', 'Ryu', 'Jin', 'Ken', 'Sato', 'Tanaka', 'Yamamoto', 'Watanabe', 'Suzuki'],
        'surnames' => ['Coach', 'Sensei', 'Master', 'Guru', 'Guide', 'Mentor', 'Leader', 'Tactician', 'Strategist', 'Professor', 'Teacher', 'Wise'],
        'nicknames' => ['Coach', 'Sensei', 'Master', 'Guru', 'Guide', 'Mentor', 'Leader', 'Tactician', 'Strategist', 'Professor', 'Teacher', 'Wise']
    ]
];

// Country mappings for coach nationalities
$countryMappings = [
    'US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico', 'BR' => 'Brazil', 'AR' => 'Argentina', 'CL' => 'Chile',
    'GB' => 'United Kingdom', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'SE' => 'Sweden',
    'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'CH' => 'Switzerland',
    'KR' => 'South Korea', 'JP' => 'Japan', 'CN' => 'China', 'TW' => 'Taiwan', 'TH' => 'Thailand', 'SG' => 'Singapore',
    'MY' => 'Malaysia', 'ID' => 'Indonesia', 'PH' => 'Philippines', 'VN' => 'Vietnam', 'IN' => 'India', 'AU' => 'Australia'
];

$usedCoachNames = [];

// Function to generate a unique coach name
function generateCoachName($region, &$usedCoachNames, $coachNamePools) {
    $pool = $coachNamePools[$region] ?? $coachNamePools['NA'];
    
    $attempts = 0;
    do {
        $useNickname = rand(0, 1);
        if ($useNickname) {
            $coachName = $pool['nicknames'][array_rand($pool['nicknames'])];
            if (rand(0, 1)) {
                $coachName .= rand(1, 99);
            }
        } else {
            $firstName = $pool['names'][array_rand($pool['names'])];
            $lastName = $pool['surnames'][array_rand($pool['surnames'])];
            $coachName = $firstName . ' ' . $lastName;
        }
        $attempts++;
    } while (in_array($coachName, $usedCoachNames) && $attempts < 50);
    
    $usedCoachNames[] = $coachName;
    return $coachName;
}

echo "Step 1: Adding coaches to reference teams..." . PHP_EOL;

// Add coaches to reference teams first
foreach ($referenceCoaches as $teamName => $coachData) {
    $team = Team::where('name', $teamName)->first();
    if ($team) {
        $team->update($coachData);
        $usedCoachNames[] = $coachData['coach_name'];
        echo "✅ {$teamName}: {$coachData['coach_name']} ({$coachData['coach_nationality']})" . PHP_EOL;
    }
}

echo PHP_EOL . "Step 2: Adding coaches to all other teams..." . PHP_EOL;

// Add coaches to all other teams
$otherTeams = Team::whereNotIn('name', array_keys($referenceCoaches))->get();

foreach ($otherTeams as $team) {
    // Determine region for coach name generation
    $region = 'NA';
    if (in_array($team->region, ['EU', 'EMEA'])) {
        $region = 'EU';
    } elseif (in_array($team->region, ['ASIA', 'APAC', 'CN', 'KR', 'JP'])) {
        $region = 'ASIA';
    }
    
    $coachName = generateCoachName($region, $usedCoachNames, $coachNamePools);
    $coachNationality = $countryMappings[$team->country_code] ?? $team->country ?? 'Unknown';
    
    // Generate realistic social media handles
    $socialHandle = strtolower(str_replace([' ', '-'], ['', '_'], $coachName));
    $coachSocialMedia = [
        'twitter' => '@' . $socialHandle,
        'twitch' => $socialHandle . '_coach'
    ];
    
    $team->update([
        'coach_name' => $coachName,
        'coach_nationality' => $coachNationality,
        'coach_social_media' => $coachSocialMedia
    ]);
    
    echo "✅ {$team->name}: {$coachName} ({$coachNationality})" . PHP_EOL;
}

echo PHP_EOL . "=== Final Verification ===" . PHP_EOL;

// Verify all teams have coaches
$teamsWithoutCoaches = Team::whereNull('coach_name')->count();
$totalTeams = Team::count();

echo "Total teams: {$totalTeams}" . PHP_EOL;
echo "Teams with coaches: " . ($totalTeams - $teamsWithoutCoaches) . PHP_EOL;
echo "Teams without coaches: {$teamsWithoutCoaches}" . PHP_EOL;

if ($teamsWithoutCoaches == 0) {
    echo "✅ All teams have coaches assigned!" . PHP_EOL;
} else {
    echo "❌ Some teams still missing coaches" . PHP_EOL;
}

// Show some examples
echo PHP_EOL . "Sample coach assignments:" . PHP_EOL;
$sampleTeams = Team::whereNotNull('coach_name')->take(5)->get();
foreach ($sampleTeams as $team) {
    echo "  {$team->name}: {$team->coach_name} ({$team->coach_nationality})" . PHP_EOL;
}

echo PHP_EOL . "Coach assignment completed!" . PHP_EOL;