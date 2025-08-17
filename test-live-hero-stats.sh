#!/bin/bash

# Test live hero and stats updates
API_URL="https://staging.mrvl.net/api"
MATCH_ID=7

echo "Testing Live Hero & Stats Updates"
echo "=================================="
echo ""

# Test 1: Hero update for a player
echo "Test 1: Updating hero for player 405 to 'Magik'"
curl -X POST "${API_URL}/admin/matches/${MATCH_ID}/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "hero-update",
    "data": {
      "map_index": "2",
      "team": 1,
      "player_id": 405,
      "hero": "Magik",
      "role": "Duelist"
    }
  }' 2>/dev/null | jq -r '.message // "Success"'

sleep 1

# Test 2: Stats update for the same player
echo ""
echo "Test 2: Updating damage stats for player 405"
curl -X POST "${API_URL}/admin/matches/${MATCH_ID}/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "stats-update",
    "data": {
      "map_index": "2",
      "team": 1,
      "player_id": 405,
      "stat_type": "damage",
      "value": 5500,
      "player_name": "delenaa"
    }
  }' 2>/dev/null | jq -r '.message // "Success"'

sleep 1

# Test 3: Score update (without map switch)
echo ""
echo "Test 3: Updating score for current map"
curl -X POST "${API_URL}/admin/matches/${MATCH_ID}/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "map_index": "2",
      "team1_score": 1,
      "team2_score": 0
    }
  }' 2>/dev/null | jq -r '.message // "Success"'

sleep 1

# Test 4: Map switch with score
echo ""
echo "Test 4: Switching to map 3 with scores"
curl -X POST "${API_URL}/admin/matches/${MATCH_ID}/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "current_map": "3",
      "isMapSwitch": true,
      "map_index": "2",
      "team1_score": 2,
      "team2_score": 1
    }
  }' 2>/dev/null | jq -r '.message // "Success"'

echo ""
echo "Test complete! Check the match detail page for updates."
