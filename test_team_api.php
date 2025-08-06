<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$teamId = 1;

// Test the query
$activePlayers = DB::table('players')
    ->where('team_id', $teamId)
    ->where('status', 'active')
    ->where(function($query) {
        $query->where('team_position', 'player')
              ->orWhereNull('team_position');
    })
    ->select([
        'id', 'name', 'username', 'real_name', 'role', 
        'avatar', 'rating', 'main_hero', 'country', 'country_flag', 
        'age', 'status', 'earnings'
    ])
    ->orderBy('rating', 'desc')
    ->limit(6)
    ->get();

echo "Active players count: " . $activePlayers->count() . "\n";
echo "First player:\n";
var_dump($activePlayers->first());

// Test the formatting
$formatRosterMember = function($member) {
    return [
        'id' => $member->id,
        'username' => $member->username,
        'real_name' => $member->real_name,
        'name' => $member->name ?: $member->username,
        'role' => $member->role ?? 'Player',
        'avatar' => $member->avatar,
        'rating' => $member->rating ?? null,
        'main_hero' => $member->main_hero ?? null,
        'country' => $member->country,
        'country_flag' => $member->country_flag,
        'age' => $member->age ?? null,
        'status' => $member->status ?? 'active',
        'earnings' => $member->earnings ?? 0
    ];
};

$currentRoster = $activePlayers->map($formatRosterMember);
echo "\nFormatted roster first item:\n";
var_dump($currentRoster->first());
echo "\nType of roster: " . gettype($currentRoster) . "\n";
echo "Class of roster: " . get_class($currentRoster) . "\n";