#!/bin/bash

# Test Admin API CRUD Operations for Teams and Players
# This script tests all CRUD operations through the admin API endpoints

API_URL="https://staging.mrvl.net/api"
TOKEN="1|iqy6qJfE3nRlEC5bdONDrVMtGJMpQ0EJrNtJoqz5cdd98507"

echo "=== TESTING ADMIN API CRUD OPERATIONS ==="
echo ""

# 1. Test Team CRUD
echo "1. TESTING TEAM CRUD VIA API"
echo "-----------------------------"

# Create a team
echo "Creating new team..."
TEAM_RESPONSE=$(curl -s -X POST "$API_URL/admin/teams" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "API Test Team",
    "short_name": "ATT",
    "region": "ASIA",
    "country": "Philippines",
    "rating": 2500,
    "description": "Test team via API",
    "website": "https://apitest.com",
    "social_media": "{\"twitter\":\"apitest\",\"instagram\":\"apitest_ig\"}"
  }')

echo "Create response: $TEAM_RESPONSE"
TEAM_ID=$(echo $TEAM_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo "Created team ID: $TEAM_ID"

if [ ! -z "$TEAM_ID" ]; then
  # Update the team
  echo ""
  echo "Updating team $TEAM_ID..."
  UPDATE_RESPONSE=$(curl -s -X PUT "$API_URL/admin/teams/$TEAM_ID" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
      "name": "API Test Team Updated",
      "region": "EU",
      "rating": 2600,
      "country": "Germany"
    }')
  echo "Update response: $UPDATE_RESPONSE"
  
  # Get team details
  echo ""
  echo "Getting team details..."
  GET_RESPONSE=$(curl -s -X GET "$API_URL/teams/$TEAM_ID" \
    -H "Authorization: Bearer $TOKEN")
  echo "Get response: $GET_RESPONSE"
  
  # Delete the team
  echo ""
  echo "Deleting team $TEAM_ID..."
  DELETE_RESPONSE=$(curl -s -X DELETE "$API_URL/admin/teams/$TEAM_ID" \
    -H "Authorization: Bearer $TOKEN")
  echo "Delete response: $DELETE_RESPONSE"
fi

# 2. Test Player CRUD
echo ""
echo "2. TESTING PLAYER CRUD VIA API"
echo "-------------------------------"

# Get existing players
echo "Getting player list..."
PLAYERS_RESPONSE=$(curl -s -X GET "$API_URL/players?limit=5" \
  -H "Authorization: Bearer $TOKEN")
echo "Players list response (first 200 chars): ${PLAYERS_RESPONSE:0:200}..."

# Test player update (using existing player)
PLAYER_ID=1  # Using a known player ID
echo ""
echo "Updating player $PLAYER_ID..."
UPDATE_PLAYER_RESPONSE=$(curl -s -X PUT "$API_URL/players/$PLAYER_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "rating": 1900,
    "role": "Vanguard",
    "status": "active"
  }')
echo "Player update response: $UPDATE_PLAYER_RESPONSE"

# 3. Test all regions
echo ""
echo "3. TESTING ALL REGIONS"
echo "----------------------"

REGIONS=("NA" "EU" "ASIA" "APAC" "LATAM" "BR" "Americas" "EMEA" "Oceania" "China")

for REGION in "${REGIONS[@]}"; do
  echo "Testing region: $REGION"
  REGION_RESPONSE=$(curl -s -X POST "$API_URL/admin/teams" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"name\": \"Region Test $REGION\",
      \"short_name\": \"RT$REGION\",
      \"region\": \"$REGION\",
      \"rating\": 1500
    }")
  
  if echo "$REGION_RESPONSE" | grep -q "success.*true"; then
    echo "  ✅ $REGION accepted"
    # Extract ID and delete
    TEMP_ID=$(echo $REGION_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
    if [ ! -z "$TEMP_ID" ]; then
      curl -s -X DELETE "$API_URL/admin/teams/$TEMP_ID" -H "Authorization: Bearer $TOKEN" > /dev/null
    fi
  else
    echo "  ❌ $REGION rejected: $REGION_RESPONSE"
  fi
done

# 4. Test field validations
echo ""
echo "4. TESTING FIELD VALIDATIONS"
echo "-----------------------------"

# Test rating over max
echo "Testing rating over 5000..."
VALIDATION_RESPONSE=$(curl -s -X POST "$API_URL/admin/teams" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Validation Test",
    "short_name": "VT",
    "region": "NA",
    "rating": 5001
  }')

if echo "$VALIDATION_RESPONSE" | grep -q "error\|validation"; then
  echo "  ✅ Rating validation working - rejected value over 5000"
else
  echo "  ❌ Rating validation failed - accepted value over 5000"
fi

# Test duplicate team name
echo "Testing duplicate team name..."
# First create a team
FIRST_RESPONSE=$(curl -s -X POST "$API_URL/admin/teams" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Duplicate Test Team",
    "short_name": "DTT1",
    "region": "NA",
    "rating": 1500
  }')

FIRST_ID=$(echo $FIRST_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

# Try to create duplicate
DUPLICATE_RESPONSE=$(curl -s -X POST "$API_URL/admin/teams" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Duplicate Test Team",
    "short_name": "DTT2",
    "region": "NA",
    "rating": 1500
  }')

if echo "$DUPLICATE_RESPONSE" | grep -q "error\|validation\|already exists"; then
  echo "  ✅ Unique constraint working - rejected duplicate name"
else
  echo "  ❌ Unique constraint failed - accepted duplicate name"
fi

# Clean up
if [ ! -z "$FIRST_ID" ]; then
  curl -s -X DELETE "$API_URL/admin/teams/$FIRST_ID" -H "Authorization: Bearer $TOKEN" > /dev/null
fi

echo ""
echo "=== API CRUD TESTS COMPLETE ==="