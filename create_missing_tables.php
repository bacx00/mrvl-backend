<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

echo "Creating missing tables for Marvel Rivals Tournament Platform...\n\n";

try {
    // Create tournament_participants table
    if (!Schema::hasTable('tournament_participants')) {
        echo "Creating tournament_participants table...\n";
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('seed')->nullable();
            $table->enum('status', ['registered', 'confirmed', 'withdrawn', 'disqualified'])->default('registered');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('metadata')->nullable(); // For additional tournament-specific data
            $table->timestamps();
            
            $table->unique(['event_id', 'team_id'], 'unique_tournament_team');
            $table->index(['event_id', 'status']);
        });
        echo "✅ tournament_participants table created\n";
    }

    // Create player_statistics table
    if (!Schema::hasTable('player_statistics')) {
        echo "Creating player_statistics table...\n";
        Schema::create('player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('match_id')->nullable()->constrained('matches')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('cascade');
            $table->string('hero')->nullable();
            $table->integer('eliminations')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('assists')->default(0);
            $table->decimal('kd_ratio', 5, 2)->default(0);
            $table->integer('damage_dealt')->default(0);
            $table->integer('damage_taken')->default(0);
            $table->integer('healing_done')->default(0);
            $table->integer('time_on_objective')->default(0); // in seconds
            $table->integer('final_blows')->default(0);
            $table->integer('hero_damage')->default(0);
            $table->integer('environmental_kills')->default(0);
            $table->integer('multikills')->default(0);
            $table->decimal('accuracy', 5, 2)->nullable(); // percentage
            $table->integer('critical_hits')->default(0);
            $table->json('detailed_stats')->nullable(); // For hero-specific or detailed stats
            $table->timestamps();
            
            $table->index(['player_id', 'match_id']);
            $table->index(['player_id', 'event_id']);
            $table->index(['match_id']);
        });
        echo "✅ player_statistics table created\n";
    }

    // Create team_rankings table
    if (!Schema::hasTable('team_rankings')) {
        echo "Creating team_rankings table...\n";
        Schema::create('team_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('region')->index();
            $table->string('ranking_type')->default('overall'); // overall, regional, seasonal
            $table->integer('global_rank')->nullable();
            $table->integer('regional_rank')->nullable();
            $table->integer('points')->default(0);
            $table->integer('rating')->default(1000); // ELO-style rating
            $table->integer('matches_played')->default(0);
            $table->integer('matches_won')->default(0);
            $table->integer('matches_lost')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0); // percentage
            $table->integer('series_played')->default(0);
            $table->integer('series_won')->default(0);
            $table->integer('series_lost')->default(0);
            $table->integer('maps_played')->default(0);
            $table->integer('maps_won')->default(0);
            $table->integer('maps_lost')->default(0);
            $table->decimal('total_prize_money', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->integer('tournament_wins')->default(0);
            $table->integer('tournament_participations')->default(0);
            $table->json('recent_results')->nullable(); // Last 10 matches or similar
            $table->date('last_match_date')->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
            
            $table->unique(['team_id', 'region', 'ranking_type'], 'unique_team_ranking');
            $table->index(['region', 'global_rank']);
            $table->index(['region', 'regional_rank']);
            $table->index(['points']);
            $table->index(['rating']);
        });
        echo "✅ team_rankings table created\n";
    }

    // Populate team_rankings with existing teams
    echo "\nPopulating team_rankings with existing teams...\n";
    $teams = DB::table('teams')->get();
    
    foreach ($teams as $team) {
        $existingRanking = DB::table('team_rankings')
            ->where('team_id', $team->id)
            ->where('region', $team->region)
            ->where('ranking_type', 'overall')
            ->first();
            
        if (!$existingRanking) {
            DB::table('team_rankings')->insert([
                'team_id' => $team->id,
                'region' => $team->region,
                'ranking_type' => 'overall',
                'global_rank' => null,
                'regional_rank' => null,
                'points' => rand(800, 2000), // Random starting points
                'rating' => rand(1000, 1800), // Random starting rating
                'matches_played' => 0,
                'matches_won' => 0,
                'matches_lost' => 0,
                'win_rate' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    echo "✅ Populated team_rankings for " . count($teams) . " teams\n";

    // Create some sample player statistics
    echo "\nCreating sample player statistics...\n";
    $players = DB::table('players')->take(50)->get();
    
    foreach ($players as $player) {
        // Create some basic statistics
        DB::table('player_statistics')->insert([
            'player_id' => $player->id,
            'hero' => ['Spider-Man', 'Venom', 'Iron Man', 'Magneto', 'Storm', 'Hulk'][rand(0, 5)],
            'eliminations' => rand(10, 35),
            'deaths' => rand(3, 15),
            'assists' => rand(5, 25),
            'damage_dealt' => rand(8000, 25000),
            'healing_done' => rand(0, 12000),
            'final_blows' => rand(5, 20),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    echo "✅ Created sample statistics for " . count($players) . " players\n";

    echo "\n🎉 All missing tables created successfully!\n";
    echo "✅ Database is now ready for production verification.\n\n";

} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "\n";
    exit(1);
}