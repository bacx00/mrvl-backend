#!/bin/bash

# Test Event CRUD Operations
echo "Testing Event CRUD Operations..."

# Base URL for API
BASE_URL="http://localhost"
API_ENDPOINT="api/admin/events"

# Get auth token (assuming you have a test admin user)
echo "Step 1: Testing Event Creation..."

# Test creating a new event
curl -X POST "${BASE_URL}/${API_ENDPOINT}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test Tournament CRUD",
    "description": "This is a test tournament to verify CRUD operations work correctly",
    "type": "tournament",
    "tier": "B",
    "format": "single_elimination",
    "region": "International",
    "game_mode": "Convoy",
    "start_date": "2025-12-01",
    "end_date": "2025-12-03",
    "registration_start": "2025-11-15",
    "registration_end": "2025-11-30",
    "max_teams": 16,
    "prize_pool": 1000,
    "currency": "USD",
    "timezone": "UTC",
    "featured": false,
    "public": true,
    "status": "upcoming",
    "rules": "Standard tournament rules apply",
    "prize_distribution": {"1st": 500, "2nd": 300, "3rd": 200},
    "registration_requirements": {"min_rank": "Bronze"},
    "streams": {"twitch": "test_stream"},
    "social_links": {"twitter": "@test"}
  }' | jq '.'

echo -e "\nStep 2: Getting all events..."

# Test getting events list
curl -X GET "${BASE_URL}/${API_ENDPOINT}" \
  -H "Accept: application/json" | jq '.data[0:3]'

echo -e "\nStep 3: Testing Event Update..."

# Try to get the first event ID for update test
EVENT_ID=$(curl -s -X GET "${BASE_URL}/${API_ENDPOINT}" -H "Accept: application/json" | jq -r '.data[0].id // empty')

if [ ! -z "$EVENT_ID" ]; then
  echo "Found event ID: $EVENT_ID"

  # Test updating the event
  curl -X PUT "${BASE_URL}/${API_ENDPOINT}/${EVENT_ID}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{
      "name": "Updated Test Tournament",
      "description": "Updated description for testing CRUD operations",
      "prize_pool": 2000
    }' | jq '.'

  echo -e "\nStep 4: Verifying update..."

  # Get the updated event
  curl -X GET "${BASE_URL}/${API_ENDPOINT}/${EVENT_ID}" \
    -H "Accept: application/json" | jq '.data // .'
else
  echo "No events found for update test"
fi

echo -e "\nEvent CRUD test completed!"