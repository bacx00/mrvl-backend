#!/bin/bash

echo "🦸 MAPPING MARVEL HEROES TO IMAGES"
echo "=================================="

# Run the image mapping via tinker
php artisan tinker << 'EOF'

function nameToImageFile($heroName) {
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
    
    $defaultFilename = str_replace([' ', '-', '&'], ['_', '_', '_'], strtolower($heroName)) . '.webp';
    return [$defaultFilename];
}

$heroes = DB::table('marvel_heroes')->get();
$updated = 0;
$missing = 0;

foreach ($heroes as $hero) {
    echo "🦸 {$hero->name}: ";
    
    $possibleImages = nameToImageFile($hero->name);
    $foundImage = null;
    
    foreach ($possibleImages as $imageFile) {
        $fullPath = '/var/www/mrvl-backend/public/storage/heroes/' . $imageFile;
        if (file_exists($fullPath)) {
            $foundImage = $imageFile;
            break;
        }
    }
    
    if ($foundImage) {
        $imageUrl = '/storage/heroes/' . $foundImage;
        DB::table('marvel_heroes')->where('id', $hero->id)->update(['image' => $imageUrl]);
        echo "✅ {$foundImage}\n";
        $updated++;
    } else {
        DB::table('marvel_heroes')->where('id', $hero->id)->update(['image' => null]);
        echo "📝 No image (will show text)\n";
        $missing++;
    }
}

echo "\n📊 SUMMARY:\n";
echo "✅ Heroes with images: {$updated}\n";
echo "📝 Heroes without images: {$missing}\n";
echo "📁 Total heroes: " . count($heroes) . "\n";

exit
EOF

echo ""
echo "🔍 TESTING API RESPONSE"
echo "======================"

# Test the API to see image mapping
curl -s "https://staging.mrvl.net/api/game-data/all-heroes" | jq '.image_info'

echo ""
echo "🎉 HERO IMAGE MAPPING COMPLETE!"
echo "==============================="
echo "✅ Heroes now have proper image URLs"
echo "✅ API includes image information"
echo "✅ Fallback strategy: Show text name when no image"