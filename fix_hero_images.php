<?php

// ==========================================
// IMPROVED MARVEL HEROES IMAGE MAPPER
// Matches actual image files to database heroes
// ==========================================

echo "🦸 IMPROVED MARVEL HEROES IMAGE MAPPER\n";
echo "======================================\n";

// Get actual image files that exist
$imageDir = '/var/www/mrvl-backend/public/storage/heroes/';
$actualFiles = [];

if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
            $actualFiles[] = $file;
        }
    }
}

echo "📁 Found " . count($actualFiles) . " image files:\n";
foreach ($actualFiles as $file) {
    echo "  - {$file}\n";
}
echo "\n";

// Get heroes from database
$heroes = DB::table('marvel_heroes')->get();
echo "🦸 Found " . count($heroes) . " heroes in database\n\n";

// Create comprehensive mapping
function findBestImageMatch($heroName, $availableFiles) {
    $heroKey = strtolower(trim($heroName));
    
    // Direct name mappings (exact matches we know work)
    $exactMappings = [
        'adam warlock' => 'adam_warlock.webp',
        'black panther' => 'black_panther.webp',
        'black widow' => 'black_widow.webp',
        'captain america' => 'captain_america.webp',
        'cloak & dagger' => 'cloak_&_dagger.webp',
        'doctor strange' => 'doctor_strange.webp',
        'groot' => 'groot.webp',
        'hawkeye' => 'hawkeye.webp',
        'hela' => 'hela.webp',
        'hulk' => 'hulk.webp',
        'invisible woman' => 'invisible_woman.webp',
        'iron fist' => 'iron_fist.webp',
        'jeff the land shark' => 'jeff_the_land_shark.webp',
        'loki' => 'loki.webp',
        'luna snow' => 'luna_snow.webp',
        'magik' => 'magik.webp',
        'magneto' => 'magneto.webp',
        'mantis' => 'mantis.webp',
        'moon knight' => 'moon_knight.webp',
        'namor' => 'namor.webp',
        'peni parker' => 'peni_parker.webp',
        'psylocke' => 'psylocke.webp',
        'punisher' => 'punisher.webp',
        'scarlet witch' => 'scarlet_witch.webp',
        'star-lord' => 'star-lord.webp',
        'venom' => 'venom.webp',
        'winter soldier' => 'winter_soldier.webp',
        'wolverine' => 'wolverine.webp',
        'emma frost' => 'emma_frost.webp',
        'human torch' => 'human_torch.webp',
        'mister fantastic' => 'mister_fantastic.webp',
        'the thing' => 'the_thing.webp',
        'squirrel girl' => 'squirrel_girl.webp',
        'ultron' => 'ultron.webp'
    ];
    
    // Check exact mapping first
    if (isset($exactMappings[$heroKey])) {
        $mappedFile = $exactMappings[$heroKey];
        if (in_array($mappedFile, $availableFiles)) {
            return $mappedFile;
        }
    }
    
    // Try alternative filename patterns
    $alternatives = [
        str_replace([' ', '-', '&'], ['_', '_', '_'], $heroKey) . '.webp',
        str_replace([' ', '-', '&'], ['_', '-', '&'], $heroKey) . '.webp',
        'the_' . str_replace([' ', '-', '&'], ['_', '_', '_'], $heroKey) . '.webp'
    ];
    
    foreach ($alternatives as $alt) {
        if (in_array($alt, $availableFiles)) {
            return $alt;
        }
    }
    
    // Try fuzzy matching (partial name matches)
    $heroWords = explode(' ', $heroKey);
    foreach ($availableFiles as $file) {
        $fileNameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
        $matches = 0;
        foreach ($heroWords as $word) {
            if (stripos($fileNameWithoutExt, $word) !== false) {
                $matches++;
            }
        }
        if ($matches >= count($heroWords)) {
            return $file;
        }
    }
    
    return null;
}

echo "🔍 MAPPING HEROES TO IMAGES\n";
echo "===========================\n";

$matched = 0;
$unmatched = 0;
$mappings = [];

foreach ($heroes as $hero) {
    $matchedFile = findBestImageMatch($hero->name, $actualFiles);
    
    if ($matchedFile) {
        $imageUrl = '/storage/heroes/' . $matchedFile;
        $mappings[] = [
            'hero' => $hero->name,
            'file' => $matchedFile,
            'url' => $imageUrl,
            'status' => 'matched'
        ];
        
        // Update database
        DB::table('marvel_heroes')
            ->where('id', $hero->id)
            ->update(['image' => $imageUrl]);
        
        echo "✅ {$hero->name} → {$matchedFile}\n";
        $matched++;
    } else {
        $mappings[] = [
            'hero' => $hero->name,
            'file' => null,
            'url' => null,
            'status' => 'unmatched'
        ];
        
        // Set to null for text fallback
        DB::table('marvel_heroes')
            ->where('id', $hero->id)
            ->update(['image' => null]);
        
        echo "❌ {$hero->name} → No image found\n";
        $unmatched++;
    }
}

echo "\n📊 MAPPING RESULTS\n";
echo "=================\n";
echo "✅ Heroes with images: {$matched}\n";
echo "❌ Heroes without images: {$unmatched}\n";
echo "📁 Total heroes: " . count($heroes) . "\n";
echo "📷 Available image files: " . count($actualFiles) . "\n\n";

echo "🎯 UNMATCHED HEROES (will show text names):\n";
echo "==========================================\n";
foreach ($mappings as $mapping) {
    if ($mapping['status'] === 'unmatched') {
        echo "📝 {$mapping['hero']}\n";
    }
}

echo "\n🎉 IMAGE MAPPING COMPLETE!\n";
echo "==========================\n";
echo "✅ Database updated with image URLs\n";
echo "✅ API will now serve proper image information\n";
echo "✅ Heroes without images will show text names\n";