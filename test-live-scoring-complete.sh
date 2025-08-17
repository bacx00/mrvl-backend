#!/bin/bash

# Complete Live Scoring Test Script
# Tests hero updates, map scores, and player stats

API_URL="https://staging.mrvl.net/api"
MATCH_ID=7
TOKEN="1|RiSBCksAgjwjN8pOLCVCXjJgaJpCKu0UvCjykfrR"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}🎮 COMPLETE LIVE SCORING TEST - MATCH ${MATCH_ID}${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Function to send update and display result
send_update() {
    local TYPE=$1
    local DATA=$2
    local MESSAGE=$3
    
    echo -e "${YELLOW}📡 ${MESSAGE}${NC}"
    
    RESPONSE=$(curl -s -X POST "$API_URL/matches/$MATCH_ID/live-update" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"type\": \"$TYPE\",
            \"data\": $DATA,
            \"timestamp\": \"$(date -Iseconds)\"
        }")
    
    if echo "$RESPONSE" | jq -e '.success' > /dev/null 2>&1; then
        echo -e "${GREEN}   ✅ Success${NC}"
    else
        echo -e "   ❌ Failed: $RESPONSE"
    fi
    
    sleep 1
}

echo -e "${BLUE}1. Testing Status Updates${NC}"
echo "--------------------------------"
send_update "status-update" '{"status": "live"}' "Setting match to LIVE"

echo ""
echo -e "${BLUE}2. Testing Map Score Updates${NC}"
echo "--------------------------------"
send_update "score-update" '{
    "map_index": 0,
    "team1_score": 1,
    "team2_score": 0,
    "map_name": "Hellfire Gala: Krakoa",
    "game_mode": "Domination"
}' "Map 1: Team 1 takes the lead (1-0)"

send_update "score-update" '{
    "map_index": 0,
    "team1_score": 2,
    "team2_score": 0
}' "Map 1: Team 1 wins (2-0)"

echo ""
echo -e "${BLUE}3. Testing Hero Updates (Individual)${NC}"
echo "--------------------------------"

# Team 1 Heroes
send_update "hero-update" '{
    "map_index": 0,
    "team": 1,
    "player_id": 659,
    "hero": "spider-man",
    "role": "duelist"
}' "Team 1 Player 1: Spider-Man (Duelist)"

send_update "hero-update" '{
    "map_index": 0,
    "team": 1,
    "player_id": 657,
    "hero": "iron-man",
    "role": "duelist"
}' "Team 1 Player 2: Iron Man (Duelist)"

send_update "hero-update" '{
    "map_index": 0,
    "team": 1,
    "player_id": 660,
    "hero": "doctor-strange",
    "role": "vanguard"
}' "Team 1 Player 3: Doctor Strange (Vanguard)"

# Team 2 Heroes
send_update "hero-update" '{
    "map_index": 0,
    "team": 2,
    "player_id": 632,
    "hero": "black-panther",
    "role": "duelist"
}' "Team 2 Player 1: Black Panther (Duelist)"

send_update "hero-update" '{
    "map_index": 0,
    "team": 2,
    "player_id": 628,
    "hero": "scarlet-witch",
    "role": "duelist"
}' "Team 2 Player 2: Scarlet Witch (Duelist)"

send_update "hero-update" '{
    "map_index": 0,
    "team": 2,
    "player_id": 630,
    "hero": "magneto",
    "role": "vanguard"
}' "Team 2 Player 3: Magneto (Vanguard)"

echo ""
echo -e "${BLUE}4. Testing Player Stats Updates (Individual)${NC}"
echo "--------------------------------"

# Team 1 Stats
send_update "stats-update" '{
    "map_index": 0,
    "team": 1,
    "player_id": 659,
    "stat_type": "kills",
    "value": 5
}' "Team 1 Player 1: 5 Kills"

send_update "stats-update" '{
    "map_index": 0,
    "team": 1,
    "player_id": 659,
    "stat_type": "deaths",
    "value": 2
}' "Team 1 Player 1: 2 Deaths"

send_update "stats-update" '{
    "map_index": 0,
    "team": 1,
    "player_id": 659,
    "stat_type": "assists",
    "value": 3
}' "Team 1 Player 1: 3 Assists"

# Team 2 Stats
send_update "stats-update" '{
    "map_index": 0,
    "team": 2,
    "player_id": 632,
    "stat_type": "kills",
    "value": 3
}' "Team 2 Player 1: 3 Kills"

send_update "stats-update" '{
    "map_index": 0,
    "team": 2,
    "player_id": 632,
    "stat_type": "deaths",
    "value": 4
}' "Team 2 Player 1: 4 Deaths"

send_update "stats-update" '{
    "map_index": 0,
    "team": 2,
    "player_id": 632,
    "stat_type": "assists",
    "value": 2
}' "Team 2 Player 1: 2 Assists"

echo ""
echo -e "${BLUE}5. Testing Map 2 Updates${NC}"
echo "--------------------------------"
send_update "map-update" '{
    "current_map": 2,
    "map_index": 1
}' "Switching to Map 2"

send_update "score-update" '{
    "map_index": 1,
    "team1_score": 0,
    "team2_score": 1,
    "map_name": "Hydra Base",
    "game_mode": "Convoy"
}' "Map 2: Team 2 leads (0-1)"

send_update "score-update" '{
    "map_index": 1,
    "team1_score": 0,
    "team2_score": 2
}' "Map 2: Team 2 wins (0-2)"

echo ""
echo -e "${BLUE}6. Testing Series Score Update${NC}"
echo "--------------------------------"
send_update "score-update" '{
    "series_score_team1": 1,
    "series_score_team2": 1
}' "Series tied 1-1"

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}✅ TEST COMPLETE${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo "Check the following:"
echo "1. Live Scoring Panel: https://staging.mrvl.net/admin/matches/live-scoring"
echo "2. Match Detail Page: https://staging.mrvl.net/#match-detail/7"
echo ""
echo "Verify:"
echo "- Heroes are displayed correctly"
echo "- Map scores update immediately"
echo "- Player stats show proper values"
echo "- Series score shows 1-1"
echo "- No resets or loops occur"