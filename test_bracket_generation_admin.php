<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;
use App\Models\Team;
use App\Models\MatchModel;
use App\Models\User;
use App\Http\Controllers\BracketController;
use Illuminate\Http\Request;

echo "=== TESTING BRACKET GENERATION WITH ADMIN AUTH ===\n";

try {
    // Get admin user
    $admin = User::where('email', 'admin@mrvl.net')->first();
    if (!$admin) {
        echo "ERROR: Admin user not found!\n";
        exit(1);
    }
    
    // Set the authenticated user for this request
    auth('api')->setUser($admin);
    
    echo "Admin user authenticated: {$admin->name}\n";
    echo "Admin roles: " . $admin->roles->pluck('name')->implode(', ') . "\n";
    
    // Get tournament
    $tournament = Event::first();
    echo "Tournament: {$tournament->name}\n";
    echo "Teams in tournament: " . $tournament->teams()->count() . "\n";
    
    // Create bracket controller instance
    $bracketController = new BracketController();
    
    // Create request for single elimination bracket
    $request = new Request([
        'format' => 'single_elimination',
        'start_immediately' => false
    ]);
    
    // Set the authenticated user on the request
    $request->setUserResolver(function () use ($admin) {
        return $admin;
    });
    
    echo "\nGenerating bracket...\n";
    
    $response = $bracketController->generate($request, $tournament->id);
    $responseData = json_decode($response->getContent(), true);
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
    
    // Check if matches were created
    $matches = MatchModel::where('event_id', $tournament->id)->get();
    echo "\nMatches created: " . $matches->count() . "\n";
    
    foreach ($matches as $match) {
        echo "Match {$match->id}: ";
        echo ($match->team1 ? $match->team1->name : 'TBD');
        echo " vs ";
        echo ($match->team2 ? $match->team2->name : 'TBD');
        echo " (Round {$match->round}, Status: {$match->status})\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}