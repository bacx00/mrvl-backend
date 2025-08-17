#!/bin/bash

echo "Getting admin token..."

curl -X POST "https://staging.mrvl.net/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@mrvl.net",
    "password": "password123"
  }' \
  -s | jq -r '.access_token' > admin_token.txt

if [ -s admin_token.txt ]; then
    TOKEN=$(cat admin_token.txt)
    echo "Admin token obtained: ${TOKEN:0:50}..."
    
    echo "Testing live scoring with admin authentication..."
    curl -X POST "https://staging.mrvl.net/api/admin/matches/6/live-update" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -H "Authorization: Bearer $TOKEN" \
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
else
    echo "Failed to get admin token"
fi