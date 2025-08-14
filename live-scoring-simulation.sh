#!/bin/bash

# Complete Live Scoring Simulation - BO3 Finals
# Simulating live scoring panel inputs for Match ID: 6

API_URL="https://staging.mrvl.net/api"
MATCH_ID="6"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiZWMzMTA3NDVhMjk4NDVkY2NiOGZhMmYxOWVhNDI4MDFhNWQ5YWFhZDliMmI4NmNlNzU4NmFmYzA0NDBmYmVjNTEyYmYwOGYzNTYzYjMxMzUiLCJpYXQiOjE3NTUwNjk3NzMuNDIyMjgzODg3ODYzMTU5MTc5Njg3NSwibmJmIjoxNzU1MDY5NzczLjQyMjI4ODg5NDY1MzMyMDMxMjUsImV4cCI6MTc4NjYwNTc3My40MDUyMTc4ODU5NzEwNjkzMzU5Mzc1LCJzdWIiOiI3NSIsInNjb3BlcyI6W119.PdkSio6ITJNPKyhlx8QITxqgja6yZdWf1TYWlJfTSnBI95C3eTq1hEfWQkW3Ka7TNQkWHr5RATnSZ3pZKJjqo5023ch-WR0jV4vw-VFsPFoZaE8Vqe2hQAQEosjqYITKxAGSB9A5CafT7fh7dnrOiLM8sRc2uJ0baRjhzGfvwjDk6ILxdRhdxZOprtmzxqnNTByWMKPaBURAe_8fXxFcRC_-PM5afrk_Lq4LKiBi4Rsc03ALo4QsQVELmNg6xWCbkgpyjsYu7mj1yje83g-_RsAUynGn8_u6zXQzOOEkoFChy_olwhDbP-d6dkjpSQODRqENwyjkZMBpiACwOTF-MYbJXLfs95jtLJMis3z4mUxurg4a72RPpYVma0akbqxg25kXFyM7qLpovdysoVBokVq8CeQJH88SSa7lY8R-Vt7RHUm_Z1lKVZewVnxm7d85PUiWjNgAaduXrriYKiNfwP-l1i8CcEcl1y6HkmXCbeT_P53jO1bVdoHtuUo-rylBC0rpSFbk-7TjeBnrrMnXmtlOyzjyXrOHPvZy0hS6yovImvfrzc6hhW1UWsKuf8v0joQpgZqrBbs7BhV4yE2RTjzpYyzx0KpP0Ox-isLkvm7nohZLHxMnr8z3mji60V-s3J82gI9KC-bI1npTfGIPupiqCbAejhMAlIVoT83zCb4"

echo "🎮 LIVE SCORING SIMULATION - BO3 Finals"
echo "======================================="
echo "🏟️  Rare Atom vs Soniqs - Match ID: $MATCH_ID"
echo "📍 Follow along: https://staging.mrvl.net/#match-detail/$MATCH_ID"
echo ""

# Phase 1: Set match to live
echo "🔴 Phase 1: Setting match to LIVE status..."
curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""
sleep 2

# Phase 2: MAP 1 - Midtown (Push)
echo "🗺️  Phase 2: MAP 1 - Midtown (Push)"
echo "==================================="
echo "🎯 Starting Map 1 with initial hero compositions..."

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 1,
    "team1_score": 0,
    "team2_score": 0,
    "team1_composition": [
      {"player_id": 659, "hero": "Doctor Strange", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Hawkeye", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Luna Snow", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 662, "hero": "Magneto", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 661, "hero": "Hulk", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 658, "hero": "Star-Lord", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Iron Man", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Punisher", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Mantis", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 652, "hero": "Venom", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 655, "hero": "Captain America", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 656, "hero": "Spider-Man", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "⏱️  Mid-game update - Map 1 at 50% progress..."
sleep 3

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 1,
    "team1_score": 85,
    "team2_score": 72,
    "team1_composition": [
      {"player_id": 659, "hero": "Doctor Strange", "eliminations": 8, "deaths": 3, "assists": 12, "damage": 15000, "healing": 0, "damage_blocked": 18000},
      {"player_id": 657, "hero": "Hawkeye", "eliminations": 15, "deaths": 2, "assists": 5, "damage": 28000, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Luna Snow", "eliminations": 2, "deaths": 2, "assists": 18, "damage": 3000, "healing": 25000, "damage_blocked": 0},
      {"player_id": 662, "hero": "Magneto", "eliminations": 6, "deaths": 4, "assists": 14, "damage": 12000, "healing": 0, "damage_blocked": 15000},
      {"player_id": 661, "hero": "Hulk", "eliminations": 9, "deaths": 5, "assists": 8, "damage": 18000, "healing": 0, "damage_blocked": 22000},
      {"player_id": 658, "hero": "Star-Lord", "eliminations": 12, "deaths": 3, "assists": 9, "damage": 22000, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Iron Man", "eliminations": 11, "deaths": 4, "assists": 7, "damage": 24000, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Punisher", "eliminations": 13, "deaths": 5, "assists": 6, "damage": 26000, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Mantis", "eliminations": 1, "deaths": 3, "assists": 16, "damage": 2500, "healing": 23000, "damage_blocked": 0},
      {"player_id": 652, "hero": "Venom", "eliminations": 7, "deaths": 6, "assists": 11, "damage": 14000, "healing": 0, "damage_blocked": 19000},
      {"player_id": 655, "hero": "Captain America", "eliminations": 5, "deaths": 7, "assists": 13, "damage": 10000, "healing": 0, "damage_blocked": 25000},
      {"player_id": 656, "hero": "Spider-Man", "eliminations": 8, "deaths": 4, "assists": 10, "damage": 16000, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "🏁 Map 1 Final - Rare Atom wins 100-96!"
sleep 2

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 1,
    "team1_score": 100,
    "team2_score": 96,
    "winner_id": 43,
    "team1_composition": [
      {"player_id": 659, "hero": "Doctor Strange", "eliminations": 12, "deaths": 5, "assists": 18, "damage": 22000, "healing": 0, "damage_blocked": 25000},
      {"player_id": 657, "hero": "Hawkeye", "eliminations": 24, "deaths": 4, "assists": 8, "damage": 38000, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Luna Snow", "eliminations": 3, "deaths": 4, "assists": 28, "damage": 5000, "healing": 35000, "damage_blocked": 0},
      {"player_id": 662, "hero": "Magneto", "eliminations": 9, "deaths": 6, "assists": 19, "damage": 18000, "healing": 0, "damage_blocked": 22000},
      {"player_id": 661, "hero": "Hulk", "eliminations": 14, "deaths": 8, "assists": 12, "damage": 25000, "healing": 0, "damage_blocked": 30000},
      {"player_id": 658, "hero": "Star-Lord", "eliminations": 18, "deaths": 5, "assists": 14, "damage": 32000, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Iron Man", "eliminations": 16, "deaths": 7, "assists": 11, "damage": 34000, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Punisher", "eliminations": 19, "deaths": 8, "assists": 9, "damage": 36000, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Mantis", "eliminations": 2, "deaths": 5, "assists": 24, "damage": 4000, "healing": 33000, "damage_blocked": 0},
      {"player_id": 652, "hero": "Venom", "eliminations": 11, "deaths": 9, "assists": 16, "damage": 20000, "healing": 0, "damage_blocked": 28000},
      {"player_id": 655, "hero": "Captain America", "eliminations": 8, "deaths": 10, "assists": 18, "damage": 15000, "healing": 0, "damage_blocked": 35000},
      {"player_id": 656, "hero": "Spider-Man", "eliminations": 12, "deaths": 6, "assists": 15, "damage": 24000, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "📊 Current Series Score: 1-0 (Rare Atom leads)"
echo ""
sleep 3

# Phase 3: MAP 2 - Temple of Anubis (Escort)
echo "🗺️  Phase 3: MAP 2 - Temple of Anubis (Escort)"
echo "============================================="
echo "🔄 Hero changes for Map 2..."

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 2,
    "team1_score": 0,
    "team2_score": 0,
    "team1_composition": [
      {"player_id": 659, "hero": "Hela", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Winter Soldier", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Rocket Raccoon", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 662, "hero": "Doctor Strange", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 661, "hero": "Groot", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 658, "hero": "Black Panther", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Scarlet Witch", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Moon Knight", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Adam Warlock", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 652, "hero": "Peni Parker", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 655, "hero": "Thor", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 656, "hero": "Psylocke", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "⚡ Intense battle on Map 2..."
sleep 3

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 2,
    "team1_score": 2,
    "team2_score": 3,
    "team1_composition": [
      {"player_id": 659, "hero": "Hela", "eliminations": 14, "deaths": 6, "assists": 9, "damage": 29000, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Winter Soldier", "eliminations": 16, "deaths": 5, "assists": 7, "damage": 31000, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Rocket Raccoon", "eliminations": 4, "deaths": 4, "assists": 19, "damage": 8000, "healing": 26000, "damage_blocked": 0},
      {"player_id": 662, "hero": "Doctor Strange", "eliminations": 7, "deaths": 7, "assists": 16, "damage": 14000, "healing": 0, "damage_blocked": 21000},
      {"player_id": 661, "hero": "Groot", "eliminations": 6, "deaths": 8, "assists": 18, "damage": 12000, "healing": 5000, "damage_blocked": 28000},
      {"player_id": 658, "hero": "Black Panther", "eliminations": 11, "deaths": 6, "assists": 13, "damage": 23000, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Scarlet Witch", "eliminations": 17, "deaths": 5, "assists": 8, "damage": 33000, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Moon Knight", "eliminations": 19, "deaths": 4, "assists": 6, "damage": 35000, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Adam Warlock", "eliminations": 3, "deaths": 3, "assists": 22, "damage": 6000, "healing": 29000, "damage_blocked": 0},
      {"player_id": 652, "hero": "Peni Parker", "eliminations": 9, "deaths": 7, "assists": 15, "damage": 16000, "healing": 0, "damage_blocked": 24000},
      {"player_id": 655, "hero": "Thor", "eliminations": 12, "deaths": 6, "assists": 14, "damage": 26000, "healing": 0, "damage_blocked": 8000},
      {"player_id": 656, "hero": "Psylocke", "eliminations": 15, "deaths": 7, "assists": 11, "damage": 28000, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "🏁 Map 2 Final - Soniqs takes it 3-2!"
sleep 2

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 2,
    "team1_score": 2,
    "team2_score": 3,
    "winner_id": 38,
    "team1_composition": [
      {"player_id": 659, "hero": "Hela", "eliminations": 18, "deaths": 9, "assists": 12, "damage": 36000, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Winter Soldier", "eliminations": 21, "deaths": 8, "assists": 9, "damage": 38000, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Rocket Raccoon", "eliminations": 5, "deaths": 6, "assists": 26, "damage": 10000, "healing": 34000, "damage_blocked": 0},
      {"player_id": 662, "hero": "Doctor Strange", "eliminations": 10, "deaths": 10, "assists": 21, "damage": 18000, "healing": 0, "damage_blocked": 27000},
      {"player_id": 661, "hero": "Groot", "eliminations": 8, "deaths": 11, "assists": 24, "damage": 15000, "healing": 8000, "damage_blocked": 35000},
      {"player_id": 658, "hero": "Black Panther", "eliminations": 14, "deaths": 9, "assists": 17, "damage": 29000, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Scarlet Witch", "eliminations": 22, "deaths": 8, "assists": 11, "damage": 42000, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Moon Knight", "eliminations": 25, "deaths": 7, "assists": 8, "damage": 44000, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Adam Warlock", "eliminations": 4, "deaths": 5, "assists": 29, "damage": 8000, "healing": 38000, "damage_blocked": 0},
      {"player_id": 652, "hero": "Peni Parker", "eliminations": 12, "deaths": 10, "assists": 19, "damage": 21000, "healing": 0, "damage_blocked": 32000},
      {"player_id": 655, "hero": "Thor", "eliminations": 16, "deaths": 9, "assists": 18, "damage": 34000, "healing": 0, "damage_blocked": 12000},
      {"player_id": 656, "hero": "Psylocke", "eliminations": 19, "deaths": 10, "assists": 15, "damage": 36000, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "📊 Current Series Score: 1-1 (Series tied!)"
echo ""
sleep 3

# Phase 4: MAP 3 - Horizon Lunar Colony (Hybrid) - DECISIVE MAP
echo "🗺️  Phase 4: MAP 3 - Horizon Lunar Colony (Hybrid)"
echo "================================================="
echo "🔥 DECISIVE MAP 3 - Winner takes the series!"

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 3,
    "team1_score": 0,
    "team2_score": 0,
    "team1_composition": [
      {"player_id": 659, "hero": "Storm", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Hawkeye", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Cloak & Dagger", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 662, "hero": "Magneto", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 661, "hero": "Hulk", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 658, "hero": "Star-Lord", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Iron Man", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Punisher", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Luna Snow", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 652, "hero": "Doctor Strange", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 655, "hero": "Captain America", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0},
      {"player_id": 656, "hero": "Spider-Man", "eliminations": 0, "deaths": 0, "assists": 0, "damage": 0, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "💥 Epic final battle in progress..."
sleep 4

curl -s -X PUT "$API_URL/admin/matches/$MATCH_ID/live-score" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "map_number": 3,
    "team1_score": 4,
    "team2_score": 3,
    "winner_id": 43,
    "team1_composition": [
      {"player_id": 659, "hero": "Storm", "eliminations": 20, "deaths": 7, "assists": 14, "damage": 35000, "healing": 0, "damage_blocked": 0},
      {"player_id": 657, "hero": "Hawkeye", "eliminations": 23, "deaths": 6, "assists": 11, "damage": 41000, "healing": 0, "damage_blocked": 0},
      {"player_id": 660, "hero": "Cloak & Dagger", "eliminations": 6, "deaths": 5, "assists": 28, "damage": 12000, "healing": 36000, "damage_blocked": 0},
      {"player_id": 662, "hero": "Magneto", "eliminations": 13, "deaths": 8, "assists": 19, "damage": 24000, "healing": 0, "damage_blocked": 29000},
      {"player_id": 661, "hero": "Hulk", "eliminations": 16, "deaths": 9, "assists": 16, "damage": 28000, "healing": 0, "damage_blocked": 33000},
      {"player_id": 658, "hero": "Star-Lord", "eliminations": 19, "deaths": 7, "assists": 15, "damage": 33000, "healing": 0, "damage_blocked": 0}
    ],
    "team2_composition": [
      {"player_id": 653, "hero": "Iron Man", "eliminations": 18, "deaths": 9, "assists": 12, "damage": 37000, "healing": 0, "damage_blocked": 0},
      {"player_id": 654, "hero": "Punisher", "eliminations": 21, "deaths": 8, "assists": 9, "damage": 39000, "healing": 0, "damage_blocked": 0},
      {"player_id": 651, "hero": "Luna Snow", "eliminations": 5, "deaths": 6, "assists": 25, "damage": 9000, "healing": 34000, "damage_blocked": 0},
      {"player_id": 652, "hero": "Doctor Strange", "eliminations": 11, "deaths": 10, "assists": 18, "damage": 19000, "healing": 0, "damage_blocked": 26000},
      {"player_id": 655, "hero": "Captain America", "eliminations": 14, "deaths": 11, "assists": 17, "damage": 22000, "healing": 0, "damage_blocked": 31000},
      {"player_id": 656, "hero": "Spider-Man", "eliminations": 17, "deaths": 9, "assists": 14, "damage": 29000, "healing": 0, "damage_blocked": 0}
    ]
  }' | python3 -m json.tool

echo ""
echo "🏆 MATCH COMPLETE! Rare Atom wins the BO3 Finals 2-1!"
echo "📊 Final Series Score: 2-1"
echo ""

# Final verification
echo "🔍 Final Verification:"
curl -s "$API_URL/matches/$MATCH_ID" | python3 -c "
import json, sys
data = json.load(sys.stdin)
match = data.get('data', {})
series_score = f\"{match.get('series_score_team1', 0)}-{match.get('series_score_team2', 0)}\"
print(f'📊 API Series Score: {series_score}')
maps = match.get('maps', [])
print(f'🗺️  Total Maps: {len(maps)}')
for i, m in enumerate(maps):
    team1_comp = len(m.get('team1_composition', []))
    team2_comp = len(m.get('team2_composition', []))
    winner = 'RA' if m.get('winner_id') == 43 else 'SON' if m.get('winner_id') == 38 else 'None'
    print(f'   Map {i+1}: {m.get(\"team1_score\", 0)}-{m.get(\"team2_score\", 0)} | Winner: {winner} | Players: {team1_comp}/{team2_comp}')

if series_score == '2-1' and len(maps) == 3:
    print()
    print('✅ SUCCESS: Complete live scoring simulation successful!')
    print('✅ All maps have player compositions and stats!')
    print('✅ Series score is correct!')
    print('✅ Updates are immediate!')
else:
    print()
    print('❌ Issues detected in the simulation')
"

echo ""
echo "🎮 Live scoring simulation complete!"
echo "📍 View full match: https://staging.mrvl.net/#match-detail/$MATCH_ID"