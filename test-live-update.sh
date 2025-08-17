#!/bin/bash

echo "Testing live scoring update for match ID 6..."

curl -X POST "https://staging.mrvl.net/api/matches/6/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "score",
    "data": {
      "map_index": 0,
      "team1_score": 1,
      "team2_score": 0,
      "map_status": "ongoing"
    }
  }' \
  -v

echo -e "\n\nResponse completed."