#!/bin/bash

echo "🔌 Manual Bracket API Endpoint Test"
echo "=================================="

# Configuration
BASE_URL="http://localhost:8000/api"
ADMIN_TOKEN=""

# Try to get admin token from file
if [ -f "admin_token.txt" ]; then
    ADMIN_TOKEN=$(cat admin_token.txt | tr -d '\n')
    echo "🔑 Using admin token from file"
else
    echo "❌ No admin token found. Please create admin_token.txt"
    exit 1
fi

echo ""
echo "📋 Test 1: Get public formats"
echo "-----------------------------"
curl -s -X GET "$BASE_URL/manual-bracket/formats" | jq .

echo ""
echo "📋 Test 2: Get admin formats"
echo "----------------------------"
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
     -X GET "$BASE_URL/admin/manual-bracket/formats" | jq .

echo ""
echo "📋 Test 3: Get tournament list"
echo "------------------------------"
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
     -X GET "$BASE_URL/admin/tournaments" | jq '.data | length'

echo ""
echo "📋 Test 4: Create GSL bracket"
echo "-----------------------------"
TOURNAMENT_ID=8  # Using existing tournament

GSL_RESPONSE=$(curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
     -H "Content-Type: application/json" \
     -X POST "$BASE_URL/admin/tournaments/$TOURNAMENT_ID/manual-bracket" \
     -d '{
         "format_key": "play_in",
         "team_ids": [21, 22, 23, 24],
         "name": "API Test GSL Bracket",
         "bracket_type": "gsl",
         "best_of": 3
     }')

echo "$GSL_RESPONSE" | jq .

# Extract bracket ID for further testing
BRACKET_ID=$(echo "$GSL_RESPONSE" | jq -r '.bracket_id // empty')

if [ ! -z "$BRACKET_ID" ]; then
    echo ""
    echo "📋 Test 5: Get bracket state"
    echo "---------------------------"
    curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
         -X GET "$BASE_URL/admin/manual-bracket/$BRACKET_ID" | jq .

    echo ""
    echo "📋 Test 6: Get public bracket view"
    echo "----------------------------------"
    curl -s -X GET "$BASE_URL/manual-bracket/$BRACKET_ID" | jq .

    echo ""
    echo "📋 Test 7: Update match score"
    echo "-----------------------------"
    # Get first match ID
    MATCH_RESPONSE=$(curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
                          -X GET "$BASE_URL/admin/manual-bracket/$BRACKET_ID")
    
    FIRST_MATCH_ID=$(echo "$MATCH_RESPONSE" | jq -r '.bracket.matches[0].id // empty')
    
    if [ ! -z "$FIRST_MATCH_ID" ]; then
        curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
             -H "Content-Type: application/json" \
             -X PUT "$BASE_URL/admin/manual-bracket/matches/$FIRST_MATCH_ID/score" \
             -d '{
                 "team1_score": 2,
                 "team2_score": 1,
                 "complete_match": true,
                 "game_details": [
                     {"mode": "domination", "winner_id": null},
                     {"mode": "convoy", "winner_id": null},
                     {"mode": "convergence", "winner_id": null}
                 ]
             }' | jq .
    else
        echo "❌ Could not find match ID for score update test"
    fi

    echo ""
    echo "📋 Test 8: Reset bracket"
    echo "-----------------------"
    curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
         -X POST "$BASE_URL/admin/manual-bracket/$BRACKET_ID/reset" | jq .

else
    echo "❌ Could not create bracket for further testing"
fi

echo ""
echo "📋 Test 9: Create single elimination bracket"
echo "--------------------------------------------"
SINGLE_RESPONSE=$(curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
     -H "Content-Type: application/json" \
     -X POST "$BASE_URL/admin/tournaments/$TOURNAMENT_ID/manual-bracket" \
     -d '{
         "format_key": "open_qualifier",
         "team_ids": [25, 26, 27, 28, 29, 30, 31, 32],
         "name": "API Test Single Elimination",
         "bracket_type": "single_elimination",
         "best_of": 1
     }')

echo "$SINGLE_RESPONSE" | jq .

echo ""
echo "📋 Test 10: Error handling - Invalid team count"
echo "-----------------------------------------------"
curl -s -H "Authorization: Bearer $ADMIN_TOKEN" \
     -H "Content-Type: application/json" \
     -X POST "$BASE_URL/admin/tournaments/$TOURNAMENT_ID/manual-bracket" \
     -d '{
         "format_key": "play_in",
         "team_ids": [1, 2],
         "name": "Invalid GSL Test",
         "bracket_type": "gsl",
         "best_of": 3
     }' | jq .

echo ""
echo "📋 Test 11: Authentication test - No token"
echo "------------------------------------------"
curl -s -H "Content-Type: application/json" \
     -X POST "$BASE_URL/admin/tournaments/$TOURNAMENT_ID/manual-bracket" \
     -d '{
         "format_key": "custom",
         "team_ids": [1, 2],
         "name": "Unauthorized Test"
     }' | jq .

echo ""
echo "✨ API Endpoint Testing Completed!"
echo "=================================="