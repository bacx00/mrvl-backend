#!/bin/bash

# Test live map switching functionality
API_URL="http://localhost/api"
MATCH_ID=7

echo "Testing Live Map Switching"
echo "=========================="
echo ""

# Test 1: Switch to Map 2
echo "Test 1: Switching to Map 2..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "current_map": 2,
      "isMapSwitch": true,
      "team1_score": 0,
      "team2_score": 0,
      "map_index": 1
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

echo ""
echo "Checking database..."
php artisan tinker --execute="
\$match = \App\Models\MvrlMatch::find($MATCH_ID);
echo 'current_map: ' . \$match->current_map . PHP_EOL;
echo 'current_map_number: ' . \$match->current_map_number . PHP_EOL;
"

echo ""
echo "Test 2: Switching to Map 3..."
curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "score-update",
    "data": {
      "current_map": 3,
      "isMapSwitch": true,
      "team1_score": 0,
      "team2_score": 0,
      "map_index": 2
    },
    "timestamp": "'$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")'"
  }' | jq '.'

echo ""
echo "Final database check..."
php artisan tinker --execute="
\$match = \App\Models\MvrlMatch::find($MATCH_ID);
echo 'current_map: ' . \$match->current_map . PHP_EOL;
echo 'current_map_number: ' . \$match->current_map_number . PHP_EOL;
"

echo ""
echo "Test 3: Getting match status..."
curl -s -X GET "$API_URL/matches/$MATCH_ID/live-status" \
  -H "Content-Type: application/json" | jq '.'