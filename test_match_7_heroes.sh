#!/bin/bash

# Comprehensive test script for match 7 on staging.mrvl.net
# Tests hero data for player 405 across maps and polling functionality

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test configuration
BASE_URL="https://staging.mrvl.net/api"
MATCH_ID=7
PLAYER_ID=405
EXPECTED_HEROES=("Hela" "Iron Man" "Rocket Raccoon")
EXPECTED_MAPS=("map_1" "map_2" "map_3")

# Test results tracking
PASSED=0
FAILED=0
TOTAL_TESTS=0

# Function to print test header
print_header() {
    echo -e "\n${BLUE}=== $1 ===${NC}"
}

# Function to print test result
print_result() {
    local test_name="$1"
    local result="$2"
    local details="$3"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗ FAIL${NC}: $test_name"
        if [ -n "$details" ]; then
            echo -e "  ${YELLOW}Details:${NC} $details"
        fi
        FAILED=$((FAILED + 1))
    fi
}

# Function to make API request and return response
make_request() {
    local url="$1"
    local description="$2"
    
    echo -e "${YELLOW}Requesting:${NC} $url"
    
    response=$(curl -s -w "HTTPSTATUS:%{http_code}" "$url" 2>/dev/null)
    http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
    body=$(echo "$response" | sed -e 's/HTTPSTATUS:.*//g')
    
    if [ "$http_code" != "200" ]; then
        print_result "$description" "FAIL" "HTTP $http_code"
        echo -e "${RED}Response body:${NC} $body"
        return 1
    fi
    
    echo "$body"
    return 0
}

# Function to extract hero for player on specific map
extract_hero_for_player_map() {
    local json="$1"
    local player_id="$2"
    local map_number="$3"
    
    # Use jq to extract the hero for the specific player and map number
    echo "$json" | jq -r --arg pid "$player_id" --arg mapnum "$map_number" '
        .data.score.maps // [] | 
        map(select(.map_number == ($mapnum | tonumber))) | 
        .[0].team1_composition // [] | 
        map(select(.player_id == ($pid | tonumber))) | 
        .[0].hero // "null"'
}

# Function to count total eliminations for a player across all maps
count_player_total_eliminations() {
    local json="$1"
    local player_id="$2"
    
    echo "$json" | jq -r --arg pid "$player_id" '
        [.data.score.maps // [] | .[] | .team1_composition // [] | map(select(.player_id == ($pid | tonumber))) | .[0].eliminations // 0] | 
        map(tonumber) | 
        add // 0'
}

print_header "MATCH 7 COMPREHENSIVE TEST SUITE"
echo "Testing match endpoint and hero data for player $PLAYER_ID"
echo "Expected heroes: ${EXPECTED_HEROES[0]} (map 1), ${EXPECTED_HEROES[1]} (map 2), ${EXPECTED_HEROES[2]} (map 3)"

# Test 1: Main match endpoint
print_header "Test 1: Main Match Endpoint"
if response=$(make_request "$BASE_URL/matches/$MATCH_ID" "GET /api/matches/7"); then
    print_result "Main match endpoint accessibility" "PASS"
    
    # Store the main response for hero verification
    main_response="$response"
    
    # Verify match ID
    match_id=$(echo "$response" | jq -r '.data.id // "null"')
    if [ "$match_id" = "$MATCH_ID" ]; then
        print_result "Match ID verification" "PASS"
    else
        print_result "Match ID verification" "FAIL" "Expected $MATCH_ID, got $match_id"
    fi
    
    # Verify maps exist
    map_count=$(echo "$response" | jq -r '.data.score.maps | length // 0')
    if [ "$map_count" -ge 3 ]; then
        print_result "Maps data availability" "PASS" "Found $map_count maps"
    else
        print_result "Maps data availability" "FAIL" "Expected at least 3 maps, found $map_count"
    fi
else
    main_response=""
fi

# Test 2: Hero verification for each map
print_header "Test 2: Hero Verification for Player $PLAYER_ID"
if [ -n "$main_response" ]; then
    for i in {0..2}; do
        map_number=$((i + 1))
        expected_hero="${EXPECTED_HEROES[$i]}"
        
        # Extract actual hero for this map
        actual_hero=$(extract_hero_for_player_map "$main_response" "$PLAYER_ID" "$map_number")
        
        if [ "$actual_hero" = "$expected_hero" ]; then
            print_result "Player $PLAYER_ID hero on map $map_number" "PASS" "$expected_hero"
        else
            print_result "Player $PLAYER_ID hero on map $map_number" "FAIL" "Expected '$expected_hero', got '$actual_hero'"
        fi
    done
else
    print_result "Hero verification skipped" "FAIL" "Main response not available"
fi

# Test 3: Polling parameter test
print_header "Test 3: Polling Parameter Test"
timestamp=$(date +%s)
polling_url="$BASE_URL/matches/$MATCH_ID?t=$timestamp"

if response=$(make_request "$polling_url" "GET /api/matches/7 with polling parameter"); then
    print_result "Polling parameter endpoint accessibility" "PASS"
    polling_response="$response"
else
    polling_response=""
fi

# Test 4: Multiple consecutive polling requests
print_header "Test 4: Consecutive Polling Requests (Stats Accumulation Check)"
if [ -n "$main_response" ]; then
    # Get baseline eliminations for player 405
    baseline_eliminations=$(count_player_total_eliminations "$main_response" "$PLAYER_ID")
    
    echo "Baseline eliminations for player $PLAYER_ID: $baseline_eliminations"
    
    stats_consistent=true
    for i in {1..5}; do
        timestamp=$(date +%s)
        poll_url="$BASE_URL/matches/$MATCH_ID?t=$timestamp"
        
        if poll_response=$(make_request "$poll_url" "Polling request $i/5"); then
            current_eliminations=$(count_player_total_eliminations "$poll_response" "$PLAYER_ID")
            
            if [ "$current_eliminations" != "$baseline_eliminations" ]; then
                stats_consistent=false
                print_result "Polling request $i stats consistency" "FAIL" "Eliminations: $baseline_eliminations->$current_eliminations"
            else
                print_result "Polling request $i stats consistency" "PASS" "Stats unchanged ($current_eliminations eliminations)"
            fi
        else
            stats_consistent=false
        fi
        
        # Small delay between requests
        sleep 0.5
    done
    
    if [ "$stats_consistent" = true ]; then
        print_result "Overall stats accumulation test" "PASS" "No stat accumulation detected"
    else
        print_result "Overall stats accumulation test" "FAIL" "Stats changed during polling"
    fi
else
    print_result "Consecutive polling test skipped" "FAIL" "Main response not available"
fi

# Test 5: Live scoring endpoints
print_header "Test 5: Live Scoring Endpoints Check"

# Test common live scoring endpoint patterns
live_endpoints=(
    "$BASE_URL/matches/$MATCH_ID/live"
    "$BASE_URL/matches/$MATCH_ID/score"
    "$BASE_URL/matches/$MATCH_ID/events"
    "$BASE_URL/live/matches/$MATCH_ID"
)

live_endpoints_working=0
for endpoint in "${live_endpoints[@]}"; do
    echo -e "${YELLOW}Testing live endpoint:${NC} $endpoint"
    
    response=$(curl -s -w "HTTPSTATUS:%{http_code}" "$endpoint" 2>/dev/null)
    http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
    
    if [ "$http_code" = "200" ]; then
        print_result "Live endpoint $(basename "$endpoint")" "PASS" "HTTP 200"
        live_endpoints_working=$((live_endpoints_working + 1))
    elif [ "$http_code" = "404" ]; then
        print_result "Live endpoint $(basename "$endpoint")" "FAIL" "HTTP 404 - Not Found"
    else
        print_result "Live endpoint $(basename "$endpoint")" "FAIL" "HTTP $http_code"
    fi
done

if [ $live_endpoints_working -gt 0 ]; then
    print_result "Live scoring availability" "PASS" "$live_endpoints_working endpoint(s) working"
else
    print_result "Live scoring availability" "FAIL" "No live endpoints accessible"
fi

# Final summary
print_header "TEST SUMMARY"
echo -e "Total tests run: ${BLUE}$TOTAL_TESTS${NC}"
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "\n${GREEN}🎉 ALL TESTS PASSED!${NC}"
    exit 0
else
    echo -e "\n${RED}❌ $FAILED TEST(S) FAILED${NC}"
    exit 1
fi