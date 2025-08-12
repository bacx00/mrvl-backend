#!/bin/bash

# Create new BO3 match
echo "Creating new BO3 match: 100 Thieves vs EDward Gaming..."

RESPONSE=$(curl -s -X POST "https://staging.mrvl.net/api/admin/matches" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "team1_id": 4,
    "team2_id": 2,
    "event_id": 1,
    "format": "BO3",
    "status": "upcoming",
    "scheduled_at": "2025-08-11T09:00:00.000Z",
    "match_info": {
      "stream_url": "https://twitch.tv/mrvl_esports",
      "venue": "Los Angeles Arena",
      "casters": ["Alex Goldenboy Mendez", "Mitch Uber Leslie"]
    }
  }')

# Extract match ID
MATCH_ID=$(echo $RESPONSE | jq -r '.id // .data.id // empty')

if [ -z "$MATCH_ID" ]; then
  echo "Failed to create match. Response:"
  echo $RESPONSE | jq
  exit 1
fi

echo "✅ Match created with ID: $MATCH_ID"
echo "Match URL: https://staging.mrvl.net/#match-detail/$MATCH_ID"
echo ""
echo "Now you can open the Live Scoring Panel for this match:"
echo "https://staging.mrvl.net/#live-scoring-panel/$MATCH_ID"