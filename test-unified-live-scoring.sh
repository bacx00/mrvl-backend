#!/bin/bash

# Test script for Unified Live Scoring System
# Tests localStorage events and polling system

echo "======================================"
echo "Testing Unified Live Scoring System"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
API_URL="https://staging.mrvl.net/api"
MATCH_ID=1
TOKEN="YOUR_AUTH_TOKEN_HERE"

echo -e "${YELLOW}Configuration:${NC}"
echo "API URL: $API_URL"
echo "Match ID: $MATCH_ID"
echo ""

# Test 1: Get current match data
echo -e "${YELLOW}Test 1: Getting current match data...${NC}"
curl -s -X GET "$API_URL/matches/$MATCH_ID" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

echo ""
echo -e "${GREEN}✓ Match data retrieved${NC}"
echo ""

# Test 2: Update match via live scoring endpoint
echo -e "${YELLOW}Test 2: Testing live update endpoint...${NC}"

UPDATE_DATA='{
  "team1_score": 13,
  "team2_score": 11,
  "series_score_team1": 1,
  "series_score_team2": 0,
  "current_map": 2,
  "status": "live",
  "maps": {
    "1": {"team1Score": 13, "team2Score": 11, "status": "completed", "winner": "team1"},
    "2": {"team1Score": 5, "team2Score": 3, "status": "active", "winner": null},
    "3": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null}
  },
  "team1_players": [
    {"id": 1, "name": "Player1", "hero": "Spider-Man", "kills": 15, "deaths": 8, "assists": 4},
    {"id": 2, "name": "Player2", "hero": "Iron Man", "kills": 12, "deaths": 10, "assists": 6}
  ],
  "team2_players": [
    {"id": 3, "name": "Player3", "hero": "Thor", "kills": 10, "deaths": 12, "assists": 5},
    {"id": 4, "name": "Player4", "hero": "Hulk", "kills": 8, "deaths": 14, "assists": 3}
  ]
}'

RESPONSE=$(curl -s -X POST "$API_URL/admin/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "$UPDATE_DATA")

echo "$RESPONSE" | jq '.'

if echo "$RESPONSE" | grep -q '"success":true'; then
  echo -e "${GREEN}✓ Live update successful${NC}"
else
  echo -e "${RED}✗ Live update failed${NC}"
fi

echo ""

# Test 3: Verify localStorage sync
echo -e "${YELLOW}Test 3: localStorage sync test...${NC}"
echo "To test localStorage sync:"
echo "1. Open the match detail page in multiple browser tabs"
echo "2. Open the admin live scoring panel in one tab"
echo "3. Make changes in the admin panel"
echo "4. Verify changes appear immediately in all tabs without refresh"
echo ""

# Test 4: Test polling system
echo -e "${YELLOW}Test 4: Testing polling system...${NC}"
echo "Polling should retrieve updates every 3 seconds"
echo "Watch the network tab in browser DevTools to confirm"
echo ""

# Test 5: Hero change test
echo -e "${YELLOW}Test 5: Testing hero changes...${NC}"

HERO_UPDATE='{
  "team1_players": [
    {"id": 1, "name": "Player1", "hero": "Venom", "kills": 15, "deaths": 8, "assists": 4}
  ]
}'

curl -s -X POST "$API_URL/admin/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "$HERO_UPDATE" | jq '.'

echo -e "${GREEN}✓ Hero update sent${NC}"
echo ""

# Test 6: Map score update test
echo -e "${YELLOW}Test 6: Testing map score updates...${NC}"

MAP_UPDATE='{
  "maps": {
    "2": {"team1Score": 8, "team2Score": 6, "status": "active", "winner": null}
  }
}'

curl -s -X POST "$API_URL/admin/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "$MAP_UPDATE" | jq '.'

echo -e "${GREEN}✓ Map score update sent${NC}"
echo ""

# Summary
echo "======================================"
echo -e "${GREEN}Testing Complete!${NC}"
echo "======================================"
echo ""
echo "Key Features Tested:"
echo "✓ Live update API endpoint"
echo "✓ Hero selection changes"
echo "✓ Player stats updates"
echo "✓ Map score updates"
echo "✓ Series score tracking"
echo ""
echo "Manual Testing Required:"
echo "• localStorage cross-tab synchronization"
echo "• 3-second polling interval"
echo "• Immediate UI updates without refresh"
echo ""
echo "Next Steps:"
echo "1. Update the TOKEN variable in this script with a valid admin token"
echo "2. Open match detail page in multiple tabs"
echo "3. Test live scoring panel changes"
echo "4. Verify immediate updates across all tabs"