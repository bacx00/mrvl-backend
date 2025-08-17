#!/bin/bash

# Test map switching
API_URL="https://staging.mrvl.net/api"
MATCH_ID=7
TOKEN="1|RiSBCksAgjwjN8pOLCVCXjJgaJpCKu0UvCjykfrR"

echo "Testing Map Switch to Map 3"
echo "============================"

# Send map switch update
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "id": 7,
      "current_map": 3,
      "isMapSwitch": true,
      "team1_score": 2,
      "team2_score": 0,
      "status": "live"
    }
  }' | jq '.'

echo ""
echo "Checking current_map in database..."
php artisan tinker --execute="print_r(DB::table('matches')->where('id', 7)->select('current_map', 'current_map_number')->first());"