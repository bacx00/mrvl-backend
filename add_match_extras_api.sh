#!/bin/bash

# API Base URL
API_URL="https://staging.mrvl.net/api"
EMAIL="jhonny@ar-mediia.com"
PASSWORD="password123"

echo "🔐 Logging in..."

# Login and get token
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | sed 's/"token":"//')
USER_ID=$(echo $LOGIN_RESPONSE | grep -o '"id":[0-9]*' | head -1 | sed 's/"id"://')

if [ -z "$TOKEN" ]; then
    echo "❌ Login failed"
    exit 1
fi

echo "✅ Logged in successfully (User ID: $USER_ID)"
echo ""

MATCH_ID=13

# Update match with URLs
echo "📺 Adding URLs to match $MATCH_ID..."
curl -s -X PUT "$API_URL/matches/$MATCH_ID/urls" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "stream_url": "https://www.twitch.tv/marvel_rivals",
    "vod_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "betting_url": "https://www.bet365.com/esports/marvel-rivals/sentinels-vs-nrg"
  }' > /dev/null

echo "✅ URLs added successfully"
echo ""

# Add comments
echo "💬 Adding comments to match $MATCH_ID..."

# Comment 1
echo "  Adding comment 1..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "SENTINELS LOOKING STRONG! 🔥 That Map 1 performance was insane, especially @player:Coluge with the Spider-Man plays!"
  }' > /dev/null

# Comment 2
echo "  Adding comment 2..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "NRG bounced back hard on Map 2 though. @player:Titan absolutely dominated with 134 eliminations across 5 heroes! This BO5 is going to be legendary 🎮"
  }' > /dev/null

# Comment 3
echo "  Adding comment 3..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Anyone else notice how many hero switches we are seeing? This meta is so diverse! Every player switching between 5 different heroes per map is wild"
  }' > /dev/null

# Comment 4
echo "  Adding comment 4..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Map 3 is LIVE right now and it is neck and neck! @team:Sentinels needs this map to secure match point. The pressure is real! 💪"
  }' > /dev/null

# Comment 5
echo "  Adding comment 5..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "The hero diversity in this match is incredible. Seeing players flex between DPS, Tank, and Support roles shows true skill. This is peak Marvel Rivals gameplay!"
  }' > /dev/null

# Comment 6
echo "  Adding comment 6..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Predictions for Map 4 and 5? I think @team:NRG takes it 3-2 in the reverse sweep. They have momentum after that Map 2 domination!"
  }' > /dev/null

echo "✅ All parent comments added"
echo ""

# Get the comments to add replies
echo "🔍 Fetching comment IDs..."
COMMENTS=$(curl -s -X GET "$API_URL/matches/$MATCH_ID/comments" \
  -H "Authorization: Bearer $TOKEN")

# Parse first few comment IDs (using grep and sed for simplicity)
COMMENT_1_ID=$(echo $COMMENTS | grep -o '"id":[0-9]*' | head -1 | sed 's/"id"://')
COMMENT_2_ID=$(echo $COMMENTS | grep -o '"id":[0-9]*' | head -2 | tail -1 | sed 's/"id"://')
COMMENT_3_ID=$(echo $COMMENTS | grep -o '"id":[0-9]*' | head -3 | tail -1 | sed 's/"id"://')
COMMENT_4_ID=$(echo $COMMENTS | grep -o '"id":[0-9]*' | head -4 | tail -1 | sed 's/"id"://')
COMMENT_5_ID=$(echo $COMMENTS | grep -o '"id":[0-9]*' | head -5 | tail -1 | sed 's/"id"://')
COMMENT_6_ID=$(echo $COMMENTS | grep -o '"id":[0-9]*' | head -6 | tail -1 | sed 's/"id"://')

echo "  Found comment IDs: $COMMENT_1_ID, $COMMENT_2_ID, $COMMENT_3_ID, $COMMENT_4_ID, $COMMENT_5_ID, $COMMENT_6_ID"
echo ""

# Add replies
echo "💬 Adding replies..."

if [ ! -z "$COMMENT_1_ID" ]; then
  echo "  Adding reply to comment 1..."
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"Facts! @player:Coluge's movement was insane. That 132 elimination game across 5 heroes is MVP worthy 🏆\",
      \"parent_id\": $COMMENT_1_ID
    }" > /dev/null

  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"Don't sleep on @player:Rymazing though, 148 elims on Map 1! The whole team was firing on all cylinders\",
      \"parent_id\": $COMMENT_1_ID
    }" > /dev/null
fi

if [ ! -z "$COMMENT_2_ID" ]; then
  echo "  Adding reply to comment 2..."
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"True, but Sentinels came back strong on Map 3. This series could go either way!\",
      \"parent_id\": $COMMENT_2_ID
    }" > /dev/null
fi

if [ ! -z "$COMMENT_4_ID" ]; then
  echo "  Adding replies to comment 4..."
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"The live scoring updates are so smooth! Love watching the stats update in real-time 📊\",
      \"parent_id\": $COMMENT_4_ID
    }" > /dev/null

  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"LETS GO SENTINELS! One more map for the W! 🎯\",
      \"parent_id\": $COMMENT_4_ID
    }" > /dev/null
fi

if [ ! -z "$COMMENT_6_ID" ]; then
  echo "  Adding replies to comment 6..."
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"Nah, Sentinels got this 3-1. They're looking too strong on these control maps\",
      \"parent_id\": $COMMENT_6_ID
    }" > /dev/null

  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"content\": \"It's anyone's game at this point. Both teams showing why they're top tier!\",
      \"parent_id\": $COMMENT_6_ID
    }" > /dev/null
fi

echo "✅ All replies added"
echo ""

# Add votes to some comments
echo "👍 Adding votes to comments..."

if [ ! -z "$COMMENT_1_ID" ]; then
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments/$COMMENT_1_ID/vote" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"vote": "up"}' > /dev/null
  echo "  Upvoted comment 1"
fi

if [ ! -z "$COMMENT_2_ID" ]; then
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments/$COMMENT_2_ID/vote" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"vote": "up"}' > /dev/null
  echo "  Upvoted comment 2"
fi

if [ ! -z "$COMMENT_4_ID" ]; then
  curl -s -X POST "$API_URL/matches/$MATCH_ID/comments/$COMMENT_4_ID/vote" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"vote": "up"}' > /dev/null
  echo "  Upvoted comment 4"
fi

echo ""
echo "✅ Successfully added to Match $MATCH_ID:"
echo "  - Stream URL: Twitch"
echo "  - VOD URL: YouTube"
echo "  - Betting URL: Bet365"
echo "  - 6 parent comments"
echo "  - 7 replies"
echo "  - Multiple upvotes"
echo ""
echo "🔗 View the match: https://staging.mrvl.net/#match-detail/$MATCH_ID"