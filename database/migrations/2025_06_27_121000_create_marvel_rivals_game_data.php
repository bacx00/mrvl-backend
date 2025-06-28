<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Create marvel_heroes table with official 39 heroes
        Schema::create('marvel_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->enum('role', ['Vanguard', 'Duelist', 'Strategist']);
            $table->string('type', 50); // Tank, DPS, Support
            $table->json('abilities')->nullable();
            $table->json('ultimate_info')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('available_in_competitive')->default(true);
            $table->integer('difficulty_rating')->default(1); // 1-5
            $table->timestamps();
            
            $table->index(['role', 'available_in_competitive']);
        });

        // Create marvel_maps table with official 11 maps
        Schema::create('marvel_maps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('environment', 100);
            $table->json('supported_modes'); // Array of game modes
            $table->enum('map_type', ['competitive', 'casual', 'arcade'])->default('competitive');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->json('strategic_points')->nullable(); // Control points, payload routes
            $table->boolean('available_in_ranked')->default(true);
            $table->timestamps();
            
            $table->index(['map_type', 'available_in_ranked']);
        });

        // Create game_modes table with official 5 modes
        Schema::create('game_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description');
            $table->string('objective');
            $table->json('timer_settings'); // Default timers for mode
            $table->json('scoring_system'); // How points/wins are calculated
            $table->boolean('team_role_swaps')->default(false); // Convoy mode
            $table->integer('max_rounds')->default(1); // For best-of formats
            $table->json('overtime_rules')->nullable();
            $table->boolean('available_in_competitive')->default(true);
            $table->timestamps();
        });

        // Insert official Marvel Rivals data
        $this->insertHeroesData();
        $this->insertMapsData();
        $this->insertGameModesData();
    }

    private function insertHeroesData()
    {
        $heroes = [
            // Vanguard (10 heroes)
            ['name' => 'Captain America', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 2],
            ['name' => 'Doctor Strange', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 4],
            ['name' => 'Emma Frost', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 3],
            ['name' => 'Groot', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 2],
            ['name' => 'Hulk', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 1],
            ['name' => 'Magneto', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 4],
            ['name' => 'Peni Parker', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 3],
            ['name' => 'The Thing', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 1],
            ['name' => 'Thor', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 2],
            ['name' => 'Venom', 'role' => 'Vanguard', 'type' => 'Tank', 'difficulty_rating' => 3],

            // Duelist (20 heroes)
            ['name' => 'Black Panther', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Black Widow', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 2],
            ['name' => 'Hawkeye', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 4],
            ['name' => 'Hela', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Human Torch', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 2],
            ['name' => 'Iron Fist', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Iron Man', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Magik', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 4],
            ['name' => 'Mister Fantastic', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Moon Knight', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 4],
            ['name' => 'Namor', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Psylocke', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 4],
            ['name' => 'Scarlet Witch', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 4],
            ['name' => 'Spider-Man', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Squirrel Girl', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 2],
            ['name' => 'Star-Lord', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 2],
            ['name' => 'Storm', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'The Punisher', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 2],
            ['name' => 'Winter Soldier', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 3],
            ['name' => 'Wolverine', 'role' => 'Duelist', 'type' => 'DPS', 'difficulty_rating' => 2],

            // Strategist (9 heroes)
            ['name' => 'Adam Warlock', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 4],
            ['name' => 'Cloak & Dagger', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 4],
            ['name' => 'Invisible Woman', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 3],
            ['name' => 'Jeff the Land Shark', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 1],
            ['name' => 'Loki', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 4],
            ['name' => 'Luna Snow', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 2],
            ['name' => 'Mantis', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 2],
            ['name' => 'Rocket Raccoon', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 3],
            ['name' => 'Ultron', 'role' => 'Strategist', 'type' => 'Support', 'difficulty_rating' => 4],
        ];

        foreach ($heroes as $hero) {
            DB::table('marvel_heroes')->insert(array_merge($hero, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    private function insertMapsData()
    {
        $maps = [
            [
                'name' => 'Yggsgard: Royal Palace',
                'environment' => 'Asgardian Palace',
                'supported_modes' => json_encode(['Domination', 'Convergence']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Intergalactic Empire of Wakanda: Birnin T\'Challa',
                'environment' => 'Futuristic Wakanda',
                'supported_modes' => json_encode(['Domination', 'Convoy']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Hydra Charteris Base: Hell\'s Heaven',
                'environment' => 'Military Base',
                'supported_modes' => json_encode(['Domination']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Yggsgard: Yggdrasill Path',
                'environment' => 'World Tree',
                'supported_modes' => json_encode(['Convoy']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Tokyo 2099: Spider-Islands',
                'environment' => 'Futuristic Tokyo',
                'supported_modes' => json_encode(['Convoy', 'Domination']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Empire of Eternal Night: Midtown',
                'environment' => 'Dark Dimension',
                'supported_modes' => json_encode(['Convoy']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Tokyo 2099: Shin-Shibuya',
                'environment' => 'Neo Tokyo',
                'supported_modes' => json_encode(['Convergence']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Intergalactic Empire of Wakanda: Hall of Djalia',
                'environment' => 'Wakandan Temple',
                'supported_modes' => json_encode(['Convergence']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Klyntar: Symbiotic Surface',
                'environment' => 'Alien Homeworld',
                'supported_modes' => json_encode(['Convergence']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Tokyo 2099: Ninomaru',
                'environment' => 'Cyber City',
                'supported_modes' => json_encode(['Conquest']),
                'map_type' => 'competitive'
            ],
            [
                'name' => 'Sanctum Sanctorum',
                'environment' => 'Mystic Sanctuary',
                'supported_modes' => json_encode(['Doom Match']),
                'map_type' => 'competitive'
            ]
        ];

        foreach ($maps as $map) {
            DB::table('marvel_maps')->insert(array_merge($map, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    private function insertGameModesData()
    {
        $modes = [
            [
                'name' => 'Domination',
                'description' => 'Teams compete to control a single point, known as the Mission Area. The team that holds the point until their progress bar reaches 100% wins the round.',
                'objective' => 'Capture and hold control points',
                'timer_settings' => json_encode([
                    'default_duration' => 600, // 10 minutes
                    'overtime_enabled' => true,
                    'grace_period_ms' => 500
                ]),
                'scoring_system' => json_encode([
                    'type' => 'best_of_rounds',
                    'rounds_to_win' => 2,
                    'max_rounds' => 3
                ]),
                'team_role_swaps' => false,
                'max_rounds' => 3
            ],
            [
                'name' => 'Convoy',
                'description' => 'One team escorts a moving vehicle to the end of the map, while the opposing team attempts to stop them. The vehicle moves forward when attackers are nearby.',
                'objective' => 'Escort payload to destination',
                'timer_settings' => json_encode([
                    'default_duration' => 480, // 8 minutes per side
                    'overtime_enabled' => true,
                    'grace_period_ms' => 500
                ]),
                'scoring_system' => json_encode([
                    'type' => 'distance_based',
                    'measurement' => 'percentage_complete'
                ]),
                'team_role_swaps' => true,
                'max_rounds' => 2
            ],
            [
                'name' => 'Convergence',
                'description' => 'A hybrid mode where attackers first capture a control point to unlock a convoy, which they must then escort to its destination.',
                'objective' => 'Capture point then escort payload',
                'timer_settings' => json_encode([
                    'default_duration' => 600, // 10 minutes
                    'overtime_enabled' => true,
                    'grace_period_ms' => 500
                ]),
                'scoring_system' => json_encode([
                    'type' => 'hybrid_progress',
                    'capture_points' => 1,
                    'escort_distance' => 100
                ]),
                'team_role_swaps' => true,
                'max_rounds' => 2
            ],
            [
                'name' => 'Conquest',
                'description' => 'A team deathmatch where teams earn points by defeating opponents. The first team to reach 50 points or the team with the most points when time runs out wins.',
                'objective' => 'First team to 50 eliminations wins',
                'timer_settings' => json_encode([
                    'default_duration' => 420, // 7 minutes
                    'overtime_enabled' => false
                ]),
                'scoring_system' => json_encode([
                    'type' => 'elimination_count',
                    'target_score' => 50
                ]),
                'team_role_swaps' => false,
                'max_rounds' => 1
            ],
            [
                'name' => 'Doom Match',
                'description' => 'A free-for-all mode where 12 players compete individually. Points are earned by landing final hits, and the game ends when a player achieves 16 final hits.',
                'objective' => 'First to 16 final hits wins',
                'timer_settings' => json_encode([
                    'default_duration' => 600, // 10 minutes
                    'overtime_enabled' => false
                ]),
                'scoring_system' => json_encode([
                    'type' => 'free_for_all',
                    'target_score' => 16,
                    'winners' => 6
                ]),
                'team_role_swaps' => false,
                'max_rounds' => 1
            ]
        ];

        foreach ($modes as $mode) {
            DB::table('game_modes')->insert(array_merge($mode, [
                'available_in_competitive' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    public function down()
    {
        Schema::dropIfExists('game_modes');
        Schema::dropIfExists('marvel_maps');
        Schema::dropIfExists('marvel_heroes');
    }
};