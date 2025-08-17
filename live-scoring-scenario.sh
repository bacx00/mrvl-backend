#!/bin/bash

# Live Scoring Scenario Script - Marvel Rivals BO3 Match
# This simulates a real live scoring operator updating match data

API_URL="https://staging.mrvl.net/api"
MATCH_ID=6
TOKEN="1|RiSBCksAgjwjN8pOLCVCXjJgaJpCKu0UvCjykfrR"

echo "================================================"
echo "🎮 MARVEL RIVALS - LIVE SCORING SIMULATION"
echo "================================================"
echo ""

# Function to send update
send_update() {
    local TYPE=$1
    local DATA=$2
    local MESSAGE=$3
    
    echo "📡 $MESSAGE"
    
    curl -X POST "$API_URL/matches/$MATCH_ID/live-update" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"type\": \"$TYPE\",
            \"data\": $DATA,
            \"timestamp\": \"$(date -Iseconds)\"
        }" 2>/dev/null | jq -r '.success' > /dev/null && echo "   ✅ Updated" || echo "   ❌ Failed"
    
    sleep 2
}

# Reset match to upcoming
echo "🔄 Resetting match to upcoming state..."
curl -X POST "$API_URL/matches/$MATCH_ID/live-update" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "type": "status-update",
        "data": {
            "status": "upcoming",
            "series_score_team1": 0,
            "series_score_team2": 0
        },
        "timestamp": "'$(date -Iseconds)'"
    }' 2>/dev/null > /dev/null

echo ""
echo "================================================"
echo "📍 MAP 1: CONVOY - PAYLOAD"
echo "================================================"
echo ""

# Start match and Map 1
send_update "status-update" '{
    "status": "live",
    "current_map": 1
}' "Match is now LIVE! Map 1 starting..."

# Hero selections for Map 1
send_update "hero-update" '{
    "map_index": 0,
    "team1_players": [
        {"player_id": 1, "hero": "spider-man", "role": "duelist"},
        {"player_id": 2, "hero": "iron-man", "role": "duelist"},
        {"player_id": 3, "hero": "doctor-strange", "role": "vanguard"},
        {"player_id": 4, "hero": "hulk", "role": "vanguard"},
        {"player_id": 5, "hero": "luna-snow", "role": "strategist"},
        {"player_id": 6, "hero": "mantis", "role": "strategist"}
    ],
    "team2_players": [
        {"player_id": 7, "hero": "black-panther", "role": "duelist"},
        {"player_id": 8, "hero": "scarlet-witch", "role": "duelist"},
        {"player_id": 9, "hero": "magneto", "role": "vanguard"},
        {"player_id": 10, "hero": "venom", "role": "vanguard"},
        {"player_id": 11, "hero": "adam-warlock", "role": "strategist"},
        {"player_id": 12, "hero": "rocket-raccoon", "role": "strategist"}
    ]
}' "Hero selections locked in for Map 1"

# First blood - early game
send_update "score-update" '{
    "map_index": 0,
    "team1_score": 15,
    "team2_score": 10
}' "First checkpoint reached! Team 1 leading 15-10"

# Update player stats - early game
send_update "stats-update" '{
    "map_index": 0,
    "team1_players": [
        {"player_id": 1, "kills": 3, "deaths": 1, "assists": 2, "damage": 4500, "healing": 0},
        {"player_id": 2, "kills": 2, "deaths": 0, "assists": 1, "damage": 3200, "healing": 0},
        {"player_id": 3, "kills": 0, "deaths": 1, "assists": 4, "damage": 2100, "healing": 0, "blocked": 3500},
        {"player_id": 4, "kills": 1, "deaths": 2, "assists": 3, "damage": 1800, "healing": 0, "blocked": 4200},
        {"player_id": 5, "kills": 0, "deaths": 1, "assists": 5, "damage": 800, "healing": 5600},
        {"player_id": 6, "kills": 1, "deaths": 0, "assists": 4, "damage": 600, "healing": 4200}
    ],
    "team2_players": [
        {"player_id": 7, "kills": 2, "deaths": 2, "assists": 1, "damage": 3800, "healing": 0},
        {"player_id": 8, "kills": 1, "deaths": 1, "assists": 2, "damage": 2900, "healing": 0},
        {"player_id": 9, "kills": 1, "deaths": 1, "assists": 2, "damage": 2500, "healing": 0, "blocked": 3000},
        {"player_id": 10, "kills": 0, "deaths": 2, "assists": 3, "damage": 1900, "healing": 0, "blocked": 3800},
        {"player_id": 11, "kills": 0, "deaths": 1, "assists": 4, "damage": 700, "healing": 4800},
        {"player_id": 12, "kills": 1, "deaths": 0, "assists": 3, "damage": 900, "healing": 3900}
    ]
}' "Player stats updated - early game"

# Mid game update
send_update "score-update" '{
    "map_index": 0,
    "team1_score": 35,
    "team2_score": 30
}' "Mid-game: Close fight! 35-30"

# Update player stats - mid game
send_update "stats-update" '{
    "map_index": 0,
    "team1_players": [
        {"player_id": 1, "kills": 8, "deaths": 3, "assists": 5, "damage": 12500, "healing": 0},
        {"player_id": 2, "kills": 6, "deaths": 2, "assists": 4, "damage": 9800, "healing": 0},
        {"player_id": 3, "kills": 2, "deaths": 4, "assists": 10, "damage": 5600, "healing": 0, "blocked": 9800},
        {"player_id": 4, "kills": 3, "deaths": 5, "assists": 8, "damage": 4900, "healing": 0, "blocked": 11200},
        {"player_id": 5, "kills": 1, "deaths": 3, "assists": 14, "damage": 2100, "healing": 15600},
        {"player_id": 6, "kills": 2, "deaths": 2, "assists": 11, "damage": 1800, "healing": 12400}
    ],
    "team2_players": [
        {"player_id": 7, "kills": 5, "deaths": 5, "assists": 4, "damage": 10200, "healing": 0},
        {"player_id": 8, "kills": 4, "deaths": 4, "assists": 5, "damage": 8600, "healing": 0},
        {"player_id": 9, "kills": 3, "deaths": 3, "assists": 6, "damage": 6800, "healing": 0, "blocked": 8500},
        {"player_id": 10, "kills": 2, "deaths": 4, "assists": 7, "damage": 5200, "healing": 0, "blocked": 10100},
        {"player_id": 11, "kills": 1, "deaths": 3, "assists": 10, "damage": 1900, "healing": 13200},
        {"player_id": 12, "kills": 3, "deaths": 3, "assists": 8, "damage": 2800, "healing": 10800}
    ]
}' "Player stats updated - mid game"

# Map 1 conclusion
send_update "score-update" '{
    "map_index": 0,
    "team1_score": 50,
    "team2_score": 48
}' "MAP 1 COMPLETE: Team 1 wins 50-48!"

# Final stats for Map 1
send_update "stats-update" '{
    "map_index": 0,
    "team1_players": [
        {"player_id": 1, "kills": 12, "deaths": 5, "assists": 8, "damage": 21500, "healing": 0},
        {"player_id": 2, "kills": 10, "deaths": 4, "assists": 6, "damage": 18200, "healing": 0},
        {"player_id": 3, "kills": 3, "deaths": 6, "assists": 15, "damage": 9800, "healing": 0, "blocked": 16500},
        {"player_id": 4, "kills": 5, "deaths": 7, "assists": 12, "damage": 8700, "healing": 0, "blocked": 19200},
        {"player_id": 5, "kills": 2, "deaths": 5, "assists": 22, "damage": 3600, "healing": 28900},
        {"player_id": 6, "kills": 3, "deaths": 4, "assists": 18, "damage": 3200, "healing": 23400}
    ],
    "team2_players": [
        {"player_id": 7, "kills": 9, "deaths": 8, "assists": 7, "damage": 19800, "healing": 0},
        {"player_id": 8, "kills": 7, "deaths": 7, "assists": 8, "damage": 16400, "healing": 0},
        {"player_id": 9, "kills": 5, "deaths": 5, "assists": 10, "damage": 12600, "healing": 0, "blocked": 15800},
        {"player_id": 10, "kills": 4, "deaths": 6, "assists": 11, "damage": 9900, "healing": 0, "blocked": 18700},
        {"player_id": 11, "kills": 2, "deaths": 5, "assists": 17, "damage": 3800, "healing": 25600},
        {"player_id": 12, "kills": 5, "deaths": 5, "assists": 13, "damage": 5400, "healing": 20100}
    ]
}' "Final stats for Map 1 recorded"

# Update series score
send_update "map-update" '{
    "map_index": 0,
    "status": "completed",
    "winner": "team1",
    "series_score_team1": 1,
    "series_score_team2": 0
}' "Series update: Team 1 leads 1-0"

echo ""
echo "================================================"
echo "📍 MAP 2: DOMINATION - KING OF THE HILL"
echo "================================================"
echo ""

# Start Map 2 - Heroes changed!
send_update "map-update" '{
    "current_map": 2,
    "map_index": 1,
    "status": "active"
}' "Map 2 starting - Domination mode"

# New hero selections for Map 2 (some players switch)
send_update "hero-update" '{
    "map_index": 1,
    "team1_players": [
        {"player_id": 1, "hero": "star-lord", "role": "duelist"},
        {"player_id": 2, "hero": "iron-man", "role": "duelist"},
        {"player_id": 3, "hero": "captain-america", "role": "vanguard"},
        {"player_id": 4, "hero": "hulk", "role": "vanguard"},
        {"player_id": 5, "hero": "jeff-the-land-shark", "role": "strategist"},
        {"player_id": 6, "hero": "mantis", "role": "strategist"}
    ],
    "team2_players": [
        {"player_id": 7, "hero": "psylocke", "role": "duelist"},
        {"player_id": 8, "hero": "magik", "role": "duelist"},
        {"player_id": 9, "hero": "magneto", "role": "vanguard"},
        {"player_id": 10, "hero": "thor", "role": "vanguard"},
        {"player_id": 11, "hero": "adam-warlock", "role": "strategist"},
        {"player_id": 12, "hero": "loki", "role": "strategist"}
    ]
}' "NEW HEROES selected for Map 2!"

# Map 2 progress
send_update "score-update" '{
    "map_index": 1,
    "team1_score": 20,
    "team2_score": 25
}' "Team 2 taking control early! 20-25"

send_update "score-update" '{
    "map_index": 1,
    "team1_score": 38,
    "team2_score": 42
}' "Team 2 extending lead: 38-42"

send_update "score-update" '{
    "map_index": 1,
    "team1_score": 45,
    "team2_score": 50
}' "MAP 2 COMPLETE: Team 2 wins 45-50! Series tied 1-1"

# Update series score
send_update "map-update" '{
    "map_index": 1,
    "status": "completed",
    "winner": "team2",
    "series_score_team1": 1,
    "series_score_team2": 1
}' "Series tied 1-1! Map 3 will decide it all!"

echo ""
echo "================================================"
echo "📍 MAP 3: CONVERGENCE - CONTROL POINT (DECIDER)"
echo "================================================"
echo ""

# Start Map 3
send_update "map-update" '{
    "current_map": 3,
    "map_index": 2,
    "status": "active"
}' "MAP 3 - THE DECIDER!"

# Hero selections for Map 3
send_update "hero-update" '{
    "map_index": 2,
    "team1_players": [
        {"player_id": 1, "hero": "spider-man", "role": "duelist"},
        {"player_id": 2, "hero": "hela", "role": "duelist"},
        {"player_id": 3, "hero": "doctor-strange", "role": "vanguard"},
        {"player_id": 4, "hero": "groot", "role": "vanguard"},
        {"player_id": 5, "hero": "luna-snow", "role": "strategist"},
        {"player_id": 6, "hero": "cloak-and-dagger", "role": "strategist"}
    ],
    "team2_players": [
        {"player_id": 7, "hero": "black-panther", "role": "duelist"},
        {"player_id": 8, "hero": "winter-soldier", "role": "duelist"},
        {"player_id": 9, "hero": "peni-parker", "role": "vanguard"},
        {"player_id": 10, "hero": "venom", "role": "vanguard"},
        {"player_id": 11, "hero": "adam-warlock", "role": "strategist"},
        {"player_id": 12, "hero": "rocket-raccoon", "role": "strategist"}
    ]
}' "Final map hero selections locked!"

# Map 3 intense battle
send_update "score-update" '{
    "map_index": 2,
    "team1_score": 25,
    "team2_score": 25
}' "TIED at 25-25! Intense battle!"

send_update "score-update" '{
    "map_index": 2,
    "team1_score": 48,
    "team2_score": 48
}' "OVERTIME! 48-48! Next point wins it all!"

send_update "score-update" '{
    "map_index": 2,
    "team1_score": 49,
    "team2_score": 51
}' "TEAM 2 WINS! 49-51 in overtime!"

# Final match update
send_update "status-update" '{
    "status": "completed",
    "series_score_team1": 1,
    "series_score_team2": 2
}' "MATCH COMPLETE: Team 2 wins the series 2-1!"

echo ""
echo "================================================"
echo "🏆 MATCH COMPLETE - Team 2 Victory (2-1)"
echo "================================================"
echo ""
echo "Map 1: Team 1 wins 50-48"
echo "Map 2: Team 2 wins 50-45"
echo "Map 3: Team 2 wins 51-49 (OT)"
echo ""
echo "Check https://staging.mrvl.net/#match-detail/6"
echo "All updates should appear IMMEDIATELY!"