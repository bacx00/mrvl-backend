#!/bin/bash

# Test Live Scoring with Hero Updates and Player Stats
# Match ID: 1

API_URL="https://staging.mrvl.net/api"
MATCH_ID="1"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiZWMzMTA3NDVhMjk4NDVkY2NiOGZhMmYxOWVhNDI4MDFhNWQ5YWFhZDliMmI4NmNlNzU4NmFmYzA0NDBmYmVjNTEyYmYwOGYzNTYzYjMxMzUiLCJpYXQiOjE3NTUwNjk3NzMuNDIyMjgzODg3ODYzMTU5MTc5Njg3NSwibmJmIjoxNzU1MDY5NzczLjQyMjI4ODg5NDY1MzMyMDMxMjUsImV4cCI6MTc4NjYwNTc3My40MDUyMTc4ODU5NzEwNjkzMzU5Mzc1LCJzdWIiOiI3NSIsInNjb3BlcyI6W119.PdkSio6ITJNPKyhlx8QITxqgja6yZdWf1TYWlJfTSnBI95C3eTq1hEfWQkW3Ka7TNQkWHr5RATnSZ3pZKJjqo5023ch-WR0jV4vw-VFsPFoZaE8Vqe2hQAQEosjqYITKxAGSB9A5CafT7fh7dnrOiLM8sRc2uJ0baRjhzGfvwjDk6ILxdRhdxZOprtmzxqnNTByWMKPaBURAe_8fXxFcRC_-PM5afrk_Lq4LKiBi4Rsc03ALo4QsQVELmNg6xWCbkgpyjsYu7mj1yje83g-_RsAUynGn8_u6zXQzOOEkoFChy_olwhDbP-d6dkjpSQODRqENwyjkZMBpiACwOTF-MYbJXLfs95jtLJMis3z4mUxurg4a72RPpYVma0akbqxg25kXFyM7qLpovdysoVBokVq8CeQJH88SSa7lY8R-Vt7RHUm_Z1lKVZewVnxm7d85PUiWjNgAaduXrriYKiNfwP-l1i8CcEcl1y6HkmXCbeT_P53jO1bVdoHtuUo-rylBC0rpSFbk-7TjeBnrrMnXmtlOyzjyXrOHPvZy0hS6yovImvfrzc6hhW1UWsKuf8v0joQpgZqrBbs7BhV4yE2RTjzpYyzx0KpP0Ox-isLkvm7nohZLHxMnr8z3mji60V-s3J82gI9KC-bI1npTfGIPupiqCbAejhMAlIVoT83zCb4"

echo "🎮 Testing Live Scoring System - Hero & Stats Updates"
echo "=================================================="
echo ""

# Get current match state
echo "📊 Current match state:"
curl -s -X GET "$API_URL/matches/$MATCH_ID" | python3 -c "
import json, sys
data = json.load(sys.stdin)
match = data.get('data', {})
print(f\"Series Score: {match.get('team1_score', 0)} - {match.get('team2_score', 0)}\")
print(f\"Status: {match.get('status', 'unknown')}\")
maps = match.get('maps', [])
for i, m in enumerate(maps):
    print(f\"Map {i+1}: {m.get('team1_score', 0)}-{m.get('team2_score', 0)} ({m.get('status', 'pending')})\")
"
echo ""

# Update Map 2 with hero changes and player stats
echo "🔄 Updating Map 2 with hero changes and player stats..."
curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 2,
    "team1_score": 9,
    "team2_score": 7,
    "team1_composition": [
      {"player_id": 659, "hero": "Iron Man", "eliminations": 15, "deaths": 5, "assists": 8, "damage": 25000, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Hela", "eliminations": 18, "deaths": 4, "assists": 6, "damage": 32000, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Luna Snow", "eliminations": 3, "deaths": 3, "assists": 22, "damage": 5000, "healing": 28000, "damage_blocked": 0},
      {"player_id": 662, "hero": "Doctor Strange", "eliminations": 8, "deaths": 6, "assists": 15, "damage": 12000, "healing": 0, "damage_blocked": 18000},
      {"player_id": 661, "hero": "Venom", "eliminations": 10, "deaths": 7, "assists": 12, "damage": 15000, "healing": 0, "damage_blocked": 25000},
      {"player_id": 658, "hero": "Star-Lord", "eliminations": 12, "deaths": 5, "assists": 10, "damage": 20000, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Scarlet Witch", "eliminations": 14, "deaths": 8, "assists": 9, "damage": 27000, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Hawkeye", "eliminations": 16, "deaths": 6, "assists": 7, "damage": 29000, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Mantis", "eliminations": 2, "deaths": 4, "assists": 20, "damage": 4000, "healing": 31000, "damage_blocked": 0},
      {"player_id": 652, "hero": "Magneto", "eliminations": 7, "deaths": 8, "assists": 14, "damage": 11000, "healing": 0, "damage_blocked": 22000},
      {"player_id": 655, "hero": "Hulk", "eliminations": 9, "deaths": 9, "assists": 11, "damage": 13000, "healing": 0, "damage_blocked": 28000},
      {"player_id": 656, "hero": "Punisher", "eliminations": 11, "deaths": 7, "assists": 8, "damage": 18000, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "⏱️ Waiting 2 seconds for update to propagate..."
sleep 2

# Verify the update
echo ""
echo "✅ Verifying update - checking Map 2 data:"
curl -s -X GET "$API_URL/matches/$MATCH_ID" | python3 -c "
import json, sys
data = json.load(sys.stdin)
match = data.get('data', {})
maps = match.get('maps', [])
if len(maps) > 1:
    map2 = maps[1]
    print(f\"Map 2 Score: {map2.get('team1_score', 0)}-{map2.get('team2_score', 0)}\")
    print(f\"Map 2 Status: {map2.get('status', 'pending')}\")
    
    team1_comp = map2.get('team1_composition', []) or map2.get('team1_players', [])
    team2_comp = map2.get('team2_composition', []) or map2.get('team2_players', [])
    
    if team1_comp:
        print(f'\\nTeam 1 Composition ({len(team1_comp)} players):')
        for p in team1_comp[:3]:  # Show first 3 players
            print(f\"  - {p.get('name', 'Unknown')}: {p.get('hero', 'N/A')} | K:{p.get('eliminations', 0)} D:{p.get('deaths', 0)} A:{p.get('assists', 0)}\")
    
    if team2_comp:
        print(f'\\nTeam 2 Composition ({len(team2_comp)} players):')
        for p in team2_comp[:3]:  # Show first 3 players
            print(f\"  - {p.get('name', 'Unknown')}: {p.get('hero', 'N/A')} | K:{p.get('eliminations', 0)} D:{p.get('deaths', 0)} A:{p.get('assists', 0)}\")
else:
    print('No Map 2 data found')
"

echo ""
echo "🎯 Test complete! Check the match detail page to see if:"
echo "  1. Hero images have updated (Iron Man, Hela, Luna Snow, etc.)"
echo "  2. Player stats are populated (K/D/A, damage, healing)"
echo "  3. Map score shows 9-7"
echo ""
echo "📍 View at: https://staging.mrvl.net/#match-detail/1"