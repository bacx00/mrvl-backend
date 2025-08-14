#!/bin/bash

# Create a new BO3 match for live scoring simulation
API_URL="https://staging.mrvl.net/api"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiZWMzMTA3NDVhMjk4NDVkY2NiOGZhMmYxOWVhNDI4MDFhNWQ5YWFhZDliMmI4NmNlNzU4NmFmYzA0NDBmYmVjNTEyYmYwOGYzNTYzYjMxMzUiLCJpYXQiOjE3NTUwNjk3NzMuNDIyMjgzODg3ODYzMTU5MTc5Njg3NSwibmJmIjoxNzU1MDY5NzczLjQyMjI4ODg5NDY1MzMyMDMxMjUsImV4cCI6MTc4NjYwNTc3My40MDUyMTc4ODU5NzEwNjkzMzU5Mzc1LCJzdWIiOiI3NSIsInNjb3BlcyI6W119.PdkSio6ITJNPKyhlx8QITxqgja6yZdWf1TYWlJfTSnBI95C3eTq1hEfWQkW3Ka7TNQkWHr5RATnSZ3pZKJjqo5023ch-WR0jV4vw-VFsPFoZaE8Vqe2hQAQEosjqYITKxAGSB9A5CafT7fh7dnrOiLM8sRc2uJ0baRjhzGfvwjDk6ILxdRhdxZOprtmzxqnNTByWMKPaBURAe_8fXxFcRC_-PM5afrk_Lq4LKiBi4Rsc03ALo4QsQVELmNg6xWCbkgpyjsYu7mj1yje83g-_RsAUynGn8_u6zXQzOOEkoFChy_olwhDbP-d6dkjpSQODRqENwyjkZMBpiACwOTF-MYbJXLfs95jtLJMis3z4mUxurg4a72RPpYVma0akbqxg25kXFyM7qLpovdysoVBokVq8CeQJH88SSa7lY8R-Vt7RHUm_Z1lKVZewVnxm7d85PUiWjNgAaduXrriYKiNfwP-l1i8CcEcl1y6HkmXCbeT_P53jO1bVdoHtuUo-rylBC0rpSFbk-7TjeBnrrMnXmtlOyzjyXrOHPvZy0hS6yovImvfrzc6hhW1UWsKuf8v0joQpgZqrBbs7BhV4yE2RTjzpYyzx0KpP0Ox-isLkvm7nohZLHxMnr8z3mji60V-s3J82gI9KC-bI1npTfGIPupiqCbAejhMAlIVoT83zCb4"

echo "🎮 Creating New BO3 Match - Live Scoring Simulation"
echo "=================================================="
echo ""

# Create the match
echo "📝 Creating BO3 match: Rare Atom vs Soniqs..."
RESPONSE=$(curl -s -X POST "$API_URL/admin/matches" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "team1_id": 43,
    "team2_id": 38,
    "event_id": null,
    "scheduled_at": "2025-08-13 23:00:00",
    "format": "BO3",
    "maps_data": [
      {"map_name": "Midtown", "mode": "Push"},
      {"map_name": "Temple of Anubis", "mode": "Escort"},
      {"map_name": "Horizon Lunar Colony", "mode": "Hybrid"}
    ],
    "round": "Finals",
    "allow_past_date": true
  }')

echo "$RESPONSE" | python3 -m json.tool

# Extract match ID
MATCH_ID=$(echo "$RESPONSE" | python3 -c "
import json, sys
try:
    data = json.load(sys.stdin)
    print(data.get('data', {}).get('id', ''))
except:
    pass
")

if [ -n "$MATCH_ID" ]; then
    echo ""
    echo "✅ Match created successfully!"
    echo "🆔 Match ID: $MATCH_ID"
    echo "📍 URL: https://staging.mrvl.net/#match-detail/$MATCH_ID"
    echo "$MATCH_ID" > new_match_id.txt
    echo ""
    echo "🎯 Ready for live scoring simulation!"
else
    echo "❌ Failed to create match"
fi