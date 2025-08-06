<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;

echo "Starting player social media extraction...\n\n";

$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
];

// Get players with Liquipedia URLs
$players = Player::whereNotNull('liquipedia_url')
    ->where('liquipedia_url', 'NOT LIKE', '%redlink=1%')
    ->get();

echo "Found " . $players->count() . " players with valid Liquipedia URLs\n\n";

$updatedCount = 0;
$processedCount = 0;

foreach ($players as $player) {
    $processedCount++;
    echo "[$processedCount/" . $players->count() . "] Processing: {$player->name}";
    
    try {
        $response = Http::withHeaders($headers)->get($player->liquipedia_url);
        
        if (!$response->successful()) {
            echo " ✗ Failed to fetch page\n";
            continue;
        }
        
        $crawler = new Crawler($response->body());
        $updates = [];
        
        // Extract social media links
        $crawler->filter('.infobox-icons a, .infobox-cell-2 a[href]')->each(function ($node) use (&$updates) {
            $href = $node->attr('href');
            
            if (strpos($href, 'twitter.com') !== false || strpos($href, 'x.com') !== false) {
                $updates['twitter'] = $href;
            } elseif (strpos($href, 'instagram.com') !== false) {
                $updates['instagram'] = $href;
            } elseif (strpos($href, 'youtube.com') !== false) {
                $updates['youtube'] = $href;
            } elseif (strpos($href, 'twitch.tv') !== false) {
                $updates['twitch'] = $href;
            } elseif (strpos($href, 'facebook.com') !== false) {
                $updates['facebook'] = $href;
            }
        });
        
        // Extract real name if not set
        if (empty($player->real_name)) {
            $realNameNode = $crawler->filter('.infobox-cell-2:contains("Name") + .infobox-cell-2')->first();
            if ($realNameNode->count() > 0) {
                $realName = trim($realNameNode->text());
                if (!empty($realName) && $realName !== $player->name) {
                    $updates['real_name'] = $realName;
                }
            }
        }
        
        // Extract nationality if different
        $nationalityNode = $crawler->filter('.infobox-cell-2:contains("Nationality") + .infobox-cell-2')->first();
        if ($nationalityNode->count() > 0) {
            $flagImg = $nationalityNode->filter('img[alt]')->first();
            if ($flagImg->count() > 0) {
                $country = trim($flagImg->attr('alt'));
                if ($country && $country !== $player->country) {
                    $updates['country'] = $country;
                    $updates['country_flag'] = getCountryFlag($country);
                }
            }
        }
        
        // Extract role if not set properly
        if ($player->role === 'flex' || empty($player->role)) {
            $roleNode = $crawler->filter('.infobox-cell-2:contains("Role") + .infobox-cell-2')->first();
            if ($roleNode->count() > 0) {
                $role = normalizeRole(trim($roleNode->text()));
                if ($role !== 'flex') {
                    $updates['role'] = $role;
                }
            }
        }
        
        // Extract earnings
        $earningsNode = $crawler->filter('.infobox-cell-2:contains("Approx. Total Winnings") + .infobox-cell-2')->first();
        if ($earningsNode->count() > 0) {
            $text = $earningsNode->text();
            if (preg_match('/\$?([\d,]+)/', $text, $matches)) {
                $earnings = intval(str_replace(',', '', $matches[1]));
                if ($earnings > 0) {
                    $updates['earnings'] = $earnings;
                }
            }
        }
        
        if (!empty($updates)) {
            $player->update($updates);
            $updatedCount++;
            echo " ✓ Updated";
            if (isset($updates['twitter'])) echo " [Twitter]";
            if (isset($updates['instagram'])) echo " [Instagram]";
            if (isset($updates['twitch'])) echo " [Twitch]";
            if (isset($updates['youtube'])) echo " [YouTube]";
            if (isset($updates['real_name'])) echo " [Name]";
            if (isset($updates['earnings'])) echo " [Earnings: $" . number_format($updates['earnings']) . "]";
            echo "\n";
        } else {
            echo " - No updates\n";
        }
        
        // Rate limiting
        usleep(500000); // 0.5 second delay
        
    } catch (\Exception $e) {
        echo " ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Processed: $processedCount players\n";
echo "Updated: $updatedCount players\n";

// Show players with social media
echo "\nPlayers with social media:\n";
Player::where(function($q) {
    $q->whereNotNull('twitter')
      ->orWhereNotNull('instagram')
      ->orWhereNotNull('twitch')
      ->orWhereNotNull('youtube');
})->get(['name', 'twitter', 'instagram', 'twitch', 'youtube'])
  ->each(function($player) {
      $socials = [];
      if ($player->twitter) $socials[] = 'Twitter';
      if ($player->instagram) $socials[] = 'Instagram';
      if ($player->twitch) $socials[] = 'Twitch';
      if ($player->youtube) $socials[] = 'YouTube';
      echo "  {$player->name}: " . implode(', ', $socials) . "\n";
  });

function normalizeRole($role) {
    $role = strtolower(trim($role));
    
    $roleMap = [
        'dps' => 'duelist',
        'damage' => 'duelist',
        'duelist' => 'duelist',
        'tank' => 'vanguard',
        'vanguard' => 'vanguard',
        'support' => 'strategist',
        'healer' => 'strategist',
        'strategist' => 'strategist',
        'flex' => 'flex',
        'substitute' => 'substitute',
        'sub' => 'substitute'
    ];
    
    return $roleMap[$role] ?? 'flex';
}

function getCountryFlag($country) {
    $flags = [
        'United States' => '🇺🇸',
        'Canada' => '🇨🇦',
        'Mexico' => '🇲🇽',
        'Brazil' => '🇧🇷',
        'Argentina' => '🇦🇷',
        'Chile' => '🇨🇱',
        'United Kingdom' => '🇬🇧',
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
        'Russia' => '🇷🇺',
        'Ukraine' => '🇺🇦',
        'Turkey' => '🇹🇷',
        'Armenia' => '🇦🇲',
        'Iceland' => '🇮🇸',
        'South Korea' => '🇰🇷',
        'Japan' => '🇯🇵',
        'China' => '🇨🇳',
        'Taiwan' => '🇹🇼',
        'Singapore' => '🇸🇬',
        'Australia' => '🇦🇺',
        'New Zealand' => '🇳🇿',
        'Saudi Arabia' => '🇸🇦',
        'United Arab Emirates' => '🇦🇪',
        'Egypt' => '🇪🇬',
        'Malta' => '🇲🇹',
        'Malaysia' => '🇲🇾',
        'Philippines' => '🇵🇭',
        'Indonesia' => '🇮🇩',
        'Thailand' => '🇹🇭',
        'Vietnam' => '🇻🇳',
        'India' => '🇮🇳',
        'Qatar' => '🇶🇦',
        'Kuwait' => '🇰🇼',
        'Lebanon' => '🇱🇧',
        'Jordan' => '🇯🇴',
        'Peru' => '🇵🇪',
        'Colombia' => '🇨🇴',
        'Venezuela' => '🇻🇪',
        'Uruguay' => '🇺🇾',
        'Puerto Rico' => '🇵🇷'
    ];
    
    return $flags[$country] ?? '🌍';
}