<?php

// Simple script to populate Marvel Rivals teams and players directly

// Use environment variables from .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $db = $env['DB_DATABASE'] ?? 'mrvl_production';
    $user = $env['DB_USERNAME'] ?? 'root';
    $pass = $env['DB_PASSWORD'] ?? '';
} else {
    $host = '127.0.0.1';
    $db = 'mrvl_production';
    $user = 'mrvl_user';
    $pass = '1f9ER!ancao13$18jdw9ioqs';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Clear existing data
    echo "Clearing existing data...\n";
    $pdo->exec("DELETE FROM players");
    $pdo->exec("DELETE FROM teams");
    
    // Teams data - Top 57 Marvel Rivals teams
    $teams = [
        // North America
        ['name' => 'Sentinels', 'short_name' => 'SEN', 'region' => 'NA', 'country' => 'United States', 'rating' => 2400, 'earnings' => 125000],
        ['name' => 'NRG', 'short_name' => 'NRG', 'region' => 'NA', 'country' => 'United States', 'rating' => 2350, 'earnings' => 95000],
        ['name' => 'Cloud9', 'short_name' => 'C9', 'region' => 'NA', 'country' => 'United States', 'rating' => 2300, 'earnings' => 85000],
        ['name' => '100 Thieves', 'short_name' => '100T', 'region' => 'NA', 'country' => 'United States', 'rating' => 2250, 'earnings' => 75000],
        ['name' => 'TSM', 'short_name' => 'TSM', 'region' => 'NA', 'country' => 'United States', 'rating' => 2200, 'earnings' => 65000],
        ['name' => 'Evil Geniuses', 'short_name' => 'EG', 'region' => 'NA', 'country' => 'United States', 'rating' => 2150, 'earnings' => 55000],
        ['name' => 'OpTic Gaming', 'short_name' => 'OG', 'region' => 'NA', 'country' => 'United States', 'rating' => 2100, 'earnings' => 50000],
        ['name' => 'FaZe Clan', 'short_name' => 'FaZe', 'region' => 'NA', 'country' => 'United States', 'rating' => 2050, 'earnings' => 45000],
        ['name' => 'Luminosity', 'short_name' => 'LG', 'region' => 'NA', 'country' => 'United States', 'rating' => 2000, 'earnings' => 40000],
        ['name' => 'DarkZero', 'short_name' => 'DZ', 'region' => 'NA', 'country' => 'United States', 'rating' => 1950, 'earnings' => 35000],
        ['name' => 'Version1', 'short_name' => 'V1', 'region' => 'NA', 'country' => 'United States', 'rating' => 1900, 'earnings' => 30000],
        ['name' => 'XSET', 'short_name' => 'XSET', 'region' => 'NA', 'country' => 'United States', 'rating' => 1850, 'earnings' => 25000],
        ['name' => 'The Guard', 'short_name' => 'GRD', 'region' => 'NA', 'country' => 'United States', 'rating' => 1800, 'earnings' => 20000],
        ['name' => 'Shopify Rebellion', 'short_name' => 'SR', 'region' => 'NA', 'country' => 'Canada', 'rating' => 1750, 'earnings' => 15000],
        ['name' => 'Oxygen Esports', 'short_name' => 'OXG', 'region' => 'NA', 'country' => 'United States', 'rating' => 1700, 'earnings' => 10000],
        
        // Europe
        ['name' => 'G2 Esports', 'short_name' => 'G2', 'region' => 'EU', 'country' => 'Germany', 'rating' => 2450, 'earnings' => 150000],
        ['name' => 'Team Liquid', 'short_name' => 'TL', 'region' => 'EU', 'country' => 'Netherlands', 'rating' => 2400, 'earnings' => 120000],
        ['name' => 'Fnatic', 'short_name' => 'FNC', 'region' => 'EU', 'country' => 'United Kingdom', 'rating' => 2350, 'earnings' => 100000],
        ['name' => 'Team Vitality', 'short_name' => 'VIT', 'region' => 'EU', 'country' => 'France', 'rating' => 2300, 'earnings' => 90000],
        ['name' => 'NAVI', 'short_name' => 'NAVI', 'region' => 'EU', 'country' => 'Ukraine', 'rating' => 2250, 'earnings' => 80000],
        ['name' => 'Team Heretics', 'short_name' => 'TH', 'region' => 'EU', 'country' => 'Spain', 'rating' => 2200, 'earnings' => 70000],
        ['name' => 'KOI', 'short_name' => 'KOI', 'region' => 'EU', 'country' => 'Spain', 'rating' => 2150, 'earnings' => 60000],
        ['name' => 'Karmine Corp', 'short_name' => 'KC', 'region' => 'EU', 'country' => 'France', 'rating' => 2100, 'earnings' => 55000],
        ['name' => 'Giants Gaming', 'short_name' => 'GIA', 'region' => 'EU', 'country' => 'Spain', 'rating' => 2050, 'earnings' => 45000],
        ['name' => 'FUT Esports', 'short_name' => 'FUT', 'region' => 'EU', 'country' => 'Turkey', 'rating' => 2000, 'earnings' => 40000],
        
        // Asia Pacific
        ['name' => 'Paper Rex', 'short_name' => 'PRX', 'region' => 'APAC', 'country' => 'Singapore', 'rating' => 2500, 'earnings' => 180000],
        ['name' => 'DRX', 'short_name' => 'DRX', 'region' => 'APAC', 'country' => 'South Korea', 'rating' => 2450, 'earnings' => 160000],
        ['name' => 'T1', 'short_name' => 'T1', 'region' => 'APAC', 'country' => 'South Korea', 'rating' => 2400, 'earnings' => 140000],
        ['name' => 'Gen.G', 'short_name' => 'GEN', 'region' => 'APAC', 'country' => 'South Korea', 'rating' => 2350, 'earnings' => 120000],
        ['name' => 'ZETA DIVISION', 'short_name' => 'ZETA', 'region' => 'APAC', 'country' => 'Japan', 'rating' => 2300, 'earnings' => 100000],
        ['name' => 'Talon Esports', 'short_name' => 'TLN', 'region' => 'APAC', 'country' => 'Thailand', 'rating' => 2250, 'earnings' => 85000],
        ['name' => 'Team Secret', 'short_name' => 'TS', 'region' => 'APAC', 'country' => 'Philippines', 'rating' => 2200, 'earnings' => 75000],
        ['name' => 'BOOM Esports', 'short_name' => 'BOOM', 'region' => 'APAC', 'country' => 'Indonesia', 'rating' => 2150, 'earnings' => 65000],
        ['name' => 'Rex Regum Qeon', 'short_name' => 'RRQ', 'region' => 'APAC', 'country' => 'Indonesia', 'rating' => 2100, 'earnings' => 55000],
        ['name' => 'FULL SENSE', 'short_name' => 'FS', 'region' => 'APAC', 'country' => 'Thailand', 'rating' => 2050, 'earnings' => 45000],
        
        // China
        ['name' => 'Edward Gaming', 'short_name' => 'EDG', 'region' => 'CN', 'country' => 'China', 'rating' => 2400, 'earnings' => 130000],
        ['name' => 'FunPlus Phoenix', 'short_name' => 'FPX', 'region' => 'CN', 'country' => 'China', 'rating' => 2350, 'earnings' => 110000],
        ['name' => 'Bilibili Gaming', 'short_name' => 'BLG', 'region' => 'CN', 'country' => 'China', 'rating' => 2300, 'earnings' => 95000],
        ['name' => 'JD Gaming', 'short_name' => 'JDG', 'region' => 'CN', 'country' => 'China', 'rating' => 2250, 'earnings' => 85000],
        ['name' => 'Wolves Esports', 'short_name' => 'WOL', 'region' => 'CN', 'country' => 'China', 'rating' => 2200, 'earnings' => 75000],
        ['name' => 'Rare Atom', 'short_name' => 'RA', 'region' => 'CN', 'country' => 'China', 'rating' => 2150, 'earnings' => 65000],
        ['name' => 'TEC', 'short_name' => 'TEC', 'region' => 'CN', 'country' => 'China', 'rating' => 2100, 'earnings' => 55000],
        
        // Latin America
        ['name' => 'LOUD', 'short_name' => 'LOUD', 'region' => 'SA', 'country' => 'Brazil', 'rating' => 2350, 'earnings' => 105000],
        ['name' => 'FURIA', 'short_name' => 'FUR', 'region' => 'SA', 'country' => 'Brazil', 'rating' => 2300, 'earnings' => 90000],
        ['name' => 'MIBR', 'short_name' => 'MIBR', 'region' => 'SA', 'country' => 'Brazil', 'rating' => 2250, 'earnings' => 80000],
        ['name' => 'paiN Gaming', 'short_name' => 'PNG', 'region' => 'SA', 'country' => 'Brazil', 'rating' => 2200, 'earnings' => 70000],
        ['name' => 'KRÜ Esports', 'short_name' => 'KRU', 'region' => 'SA', 'country' => 'Chile', 'rating' => 2150, 'earnings' => 60000],
        ['name' => 'Leviatán', 'short_name' => 'LEV', 'region' => 'SA', 'country' => 'Argentina', 'rating' => 2100, 'earnings' => 55000],
        ['name' => 'Infinity Esports', 'short_name' => 'INF', 'region' => 'SA', 'country' => 'Argentina', 'rating' => 2050, 'earnings' => 45000],
        
        // Oceania
        ['name' => 'Chiefs Esports', 'short_name' => 'CHF', 'region' => 'OCE', 'country' => 'Australia', 'rating' => 2100, 'earnings' => 50000],
        ['name' => 'Dire Wolves', 'short_name' => 'DW', 'region' => 'OCE', 'country' => 'Australia', 'rating' => 2050, 'earnings' => 40000],
        ['name' => 'ORDER', 'short_name' => 'ORD', 'region' => 'OCE', 'country' => 'Australia', 'rating' => 2000, 'earnings' => 35000],
        ['name' => 'Bonkers', 'short_name' => 'BNK', 'region' => 'OCE', 'country' => 'Australia', 'rating' => 1950, 'earnings' => 30000],
        
        // Middle East
        ['name' => 'Team Falcons', 'short_name' => 'FAL', 'region' => 'MENA', 'country' => 'Saudi Arabia', 'rating' => 2200, 'earnings' => 70000],
        ['name' => 'Twisted Minds', 'short_name' => 'TM', 'region' => 'MENA', 'country' => 'Saudi Arabia', 'rating' => 2150, 'earnings' => 60000],
        ['name' => 'Anubis Gaming', 'short_name' => 'ANG', 'region' => 'MENA', 'country' => 'Egypt', 'rating' => 2100, 'earnings' => 50000],
    ];
    
    // Insert teams
    $teamStmt = $pdo->prepare("
        INSERT INTO teams (name, short_name, logo, region, country, founded, earnings, rating, platform, game, division, status, created_at, updated_at)
        VALUES (:name, :short_name, :logo, :region, :country, '2024-01-01', :earnings, :rating, 'PC', 'Marvel Rivals', :division, 'Active', NOW(), NOW())
    ");
    
    echo "Inserting " . count($teams) . " teams...\n";
    $teamIds = [];
    foreach ($teams as $team) {
        $division = 'Bronze';
        if ($team['rating'] >= 2500) $division = 'Grandmaster';
        elseif ($team['rating'] >= 2000) $division = 'Master';
        elseif ($team['rating'] >= 1750) $division = 'Diamond';
        elseif ($team['rating'] >= 1500) $division = 'Platinum';
        elseif ($team['rating'] >= 1250) $division = 'Gold';
        elseif ($team['rating'] >= 1000) $division = 'Silver';
        
        $logo = '/teams/' . strtolower(str_replace([' ', '.'], ['-', ''], $team['name'])) . '-logo.png';
        
        $teamStmt->execute([
            'name' => $team['name'],
            'short_name' => $team['short_name'],
            'logo' => $logo,
            'region' => $team['region'],
            'country' => $team['country'],
            'earnings' => $team['earnings'],
            'rating' => $team['rating'],
            'division' => $division
        ]);
        
        $teamIds[$team['name']] = $pdo->lastInsertId();
        echo "  - Added team: {$team['name']}\n";
    }
    
    // Player names and roles
    $firstNames = ['Alex', 'Brandon', 'Chris', 'David', 'Eric', 'Frank', 'George', 'Henry', 'Ian', 'Jake', 
                   'Kevin', 'Leo', 'Mike', 'Nathan', 'Oliver', 'Paul', 'Quinn', 'Ryan', 'Sam', 'Tyler',
                   'Victor', 'William', 'Xavier', 'Yuki', 'Zack', 'Adam', 'Blake', 'Carlos', 'Daniel', 'Ethan'];
    $lastNames = ['Johnson', 'Smith', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                  'Hernandez', 'Lopez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee'];
    $roles = ['Duelist', 'Duelist', 'Vanguard', 'Vanguard', 'Strategist', 'Strategist'];
    
    // Heroes by role
    $duelistHeroes = ['Spider-Man', 'Iron Man', 'Black Panther', 'Hawkeye', 'Winter Soldier', 'Star-Lord', 'Psylocke', 'Scarlet Witch'];
    $vanguardHeroes = ['Venom', 'Thor', 'Hulk', 'Magneto', 'Captain America', 'Doctor Strange', 'Groot', 'Peni Parker'];
    $strategistHeroes = ['Mantis', 'Luna Snow', 'Rocket Raccoon', 'Adam Warlock', 'Loki', 'Jeff the Land Shark', 'Cloak & Dagger'];
    
    // Insert players (6 per team = 342 players)
    $playerStmt = $pdo->prepare("
        INSERT INTO players (name, real_name, nationality, age, role, team_id, earnings, rating, signature_heroes, 
                           social_media, past_teams, avatar, status, country, created_at, updated_at)
        VALUES (:name, :real_name, :nationality, :age, :role, :team_id, :earnings, :rating, :signature_heroes,
                :social_media, :past_teams, :avatar, 'Active', :country, NOW(), NOW())
    ");
    
    echo "Inserting players...\n";
    $playerCount = 0;
    foreach ($teams as $team) {
        foreach ($roles as $roleIndex => $role) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $playerTag = $firstName . rand(100, 999);
            
            // Select signature heroes based on role
            $heroes = [];
            if ($role === 'Duelist') {
                $selectedHeroes = array_rand(array_flip($duelistHeroes), 3);
                $heroes = is_array($selectedHeroes) ? $selectedHeroes : [$selectedHeroes];
            } elseif ($role === 'Vanguard') {
                $selectedHeroes = array_rand(array_flip($vanguardHeroes), 3);
                $heroes = is_array($selectedHeroes) ? $selectedHeroes : [$selectedHeroes];
            } else {
                $selectedHeroes = array_rand(array_flip($strategistHeroes), 3);
                $heroes = is_array($selectedHeroes) ? $selectedHeroes : [$selectedHeroes];
            }
            
            $playerStmt->execute([
                'name' => $playerTag,
                'real_name' => $firstName . ' ' . $lastName,
                'nationality' => $team['country'],
                'age' => rand(18, 28),
                'role' => $role,
                'team_id' => $teamIds[$team['name']],
                'earnings' => rand(5000, 50000),
                'rating' => $team['rating'] + rand(-100, 100),
                'signature_heroes' => json_encode($heroes),
                'social_media' => json_encode([
                    'twitter' => 'https://twitter.com/' . strtolower($playerTag),
                    'twitch' => 'https://twitch.tv/' . strtolower($playerTag)
                ]),
                'past_teams' => json_encode([]),
                'avatar' => '/players/' . strtolower($playerTag) . '.png',
                'country' => $team['country']
            ]);
            
            $playerCount++;
        }
    }
    
    echo "  - Added $playerCount players\n";
    
    echo "\nDatabase population complete!\n";
    echo "Teams: " . count($teams) . "\n";
    echo "Players: " . $playerCount . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}