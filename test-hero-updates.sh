#!/bin/bash

# Test hero updates functionality
API_URL="http://localhost/api"
MATCH_ID=7

echo "Testing Hero Updates"
echo "===================="
echo ""

# First, get the current match data
echo "Current match data:"
php artisan tinker --execute="
\$match = \App\Models\MvrlMatch::find($MATCH_ID);
\$mapsData = is_string(\$match->maps_data) ? json_decode(\$match->maps_data, true) : \$match->maps_data;
if (isset(\$mapsData[0]['team1_composition'][0])) {
    echo 'Team 1 Player 1: ' . \$mapsData[0]['team1_composition'][0]['hero'] . PHP_EOL;
}
if (isset(\$mapsData[0]['team2_composition'][0])) {
    echo 'Team 2 Player 1: ' . \$mapsData[0]['team2_composition'][0]['hero'] . PHP_EOL;
}
"

echo ""
echo "Test 1: Update Team 1 Player Hero to 'Spider-Man'..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "hero-update",
    "data": {
      "player_id": 1,
      "player_name": "Player One",
      "hero": "Spider-Man",
      "role": "Duelist",
      "team": 1,
      "map_index": 0
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

echo ""
echo "Test 2: Update Team 2 Player Hero to 'Iron Man'..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "hero-update",
    "data": {
      "player_id": 7,
      "player_name": "Player Seven",
      "hero": "Iron Man",
      "role": "Duelist",
      "team": 2,
      "map_index": 0
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

echo ""
echo "Checking database after hero updates..."
php artisan tinker --execute="
\$match = \App\Models\MvrlMatch::find($MATCH_ID);
\$mapsData = is_string(\$match->maps_data) ? json_decode(\$match->maps_data, true) : \$match->maps_data;

echo 'Map 1 Team Compositions:' . PHP_EOL;
echo '------------------------' . PHP_EOL;

if (isset(\$mapsData[0]['team1_composition'])) {
    echo 'Team 1:' . PHP_EOL;
    foreach (\$mapsData[0]['team1_composition'] as \$player) {
        if (isset(\$player['player_id']) && \$player['player_id'] == 1) {
            echo '  Player ID ' . \$player['player_id'] . ': ' . \$player['hero'] . ' (' . \$player['role'] . ')' . PHP_EOL;
        }
    }
}

if (isset(\$mapsData[0]['team2_composition'])) {
    echo 'Team 2:' . PHP_EOL;
    foreach (\$mapsData[0]['team2_composition'] as \$player) {
        if (isset(\$player['player_id']) && \$player['player_id'] == 7) {
            echo '  Player ID ' . \$player['player_id'] . ': ' . \$player['hero'] . ' (' . \$player['role'] . ')' . PHP_EOL;
        }
    }
}
"