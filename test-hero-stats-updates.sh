#!/bin/bash

# Test hero and stats updates for live scoring
API_URL="https://staging.mrvl.net/api"
MATCH_ID=7
TOKEN="1|RiSBCksAgjwjN8pOLCVCXjJgaJpCKu0UvCjykfrR"

echo "===================================="
echo "Testing Hero & Stats Updates"
echo "Match ID: $MATCH_ID"
echo "===================================="

# Test 1: Send a hero update for player 1 team 1
echo -e "\n1. Testing hero update for Team 1 Player 1..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "hero-update",
    "data": {
      "map_index": 0,
      "team": 1,
      "player_id": 1,
      "hero": "Spider-Man",
      "role": "Duelist",
      "player_name": "Player1"
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

sleep 1

# Test 2: Send stats update for same player
echo -e "\n2. Testing stats update for Team 1 Player 1..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "stats-update",
    "data": {
      "map_index": 0,
      "team": 1,
      "player_id": 1,
      "stat_type": "kills",
      "value": 10,
      "player_name": "Player1"
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

sleep 1

# Test 3: Send map score update
echo -e "\n3. Testing map score update..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "map_index": 0,
      "team1_score": 100,
      "team2_score": 75,
      "status": "in_progress"
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

sleep 1

# Test 4: Get match data to verify updates
echo -e "\n4. Fetching match data to verify updates..."
curl -s -X GET "$API_URL/matches/$MATCH_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '.data | {
    id,
    team1_score,
    team2_score,
    status,
    maps: .maps | map({
      map_name,
      team1_score,
      team2_score,
      status,
      team1_players: .team1_players | length,
      team2_players: .team2_players | length,
      first_player_hero: .team1_players[0].hero,
      first_player_kills: .team1_players[0].kills
    })
  }'

echo -e "\n===================================="
echo "Test Complete!"
echo "Check the match detail page to verify:"
echo "1. Hero selection is visible"
echo "2. Player stats are updated"
echo "3. Map scores are correct"
echo "===================================="