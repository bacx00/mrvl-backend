<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarvelRivalsDataSeeder extends Seeder
{
    public function run()
    {
        // Marvel Rivals Maps Data (Season 2.5 - July 2025)
        $maps = [
            // DOMINATION MAPS (4 total)
            [
                'name' => 'Hellfire Gala: Krakoa',
                'slug' => 'hellfire-gala-krakoa',
                'game_mode' => 'Domination',
                'type' => 'Competitive',
                'location' => 'Krakoa',
                'description' => 'NEW Season 2 competitive map - Best of 3 rounds format',
                'active' => true
            ],
            [
                'name' => 'Yggsgard: Royal Palace',
                'slug' => 'yggsgard-royal-palace',
                'game_mode' => 'Domination', 
                'type' => 'Removed',
                'location' => 'Yggsgard',
                'description' => 'REMOVED Season 2 - Previously competitive domination map',
                'active' => false
            ],
            [
                'name' => 'Hydra Charteris Base: Hell\'s Heaven',
                'slug' => 'hydra-charteris-base-hells-heaven',
                'game_mode' => 'Domination',
                'type' => 'Competitive',
                'location' => 'Hydra Base',
                'description' => 'Competitive domination map - Control strategic points',
                'active' => true
            ],
            [
                'name' => 'Intergalactic Empire of Wakanda: Birnin T\'Challa',
                'slug' => 'wakanda-birnin-tchalla',
                'game_mode' => 'Domination',
                'type' => 'Competitive',
                'location' => 'Wakanda',
                'description' => 'Competitive domination map - Wakandan technology theme',
                'active' => true
            ],
            
            // CONVOY MAPS (4 total)
            [
                'name' => 'Empire of Eternal Night: Central Park',
                'slug' => 'empire-eternal-night-central-park',
                'game_mode' => 'Convoy',
                'type' => 'Competitive',
                'location' => 'Central Park',
                'description' => 'THE ONLY competitive convoy map - Escort payload through checkpoints',
                'active' => true
            ],
            [
                'name' => 'Tokyo 2099: Spider-Islands',
                'slug' => 'tokyo-2099-spider-islands',
                'game_mode' => 'Convoy',
                'type' => 'Casual',
                'location' => 'Tokyo 2099',
                'description' => 'Casual convoy map - Futuristic Tokyo setting',
                'active' => true
            ],
            [
                'name' => 'Yggsgard: Yggdrasill Path',
                'slug' => 'yggsgard-yggdrasill-path',
                'game_mode' => 'Convoy',
                'type' => 'Casual',
                'location' => 'Yggsgard',
                'description' => 'Casual convoy map - Mystical Asgardian pathways',
                'active' => true
            ],
            [
                'name' => 'Empire of Eternal Night: Midtown',
                'slug' => 'empire-eternal-night-midtown',
                'game_mode' => 'Convoy',
                'type' => 'Casual',
                'location' => 'Midtown',
                'description' => 'Casual convoy map - Urban battlefield',
                'active' => true
            ],
            
            // CONVERGENCE MAPS (4 total)
            [
                'name' => 'Empire of Eternal Night: Sanctum Sanctorum',
                'slug' => 'empire-eternal-night-sanctum-sanctorum',
                'game_mode' => 'Convergence',
                'type' => 'Casual',
                'location' => 'Sanctum Sanctorum',
                'description' => 'Convergence map - Doctor Strange\'s mystical sanctum',
                'active' => true
            ],
            [
                'name' => 'Tokyo 2099: Shin-Shibuya',
                'slug' => 'tokyo-2099-shin-shibuya',
                'game_mode' => 'Convergence',
                'type' => 'Removed',
                'location' => 'Tokyo 2099',
                'description' => 'REMOVED Season 2 - Previously available convergence map',
                'active' => false
            ],
            [
                'name' => 'Klyntar: Symbiotic Surface',
                'slug' => 'klyntar-symbiotic-surface',
                'game_mode' => 'Convergence',
                'type' => 'Casual',
                'location' => 'Klyntar',
                'description' => 'Convergence map - Symbiote home planet surface',
                'active' => true
            ],
            [
                'name' => 'Intergalactic Empire of Wakanda: Hall of Djalia',
                'slug' => 'wakanda-hall-of-djalia',
                'game_mode' => 'Convergence',
                'type' => 'Casual',
                'location' => 'Wakanda',
                'description' => 'Convergence map - Sacred Wakandan hall',
                'active' => true
            ]
        ];

        foreach ($maps as $map) {
            $mapData = [
                'name' => $map['name'],
                'game_mode' => $map['game_mode'],
                'description' => $map['description'] ?? null,
                'is_competitive' => $map['type'] === 'Competitive' ? 1 : 0,
                'season' => 'Season 2',
                'status' => $map['active'] ? 'active' : 'removed',
                'created_at' => now(),
                'updated_at' => now()
            ];
            DB::table('marvel_rivals_maps')->updateOrInsert(
                ['name' => $map['name']],
                $mapData
            );
        }

        // Marvel Rivals Heroes Data (39 total as of Season 2.5)
        $heroes = [
            // VANGUARDS (12 Heroes)
            ['name' => 'Captain America', 'slug' => 'captain-america', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Doctor Strange', 'slug' => 'doctor-strange', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Groot', 'slug' => 'groot', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Hulk', 'slug' => 'hulk', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Magneto', 'slug' => 'magneto', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Peni Parker', 'slug' => 'peni-parker', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Thor', 'slug' => 'thor', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Venom', 'slug' => 'venom', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Emma Frost', 'slug' => 'emma-frost', 'role' => 'Vanguard', 'season_added' => 'Season 2', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true, 'is_new' => true],
            ['name' => 'Bruce Banner', 'slug' => 'bruce-banner', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Mr. Fantastic', 'slug' => 'mr-fantastic', 'role' => 'Vanguard', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],

            // DUELISTS (19 Heroes)
            ['name' => 'Black Panther', 'slug' => 'black-panther', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Black Widow', 'slug' => 'black-widow', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Hawkeye', 'slug' => 'hawkeye', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Hela', 'slug' => 'hela', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Iron Man', 'slug' => 'iron-man', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Magik', 'slug' => 'magik', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Moon Knight', 'slug' => 'moon-knight', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Namor', 'slug' => 'namor', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Psylocke', 'slug' => 'psylocke', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Punisher', 'slug' => 'punisher', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Scarlet Witch', 'slug' => 'scarlet-witch', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Spider-Man', 'slug' => 'spider-man', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Star-Lord', 'slug' => 'star-lord', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Storm', 'slug' => 'storm', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Wolverine', 'slug' => 'wolverine', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Winter Soldier', 'slug' => 'winter-soldier', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Iron Fist', 'slug' => 'iron-fist', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Squirrel Girl', 'slug' => 'squirrel-girl', 'role' => 'Duelist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],

            // STRATEGISTS (8 Heroes)
            ['name' => 'Adam Warlock', 'slug' => 'adam-warlock', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Cloak & Dagger', 'slug' => 'cloak-and-dagger', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Jeff the Land Shark', 'slug' => 'jeff-the-land-shark', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Loki', 'slug' => 'loki', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Hard', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Luna Snow', 'slug' => 'luna-snow', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Mantis', 'slug' => 'mantis', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Easy', 'universe' => 'Marvel', 'active' => true],
            ['name' => 'Rocket Raccoon', 'slug' => 'rocket-raccoon', 'role' => 'Strategist', 'season_added' => 'Season 1', 'difficulty' => 'Medium', 'universe' => 'Marvel', 'active' => true]
        ];

        foreach ($heroes as $hero) {
            DB::table('marvel_rivals_heroes')->updateOrInsert(
                ['slug' => $hero['slug']],
                array_merge($hero, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        // Game Mode Specifications
        $gameModes = [
            [
                'name' => 'Domination',
                'format' => 'Best of 3 rounds',
                'preparation_time' => 30,
                'description' => 'Capture and control strategic points. 1% progress per 1.2 seconds when controlled.',
                'rules' => json_encode([
                    'rounds' => 3,
                    'prep_time' => '30 seconds preparation before Mission Area unlock',
                    'capture_rate' => '1% progress per 1.2 seconds when controlled',
                    'overtime' => 'Triggered when contested near round end',
                    'competitive_maps' => 4,
                    'total_maps' => 4
                ])
            ],
            [
                'name' => 'Convoy',
                'format' => '2 rounds in Competitive',
                'preparation_time' => 30,
                'description' => 'Escort the payload through 2 checkpoints + final destination.',
                'rules' => json_encode([
                    'rounds' => 2,
                    'prep_time' => '30 seconds defender preparation',
                    'checkpoints' => '2 checkpoints + final destination',
                    'team_swap' => 'Teams swap attack/defend each round',
                    'competitive_maps' => 1,
                    'total_maps' => 4
                ])
            ],
            [
                'name' => 'Convergence',
                'format' => '2 phases per round',
                'preparation_time' => 30,
                'description' => 'Phase 1: Capture point, Phase 2: Escort payload if successful.',
                'rules' => json_encode([
                    'phase_1' => 'Capture point (30 second defender prep)',
                    'phase_2' => 'Escort payload if Phase 1 successful',
                    'rounds' => 2,
                    'team_swap' => 'Teams swap sides each round',
                    'competitive_maps' => 0,
                    'total_maps' => 4
                ])
            ]
        ];

        foreach ($gameModes as $mode) {
            DB::table('game_modes')->updateOrInsert(
                ['name' => $mode['name']],
                array_merge($mode, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        // Tournament Formats (BO1, BO3, BO5, BO7, BO9)
        $formats = [
            ['name' => 'BO1', 'description' => 'Best of 1', 'maps_to_win' => 1, 'max_maps' => 1],
            ['name' => 'BO3', 'description' => 'Best of 3', 'maps_to_win' => 2, 'max_maps' => 3],
            ['name' => 'BO5', 'description' => 'Best of 5', 'maps_to_win' => 3, 'max_maps' => 5],
            ['name' => 'BO7', 'description' => 'Best of 7', 'maps_to_win' => 4, 'max_maps' => 7],
            ['name' => 'BO9', 'description' => 'Best of 9', 'maps_to_win' => 5, 'max_maps' => 9]
        ];

        foreach ($formats as $format) {
            DB::table('tournament_formats')->updateOrInsert(
                ['name' => $format['name']],
                array_merge($format, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        echo "✅ Marvel Rivals Season 2.5 data seeded successfully!" . PHP_EOL;
        echo "Maps: " . count($maps) . " (4 Competitive: 3 Domination + 1 Convoy)" . PHP_EOL;
        echo "Heroes: " . count($heroes) . " (12 Vanguards, 18 Duelists, 7 Strategists)" . PHP_EOL;
        echo "Game Modes: " . count($gameModes) . PHP_EOL;
        echo "Tournament Formats: " . count($formats) . " (BO1, BO3, BO5, BO7, BO9)" . PHP_EOL;
    }
}