#!/bin/bash

echo "🎮 TESTING LIVE SCORING SYSTEM WITH REAL API CALLS"
echo "=================================================="

MATCH_ID=1
API_BASE="https://staging.mrvl.net/api"

echo "📊 Initial match state:"
curl -s "$API_BASE/matches/$MATCH_ID" | jq '{id, status, team1_score, team2_score, current_map}' 

echo ""
echo "🚀 Testing Match Status Update to LIVE..."
curl -s -X PUT "$API_BASE/matches/$MATCH_ID" \
-H "Content-Type: application/json" \
-d '{
  "status": "live",
  "current_map": 1
}' | jq '.status // "Error occurred"'

echo ""
echo "📈 Testing Score Update - Map 1: Round 1..."
curl -s -X POST "$API_BASE/matches/$MATCH_ID/live-stats" \
-H "Content-Type: application/json" \
-d '{
  "type": "score_update",
  "map_number": 1,
  "team1_score": 1,
  "team2_score": 0,
  "source": "live_test"
}' | head -3

echo ""
echo "📈 Testing Score Update - Map 1: Round 2..."
curl -s -X POST "$API_BASE/matches/$MATCH_ID/live-stats" \
-H "Content-Type: application/json" \
-d '{
  "type": "score_update", 
  "map_number": 1,
  "team1_score": 1,
  "team2_score": 1,
  "source": "live_test"
}' | head -3

echo ""
echo "🦸 Testing Hero Pick Update..."
curl -s -X POST "$API_BASE/matches/$MATCH_ID/live-stats" \
-H "Content-Type: application/json" \
-d '{
  "type": "hero_update",
  "team": 1,
  "player": "TenZ",
  "hero": "Spider-Man",
  "map_number": 1
}' | head -3

echo ""
echo "📊 Testing Player Stats Update..."
curl -s -X POST "$API_BASE/matches/$MATCH_ID/live-stats" \
-H "Content-Type: application/json" \
-d '{
  "type": "stats_update",
  "player_stats": [
    {"player": "TenZ", "kills": 5, "deaths": 2, "damage": 3420},
    {"player": "Zekken", "kills": 3, "deaths": 1, "damage": 2890}
  ]
}' | head -3

echo ""
echo "🏆 Testing Map Completion..."
curl -s -X POST "$API_BASE/matches/$MATCH_ID/live-stats" \
-H "Content-Type: application/json" \
-d '{
  "type": "map_complete",
  "map_number": 1,
  "winner": 1,
  "final_score": {"team1": 3, "team2": 1},
  "series_score": {"team1": 1, "team2": 0}
}' | head -3

echo ""
echo "📊 Final match state after all updates:"
curl -s "$API_BASE/matches/$MATCH_ID" | jq '{id, status, team1_score, team2_score, maps: .maps[0:1]}'

echo ""
echo "✅ Live scoring test completed!"
echo "Check the browser console for localStorage broadcasts if SimplifiedLiveScoring panel is open."