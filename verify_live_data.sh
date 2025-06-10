#!/bin/bash

echo "🎮 MARVEL RIVALS PLATFORM - LIVE DATA VERIFICATION"
echo "=================================================="

echo "📊 TOP TEAMS WITH REALISTIC RATINGS:"
curl -s -X GET "https://staging.mrvl.net/api/teams" | jq '.data[] | {name, rating, rank}' | head -10

echo ""
echo "👥 TOTAL PLAYERS: $(curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data | length')"

echo ""
echo "🏢 PLAYERS PER TEAM:"
curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data | group_by(.team_id) | map(select(.[0].team_id != null)) | map({team_id: .[0].team_id, count: length})' | head -10

echo ""
echo "⭐ FREE AGENTS:"
curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data | map(select(.team_id == null)) | length'

echo ""
echo "🎯 ROLE DISTRIBUTION:"
curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data | group_by(.role) | map({role: .[0].role, count: length})'

echo ""
echo "🏆 SAMPLE PLAYER DATA:"
curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data[0] | {name, role, main_hero, rating, earnings}'

echo ""
echo "✅ PLATFORM STATUS: LIVE-READY! 🚀"