<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tournament;
use App\Models\Team;

try {
    // Create a simple tournament first
    $tournament = Tournament::create([
        'name' => 'Marvel Rivals World Championship 2025',
        'slug' => 'marvel-rivals-world-championship-2025',
        'type' => 'tournament',
        'format' => 'double_elimination',
        'status' => 'upcoming',
        'description' => 'The premier Marvel Rivals tournament featuring the best teams from around the world competing for the ultimate championship.',
        'region' => 'global',
        'prize_pool' => 250000,
        'team_count' => 16,
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(10),
        'settings' => json_encode([
            'bracket_size' => 16,
            'bracket_reset' => true,
            'seeding_method' => 'rating_based',
            'match_format' => 'bo3',
            'grand_final_format' => 'bo5'
        ])
    ]);

    echo "✅ Tournament created successfully\!\n";
    echo "Tournament ID: {$tournament->id}\n";
    echo "Name: {$tournament->name}\n";
    echo "Format: {$tournament->format}\n";
    echo "Status: {$tournament->status}\n";

    // Create another tournament with different format
    $tournament2 = Tournament::create([
        'name' => 'Marvel Rivals Spring Split',
        'slug' => 'marvel-rivals-spring-split',
        'type' => 'tournament',
        'format' => 'swiss',
        'status' => 'ongoing',
        'description' => 'Spring Split Swiss System tournament to qualify for the World Championship.',
        'region' => 'na',
        'prize_pool' => 50000,
        'team_count' => 24,
        'start_date' => now()->subDays(2),
        'end_date' => now()->addDays(5),
        'settings' => json_encode([
            'rounds' => 7,
            'wins_to_qualify' => 5,
            'losses_to_eliminate' => 3,
            'qualified_count' => 8
        ])
    ]);

    echo "\n✅ Second tournament created\!\n";
    echo "Tournament ID: {$tournament2->id}\n";
    echo "Name: {$tournament2->name}\n";

    // Create a completed tournament
    $tournament3 = Tournament::create([
        'name' => 'Marvel Rivals Winter Invitational',
        'slug' => 'marvel-rivals-winter-invitational',
        'type' => 'tournament',
        'format' => 'single_elimination',
        'status' => 'completed',
        'description' => 'Winter Invitational featuring top 8 teams.',
        'region' => 'eu',
        'prize_pool' => 30000,
        'team_count' => 8,
        'start_date' => now()->subDays(30),
        'end_date' => now()->subDays(28),
        'settings' => json_encode([
            'bracket_size' => 8,
            'seeding_method' => 'manual'
        ])
    ]);

    echo "\n✅ Third tournament created\!\n";
    echo "Tournament ID: {$tournament3->id}\n";
    echo "Name: {$tournament3->name}\n";

    // Create GSL format tournament
    $tournament4 = Tournament::create([
        'name' => 'Marvel Rivals Asia Masters',
        'slug' => 'marvel-rivals-asia-masters',
        'type' => 'tournament',
        'format' => 'gsl',
        'status' => 'upcoming',
        'description' => 'Asia Masters tournament with GSL group format.',
        'region' => 'asia',
        'prize_pool' => 75000,
        'team_count' => 16,
        'start_date' => now()->addDays(14),
        'end_date' => now()->addDays(18),
        'settings' => json_encode([
            'group_size' => 4,
            'groups_count' => 4,
            'teams_advance_per_group' => 2
        ])
    ]);

    echo "\n✅ Fourth tournament created\!\n";
    echo "Tournament ID: {$tournament4->id}\n";
    echo "Name: {$tournament4->name}\n";

    // Create Round Robin tournament
    $tournament5 = Tournament::create([
        'name' => 'Marvel Rivals Pro League',
        'slug' => 'marvel-rivals-pro-league',
        'type' => 'tournament',
        'format' => 'round_robin',
        'status' => 'ongoing',
        'description' => 'Professional league with round robin format.',
        'region' => 'na',
        'prize_pool' => 100000,
        'team_count' => 10,
        'start_date' => now()->subDays(7),
        'end_date' => now()->addDays(21),
        'settings' => json_encode([
            'points_for_win' => 3,
            'points_for_tie' => 1,
            'points_for_loss' => 0
        ])
    ]);

    echo "\n✅ Fifth tournament created\!\n";
    echo "Tournament ID: {$tournament5->id}\n";
    echo "Name: {$tournament5->name}\n";

    echo "\n🎉 All tournaments created successfully\!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
EOF < /dev/null
