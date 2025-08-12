#!/bin/bash

# Login and get token
echo "Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST "https://staging.mrvl.net/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "jhonny@ar-mediia.com",
    "password": "password123"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token // .access_token // empty')

if [ -z "$TOKEN" ]; then
  echo "Failed to login. Response:"
  echo $LOGIN_RESPONSE | jq
  exit 1
fi

echo "✅ Logged in successfully"

# Create new BO3 match
echo "Creating new BO3 match: 100 Thieves vs EDward Gaming..."

MATCH_RESPONSE=$(curl -s -X POST "https://staging.mrvl.net/api/admin/matches" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "team1_id": 4,
    "team2_id": 2,
    "event_id": 4,
    "format": "BO3",
    "status": "upcoming",
    "scheduled_at": "2025-08-11T09:00:00.000Z",
    "team1_score": 0,
    "team2_score": 0,
    "maps_data": [],
    "match_info": {
      "stream_url": "https://twitch.tv/mrvl_esports",
      "venue": "Los Angeles Arena",
      "casters": ["Alex Goldenboy Mendez", "Mitch Uber Leslie"]
    }
  }')

# Extract match ID
MATCH_ID=$(echo $MATCH_RESPONSE | jq -r '.id // .data.id // empty')

if [ -z "$MATCH_ID" ]; then
  echo "Failed to create match. Response:"
  echo $MATCH_RESPONSE | jq
  exit 1
fi

echo "✅ Match created with ID: $MATCH_ID"
echo ""
echo "Match URL: https://staging.mrvl.net/#match-detail/$MATCH_ID"
echo "Live Scoring Panel: https://staging.mrvl.net/#live-scoring-panel/$MATCH_ID"
echo ""
echo "You can now open the Live Scoring Panel to simulate the match!"