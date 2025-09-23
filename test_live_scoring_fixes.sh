#!/bin/bash

# Test script for live scoring fixes
# This tests:
# 1. Map names persistence
# 2. Player data persistence
# 3. Status persistence

echo "Testing Live Scoring Fixes..."
echo "=============================="

# API configuration
BASE_URL="https://staging.mrvl.net/api"
ADMIN_TOKEN="4|w0Eq6fWJahfXJT7lxu4S4vLPgJvBLkj9fZPzBfQu2e9d0cb3"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Create a completed BO7 match with custom map names
echo -e "\n${YELLOW}Test 1: Creating BO7 match with custom map names...${NC}"

MATCH_DATA='{
  "team1_id": 1,
  "team2_id": 2,
  "event_id": 1,
  "scheduled_at": "2025-09-21 20:00:00",
  "format": "BO7",
  "status": "completed",
  "maps": [
    {"map_name": "Tokyo 2099", "mode": "Push"},
    {"map_name": "Midtown", "mode": "Convergence"},
    {"map_name": "Yggsgard", "mode": "Convoy"},
    {"map_name": "Shibuya Sky", "mode": "Domination"},
    {"map_name": "Sanctum Sanctorum", "mode": "Push"},
    {"map_name": "Klyntar", "mode": "Convergence"},
    {"map_name": "Hells Heaven", "mode": "Convoy"}
  ]
}'

RESPONSE=$(curl -s -X POST \
  "${BASE_URL}/admin/matches" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "${MATCH_DATA}")

MATCH_ID=$(echo $RESPONSE | jq -r '.data.id // .match.id // .id')

if [ "$MATCH_ID" != "null" ] && [ -n "$MATCH_ID" ]; then
  echo -e "${GREEN}✓ Match created with ID: ${MATCH_ID}${NC}"
else
  echo -e "${RED}✗ Failed to create match${NC}"
  echo "Response: $RESPONSE"
  exit 1
fi

# Test 2: Check if status is preserved (should be completed, not upcoming)
echo -e "\n${YELLOW}Test 2: Checking if status is preserved as 'completed'...${NC}"

MATCH_DETAILS=$(curl -s -X GET \
  "${BASE_URL}/matches/${MATCH_ID}" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}")

STATUS=$(echo $MATCH_DETAILS | jq -r '.data.status // .status')

if [ "$STATUS" == "completed" ]; then
  echo -e "${GREEN}✓ Status is correctly preserved as 'completed'${NC}"
else
  echo -e "${RED}✗ Status is incorrect: ${STATUS} (expected: completed)${NC}"
fi

# Test 3: Update live scoring data with player stats and map names
echo -e "\n${YELLOW}Test 3: Updating live scoring with player data and custom map names...${NC}"

UPDATE_DATA='{
  "type": "score-update",
  "data": {
    "team1_score": 4,
    "team2_score": 2,
    "series_score_team1": 4,
    "series_score_team2": 2,
    "status": "completed",
    "maps": [
      {
        "map_number": 1,
        "map_name": "Tokyo 2099 - Updated",
        "team1_score": 1,
        "team2_score": 0,
        "status": "completed"
      },
      {
        "map_number": 2,
        "map_name": "Midtown - Updated",
        "team1_score": 1,
        "team2_score": 0,
        "status": "completed"
      },
      {
        "map_number": 3,
        "map_name": "Yggsgard - Updated",
        "team1_score": 0,
        "team2_score": 1,
        "status": "completed"
      },
      {
        "map_number": 4,
        "map_name": "Shibuya Sky - Updated",
        "team1_score": 1,
        "team2_score": 0,
        "status": "completed"
      },
      {
        "map_number": 5,
        "map_name": "Sanctum Sanctorum - Updated",
        "team1_score": 0,
        "team2_score": 1,
        "status": "completed"
      },
      {
        "map_number": 6,
        "map_name": "Klyntar - Updated",
        "team1_score": 1,
        "team2_score": 0,
        "status": "completed"
      },
      {
        "map_number": 7,
        "map_name": "Hells Heaven - Not Played",
        "team1_score": 0,
        "team2_score": 0,
        "status": "pending"
      }
    ],
    "team1_players": [
      {"id": "p1", "name": "Player 1", "hero": "Iron Man", "kills": 15, "deaths": 8, "assists": 12},
      {"id": "p2", "name": "Player 2", "hero": "Doctor Strange", "kills": 10, "deaths": 5, "assists": 20},
      {"id": "p3", "name": "Player 3", "hero": "Venom", "kills": 18, "deaths": 10, "assists": 8},
      {"id": "p4", "name": "Player 4", "hero": "Mantis", "kills": 5, "deaths": 3, "assists": 25},
      {"id": "p5", "name": "Player 5", "hero": "Thor", "kills": 12, "deaths": 6, "assists": 15},
      {"id": "p6", "name": "Player 6", "hero": "Magneto", "kills": 8, "deaths": 4, "assists": 18}
    ],
    "team2_players": [
      {"id": "p7", "name": "Player 7", "hero": "Spider-Man", "kills": 11, "deaths": 9, "assists": 14},
      {"id": "p8", "name": "Player 8", "hero": "Luna Snow", "kills": 4, "deaths": 2, "assists": 28},
      {"id": "p9", "name": "Player 9", "hero": "Punisher", "kills": 16, "deaths": 11, "assists": 6},
      {"id": "p10", "name": "Player 10", "hero": "Hulk", "kills": 8, "deaths": 8, "assists": 10},
      {"id": "p11", "name": "Player 11", "hero": "Black Panther", "kills": 14, "deaths": 7, "assists": 12},
      {"id": "p12", "name": "Player 12", "hero": "Loki", "kills": 7, "deaths": 5, "assists": 22}
    ]
  }
}'

UPDATE_RESPONSE=$(curl -s -X POST \
  "${BASE_URL}/matches/${MATCH_ID}/live-update" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "${UPDATE_DATA}")

UPDATE_SUCCESS=$(echo $UPDATE_RESPONSE | jq -r '.success')

if [ "$UPDATE_SUCCESS" == "true" ]; then
  echo -e "${GREEN}✓ Live scoring updated successfully${NC}"
else
  echo -e "${RED}✗ Failed to update live scoring${NC}"
  echo "Response: $UPDATE_RESPONSE"
fi

# Test 4: Verify map names are preserved
echo -e "\n${YELLOW}Test 4: Verifying map names are preserved...${NC}"

UPDATED_MATCH=$(curl -s -X GET \
  "${BASE_URL}/matches/${MATCH_ID}" \
  -H "Authorization: Bearer ${ADMIN_TOKEN}")

# Check first map name
MAP1_NAME=$(echo $UPDATED_MATCH | jq -r '.data.maps_data[0].map_name // .maps_data[0].map_name // "not found"')

if [[ "$MAP1_NAME" == *"Updated"* ]]; then
  echo -e "${GREEN}✓ Map names are correctly preserved${NC}"
  echo "  - Map 1: $MAP1_NAME"
else
  echo -e "${RED}✗ Map names reverted or not found${NC}"
  echo "  - Map 1: $MAP1_NAME"
fi

# Test 5: Verify player data is preserved
echo -e "\n${YELLOW}Test 5: Verifying player data is preserved...${NC}"

TEAM1_PLAYER1=$(echo $UPDATED_MATCH | jq -r '.data.team1_players[0].name // .team1_players[0].name // "not found"')
TEAM1_PLAYER1_KILLS=$(echo $UPDATED_MATCH | jq -r '.data.team1_players[0].kills // .team1_players[0].kills // "0"')

if [ "$TEAM1_PLAYER1" == "Player 1" ] && [ "$TEAM1_PLAYER1_KILLS" == "15" ]; then
  echo -e "${GREEN}✓ Player data is correctly preserved${NC}"
  echo "  - $TEAM1_PLAYER1: $TEAM1_PLAYER1_KILLS kills"
else
  echo -e "${RED}✗ Player data not preserved correctly${NC}"
  echo "  - Player name: $TEAM1_PLAYER1 (expected: Player 1)"
  echo "  - Player kills: $TEAM1_PLAYER1_KILLS (expected: 15)"
fi

# Test 6: Verify all 7 maps are present
echo -e "\n${YELLOW}Test 6: Verifying all 7 maps are present...${NC}"

MAP_COUNT=$(echo $UPDATED_MATCH | jq '.data.maps_data | length // .maps_data | length // 0')

if [ "$MAP_COUNT" == "7" ]; then
  echo -e "${GREEN}✓ All 7 maps are present${NC}"
else
  echo -e "${RED}✗ Wrong number of maps: ${MAP_COUNT} (expected: 7)${NC}"
fi

# Summary
echo -e "\n${YELLOW}================================${NC}"
echo -e "${YELLOW}Test Summary:${NC}"
echo -e "${YELLOW}================================${NC}"

if [ "$STATUS" == "completed" ] && [[ "$MAP1_NAME" == *"Updated"* ]] && [ "$TEAM1_PLAYER1" == "Player 1" ] && [ "$MAP_COUNT" == "7" ]; then
  echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
  echo -e "${GREEN}Live scoring fixes are working correctly.${NC}"
else
  echo -e "${RED}✗ Some tests failed. Please review the output above.${NC}"
fi

echo -e "\nMatch ID for manual review: ${MATCH_ID}"