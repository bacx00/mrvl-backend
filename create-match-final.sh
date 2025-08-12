#!/bin/bash

# Login first
echo "🔐 Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST "https://staging.mrvl.net/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "jhonny@ar-mediia.com",
    "password": "password123"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token // .access_token // empty')

if [ -z "$TOKEN" ]; then
  echo "Failed to login"
  exit 1
fi

echo "✅ Logged in successfully"
echo ""

# Create match with initial BO3 structure
echo "🎮 Creating new BO3 match: 100 Thieves vs EDward Gaming"
echo "================================================"

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
    "scheduled_at": "2025-08-11T10:00:00.000Z",
    "team1_score": 0,
    "team2_score": 0,
    "current_map": 1,
    "maps_data": [
      {
        "map_name": "Tokyo 2099: Shibuya",
        "mode": "Convoy",
        "index": 1,
        "status": "upcoming",
        "team1_score": 0,
        "team2_score": 0,
        "team1_composition": [],
        "team2_composition": []
      },
      {
        "map_name": "Wakanda: Birnin T Challa",
        "mode": "Domination",
        "index": 2,
        "status": "upcoming",
        "team1_score": 0,
        "team2_score": 0,
        "team1_composition": [],
        "team2_composition": []
      },
      {
        "map_name": "Asgard: Throne Room",
        "mode": "Convergence",
        "index": 3,
        "status": "upcoming",
        "team1_score": 0,
        "team2_score": 0,
        "team1_composition": [],
        "team2_composition": []
      }
    ],
    "match_info": {
      "stream_url": "https://twitch.tv/mrvl_esports",
      "venue": "Los Angeles Arena",
      "casters": ["Alex Goldenboy Mendez", "Mitch Uber Leslie"]
    }
  }')

MATCH_ID=$(echo $MATCH_RESPONSE | jq -r '.id // .data.id // empty')

if [ -z "$MATCH_ID" ]; then
  echo "❌ Failed to create match. Response:"
  echo $MATCH_RESPONSE | jq
  exit 1
fi

echo "✅ Match created successfully!"
echo ""
echo "📋 Match Details:"
echo "   ID: $MATCH_ID"
echo "   Format: Best of 3"
echo "   Teams: 100 Thieves vs EDward Gaming"
echo ""
echo "   Map 1: Tokyo 2099 - Convoy"
echo "   Map 2: Wakanda - Domination"
echo "   Map 3: Asgard - Convergence"
echo ""
echo "🔗 URLs:"
echo "   Match Detail: https://staging.mrvl.net/#match-detail/$MATCH_ID"
echo "   Live Scoring Panel: https://staging.mrvl.net/#live-scoring-panel/$MATCH_ID"
echo ""
echo "📝 Next Steps:"
echo "   1. Open the Live Scoring Panel URL above"
echo "   2. Start the match and set it to 'live'"
echo "   3. Add hero compositions for each team"
echo "   4. Update scores and player stats as you simulate the match"
echo "   5. Complete each map one by one"
echo ""
echo "================================================"