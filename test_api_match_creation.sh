#!/bin/bash

echo "🧪 Testing match creation via HTTP API..."

# Create a test match with completed status
curl -X POST http://staging.mrvl.net/api/admin/matches \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test-token" \
  -d '{
    "team1_id": 32,
    "team2_id": 4,
    "event_id": null,
    "status": "completed",
    "format": "BO3",
    "scheduled_at": "2025-09-23T10:00:00.000Z",
    "team1_score": 2,
    "team2_score": 1,
    "maps": [
      {
        "map_name": "Test Map 1",
        "mode": "Domination",
        "team1_score": 1,
        "team2_score": 0
      },
      {
        "map_name": "Test Map 2",
        "mode": "Convoy",
        "team1_score": 1,
        "team2_score": 0
      },
      {
        "map_name": "Test Map 3",
        "mode": "Domination",
        "team1_score": 0,
        "team2_score": 1
      }
    ]
  }' \
  | jq '.'

echo -e "\n✅ Test completed"