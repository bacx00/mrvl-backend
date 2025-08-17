#!/bin/bash

echo "Testing live scoring with frontend format..."

curl -X POST "https://staging.mrvl.net/api/matches/6/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "id": 6,
      "team1_score": 3,
      "team2_score": 1,
      "series_score_team1": 1,
      "series_score_team2": 1,
      "current_map": 1,
      "status": "live",
      "maps": [
        {
          "map_number": 1,
          "team1_score": 3,
          "team2_score": 1,
          "team1_composition": [],
          "team2_composition": []
        }
      ]
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' \
  -s | jq