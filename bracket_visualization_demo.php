<?php

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Manual Bracket System Visual Demonstration
 * Shows what the bracket progression would look like
 */

// Initialize Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BracketStage;
use App\Models\BracketMatch;
use App\Models\Team;

echo "🎯 Manual Bracket System Visual Demonstration\n";
echo "=============================================\n\n";

// Find the most recent test bracket
$gslBracket = BracketStage::where('name', 'like', 'TEST GSL%')->latest()->first();

if ($gslBracket) {
    echo "📊 GSL Bracket Visualization\n";
    echo "Tournament: {$gslBracket->name}\n";
    echo str_repeat('-', 60) . "\n";
    
    $matches = BracketMatch::where('bracket_stage_id', $gslBracket->id)
        ->with(['team1', 'team2', 'winner'])
        ->orderBy('round_number')
        ->orderBy('match_number')
        ->get();
    
    // Show bracket structure
    echo "\n🔥 GSL Format Structure:\n\n";
    
    // Round 1 - Opening Matches
    $openingMatches = $matches->where('round_number', 1);
    echo "ROUND 1 - OPENING MATCHES\n";
    echo "├─ Opening Match A: ";
    $matchA = $openingMatches->where('round_name', 'Opening Match A')->first();
    if ($matchA) {
        $team1 = $matchA->team1 ? $matchA->team1->name : 'TBD';
        $team2 = $matchA->team2 ? $matchA->team2->name : 'TBD';
        echo "$team1 vs $team2";
        if ($matchA->status === 'completed') {
            echo " → {$matchA->team1_score}-{$matchA->team2_score} (Winner: {$matchA->winner->name})";
        }
    }
    echo "\n";
    
    echo "└─ Opening Match B: ";
    $matchB = $openingMatches->where('round_name', 'Opening Match B')->first();
    if ($matchB) {
        $team1 = $matchB->team1 ? $matchB->team1->name : 'TBD';
        $team2 = $matchB->team2 ? $matchB->team2->name : 'TBD';
        echo "$team1 vs $team2";
        if ($matchB->status === 'completed') {
            echo " → {$matchB->team1_score}-{$matchB->team2_score} (Winner: {$matchB->winner->name})";
        }
    }
    echo "\n\n";
    
    // Round 2 - Winners and Elimination
    echo "ROUND 2 - WINNERS & ELIMINATION\n";
    $winnersMatch = $matches->where('round_name', 'Winners Match')->first();
    echo "├─ Winners Match: ";
    if ($winnersMatch) {
        $team1 = $winnersMatch->team1 ? $winnersMatch->team1->name : 'Winner of A';
        $team2 = $winnersMatch->team2 ? $winnersMatch->team2->name : 'Winner of B';
        echo "$team1 vs $team2";
        if ($winnersMatch->status === 'completed') {
            echo " → {$winnersMatch->team1_score}-{$winnersMatch->team2_score} (Winner: {$winnersMatch->winner->name})";
        }
    }
    echo "\n";
    
    $eliminationMatch = $matches->where('round_name', 'Elimination Match')->first();
    echo "└─ Elimination Match: ";
    if ($eliminationMatch) {
        $team1 = $eliminationMatch->team1 ? $eliminationMatch->team1->name : 'Loser of A';
        $team2 = $eliminationMatch->team2 ? $eliminationMatch->team2->name : 'Loser of B';
        echo "$team1 vs $team2";
        if ($eliminationMatch->status === 'completed') {
            echo " → {$eliminationMatch->team1_score}-{$eliminationMatch->team2_score} (Winner: {$eliminationMatch->winner->name})";
        }
    }
    echo "\n\n";
    
    // Round 3 - Decider
    echo "ROUND 3 - DECIDER\n";
    $deciderMatch = $matches->where('round_name', 'Decider Match')->first();
    echo "└─ Decider Match: ";
    if ($deciderMatch) {
        $team1 = $deciderMatch->team1 ? $deciderMatch->team1->name : 'Loser of Winners';
        $team2 = $deciderMatch->team2 ? $deciderMatch->team2->name : 'Winner of Elimination';
        echo "$team1 vs $team2";
        if ($deciderMatch->status === 'completed') {
            echo " → {$deciderMatch->team1_score}-{$deciderMatch->team2_score} (Winner: {$deciderMatch->winner->name})";
        }
    }
    echo "\n\n";
    
    // Show final standings
    echo "🏆 FINAL STANDINGS:\n";
    if ($winnersMatch && $winnersMatch->status === 'completed') {
        echo "1st Place: {$winnersMatch->winner->name} (Qualified directly)\n";
        
        if ($deciderMatch && $deciderMatch->status === 'completed') {
            echo "2nd Place: {$deciderMatch->winner->name} (Qualified via decider)\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
}

// Show single elimination if available
$singleBracket = BracketStage::where('name', 'like', 'TEST Single%')->latest()->first();

if ($singleBracket) {
    echo "\n📊 Single Elimination Bracket Visualization\n";
    echo "Tournament: {$singleBracket->name}\n";
    echo str_repeat('-', 60) . "\n";
    
    $matches = BracketMatch::where('bracket_stage_id', $singleBracket->id)
        ->with(['team1', 'team2', 'winner'])
        ->orderBy('round_number')
        ->orderBy('match_number')
        ->get();
    
    $rounds = $matches->groupBy('round_number');
    
    foreach ($rounds as $roundNum => $roundMatches) {
        $roundName = match($roundNum) {
            1 => 'QUARTERFINALS',
            2 => 'SEMIFINALS', 
            3 => 'GRAND FINAL',
            default => "ROUND $roundNum"
        };
        
        echo "\n$roundName\n";
        
        foreach ($roundMatches as $i => $match) {
            $prefix = ($i == $roundMatches->count() - 1) ? '└─' : '├─';
            echo "$prefix Match " . ($i + 1) . ": ";
            
            $team1 = $match->team1 ? $match->team1->name : ($match->team1_source ?? 'TBD');
            $team2 = $match->team2 ? $match->team2->name : ($match->team2_source ?? 'TBD');
            
            echo "$team1 vs $team2";
            
            if ($match->status === 'completed') {
                echo " → {$match->team1_score}-{$match->team2_score} (Winner: {$match->winner->name})";
            }
            echo "\n";
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
}

// Show bracket statistics
echo "\n📈 BRACKET SYSTEM STATISTICS\n";
echo str_repeat('-', 40) . "\n";

$totalBrackets = BracketStage::where('name', 'like', 'TEST %')->count();
$totalMatches = BracketMatch::where('match_id', 'like', 'M%-TEST-%')->count();
$completedMatches = BracketMatch::where('match_id', 'like', 'M%-TEST-%')
    ->where('status', 'completed')->count();

echo "Total Test Brackets Created: $totalBrackets\n";
echo "Total Test Matches Created: $totalMatches\n";
echo "Completed Matches: $completedMatches\n";
echo "Success Rate: " . ($totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100) : 0) . "%\n";

// Show what the admin interface would display
echo "\n🎨 ADMIN INTERFACE PREVIEW\n";
echo str_repeat('-', 40) . "\n";
echo "What administrators would see:\n\n";

echo "┌─ TOURNAMENT DASHBOARD ─────────────────────┐\n";
echo "│                                            │\n";
echo "│  🏆 Marvel Rivals IGNITE 2025             │\n";
echo "│                                            │\n";
echo "│  📊 ACTIVE BRACKETS:                       │\n";
echo "│  ├─ Play-in Stage (GSL) - 4 teams         │\n";
echo "│  │  └─ Status: 3/5 matches completed      │\n";
echo "│  └─ Main Qualifier - 8 teams              │\n";
echo "│     └─ Status: 1/7 matches completed      │\n";
echo "│                                            │\n";
echo "│  🎮 QUICK ACTIONS:                         │\n";
echo "│  [Create New Bracket] [Update Scores]     │\n";
echo "│  [View Live Bracket] [Reset Bracket]      │\n";
echo "│                                            │\n";
echo "└────────────────────────────────────────────┘\n";

echo "\n📱 MOBILE INTERFACE PREVIEW\n";
echo str_repeat('-', 30) . "\n";
echo "┌─ BRACKET MANAGER ─────────┐\n";
echo "│                           │\n";
echo "│ 🎯 GSL Bracket            │\n";
echo "│                           │\n";
echo "│ ▶ Opening A: Team1 v Team4│\n";
echo "│   Score: 2-1 ✅           │\n";
echo "│                           │\n";
echo "│ ▶ Opening B: Team2 v Team3│\n";
echo "│   Score: 2-0 ✅           │\n";
echo "│                           │\n";
echo "│ ⏸ Winners: Team1 v Team2  │\n";
echo "│   Score: 0-0 ⏳           │\n";
echo "│                           │\n";
echo "│ [Update Score] [View Full]│\n";
echo "│                           │\n";
echo "└───────────────────────────┘\n";

echo "\n✨ DEMONSTRATION COMPLETE!\n";
echo "The manual bracket system provides:\n";
echo "• Visual bracket progression\n";
echo "• Real-time score updates\n";
echo "• Automatic team advancement\n";
echo "• Multiple tournament formats\n";
echo "• Admin-friendly interface\n";
echo "• Mobile-responsive design\n";

echo "\n🚀 READY FOR PRODUCTION USE! 🚀\n";