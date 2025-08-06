<?php

// Check team and player data integrity
$teams = json_decode(file_get_contents('http://localhost:8000/api/teams'), true);
echo "Total teams: " . $teams['total'] . PHP_EOL;

$players = json_decode(file_get_contents('http://localhost:8000/api/players'), true);
echo "Total players: " . $players['total'] . PHP_EOL;

$team_player_counts = [];
foreach ($players['data'] as $player) {
    $team_name = $player['team']['name'];
    if (!isset($team_player_counts[$team_name])) {
        $team_player_counts[$team_name] = 0;
    }
    $team_player_counts[$team_name]++;
}

echo PHP_EOL . "Teams with player counts:" . PHP_EOL;
foreach ($team_player_counts as $team => $count) {
    echo $team . ": " . $count . " players" . PHP_EOL;
}

echo PHP_EOL . "Teams with 6 players: " . count(array_filter($team_player_counts, function($count) { return $count === 6; })) . PHP_EOL;
echo "Teams with incomplete rosters: " . count(array_filter($team_player_counts, function($count) { return $count < 6; })) . PHP_EOL;

?>