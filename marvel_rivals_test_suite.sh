#!/bin/bash

# MARVEL RIVALS PLATFORM - COMPREHENSIVE TEST SUITE
# Tests the complete workflow from authentication to live match management

echo "🎮 MARVEL RIVALS PLATFORM - TESTING SUITE"
echo "=========================================="

# Configuration
BASE_URL="https://staging.mrvl.net/api"
ADMIN_EMAIL="admin@mrvl.net"
ADMIN_PASSWORD="your_admin_password"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
TESTS_PASSED=0
TESTS_FAILED=0

# Function to print test results
print_test_result() {
    local test_name="$1"
    local result="$2"
    local response="$3"
    
    if [[ "$result" == "PASS" ]]; then
        echo -e "${GREEN}✅ $test_name - PASSED${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ $test_name - FAILED${NC}"
        echo -e "${RED}Response: $response${NC}"
        ((TESTS_FAILED++))
    fi
    echo ""
}

# Function to test API endpoint
test_endpoint() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    local expected_key="$4"
    local test_name="$5"
    local headers="$6"
    
    echo -e "${BLUE}🧪 Testing: $test_name${NC}"
    
    if [[ "$method" == "GET" ]]; then
        response=$(curl -s -X GET "$BASE_URL$endpoint" $headers)
    elif [[ "$method" == "POST" ]]; then
        response=$(curl -s -X POST "$BASE_URL$endpoint" -H "Content-Type: application/json" $headers -d "$data")
    elif [[ "$method" == "PUT" ]]; then
        response=$(curl -s -X PUT "$BASE_URL$endpoint" -H "Content-Type: application/json" $headers -d "$data")
    fi
    
    if echo "$response" | jq -e ".$expected_key" > /dev/null 2>&1; then
        print_test_result "$test_name" "PASS" "$response"
        echo "$response" | jq .
    else
        print_test_result "$test_name" "FAIL" "$response"
    fi
    
    echo "$response"
}

# ==========================================
# STEP 1: AUTHENTICATION TEST
# ==========================================

echo -e "${YELLOW}🔐 STEP 1: AUTHENTICATION${NC}"
echo "=============================="

AUTH_RESPONSE=$(curl -s -X POST "$BASE_URL/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")

if echo "$AUTH_RESPONSE" | jq -e '.token' > /dev/null 2>&1; then
    TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.token')
    print_test_result "Admin Authentication" "PASS" "$AUTH_RESPONSE"
    export AUTH_HEADER="-H \"Authorization: Bearer $TOKEN\""
else
    print_test_result "Admin Authentication" "FAIL" "$AUTH_RESPONSE"
    echo "❌ Cannot proceed without authentication token"
    exit 1
fi

# ==========================================
# STEP 2: GAME DATA TESTS
# ==========================================

echo -e "${YELLOW}🎮 STEP 2: GAME DATA${NC}"
echo "======================"

# Test heroes endpoint
test_endpoint "GET" "/game-data/heroes" "" "heroes" "Marvel Rivals Heroes Data" ""

# Test maps endpoint  
test_endpoint "GET" "/game-data/maps" "" "maps" "Marvel Rivals Maps Data" ""

# ==========================================
# STEP 3: TEAM MANAGEMENT TESTS
# ==========================================

echo -e "${YELLOW}👥 STEP 3: TEAM MANAGEMENT${NC}"
echo "============================="

# Create Team 1
TEAM1_DATA='{
    "name": "Test Sentinels",
    "country": "US",
    "logo": "https://example.com/sentinels.png",
    "description": "Test team for Marvel Rivals"
}'

TEAM1_RESPONSE=$(test_endpoint "POST" "/admin/teams" "$TEAM1_DATA" "team_id" "Create Team 1" "$AUTH_HEADER")
TEAM1_ID=$(echo "$TEAM1_RESPONSE" | jq -r '.team_id')

# Create Team 2
TEAM2_DATA='{
    "name": "Test Guardians",
    "country": "KR", 
    "logo": "https://example.com/guardians.png",
    "description": "Korean test team"
}'

TEAM2_RESPONSE=$(test_endpoint "POST" "/admin/teams" "$TEAM2_DATA" "team_id" "Create Team 2" "$AUTH_HEADER")
TEAM2_ID=$(echo "$TEAM2_RESPONSE" | jq -r '.team_id')

# ==========================================
# STEP 4: PLAYER MANAGEMENT TESTS
# ==========================================

echo -e "${YELLOW}🏃 STEP 4: PLAYER MANAGEMENT${NC}"
echo "==============================="

# Create players for Team 1
PLAYERS_TEAM1=(
    '{"name":"TestTank1","team_id":'$TEAM1_ID',"role":"Vanguard","country":"US","age":22}'
    '{"name":"TestDPS1","team_id":'$TEAM1_ID',"role":"Duelist","country":"US","age":20}'  
    '{"name":"TestDPS2","team_id":'$TEAM1_ID',"role":"Duelist","country":"US","age":24}'
    '{"name":"TestSupport1","team_id":'$TEAM1_ID',"role":"Strategist","country":"US","age":23}'
    '{"name":"TestSupport2","team_id":'$TEAM1_ID',"role":"Strategist","country":"US","age":21}'
    '{"name":"TestFlex1","team_id":'$TEAM1_ID',"role":"Vanguard","country":"US","age":25}'
)

PLAYER_IDS_TEAM1=()
for i in "${!PLAYERS_TEAM1[@]}"; do
    PLAYER_RESPONSE=$(test_endpoint "POST" "/admin/players" "${PLAYERS_TEAM1[$i]}" "player_id" "Create Team 1 Player $((i+1))" "$AUTH_HEADER")
    PLAYER_ID=$(echo "$PLAYER_RESPONSE" | jq -r '.player_id')
    PLAYER_IDS_TEAM1+=($PLAYER_ID)
done

# Create players for Team 2
PLAYERS_TEAM2=(
    '{"name":"TestTank2","team_id":'$TEAM2_ID',"role":"Vanguard","country":"KR","age":21}'
    '{"name":"TestDPS3","team_id":'$TEAM2_ID',"role":"Duelist","country":"KR","age":19}'
    '{"name":"TestDPS4","team_id":'$TEAM2_ID',"role":"Duelist","country":"KR","age":22}'
    '{"name":"TestSupport3","team_id":'$TEAM2_ID',"role":"Strategist","country":"KR","age":20}'
    '{"name":"TestSupport4","team_id":'$TEAM2_ID',"role":"Strategist","country":"KR","age":23}'
    '{"name":"TestFlex2","team_id":'$TEAM2_ID',"role":"Vanguard","country":"KR","age":24}'
)

PLAYER_IDS_TEAM2=()
for i in "${!PLAYERS_TEAM2[@]}"; do
    PLAYER_RESPONSE=$(test_endpoint "POST" "/admin/players" "${PLAYERS_TEAM2[$i]}" "player_id" "Create Team 2 Player $((i+1))" "$AUTH_HEADER")
    PLAYER_ID=$(echo "$PLAYER_RESPONSE" | jq -r '.player_id')
    PLAYER_IDS_TEAM2+=($PLAYER_ID)
done

# ==========================================
# STEP 5: EVENT MANAGEMENT TESTS
# ==========================================

echo -e "${YELLOW}🏆 STEP 5: EVENT MANAGEMENT${NC}"
echo "=============================="

EVENT_DATA='{
    "name": "Test Marvel Rivals Championship 2025",
    "type": "championship",
    "start_date": "2025-02-01",
    "end_date": "2025-02-07",
    "location": "Los Angeles, CA",
    "prize_pool": 100000,
    "description": "Test championship for Marvel Rivals"
}'

EVENT_RESPONSE=$(test_endpoint "POST" "/admin/events" "$EVENT_DATA" "event_id" "Create Championship Event" "$AUTH_HEADER")
EVENT_ID=$(echo "$EVENT_RESPONSE" | jq -r '.event_id')

# ==========================================
# STEP 6: MATCH CREATION TESTS
# ==========================================

echo -e "${YELLOW}⚔️ STEP 6: MATCH CREATION${NC}"
echo "============================"

MATCH_DATA='{
    "team1_id": '$TEAM1_ID',
    "team2_id": '$TEAM2_ID',
    "event_id": '$EVENT_ID',
    "scheduled_at": "2025-02-02T18:00:00Z",
    "format": "BO5",
    "status": "scheduled"
}'

MATCH_RESPONSE=$(test_endpoint "POST" "/admin/matches" "$MATCH_DATA" "match_id" "Create Match" "$AUTH_HEADER")
MATCH_ID=$(echo "$MATCH_RESPONSE" | jq -r '.match_id')

# ==========================================
# STEP 7: LIVE MATCH MANAGEMENT TESTS
# ==========================================

echo -e "${YELLOW}🔴 STEP 7: LIVE MATCH MANAGEMENT${NC}"
echo "=================================="

# Start match
START_MATCH_DATA='{
    "status": "live",
    "started_at": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",
    "current_map": 1,
    "viewers": 15000
}'

test_endpoint "PUT" "/admin/matches/$MATCH_ID" "$START_MATCH_DATA" "success" "Start Match Live" "$AUTH_HEADER"

# Set initial composition
COMPOSITION_DATA='{
    "maps_data": [
        {
            "map_number": 1,
            "map_name": "Asgard: Royal Palace",
            "mode": "Domination", 
            "team1_score": 0,
            "team2_score": 0,
            "team1_composition": [
                {
                    "player_id": '${PLAYER_IDS_TEAM1[0]}',
                    "player_name": "TestTank1",
                    "hero": "Doctor Strange",
                    "role": "Vanguard",
                    "eliminations": 0,
                    "deaths": 0,
                    "assists": 0,
                    "damage": 0,
                    "healing": 0,
                    "damageBlocked": 0
                },
                {
                    "player_id": '${PLAYER_IDS_TEAM1[1]}',
                    "player_name": "TestDPS1",
                    "hero": "Iron Man",
                    "role": "Duelist",
                    "eliminations": 0,
                    "deaths": 0,
                    "assists": 0,
                    "damage": 0,
                    "healing": 0,
                    "damageBlocked": 0
                }
            ],
            "team2_composition": [
                {
                    "player_id": '${PLAYER_IDS_TEAM2[0]}',
                    "player_name": "TestTank2",
                    "hero": "Thor",
                    "role": "Vanguard",
                    "eliminations": 0,
                    "deaths": 0,
                    "assists": 0,
                    "damage": 0,
                    "healing": 0,
                    "damageBlocked": 0
                },
                {
                    "player_id": '${PLAYER_IDS_TEAM2[1]}',
                    "player_name": "TestDPS3",
                    "hero": "Punisher",
                    "role": "Duelist",
                    "eliminations": 0,
                    "deaths": 0,
                    "assists": 0,
                    "damage": 0,
                    "healing": 0,
                    "damageBlocked": 0
                }
            ]
        }
    ]
}'

test_endpoint "PUT" "/admin/matches/$MATCH_ID" "$COMPOSITION_DATA" "success" "Set Hero Compositions" "$AUTH_HEADER"

# Live scoring update
LIVE_SCORING_DATA='{
    "map_number": 1,
    "player_updates": [
        {
            "player_id": '${PLAYER_IDS_TEAM1[0]}',
            "eliminations": 5,
            "deaths": 2,
            "assists": 3,
            "damage": 8500,
            "healing": 0,
            "damageBlocked": 4200
        },
        {
            "player_id": '${PLAYER_IDS_TEAM2[0]}',
            "eliminations": 4,
            "deaths": 3,
            "assists": 2,
            "damage": 7800,
            "healing": 0,
            "damageBlocked": 3800
        }
    ],
    "team1_score": 2,
    "team2_score": 1,
    "viewers": 18500
}'

test_endpoint "PUT" "/admin/matches/$MATCH_ID/live-scoring" "$LIVE_SCORING_DATA" "success" "Live Scoring Update" "$AUTH_HEADER"

# ==========================================
# STEP 8: ANALYTICS TESTS
# ==========================================

echo -e "${YELLOW}📊 STEP 8: ANALYTICS${NC}"
echo "====================="

# Get complete match data
test_endpoint "GET" "/admin/matches/$MATCH_ID/complete" "" "match" "Get Complete Match Data" "$AUTH_HEADER"

# Test advanced analytics (if match creator was used)
echo -e "${BLUE}🧪 Testing: Create Marvel Rivals Test Match${NC}"
MARVEL_MATCH_RESPONSE=$(curl -s -X POST "$BASE_URL/admin/matches/create-complete-marvel" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"team1_id":'$TEAM1_ID',"team2_id":'$TEAM2_ID',"event_id":'$EVENT_ID'}')

if echo "$MARVEL_MATCH_RESPONSE" | jq -e '.match_id' > /dev/null 2>&1; then
    MARVEL_MATCH_ID=$(echo "$MARVEL_MATCH_RESPONSE" | jq -r '.match_id')
    print_test_result "Create Marvel Test Match" "PASS" "$MARVEL_MATCH_RESPONSE"
    
    # Test analytics on the Marvel match
    test_endpoint "GET" "/admin/matches/$MARVEL_MATCH_ID/marvel-analytics" "" "analytics" "Marvel Match Analytics" "$AUTH_HEADER"
    
    # Test leaderboards
    test_endpoint "GET" "/admin/marvel-leaderboards/$EVENT_ID" "" "leaderboards" "Event Leaderboards" "$AUTH_HEADER"
else
    print_test_result "Create Marvel Test Match" "FAIL" "$MARVEL_MATCH_RESPONSE"
fi

# ==========================================
# STEP 9: SEARCH & FILTER TESTS
# ==========================================

echo -e "${YELLOW}🔍 STEP 9: SEARCH & FILTERING${NC}"
echo "==============================="

# Search matches by team
test_endpoint "GET" "/admin/matches/search?team1_id=$TEAM1_ID" "" "matches" "Search Matches by Team" "$AUTH_HEADER"

# Search matches by status
test_endpoint "GET" "/admin/matches/search?status=live" "" "matches" "Search Live Matches" "$AUTH_HEADER"

# Search matches by event
test_endpoint "GET" "/admin/matches/search?event_id=$EVENT_ID" "" "matches" "Search Matches by Event" "$AUTH_HEADER"

# ==========================================
# STEP 10: COMPLETE MATCH TEST
# ==========================================

echo -e "${YELLOW}🏁 STEP 10: COMPLETE MATCH${NC}"
echo "============================"

COMPLETE_MATCH_DATA='{
    "status": "completed",
    "completed_at": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",
    "team1_score": 3,
    "team2_score": 2,
    "peak_viewers": 25000,
    "viewers": 22000
}'

test_endpoint "PUT" "/admin/matches/$MATCH_ID" "$COMPLETE_MATCH_DATA" "success" "Complete Match" "$AUTH_HEADER"

# ==========================================
# FINAL RESULTS
# ==========================================

echo ""
echo -e "${YELLOW}📋 TEST SUMMARY${NC}"
echo "================="
echo -e "${GREEN}✅ Tests Passed: $TESTS_PASSED${NC}"
echo -e "${RED}❌ Tests Failed: $TESTS_FAILED${NC}"
echo ""

TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))
if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$((TESTS_PASSED * 100 / TOTAL_TESTS))
    echo -e "${BLUE}📈 Success Rate: $SUCCESS_RATE%${NC}"
    
    if [ $SUCCESS_RATE -ge 90 ]; then
        echo -e "${GREEN}🎉 EXCELLENT! Marvel Rivals platform is working great!${NC}"
    elif [ $SUCCESS_RATE -ge 70 ]; then
        echo -e "${YELLOW}⚠️  GOOD! Some issues need attention.${NC}"
    else
        echo -e "${RED}⛔ NEEDS WORK! Multiple issues detected.${NC}"
    fi
else
    echo -e "${RED}⛔ NO TESTS EXECUTED${NC}"
fi

echo ""
echo -e "${BLUE}🎮 Marvel Rivals Platform Test Complete!${NC}"

# Export important IDs for further testing
echo ""
echo -e "${YELLOW}📝 GENERATED TEST DATA IDs:${NC}"
echo "TOKEN=$TOKEN"
echo "TEAM1_ID=$TEAM1_ID"
echo "TEAM2_ID=$TEAM2_ID"
echo "EVENT_ID=$EVENT_ID"
echo "MATCH_ID=$MATCH_ID"
if [ ! -z "$MARVEL_MATCH_ID" ]; then
    echo "MARVEL_MATCH_ID=$MARVEL_MATCH_ID"
fi