#!/bin/bash

echo "Testing hero update for match ID 6..."

curl -X POST "https://staging.mrvl.net/api/matches/6/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "hero-update",
    "data": {
      "map_index": 0,
      "team1_heroes": ["Spider-Man", "Iron Man", "Captain America", "Thor", "Hulk", "Black Widow"],
      "team2_heroes": ["Doctor Strange", "Scarlet Witch", "Loki", "Magneto", "Storm", "Wolverine"]
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' \
  -s | jq

echo -e "\nTesting stats update..."

curl -X POST "https://staging.mrvl.net/api/matches/6/live-update" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "stats-update", 
    "data": {
      "map_index": 0,
      "player_stats": [
        {
          "player_id": 1,
          "hero": "Spider-Man", 
          "kills": 5,
          "deaths": 2,
          "assists": 3
        }
      ]
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' \
  -s | jq