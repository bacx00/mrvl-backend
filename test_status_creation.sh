#!/bin/bash

# Test status creation with debugging
curl -X POST http://staging.mrvl.net/api/admin/matches \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "team1_id": 32,
    "team2_id": 4,
    "scheduled_at": "2025-01-15T10:00:00Z",
    "format": "BO3",
    "status": "completed",
    "team1_score": 2,
    "team2_score": 1,
    "maps": [
      {
        "map_name": "King'\''s Row",
        "mode": "Escort",
        "team1_score": 3,
        "team2_score": 2
      },
      {
        "map_name": "Temple of Anubis",
        "mode": "Assault",
        "team1_score": 2,
        "team2_score": 0
      },
      {
        "map_name": "Ilios",
        "mode": "Control",
        "team1_score": 1,
        "team2_score": 3
      }
    ]
  }' \
  | jq '.'

echo "Check logs for debugging output:"
tail -f /var/www/mrvl-backend/storage/logs/laravel.log | grep -E "(Creating match|Match created|Match creation data)"