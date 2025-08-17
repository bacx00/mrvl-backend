#!/bin/bash
echo "=== Live Scoring System Health Check ==="
echo ""

# Check match 7 status
echo "1. Match 7 Status:"
curl -s https://staging.mrvl.net/api/matches/7 | jq '.data | {id, current_map, status, team1_score, team2_score, maps: (.maps | length)}'

echo ""
echo "2. Map Compositions (player counts):"
curl -s https://staging.mrvl.net/api/matches/7 | jq '.data.maps[] | {map: .map_number, team1_players: (.team1_composition | length), team2_players: (.team2_composition | length)}'

echo ""
echo "3. SSE Stream Test (5 seconds):"
timeout 5 curl -s https://staging.mrvl.net/api/live-updates/7/stream 2>&1 | head -5

echo ""
echo "=== Health Check Complete ==="
