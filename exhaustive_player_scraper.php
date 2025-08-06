<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

echo "Starting exhaustive player data scraping...\n\n";

$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
];

// Get all teams and re-scrape their rosters for complete player data
$teams = Team::all();
$totalUpdated = 0;

foreach ($teams as $team) {
    echo "Processing team: {$team->name}\n";
    
    if (!$team->liquipedia_url) {
        echo "  ✗ No Liquipedia URL\n";
        continue;
    }
    
    try {
        $response = Http::withHeaders($headers)->get($team->liquipedia_url);
        
        if (!$response->successful()) {
            echo "  ✗ Failed to fetch page\n";
            continue;
        }
        
        $crawler = new Crawler($response->body());
        
        // Find all roster tables
        $crawler->filter('table.roster-card tr, .wikitable.roster tr, .table-responsive table tr')->each(function ($row) use ($team, &$totalUpdated) {
            // Skip header rows
            if ($row->filter('th')->count() > 0) {
                return;
            }
            
            // Check if this is a player row (not coach/manager)
            $positionCell = $row->filter('td:nth-child(3), td:nth-child(4)')->first();
            if ($positionCell->count() > 0) {
                $position = strtolower(trim($positionCell->text()));
                if (strpos($position, 'coach') !== false || strpos($position, 'manager') !== false) {
                    return;
                }
            }
            
            $playerData = [];
            
            // Extract all available columns
            $cells = $row->filter('td');
            if ($cells->count() < 2) {
                return;
            }
            
            // Player IGN (usually first or second column with link)
            $ignCell = $row->filter('td a[href*="/marvelrivals/"]')->first();
            if ($ignCell->count() > 0) {
                $playerData['ign'] = trim($ignCell->text());
                $playerData['liquipedia_url'] = 'https://liquipedia.net' . $ignCell->attr('href');
            } else {
                // Try without link
                $ignText = trim($cells->eq(0)->text());
                if (empty($ignText) || $ignText === '-') {
                    $ignText = trim($cells->eq(1)->text());
                }
                if (!empty($ignText) && $ignText !== '-') {
                    $playerData['ign'] = $ignText;
                }
            }
            
            if (empty($playerData['ign'])) {
                return;
            }
            
            // Real name (usually second or third column)
            $nameIndex = 1;
            if ($cells->count() > 2) {
                for ($i = 1; $i < min(4, $cells->count()); $i++) {
                    $text = trim($cells->eq($i)->text());
                    if (!empty($text) && $text !== '-' && $text !== $playerData['ign']) {
                        // Check if it looks like a real name (contains space or all lowercase)
                        if (strpos($text, ' ') !== false || ctype_lower($text)) {
                            $playerData['real_name'] = $text;
                            break;
                        }
                    }
                }
            }
            
            // Country flag
            $flagImg = $row->filter('img[src*="flag"], img[alt*="flag"], .flag img')->first();
            if ($flagImg->count() > 0) {
                $alt = $flagImg->attr('alt');
                if ($alt) {
                    $playerData['country'] = trim(str_replace(['flag', 'Flag'], '', $alt));
                }
            }
            
            // Position/Role
            if ($positionCell->count() > 0) {
                $playerData['role'] = normalizeRole(trim($positionCell->text()));
            }
            
            // Join date (usually last column)
            if ($cells->count() > 4) {
                $lastCell = $cells->last();
                $dateText = trim($lastCell->text());
                if (preg_match('/\d{4}-\d{2}-\d{2}/', $dateText)) {
                    $playerData['join_date'] = $dateText;
                }
            }
            
            // Update or create player
            $player = Player::where('name', $playerData['ign'])
                ->where('team_id', $team->id)
                ->first();
            
            if ($player) {
                $updates = [];
                
                if (!empty($playerData['real_name']) && empty($player->real_name)) {
                    $updates['real_name'] = $playerData['real_name'];
                }
                
                if (!empty($playerData['country']) && $player->country === 'International') {
                    $updates['country'] = $playerData['country'];
                    $updates['country_flag'] = getCountryFlag($playerData['country']);
                }
                
                if (!empty($playerData['role']) && ($player->role === 'flex' || empty($player->role))) {
                    $updates['role'] = $playerData['role'];
                }
                
                if (!empty($playerData['liquipedia_url']) && empty($player->liquipedia_url)) {
                    $updates['liquipedia_url'] = $playerData['liquipedia_url'];
                }
                
                if (!empty($updates)) {
                    $player->update($updates);
                    $totalUpdated++;
                    echo "  ✓ Updated {$player->name}";
                    if (isset($updates['real_name'])) echo " [Name: {$updates['real_name']}]";
                    if (isset($updates['country'])) echo " [Country: {$updates['country']}]";
                    echo "\n";
                }
            }
        });
        
        sleep(1); // Rate limiting
        
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

// Now scrape individual player pages for those with URLs
echo "\n\nScraping individual player pages for detailed info...\n";

$players = Player::whereNotNull('liquipedia_url')
    ->where('liquipedia_url', 'NOT LIKE', '%redlink=1%')
    ->whereNull('age')
    ->limit(100) // Process in batches
    ->get();

foreach ($players as $player) {
    echo "Fetching details for {$player->name}...";
    
    try {
        $response = Http::withHeaders($headers)->get($player->liquipedia_url);
        
        if (!$response->successful()) {
            echo " ✗ Failed\n";
            continue;
        }
        
        $crawler = new Crawler($response->body());
        $updates = [];
        
        // Extract birth date and calculate age
        $birthNode = $crawler->filter('.infobox-cell-2:contains("Born") + .infobox-cell-2')->first();
        if ($birthNode->count() > 0) {
            $birthText = $birthNode->text();
            if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $birthText, $matches)) {
                $birthDate = $matches[1];
                $updates['birth_date'] = $birthDate;
                
                // Calculate age
                $birth = new DateTime($birthDate);
                $today = new DateTime();
                $age = $today->diff($birth)->y;
                $updates['age'] = $age;
            } elseif (preg_match('/\(age\s+(\d+)\)/', $birthText, $matches)) {
                $updates['age'] = intval($matches[1]);
            }
        }
        
        // Extract social media
        $crawler->filter('.infobox-icons a, .infobox-cell-2 a[href]')->each(function ($node) use (&$updates, $player) {
            $href = $node->attr('href');
            
            if ((strpos($href, 'twitter.com') !== false || strpos($href, 'x.com') !== false) && empty($player->twitter)) {
                $updates['twitter'] = $href;
            } elseif (strpos($href, 'instagram.com') !== false && empty($player->instagram)) {
                $updates['instagram'] = $href;
            } elseif (strpos($href, 'youtube.com') !== false && empty($player->youtube)) {
                $updates['youtube'] = $href;
            } elseif (strpos($href, 'twitch.tv') !== false && empty($player->twitch)) {
                $updates['twitch'] = $href;
            } elseif (strpos($href, 'facebook.com') !== false && empty($player->facebook)) {
                $updates['facebook'] = $href;
            }
        });
        
        // Extract real name if still missing
        if (empty($player->real_name)) {
            $nameNode = $crawler->filter('.infobox-cell-2:contains("Name") + .infobox-cell-2')->first();
            if ($nameNode->count() > 0) {
                $realName = trim($nameNode->text());
                if (!empty($realName) && $realName !== $player->name) {
                    $updates['real_name'] = $realName;
                }
            }
        }
        
        // Extract earnings
        $earningsNode = $crawler->filter('.infobox-cell-2:contains("Approx. Total Winnings") + .infobox-cell-2')->first();
        if ($earningsNode->count() > 0) {
            $text = $earningsNode->text();
            if (preg_match('/\$?([\d,]+)/', $text, $matches)) {
                $earnings = intval(str_replace(',', '', $matches[1]));
                if ($earnings > $player->earnings) {
                    $updates['earnings'] = $earnings;
                }
            }
        }
        
        if (!empty($updates)) {
            $player->update($updates);
            echo " ✓";
            if (isset($updates['age'])) echo " [Age: {$updates['age']}]";
            if (isset($updates['earnings'])) echo " [Earnings: $" . number_format($updates['earnings']) . "]";
            if (isset($updates['twitter'])) echo " [Twitter]";
            echo "\n";
        } else {
            echo " - No new data\n";
        }
        
        usleep(500000); // Rate limit
        
    } catch (\Exception $e) {
        echo " ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total players updated: $totalUpdated\n";

// Final statistics
echo "\nData completeness:\n";
$total = Player::count();
$withRealName = Player::whereNotNull('real_name')->where('real_name', '!=', '')->count();
$withAge = Player::whereNotNull('age')->count();
$withCountry = Player::where('country', '!=', 'International')->whereNotNull('country')->count();
$withSocial = Player::where(function($q) {
    $q->whereNotNull('twitter')
      ->orWhereNotNull('instagram')
      ->orWhereNotNull('twitch')
      ->orWhereNotNull('youtube');
})->count();

echo "- Players with real names: $withRealName/" . $total . " (" . round($withRealName/$total*100) . "%)\n";
echo "- Players with age: $withAge/" . $total . " (" . round($withAge/$total*100) . "%)\n";
echo "- Players with country: $withCountry/" . $total . " (" . round($withCountry/$total*100) . "%)\n";
echo "- Players with social media: $withSocial/" . $total . " (" . round($withSocial/$total*100) . "%)\n";

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
        'India' => '🇮🇳'
    ];
    
    return $flags[$country] ?? '🌍';
}