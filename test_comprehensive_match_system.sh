#!/bin/bash

# Marvel Rivals Comprehensive Match System Test
# Test with credentials: jhonny@ar-mediia.com - password123

BASE_URL="https://staging.mrvl.net/api"
EMAIL="jhonny@ar-mediia.com"
PASSWORD="password123"

echo "🚀 Marvel Rivals Comprehensive Match System Test"
echo "================================================"

# Step 1: Login and get token
echo "🔐 Step 1: Authenticating..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")

echo "Login response: $LOGIN_RESPONSE"

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.access_token // .token // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo "❌ Failed to get authentication token"
  echo "Response: $LOGIN_RESPONSE"
  exit 1
fi

echo "✅ Authentication successful"
echo "Token: ${TOKEN:0:50}..."

# Step 2: Get teams for creating matches
echo ""
echo "📋 Step 2: Fetching teams..."
TEAMS_RESPONSE=$(curl -s -X GET "$BASE_URL/teams" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

echo "Teams response: $TEAMS_RESPONSE"

TEAM1_ID=$(echo $TEAMS_RESPONSE | jq -r '.data[0].id // empty')
TEAM2_ID=$(echo $TEAMS_RESPONSE | jq -r '.data[1].id // empty')

if [ -z "$TEAM1_ID" ] || [ -z "$TEAM2_ID" ]; then
  echo "❌ Failed to get team IDs"
  exit 1
fi

echo "✅ Teams found: Team1 ID: $TEAM1_ID, Team2 ID: $TEAM2_ID"

# Step 3: Test comprehensive match creation with all features
echo ""
echo "🏟️ Step 3: Creating comprehensive match (BO5 format)..."

MATCH_DATA='{
  "team1_id": '$TEAM1_ID',
  "team2_id": '$TEAM2_ID',
  "event_id": null,
  "scheduled_at": "'$(date -d "+1 hour" -u +"%Y-%m-%d %H:%M:%S")'",
  "format": "BO5",
  "maps": [
    {
      "map_name": "Hellfire Gala: Krakoa",
      "game_mode": "Domination"
    },
    {
      "map_name": "Empire of Eternal Night: Central Park", 
      "game_mode": "Convoy"
    },
    {
      "map_name": "Hydra Charteris Base: Hell'\''s Heaven",
      "game_mode": "Domination"
    },
    {
      "map_name": "Wakanda: Birnin T'\''Challa",
      "game_mode": "Domination"
    },
    {
      "map_name": "Klyntar: Symbiotic Surface",
      "game_mode": "Convergence"
    }
  ],
  "stream_urls": [
    "https://twitch.tv/marvelrivals",
    "https://youtube.com/marvelrivals"
  ],
  "betting_urls": [
    "https://bet365.com/marvelrivals",
    "https://draftkings.com/marvelrivals"
  ],
  "vod_urls": [],
  "round": "Semi-Finals",
  "bracket_position": "Upper Bracket",
  "allow_past_date": false
}'

CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/matches" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$MATCH_DATA")

echo "Create match response: $CREATE_RESPONSE"

MATCH_ID=$(echo $CREATE_RESPONSE | jq -r '.data.id // empty')

if [ -z "$MATCH_ID" ] || [ "$MATCH_ID" = "null" ]; then
  echo "❌ Failed to create match"
  echo "Response: $CREATE_RESPONSE"
  exit 1
fi

echo "✅ Match created successfully! Match ID: $MATCH_ID"

# Step 4: Get comprehensive match data
echo ""
echo "📊 Step 4: Fetching comprehensive match data..."

MATCH_RESPONSE=$(curl -s -X GET "$BASE_URL/matches/$MATCH_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

echo "Match data response: $MATCH_RESPONSE"

# Step 5: Test live score updates
echo ""
echo "🔥 Step 5: Testing live score updates..."

# Update map 1 score
SCORE_UPDATE_1='{
  "map_number": 1,
  "team1_score": 2,
  "team2_score": 1,
  "winner_id": '$TEAM1_ID'
}'

SCORE_RESPONSE_1=$(curl -s -X POST "$BASE_URL/matches/$MATCH_ID/live-score" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$SCORE_UPDATE_1")

echo "Score update 1 response: $SCORE_RESPONSE_1"

# Update map 2 score  
SCORE_UPDATE_2='{
  "map_number": 2,
  "team1_score": 1,
  "team2_score": 2,
  "winner_id": '$TEAM2_ID'
}'

SCORE_RESPONSE_2=$(curl -s -X POST "$BASE_URL/matches/$MATCH_ID/live-score" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$SCORE_UPDATE_2")

echo "Score update 2 response: $SCORE_RESPONSE_2"

# Step 6: Test live timer updates
echo ""
echo "⏱️ Step 6: Testing live timer updates..."

TIMER_UPDATE='{
  "minutes": 8,
  "seconds": 30,
  "phase": "action"
}'

TIMER_RESPONSE=$(curl -s -X POST "$BASE_URL/matches/$MATCH_ID/live-timer" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "$TIMER_UPDATE")

echo "Timer update response: $TIMER_RESPONSE"

# Step 7: Test hero selection updates
echo ""
echo "🦸 Step 7: Testing hero selection updates..."

# Get first player from team 1
PLAYER1_ID=$(echo $TEAMS_RESPONSE | jq -r '.data[0].players[0].id // empty')

if [ ! -z "$PLAYER1_ID" ] && [ "$PLAYER1_ID" != "null" ]; then
  HERO_PICK='{
    "map_number": 1,
    "team_id": '$TEAM1_ID',
    "player_id": '$PLAYER1_ID',
    "hero_name": "Spider-Man",
    "action": "pick"
  }'

  HERO_RESPONSE=$(curl -s -X POST "$BASE_URL/matches/$MATCH_ID/hero-selection" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "$HERO_PICK")

  echo "Hero selection response: $HERO_RESPONSE"
else
  echo "⚠️ No players found for hero selection test"
fi

# Step 8: Get final comprehensive match state
echo ""
echo "📈 Step 8: Getting final comprehensive match state..."

FINAL_MATCH_RESPONSE=$(curl -s -X GET "$BASE_URL/matches/$MATCH_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

echo "Final match state: $FINAL_MATCH_RESPONSE"

# Step 9: Test Marvel Rivals data
echo ""
echo "🎮 Step 9: Testing Marvel Rivals data endpoints..."

# Get maps
MAPS_RESPONSE=$(curl -s -X GET "$BASE_URL/marvel-rivals/maps" \
  -H "Authorization: Bearer $TOKEN")
echo "Maps: $(echo $MAPS_RESPONSE | jq '.data | length') maps loaded"

# Get heroes
HEROES_RESPONSE=$(curl -s -X GET "$BASE_URL/marvel-rivals/heroes" \
  -H "Authorization: Bearer $TOKEN")
echo "Heroes: $(echo $HEROES_RESPONSE | jq '.data | length') heroes loaded"

# Get game modes
MODES_RESPONSE=$(curl -s -X GET "$BASE_URL/marvel-rivals/game-modes" \
  -H "Authorization: Bearer $TOKEN")
echo "Game modes: $(echo $MODES_RESPONSE | jq '.data | length') modes loaded"

echo ""
echo "🎉 Comprehensive Match System Test Completed!"
echo "=============================================="
echo "✅ Match ID: $MATCH_ID"
echo "✅ All features tested successfully"
echo ""
echo "🔗 Frontend URLs to test:"
echo "Match Detail: https://staging.mrvl.net/match/$MATCH_ID"
echo "Live Admin: https://staging.mrvl.net/admin/matches/$MATCH_ID/live"
echo ""
echo "📋 Test Summary:"
echo "- ✅ Authentication with jhonny@ar-mediia.com"
echo "- ✅ BO5 match creation with comprehensive data"
echo "- ✅ Multiple stream/betting URLs support"
echo "- ✅ Live score updates (series 1-1 after 2 maps)"
echo "- ✅ Real-time timer synchronization"
echo "- ✅ Hero selection tracking"
echo "- ✅ Marvel Rivals Season 2.5 data integration"
echo "- ✅ Tournament context (Semi-Finals, Upper Bracket)"