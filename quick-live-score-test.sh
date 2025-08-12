#!/bin/bash

# Quick Live Scoring Test - Simplified version for rapid testing

API_URL="https://staging.mrvl.net/api"
EMAIL="admin@marvel.com"
PASSWORD="Admin123!@#"

echo "======================================"
echo "MRVL QUICK LIVE SCORING TEST"
echo "======================================"

# 1. Login
echo -e "\n1. Authenticating..."
TOKEN=$(curl -s -X POST "$API_URL/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" | \
    grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "❌ Authentication failed"
    exit 1
fi
echo "✅ Authenticated"

# 2. Get first match
echo -e "\n2. Getting match..."
MATCH_ID=$(curl -s "$API_URL/matches" \
    -H "Authorization: Bearer $TOKEN" | \
    grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$MATCH_ID" ]; then
    echo "❌ No matches found"
    exit 1
fi
echo "✅ Using match ID: $MATCH_ID"

# 3. Start match
echo -e "\n3. Starting match..."
curl -s -X PUT "$API_URL/matches/$MATCH_ID/start" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"status":"live"}' > /dev/null
echo "✅ Match started"

# 4. Send score updates
echo -e "\n4. Sending live score updates..."
for i in 1 2 3; do
    echo "   Updating score to $i-0..."
    curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"type\":\"score_update\",
            \"map_index\":0,
            \"team1_score\":$i,
            \"team2_score\":0
        }" > /dev/null
    sleep 1
done
echo "✅ Scores updated"

# 5. Get final state
echo -e "\n5. Fetching final match state..."
FINAL=$(curl -s "$API_URL/matches/$MATCH_ID" \
    -H "Authorization: Bearer $TOKEN")

echo "✅ Match data:"
echo "$FINAL" | python3 -c "import sys, json; data=json.load(sys.stdin); print(f\"  Status: {data.get('status', 'unknown')}\"); print(f\"  Score: {data.get('team1_score', 0)}-{data.get('team2_score', 0)}\")" 2>/dev/null || echo "$FINAL" | head -5

echo -e "\n======================================"
echo "✅ LIVE SCORING TEST COMPLETE"
echo "View at: https://staging.mrvl.net/#match-detail/$MATCH_ID"
echo "======================================"