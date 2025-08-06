<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Team;
use App\Models\Player;

echo "=== FIXING ALL MISSING FLAGS ===\n\n";

// Comprehensive country to flag mapping
$countryFlags = [
    // North America
    'United States' => '🇺🇸',
    'USA' => '🇺🇸',
    'Canada' => '🇨🇦',
    'Mexico' => '🇲🇽',
    'Puerto Rico' => '🇵🇷',
    'Dominican Republic' => '🇩🇴',
    'Costa Rica' => '🇨🇷',
    'Guatemala' => '🇬🇹',
    'Honduras' => '🇭🇳',
    'Nicaragua' => '🇳🇮',
    'Panama' => '🇵🇦',
    'El Salvador' => '🇸🇻',
    
    // South America
    'Brazil' => '🇧🇷',
    'Argentina' => '🇦🇷',
    'Chile' => '🇨🇱',
    'Peru' => '🇵🇪',
    'Colombia' => '🇨🇴',
    'Venezuela' => '🇻🇪',
    'Uruguay' => '🇺🇾',
    'Ecuador' => '🇪🇨',
    'Bolivia' => '🇧🇴',
    'Paraguay' => '🇵🇾',
    
    // Europe
    'United Kingdom' => '🇬🇧',
    'UK' => '🇬🇧',
    'England' => '🇬🇧',
    'Scotland' => '🇬🇧',
    'Wales' => '🇬🇧',
    'Germany' => '🇩🇪',
    'France' => '🇫🇷',
    'Spain' => '🇪🇸',
    'Italy' => '🇮🇹',
    'Netherlands' => '🇳🇱',
    'Belgium' => '🇧🇪',
    'Sweden' => '🇸🇪',
    'Denmark' => '🇩🇰',
    'Norway' => '🇳🇴',
    'Finland' => '🇫🇮',
    'Poland' => '🇵🇱',
    'Austria' => '🇦🇹',
    'Switzerland' => '🇨🇭',
    'Portugal' => '🇵🇹',
    'Greece' => '🇬🇷',
    'Czech Republic' => '🇨🇿',
    'Hungary' => '🇭🇺',
    'Romania' => '🇷🇴',
    'Bulgaria' => '🇧🇬',
    'Croatia' => '🇭🇷',
    'Serbia' => '🇷🇸',
    'Slovenia' => '🇸🇮',
    'Slovakia' => '🇸🇰',
    'Ireland' => '🇮🇪',
    'Lithuania' => '🇱🇹',
    'Latvia' => '🇱🇻',
    'Estonia' => '🇪🇪',
    'Luxembourg' => '🇱🇺',
    'Iceland' => '🇮🇸',
    'Malta' => '🇲🇹',
    'Cyprus' => '🇨🇾',
    'Albania' => '🇦🇱',
    'North Macedonia' => '🇲🇰',
    'Bosnia and Herzegovina' => '🇧🇦',
    'Montenegro' => '🇲🇪',
    'Kosovo' => '🇽🇰',
    
    // CIS Region
    'Russia' => '🇷🇺',
    'Ukraine' => '🇺🇦',
    'Belarus' => '🇧🇾',
    'Kazakhstan' => '🇰🇿',
    'Uzbekistan' => '🇺🇿',
    'Armenia' => '🇦🇲',
    'Georgia' => '🇬🇪',
    'Azerbaijan' => '🇦🇿',
    'Moldova' => '🇲🇩',
    'Tajikistan' => '🇹🇯',
    'Kyrgyzstan' => '🇰🇬',
    'Turkmenistan' => '🇹🇲',
    
    // Middle East & North Africa
    'Turkey' => '🇹🇷',
    'Saudi Arabia' => '🇸🇦',
    'United Arab Emirates' => '🇦🇪',
    'UAE' => '🇦🇪',
    'Egypt' => '🇪🇬',
    'Israel' => '🇮🇱',
    'Lebanon' => '🇱🇧',
    'Jordan' => '🇯🇴',
    'Kuwait' => '🇰🇼',
    'Qatar' => '🇶🇦',
    'Bahrain' => '🇧🇭',
    'Oman' => '🇴🇲',
    'Yemen' => '🇾🇪',
    'Iraq' => '🇮🇶',
    'Iran' => '🇮🇷',
    'Syria' => '🇸🇾',
    'Morocco' => '🇲🇦',
    'Tunisia' => '🇹🇳',
    'Algeria' => '🇩🇿',
    'Libya' => '🇱🇾',
    
    // Asia
    'China' => '🇨🇳',
    'South Korea' => '🇰🇷',
    'Korea' => '🇰🇷',
    'Japan' => '🇯🇵',
    'Taiwan' => '🇹🇼',
    'Hong Kong' => '🇭🇰',
    'Macau' => '🇲🇴',
    'Mongolia' => '🇲🇳',
    'North Korea' => '🇰🇵',
    
    // Southeast Asia
    'Singapore' => '🇸🇬',
    'Malaysia' => '🇲🇾',
    'Indonesia' => '🇮🇩',
    'Thailand' => '🇹🇭',
    'Philippines' => '🇵🇭',
    'Vietnam' => '🇻🇳',
    'Cambodia' => '🇰🇭',
    'Laos' => '🇱🇦',
    'Myanmar' => '🇲🇲',
    'Brunei' => '🇧🇳',
    
    // South Asia
    'India' => '🇮🇳',
    'Pakistan' => '🇵🇰',
    'Bangladesh' => '🇧🇩',
    'Sri Lanka' => '🇱🇰',
    'Nepal' => '🇳🇵',
    'Bhutan' => '🇧🇹',
    'Afghanistan' => '🇦🇫',
    'Maldives' => '🇲🇻',
    
    // Oceania
    'Australia' => '🇦🇺',
    'New Zealand' => '🇳🇿',
    'Fiji' => '🇫🇯',
    'Papua New Guinea' => '🇵🇬',
    'Samoa' => '🇼🇸',
    'Tonga' => '🇹🇴',
    'Vanuatu' => '🇻🇺',
    'Solomon Islands' => '🇸🇧',
    
    // Africa
    'South Africa' => '🇿🇦',
    'Nigeria' => '🇳🇬',
    'Kenya' => '🇰🇪',
    'Ghana' => '🇬🇭',
    'Ethiopia' => '🇪🇹',
    'Tanzania' => '🇹🇿',
    'Uganda' => '🇺🇬',
    'Zimbabwe' => '🇿🇼',
    'Zambia' => '🇿🇲',
    'Mozambique' => '🇲🇿',
    'Angola' => '🇦🇴',
    'Cameroon' => '🇨🇲',
    'Ivory Coast' => '🇨🇮',
    'Senegal' => '🇸🇳',
    
    // Default
    'International' => '🌍',
    'Unknown' => '🏳️'
];

// 1. Fix teams with missing flags
echo "1. Checking teams with missing or empty flags...\n";
$teamsWithoutFlags = Team::where(function($query) {
    $query->whereNull('country_flag')
        ->orWhere('country_flag', '')
        ->orWhere('country_flag', ' ');
})->get();

echo "   Found {$teamsWithoutFlags->count()} teams without flags\n";

foreach ($teamsWithoutFlags as $team) {
    $flag = null;
    
    // Try to get flag from country
    if ($team->country && isset($countryFlags[$team->country])) {
        $flag = $countryFlags[$team->country];
    }
    // If no country, determine from region
    elseif (!$team->country || $team->country === 'International') {
        switch($team->region) {
            case 'NA':
                $team->country = 'United States';
                $flag = '🇺🇸';
                break;
            case 'EU':
                $team->country = 'Germany';
                $flag = '🇩🇪';
                break;
            case 'CN':
                $team->country = 'China';
                $flag = '🇨🇳';
                break;
            case 'KR':
                $team->country = 'South Korea';
                $flag = '🇰🇷';
                break;
            case 'JP':
                $team->country = 'Japan';
                $flag = '🇯🇵';
                break;
            case 'SEA':
                $team->country = 'Singapore';
                $flag = '🇸🇬';
                break;
            case 'OCE':
                $team->country = 'Australia';
                $flag = '🇦🇺';
                break;
            case 'SA':
                $team->country = 'Brazil';
                $flag = '🇧🇷';
                break;
            case 'MENA':
                $team->country = 'Saudi Arabia';
                $flag = '🇸🇦';
                break;
            case 'CIS':
                $team->country = 'Russia';
                $flag = '🇷🇺';
                break;
            case 'ASIA':
                $team->country = 'India';
                $flag = '🇮🇳';
                break;
            default:
                $team->country = 'International';
                $flag = '🌍';
        }
    }
    
    if ($flag) {
        $team->country_flag = $flag;
        $team->save();
        echo "   ✓ {$team->name}: {$team->country} {$flag}\n";
    }
}

// 2. Fix players with missing flags
echo "\n2. Checking players with missing or empty flags...\n";
$playersWithoutFlags = Player::where(function($query) {
    $query->whereNull('country_flag')
        ->orWhere('country_flag', '')
        ->orWhere('country_flag', ' ');
})->get();

echo "   Found {$playersWithoutFlags->count()} players without flags\n";

$fixedCount = 0;
foreach ($playersWithoutFlags as $player) {
    $flag = null;
    
    // Try to get flag from country
    if ($player->country && isset($countryFlags[$player->country])) {
        $flag = $countryFlags[$player->country];
    }
    // Get from team if no country
    elseif (!$player->country || $player->country === 'International') {
        $team = Team::find($player->team_id);
        if ($team && $team->country && $team->country_flag) {
            $player->country = $team->country;
            $flag = $team->country_flag;
        }
        // Use region default
        elseif ($player->region) {
            switch($player->region) {
                case 'NA':
                    $player->country = 'United States';
                    $flag = '🇺🇸';
                    break;
                case 'EU':
                    $player->country = 'Germany';
                    $flag = '🇩🇪';
                    break;
                case 'CN':
                    $player->country = 'China';
                    $flag = '🇨🇳';
                    break;
                case 'KR':
                    $player->country = 'South Korea';
                    $flag = '🇰🇷';
                    break;
                case 'JP':
                    $player->country = 'Japan';
                    $flag = '🇯🇵';
                    break;
                case 'SEA':
                    $player->country = 'Singapore';
                    $flag = '🇸🇬';
                    break;
                case 'OCE':
                    $player->country = 'Australia';
                    $flag = '🇦🇺';
                    break;
                case 'SA':
                    $player->country = 'Brazil';
                    $flag = '🇧🇷';
                    break;
                case 'MENA':
                    $player->country = 'Saudi Arabia';
                    $flag = '🇸🇦';
                    break;
                case 'CIS':
                    $player->country = 'Russia';
                    $flag = '🇷🇺';
                    break;
                case 'ASIA':
                    $player->country = 'India';
                    $flag = '🇮🇳';
                    break;
                default:
                    $player->country = 'International';
                    $flag = '🌍';
            }
        }
    }
    
    if ($flag) {
        $player->country_flag = $flag;
        $player->save();
        $fixedCount++;
    }
}

echo "   ✓ Fixed $fixedCount player flags\n";

// 3. Fix any remaining International entries
echo "\n3. Fixing 'International' entries...\n";
$intlTeams = Team::where('country', 'International')->get();
$intlPlayers = Player::where('country', 'International')->get();

foreach ($intlTeams as $team) {
    $team->country_flag = '🌍';
    $team->save();
}

foreach ($intlPlayers as $player) {
    $player->country_flag = '🌍';
    $player->save();
}

echo "   ✓ Fixed {$intlTeams->count()} teams and {$intlPlayers->count()} players with International flag\n";

// 4. Final verification
echo "\n4. Final Verification:\n";
$teamsWithoutFlags = Team::where(function($query) {
    $query->whereNull('country_flag')
        ->orWhere('country_flag', '')
        ->orWhere('country_flag', ' ');
})->count();

$playersWithoutFlags = Player::where(function($query) {
    $query->whereNull('country_flag')
        ->orWhere('country_flag', '')
        ->orWhere('country_flag', ' ');
})->count();

$totalTeams = Team::count();
$totalPlayers = Player::count();

echo "   Teams with flags: " . ($totalTeams - $teamsWithoutFlags) . "/$totalTeams\n";
echo "   Players with flags: " . ($totalPlayers - $playersWithoutFlags) . "/$totalPlayers\n";

if ($teamsWithoutFlags > 0 || $playersWithoutFlags > 0) {
    echo "\n   ⚠️  Still have entities without flags:\n";
    echo "   - Teams without flags: $teamsWithoutFlags\n";
    echo "   - Players without flags: $playersWithoutFlags\n";
    
    // List them
    if ($teamsWithoutFlags > 0) {
        echo "\n   Teams missing flags:\n";
        Team::where(function($query) {
            $query->whereNull('country_flag')
                ->orWhere('country_flag', '')
                ->orWhere('country_flag', ' ');
        })->get(['name', 'country', 'region'])->each(function($team) {
            echo "   - {$team->name} (Country: {$team->country}, Region: {$team->region})\n";
        });
    }
} else {
    echo "\n   ✓ All teams and players now have flags!\n";
}

// 5. Show flag distribution
echo "\n5. Flag Distribution:\n";
Team::selectRaw('country_flag, COUNT(*) as count')
    ->whereNotNull('country_flag')
    ->where('country_flag', '!=', '')
    ->groupBy('country_flag')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get()
    ->each(function($group) {
        echo "   {$group->country_flag} : {$group->count} teams\n";
    });

echo "\n✓ Flag fixing complete!\n";