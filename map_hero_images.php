<?php

// ==========================================
// MARVEL HEROES IMAGE MAPPING SYSTEM
// Maps heroes to their correct image files
// ==========================================

echo "🦸 MARVEL HEROES IMAGE MAPPING SYSTEM\n";
echo "=====================================\n";
echo "🎯 Mapping heroes to correct images with fallbacks\n\n";

function nameToImageFile($heroName) {
    // Convert hero name to expected image filename
    $filename = strtolower($heroName);
    
    // Handle special cases and name variations
    $specialMappings = [
        'cloak & dagger' => ['cloak_&_dagger.webp', 'cloak_dagger.webp'],
        'star-lord' => ['star-lord.webp', 'star_lord.webp'],
        'punisher' => ['punisher.webp', 'the_punisher.webp'],
        'hulk' => ['hulk.webp', 'bruce_banner.webp'],
        'jeff the land shark' => ['jeff_the_land_shark.webp'],
        'winter soldier' => ['winter_soldier.webp'],
        'black panther' => ['black_panther.webp'],
        'black widow' => ['black_widow.webp'],
        'captain america' => ['captain_america.webp'],
        'doctor strange' => ['doctor_strange.webp'],
        'invisible woman' => ['invisible_woman.webp'],
        'luna snow' => ['luna_snow.webp'],
        'moon knight' => ['moon_knight.webp'],
        'peni parker' => ['peni_parker.webp'],
        'scarlet witch' => ['scarlet_witch.webp'],
        'adam warlock' => ['adam_warlock.webp'],
        'iron fist' => ['iron_fist.webp'],
        'mister fantastic' => ['mister_fantastic.webp'],
        'human torch' => ['human_torch.webp'],
        'the thing' => ['the_thing.webp'],
        'emma frost' => ['emma_frost.webp'],
        'squirrel girl' => ['squirrel_girl.webp']
    ];
    
    $heroKey = strtolower($heroName);
    
    if (isset($specialMappings[$heroKey])) {
        return $specialMappings[$heroKey];
    }
    
    // Default: convert spaces to underscores
    $defaultFilename = str_replace([' ', '-', '&'], ['_', '_', '_'], $filename) . '.webp';
    return [$defaultFilename];
}

function checkImageExists($imagePath) {
    $fullPath = '/var/www/mrvl-backend/public/storage/heroes/' . $imagePath;
    return file_exists($fullPath);
}

function getImageUrl($imagePath) {
    // Return the public URL for the image
    return '/storage/heroes/' . $imagePath;
}

// Get all heroes from database
$heroes = DB::table('marvel_heroes')->get();

echo "🔍 MAPPING " . count($heroes) . " HEROES TO IMAGES\n";
echo "==========================================\n\n";

$updated = 0;
$missing = 0;

foreach ($heroes as $hero) {
    echo "🦸 {$hero->name}:\n";
    
    $possibleImages = nameToImageFile($hero->name);
    $foundImage = null;
    
    // Try each possible image file
    foreach ($possibleImages as $imageFile) {
        if (checkImageExists($imageFile)) {
            $foundImage = $imageFile;
            echo "  ✅ Found: {$imageFile}\n";
            break;
        } else {
            echo "  ❌ Not found: {$imageFile}\n";
        }
    }
    
    if ($foundImage) {
        // Update database with image URL
        $imageUrl = getImageUrl($foundImage);
        DB::table('marvel_heroes')
            ->where('id', $hero->id)
            ->update(['image' => $imageUrl]);
        echo "  🔗 Updated database: {$imageUrl}\n";
        $updated++;
    } else {
        // Set image to null so we can show text name
        DB::table('marvel_heroes')
            ->where('id', $hero->id)
            ->update(['image' => null]);
        echo "  📝 No image found - will show text name\n";
        $missing++;
    }
    
    echo "\n";
}

echo "📊 MAPPING SUMMARY\n";
echo "=================\n";
echo "✅ Heroes with images: {$updated}\n";
echo "📝 Heroes without images: {$missing}\n";
echo "📁 Total heroes: " . count($heroes) . "\n\n";

echo "🎨 UPDATING API ENDPOINT\n";
echo "========================\n";
echo "The API will now return:\n";
echo "- image: URL path for heroes with images\n";
echo "- image: null for heroes without images (frontend shows text name)\n\n";

echo "🏁 IMAGE MAPPING COMPLETE!\n";
echo "Heroes are now properly mapped to their images with fallbacks.\n";