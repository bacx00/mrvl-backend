<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

try {
    // Find our Marvel Rivals tournament
    $tournament = Event::where('name', 'LIKE', '%Marvel Rivals Invitational%')
        ->orderBy('id', 'desc')
        ->first();
    
    if (!$tournament) {
        echo "❌ Tournament not found. Please create it first.\n";
        exit(1);
    }
    
    echo "✅ Found tournament: {$tournament->name} (ID: {$tournament->id})\n";
    
    // Define realistic Marvel Rivals teams based on actual esports organizations
    $teamsData = [
        // NA Teams
        ['name' => 'Sentinels', 'region' => 'NA', 'rating' => 2450, 'seed' => 1],
        ['name' => '100 Thieves', 'region' => 'NA', 'rating' => 2420, 'seed' => 2],
        ['name' => 'Cloud9', 'region' => 'NA', 'rating' => 2390, 'seed' => 3],
        ['name' => 'NRG Esports', 'region' => 'NA', 'rating' => 2365, 'seed' => 4],
        ['name' => 'Evil Geniuses', 'region' => 'NA', 'rating' => 2340, 'seed' => 5],
        ['name' => 'TSM', 'region' => 'NA', 'rating' => 2315, 'seed' => 6],
        
        // EU Teams
        ['name' => 'Team Liquid', 'region' => 'EU', 'rating' => 2290, 'seed' => 7],
        ['name' => 'Fnatic', 'region' => 'EU', 'rating' => 2265, 'seed' => 8],
        ['name' => 'G2 Esports', 'region' => 'EU', 'rating' => 2240, 'seed' => 9],
        ['name' => 'Team Vitality', 'region' => 'EU', 'rating' => 2215, 'seed' => 10],
        
        // APAC Teams
        ['name' => 'T1', 'region' => 'KR', 'rating' => 2190, 'seed' => 11],
        ['name' => 'Gen.G', 'region' => 'KR', 'rating' => 2165, 'seed' => 12],
        ['name' => 'DRX', 'region' => 'KR', 'rating' => 2140, 'seed' => 13],
        ['name' => 'Paper Rex', 'region' => 'SEA', 'rating' => 2115, 'seed' => 14],
        ['name' => 'ZETA DIVISION', 'region' => 'JP', 'rating' => 2090, 'seed' => 15],
        ['name' => 'Crazy Raccoon', 'region' => 'JP', 'rating' => 2065, 'seed' => 16]
    ];
    
    echo "\n📋 Registering teams for tournament...\n";
    
    $registeredCount = 0;
    foreach ($teamsData as $teamData) {
        // Check if team exists
        $team = Team::where('name', $teamData['name'])->first();
        
        if (!$team) {
            // Create the team
            $team = Team::create([
                'name' => $teamData['name'],
                'region' => $teamData['region'],
                'rating' => $teamData['rating'],
                'wins' => rand(10, 50),
                'losses' => rand(5, 20),
                'founded' => now()->subYears(rand(1, 5)),
                'active' => true
            ]);
            echo "  Created team: {$team->name}\n";
        } else {
            echo "  Found existing team: {$team->name}\n";
        }
        
        // Register team for tournament (check if not already registered)
        $existingRegistration = DB::table('event_teams')
            ->where('event_id', $tournament->id)
            ->where('team_id', $team->id)
            ->first();
        
        if (!$existingRegistration) {
            DB::table('event_teams')->insert([
                'event_id' => $tournament->id,
                'team_id' => $team->id,
                'seed' => $teamData['seed'],
                'status' => 'registered',
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $registeredCount++;
            echo "    ✅ Registered for tournament (Seed: {$teamData['seed']})\n";
        } else {
            echo "    ℹ️ Already registered (Seed: {$existingRegistration->seed})\n";
        }
    }
    
    // Verify registrations
    $totalRegistered = DB::table('event_teams')
        ->where('event_id', $tournament->id)
        ->count();
    
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "🎉 TEAM REGISTRATION COMPLETE!\n";
    echo str_repeat('=', 50) . "\n";
    echo "Tournament: {$tournament->name}\n";
    echo "Newly Registered: {$registeredCount} teams\n";
    echo "Total Registered: {$totalRegistered} teams\n";
    echo "\n✅ Tournament is ready for bracket generation!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}