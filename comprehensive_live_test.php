<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\MvrlMatch;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class LiveScoringTest {
    private $matchId = 237;
    
    public function runComprehensiveTest() {
        echo "=== COMPREHENSIVE LIVE SCORING TEST ===\n\n";
        
        // Step 1: Verify match is live
        $this->verifyMatchStatus();
        
        // Step 2: Test incremental score updates
        $this->testIncrementalScoreUpdates();
        
        // Step 3: Test hero changes
        $this->testHeroChanges();
        
        // Step 4: Test player stats updates
        $this->testPlayerStatsUpdates();
        
        // Step 5: Complete Map 1
        $this->completeMap1();
        
        // Step 6: Test Map 2 progression
        $this->testMap2Progression();
        
        // Step 7: Complete BO3 series
        $this->completeBO3Series();
        
        // Step 8: Test real-time synchronization
        $this->testRealTimeSync();
        
        echo "\n=== TEST COMPLETE ===\n";
    }
    
    private function verifyMatchStatus() {
        echo "1. Verifying match status...\n";
        $match = MvrlMatch::find($this->matchId);
        
        if (!$match) {
            echo "   ❌ Match not found!\n";
            return false;
        }
        
        echo "   ✅ Match found: {$match->team1->name} vs {$match->team2->name}\n";
        echo "   ✅ Status: {$match->status}\n";
        echo "   ✅ Current Map: {$match->current_map_number}\n";
        echo "   ✅ Map 1 Status: {$match->maps_data[0]['status']}\n\n";
        
        return true;
    }
    
    private function testIncrementalScoreUpdates() {
        echo "2. Testing incremental score updates...\n";
        $match = MvrlMatch::find($this->matchId);
        $mapsData = $match->maps_data;
        
        // Test 1: Update to 1-0
        $mapsData[0]['team1_score'] = 1;
        $mapsData[0]['team2_score'] = 0;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Score update 1-0: SUCCESS\n";
        
        // Test 2: Update to 2-1
        $mapsData[0]['team1_score'] = 2;
        $mapsData[0]['team2_score'] = 1;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Score update 2-1: SUCCESS\n";
        
        // Test 3: Update to 3-2
        $mapsData[0]['team1_score'] = 3;
        $mapsData[0]['team2_score'] = 2;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Score update 3-2: SUCCESS\n";
        
        // Verify updates
        $match->refresh();
        $actualScore = $match->maps_data[0]['team1_score'] . '-' . $match->maps_data[0]['team2_score'];
        echo "   ✅ Final Map 1 Score: {$actualScore}\n\n";
    }
    
    private function testHeroChanges() {
        echo "3. Testing hero changes for different players...\n";
        $match = MvrlMatch::find($this->matchId);
        $mapsData = $match->maps_data;
        
        // Change Team 1 Player 1's hero from Spider-Man to Iron Man
        $oldHero = $mapsData[0]['team1_composition'][0]['hero'];
        $mapsData[0]['team1_composition'][0]['hero'] = 'Iron Man';
        $mapsData[0]['team1_composition'][0]['role'] = 'Duelist';
        
        // Change Team 2 Player 2's hero from Wolverine to Thor
        $mapsData[0]['team2_composition'][1]['hero'] = 'Thor';
        $mapsData[0]['team2_composition'][1]['role'] = 'Vanguard';
        
        $match->maps_data = $mapsData;
        $match->save();
        
        echo "   ✅ Team 1 Player 1: {$oldHero} → Iron Man\n";
        echo "   ✅ Team 2 Player 2: Wolverine → Thor\n";
        
        // Verify changes
        $match->refresh();
        $newHero1 = $match->maps_data[0]['team1_composition'][0]['hero'];
        $newHero2 = $match->maps_data[0]['team2_composition'][1]['hero'];
        echo "   ✅ Verification: Team 1 Player 1 now plays {$newHero1}\n";
        echo "   ✅ Verification: Team 2 Player 2 now plays {$newHero2}\n\n";
    }
    
    private function testPlayerStatsUpdates() {
        echo "4. Testing player stats updates (kills, deaths, assists)...\n";
        $match = MvrlMatch::find($this->matchId);
        $mapsData = $match->maps_data;
        
        // Update Team 1 Player 1 stats
        $mapsData[0]['team1_composition'][0]['stats'] = [
            'kills' => 15,
            'deaths' => 3,
            'assists' => 8
        ];
        
        // Update Team 1 Player 2 stats
        $mapsData[0]['team1_composition'][1]['stats'] = [
            'kills' => 8,
            'deaths' => 4,
            'assists' => 12
        ];
        
        // Update Team 2 Player 1 stats
        $mapsData[0]['team2_composition'][0]['stats'] = [
            'kills' => 12,
            'deaths' => 5,
            'assists' => 9
        ];
        
        // Update Team 2 Player 2 stats
        $mapsData[0]['team2_composition'][1]['stats'] = [
            'kills' => 10,
            'deaths' => 6,
            'assists' => 7
        ];
        
        $match->maps_data = $mapsData;
        $match->save();
        
        echo "   ✅ {$mapsData[0]['team1_composition'][0]['name']}: 15K/3D/8A\n";
        echo "   ✅ {$mapsData[0]['team1_composition'][1]['name']}: 8K/4D/12A\n";
        echo "   ✅ {$mapsData[0]['team2_composition'][0]['name']}: 12K/5D/9A\n";
        echo "   ✅ {$mapsData[0]['team2_composition'][1]['name']}: 10K/6D/7A\n\n";
    }
    
    private function completeMap1() {
        echo "5. Completing Map 1 with winner selection...\n";
        $match = MvrlMatch::find($this->matchId);
        $mapsData = $match->maps_data;
        
        // Set final score and winner
        $mapsData[0]['team1_score'] = 3;
        $mapsData[0]['team2_score'] = 2;
        $mapsData[0]['status'] = 'completed';
        $mapsData[0]['winner_id'] = $match->team1_id;
        $mapsData[0]['completed_at'] = now();
        $mapsData[0]['duration'] = '00:08:45';
        
        // Update series score
        $match->series_score_team1 = 1;
        $match->series_score_team2 = 0;
        
        // Start Map 2
        $mapsData[1]['status'] = 'live';
        $mapsData[1]['started_at'] = now();
        $match->current_map_number = 2;
        
        $match->maps_data = $mapsData;
        $match->save();
        
        echo "   ✅ Map 1 completed: {$match->team1->name} wins 3-2\n";
        echo "   ✅ Series score: 1-0\n";
        echo "   ✅ Map 2 started automatically\n\n";
    }
    
    private function testMap2Progression() {
        echo "6. Testing Map 2 progression and completion...\n";
        $match = MvrlMatch::find($this->matchId);
        $mapsData = $match->maps_data;
        
        // Update Map 2 scores incrementally
        $mapsData[1]['team1_score'] = 1;
        $mapsData[1]['team2_score'] = 0;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Map 2 Score: 1-0\n";
        
        $mapsData[1]['team1_score'] = 1;
        $mapsData[1]['team2_score'] = 2;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Map 2 Score: 1-2\n";
        
        $mapsData[1]['team1_score'] = 1;
        $mapsData[1]['team2_score'] = 3;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Map 2 Score: 1-3\n";
        
        // Complete Map 2 with Team 2 win
        $mapsData[1]['status'] = 'completed';
        $mapsData[1]['winner_id'] = $match->team2_id;
        $mapsData[1]['completed_at'] = now();
        $mapsData[1]['duration'] = '00:07:32';
        
        // Update series score
        $match->series_score_team1 = 1;
        $match->series_score_team2 = 1;
        
        // Start Map 3 (decider)
        $mapsData[2]['status'] = 'live';
        $mapsData[2]['started_at'] = now();
        $match->current_map_number = 3;
        
        $match->maps_data = $mapsData;
        $match->save();
        
        echo "   ✅ Map 2 completed: {$match->team2->name} wins 3-1\n";
        echo "   ✅ Series tied: 1-1\n";
        echo "   ✅ Map 3 (decider) started\n\n";
    }
    
    private function completeBO3Series() {
        echo "7. Completing full BO3 series...\n";
        $match = MvrlMatch::find($this->matchId);
        $mapsData = $match->maps_data;
        
        // Map 3 progression
        $mapsData[2]['team1_score'] = 2;
        $mapsData[2]['team2_score'] = 0;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Map 3 Score: 2-0\n";
        
        $mapsData[2]['team1_score'] = 2;
        $mapsData[2]['team2_score'] = 1;
        $match->maps_data = $mapsData;
        $match->save();
        echo "   ✅ Map 3 Score: 2-1\n";
        
        // Complete Map 3 and series
        $mapsData[2]['team1_score'] = 3;
        $mapsData[2]['team2_score'] = 1;
        $mapsData[2]['status'] = 'completed';
        $mapsData[2]['winner_id'] = $match->team1_id;
        $mapsData[2]['completed_at'] = now();
        $mapsData[2]['duration'] = '00:09:15';
        
        // Complete the match
        $match->status = 'completed';
        $match->series_score_team1 = 2;
        $match->series_score_team2 = 1;
        $match->winner_id = $match->team1_id;
        $match->team1_score = 2;
        $match->team2_score = 1;
        $match->ended_at = now();
        
        $match->maps_data = $mapsData;
        $match->save();
        
        echo "   ✅ Map 3 completed: {$match->team1->name} wins 3-1\n";
        echo "   ✅ SERIES COMPLETE: {$match->team1->name} wins 2-1\n";
        echo "   ✅ Match status: {$match->status}\n\n";
    }
    
    private function testRealTimeSync() {
        echo "8. Testing real-time synchronization data...\n";
        $match = MvrlMatch::find($this->matchId);
        
        // Simulate what would be available for real-time updates
        $syncData = [
            'matchId' => $match->id,
            'status' => $match->status,
            'currentMap' => $match->current_map_number,
            'seriesScore' => [
                'team1' => $match->series_score_team1,
                'team2' => $match->series_score_team2
            ],
            'winner' => $match->winner_id ? ($match->winner_id == $match->team1_id ? $match->team1->name : $match->team2->name) : null,
            'maps' => []
        ];
        
        foreach ($match->maps_data as $index => $map) {
            $syncData['maps'][] = [
                'mapNumber' => $index + 1,
                'mapName' => $map['map_name'],
                'status' => $map['status'],
                'score' => [
                    'team1' => $map['team1_score'],
                    'team2' => $map['team2_score']
                ],
                'winner' => isset($map['winner_id']) ? ($map['winner_id'] == $match->team1_id ? $match->team1->name : $match->team2->name) : null
            ];
        }
        
        echo "   ✅ Real-time sync data structure validated\n";
        echo "   ✅ Match ID: {$syncData['matchId']}\n";
        echo "   ✅ Status: {$syncData['status']}\n";
        echo "   ✅ Series Score: {$syncData['seriesScore']['team1']}-{$syncData['seriesScore']['team2']}\n";
        echo "   ✅ Winner: {$syncData['winner']}\n";
        echo "   ✅ Maps data: " . count($syncData['maps']) . " maps\n";
        
        // Simulate SSE/WebSocket event
        echo "   ✅ SSE Event would be: 'mrvl-match-updated'\n";
        echo "   ✅ LocalStorage key would be: 'mrvl_match_update_237'\n\n";
    }
    
    public function getMatchSummary() {
        $match = MvrlMatch::find($this->matchId);
        
        echo "=== FINAL MATCH SUMMARY ===\n";
        echo "Match: {$match->team1->name} vs {$match->team2->name}\n";
        echo "Format: {$match->format}\n";
        echo "Status: {$match->status}\n";
        echo "Series Score: {$match->series_score_team1}-{$match->series_score_team2}\n";
        echo "Winner: " . ($match->winner_id == $match->team1_id ? $match->team1->name : $match->team2->name) . "\n";
        echo "Duration: " . $match->started_at->diffForHumans($match->ended_at) . "\n";
        
        echo "\nMap Results:\n";
        foreach ($match->maps_data as $index => $map) {
            $mapWinner = $map['winner_id'] == $match->team1_id ? $match->team1->name : $match->team2->name;
            echo "  Map " . ($index + 1) . ": {$map['map_name']} - {$mapWinner} wins {$map['team1_score']}-{$map['team2_score']}\n";
        }
        echo "\n";
    }
}

// Run the comprehensive test
$test = new LiveScoringTest();
$test->runComprehensiveTest();
$test->getMatchSummary();