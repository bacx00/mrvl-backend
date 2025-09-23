#!/bin/bash

# Test script for match status operations
BACKEND_URL="http://localhost:8000"

echo "🧪 Testing Match Status Operations..."

# Get authorization token
echo "📝 Getting auth token..."
AUTH_RESPONSE=$(curl -s -X POST "${BACKEND_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@mrvl.com", "password": "password"}')

TOKEN=$(echo $AUTH_RESPONSE | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ Failed to get auth token"
  exit 1
fi

echo "✅ Got auth token"

# Test 1: Create upcoming match
echo "🧪 Test 1: Creating upcoming match..."
UPCOMING_MATCH=$(curl -s -X POST "${BACKEND_URL}/api/admin/matches" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team1_id": 1,
    "team2_id": 2,
    "event_id": 1,
    "status": "upcoming",
    "format": "BO3",
    "scheduled_at": "2025-12-31T15:00:00Z",
    "maps": [
      {"map_name": "Test Map 1", "mode": "Convoy"},
      {"map_name": "Test Map 2", "mode": "Domination"},
      {"map_name": "Test Map 3", "mode": "Convergence"}
    ]
  }')

UPCOMING_ID=$(echo $UPCOMING_MATCH | grep -o '"id":[0-9]*' | cut -d':' -f2)
UPCOMING_STATUS=$(echo $UPCOMING_MATCH | grep -o '"status":"[^"]*"' | cut -d'"' -f4)

if [ "$UPCOMING_STATUS" = "upcoming" ]; then
  echo "✅ Upcoming match created with status: $UPCOMING_STATUS"
else
  echo "❌ Expected 'upcoming', got '$UPCOMING_STATUS'"
fi

# Test 2: Create completed match
echo "🧪 Test 2: Creating completed match..."
COMPLETED_MATCH=$(curl -s -X POST "${BACKEND_URL}/api/admin/matches" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team1_id": 1,
    "team2_id": 2,
    "event_id": 1,
    "status": "completed",
    "format": "BO3",
    "team1_score": 2,
    "team2_score": 1,
    "scheduled_at": "2024-01-01T15:00:00Z",
    "allow_past_date": true,
    "maps": [
      {"map_name": "Test Map 1", "mode": "Convoy", "team1_score": 100, "team2_score": 50},
      {"map_name": "Test Map 2", "mode": "Domination", "team1_score": 75, "team2_score": 100},
      {"map_name": "Test Map 3", "mode": "Convergence", "team1_score": 100, "team2_score": 25}
    ]
  }')

COMPLETED_ID=$(echo $COMPLETED_MATCH | grep -o '"id":[0-9]*' | cut -d':' -f2)
COMPLETED_STATUS=$(echo $COMPLETED_MATCH | grep -o '"status":"[^"]*"' | cut -d'"' -f4)

if [ "$COMPLETED_STATUS" = "completed" ]; then
  echo "✅ Completed match created with status: $COMPLETED_STATUS"
else
  echo "❌ Expected 'completed', got '$COMPLETED_STATUS'"
fi

# Test 3: Update upcoming match to live
if [ ! -z "$UPCOMING_ID" ]; then
  echo "🧪 Test 3: Updating upcoming match to live..."
  LIVE_UPDATE=$(curl -s -X PUT "${BACKEND_URL}/api/admin/matches-moderation/$UPCOMING_ID" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"status": "live"}')

  LIVE_SUCCESS=$(echo $LIVE_UPDATE | grep -o '"success":[^,}]*' | cut -d':' -f2)

  if [ "$LIVE_SUCCESS" = "true" ]; then
    echo "✅ Successfully updated match to live status"
  else
    echo "❌ Failed to update to live status: $LIVE_UPDATE"
  fi
fi

# Test 4: Update live match to completed
if [ ! -z "$UPCOMING_ID" ]; then
  echo "🧪 Test 4: Updating live match to completed..."
  COMPLETE_UPDATE=$(curl -s -X PUT "${BACKEND_URL}/api/admin/matches-moderation/$UPCOMING_ID" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"status": "completed", "team1_score": 2, "team2_score": 0}')

  COMPLETE_SUCCESS=$(echo $COMPLETE_UPDATE | grep -o '"success":[^,}]*' | cut -d':' -f2)

  if [ "$COMPLETE_SUCCESS" = "true" ]; then
    echo "✅ Successfully updated match to completed status"
  else
    echo "❌ Failed to update to completed status: $COMPLETE_UPDATE"
  fi
fi

# Test 5: Verify status filtering
echo "🧪 Test 5: Testing status filtering..."
ALL_MATCHES=$(curl -s -X GET "${BACKEND_URL}/api/admin/matches-moderation" \
  -H "Authorization: Bearer $TOKEN")

UPCOMING_COUNT=$(echo $ALL_MATCHES | grep -o '"status":"upcoming"' | wc -l)
LIVE_COUNT=$(echo $ALL_MATCHES | grep -o '"status":"live"' | wc -l)
COMPLETED_COUNT=$(echo $ALL_MATCHES | grep -o '"status":"completed"' | wc -l)

echo "📊 Match status counts:"
echo "   Upcoming: $UPCOMING_COUNT"
echo "   Live: $LIVE_COUNT"
echo "   Completed: $COMPLETED_COUNT"

echo "🎉 Status operations test completed!"