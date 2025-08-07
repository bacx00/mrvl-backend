<?php

require_once __DIR__ . '/bootstrap/app.php';

try {
    // Check if heroes table has data
    $count = DB::table('marvel_rivals_heroes')->count();
    echo "Heroes table has {$count} records.\n";
    
    if ($count === 0) {
        echo "Populating heroes table with basic data...\n";
        
        $heroes = [
            ['name' => 'Spider-Man', 'slug' => 'spider-man', 'role' => 'Duelist'],
            ['name' => 'Iron Man', 'slug' => 'iron-man', 'role' => 'Duelist'],
            ['name' => 'Captain America', 'slug' => 'captain-america', 'role' => 'Vanguard'],
            ['name' => 'Thor', 'slug' => 'thor', 'role' => 'Vanguard'],
            ['name' => 'Hulk', 'slug' => 'hulk', 'role' => 'Vanguard'],
            ['name' => 'Black Widow', 'slug' => 'black-widow', 'role' => 'Duelist'],
            ['name' => 'Hawkeye', 'slug' => 'hawkeye', 'role' => 'Duelist'],
            ['name' => 'Doctor Strange', 'slug' => 'doctor-strange', 'role' => 'Strategist'],
            ['name' => 'Scarlet Witch', 'slug' => 'scarlet-witch', 'role' => 'Duelist'],
            ['name' => 'Loki', 'slug' => 'loki', 'role' => 'Strategist'],
            ['name' => 'Venom', 'slug' => 'venom', 'role' => 'Vanguard'],
            ['name' => 'Magneto', 'slug' => 'magneto', 'role' => 'Vanguard'],
            ['name' => 'Storm', 'slug' => 'storm', 'role' => 'Duelist'],
            ['name' => 'Wolverine', 'slug' => 'wolverine', 'role' => 'Duelist'],
            ['name' => 'Groot', 'slug' => 'groot', 'role' => 'Vanguard'],
            ['name' => 'Rocket Raccoon', 'slug' => 'rocket-raccoon', 'role' => 'Strategist'],
            ['name' => 'Star-Lord', 'slug' => 'star-lord', 'role' => 'Duelist'],
            ['name' => 'Mantis', 'slug' => 'mantis', 'role' => 'Strategist'],
            ['name' => 'Adam Warlock', 'slug' => 'adam-warlock', 'role' => 'Strategist'],
            ['name' => 'Luna Snow', 'slug' => 'luna-snow', 'role' => 'Strategist'],
            ['name' => 'Jeff the Land Shark', 'slug' => 'jeff-the-land-shark', 'role' => 'Vanguard'],
            ['name' => 'Cloak & Dagger', 'slug' => 'cloak-dagger', 'role' => 'Duelist']
        ];
        
        foreach ($heroes as $hero) {
            $hero['created_at'] = now();
            $hero['updated_at'] = now();
            DB::table('marvel_rivals_heroes')->insert($hero);
        }
        
        echo "Populated " . count($heroes) . " heroes.\n";
    }
    
    // Test the getAllHeroImages endpoint
    echo "\nTesting getAllHeroImages endpoint...\n";
    $app = app();
    $controller = new App\Http\Controllers\HeroController();
    
    try {
        $response = $controller->getAllHeroImages();
        $data = $response->getData(true);
        
        if ($data['success']) {
            echo "✓ getAllHeroImages endpoint working - returned " . count($data['data']) . " heroes\n";
            echo "Missing images: " . count($data['missing_images']) . "\n";
        } else {
            echo "✗ getAllHeroImages endpoint failed: " . $data['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "✗ getAllHeroImages endpoint error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}