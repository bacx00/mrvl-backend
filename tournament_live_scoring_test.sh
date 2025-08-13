#!/bin/bash

# Tournament Live Scoring System Verification Script
# Tests the complete integration between SimplifiedLiveScoring and MatchDetailPage

BACKEND_URL="${BACKEND_URL:-https://staging.mrvl.net}"
ADMIN_EMAIL="admin@mrvl.net"
ADMIN_PASSWORD="admin123"

echo "🚀 Starting Tournament Live Scoring System Verification..."
echo "Backend URL: $BACKEND_URL"
echo ""

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
ERRORS=()

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to make HTTP requests
make_request() {
    local method="$1"
    local url="$2"
    local data="$3"
    local auth_token="$4"
    
    local headers=("-H" "Content-Type: application/json" "-H" "Accept: application/json")
    
    if [ ! -z "$auth_token" ]; then
        headers+=("-H" "Authorization: Bearer $auth_token")
    fi
    
    if [ "$method" = "POST" ] || [ "$method" = "PUT" ]; then
        curl -s -X "$method" "${headers[@]}" -d "$data" "$url"
    else
        curl -s -X "$method" "${headers[@]}" "$url"
    fi
}

# Function to test result
test_result() {
    local test_name="$1"
    local success="$2"
    local message="$3"
    
    if [ "$success" = "true" ]; then
        log "✅ $test_name: PASSED"
        ((TESTS_PASSED++))
    else
        log "❌ $test_name: FAILED - $message"
        ERRORS+=("$test_name: $message")
        ((TESTS_FAILED++))
    fi
}

# Step 1: Use admin token
log "🔐 Using admin token for authentication..."
ACCESS_TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiOWVjMjRiNTNlYmUxZmMxMGFhNjI3MGViYjg4YTQ3YmY3NDMzMjI1NGNiYThhMzczNTJhZTRkY2EzZjk3NmI0Njk4Mzg2MTRlOWVkMjk4ZjAiLCJpYXQiOjE3NTUwMzQyOTIuMTk1NDE3ODgxMDExOTYyODkwNjI1LCJuYmYiOjE3NTUwMzQyOTIuMTk1NDI0MDc5ODk1MDE5NTMxMjUsImV4cCI6MTc4NjU3MDI5Mi4xNzU4MDEwMzg3NDI2NjU0Mjk2ODc1LCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.TtKY0rpjDZpLiIR-m9z9Jpgwq7wIygK4Vg9JC8396hSxAQOi2nE8YbobBffOfDEwg-7Xolk7GQ-CAuCkFyg0oKIEwUHOxWeVyCpSdDGtOl6vlkJeUCGAwipWbdOYWK50AC2ZeK9pSdNuTab7gmR7GbhTrJcl4_Yt3I1ziIwTpJHdwGDebhsYiiqjCLnkkKhOr1XnGyqqq9WBqYfsTCTtPWds8LEklRk1vqWMdA4H1QknBfS3pZVfdh1jfA647cQeLF8cNKgkJgmfwr9fattzP9ADu_VDf7UXbauls5ug_3nkxN8IfIK-QYuEMAi3egl5EAS_5st5G5ZcmqWlsyRYnfMK8hR3ziG1PgZLMJykEqUcJOKtQpNNR7HXWlIeFcM4zTb6SGX2TLFl2Lc7dxzrGk9KLkJ0_ZQJMJxRZ6zpZUVzLezblLGkMkif9JJ1P0yP-GHGxmDnvmwW8cggNSxD6ir3m_Dnhm7uTItkziqHTWZSKMYGsW2rxKp_szt68NSjWg7UF7Ih8ke3yqhm5Hph0oTFvTbQGQfV66-aj1LD2zUJGvGHi72PVkKxDIF_N6L-hCAJUh8oDNvClpfRV99gPA3M7CL-N2EN7FkB2uZiFe0_4b8alp3NnSaH-xPoTpgJLXdsvnY62hQb8GJK_I-3cxpxo-lhfPHSFp8TeaGb0UU"

if [ ! -z "$ACCESS_TOKEN" ]; then
    log "✅ Admin token configured"
else
    log "❌ No admin token available"
    exit 1
fi

# Step 2: Create test BO3 match
log "🎮 Creating test BO3 match..."
MATCH_DATA='{
    "team1_id": 1,
    "team2_id": 2,
    "event_id": 1,
    "scheduled_at": "'$(date -u +%Y-%m-%dT%H:%M:%S.000Z)'",
    "format": "BO3",
    "status": "live",
    "maps": [
        {"map_name": "Tokyo 2099", "mode": "Convoy", "team1_score": 0, "team2_score": 0},
        {"map_name": "New York 2099", "mode": "Domination", "team1_score": 0, "team2_score": 0},
        {"map_name": "Shanghai 2099", "mode": "Push", "team1_score": 0, "team2_score": 0}
    ],
    "allow_past_date": true
}'

MATCH_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches" "$MATCH_DATA" "$ACCESS_TOKEN")
TEST_MATCH_ID=$(echo "$MATCH_RESPONSE" | jq -r '.data.id // empty')

if [ ! -z "$TEST_MATCH_ID" ] && [ "$TEST_MATCH_ID" != "null" ]; then
    log "✅ Test match created with ID: $TEST_MATCH_ID"
else
    log "❌ Test match creation failed"
    echo "Response: $MATCH_RESPONSE"
    exit 1
fi

# Step 3: Test live scoring data synchronization
log "🔄 Testing live scoring data synchronization..."
LIVE_STATS_UPDATE='{
    "team1_players": [
        {"id": 1, "username": "Player1", "hero": "Spider-Man", "kills": 15, "deaths": 3, "assists": 8, "damage": 12500, "healing": 0, "blocked": 2300},
        {"id": 2, "username": "Player2", "hero": "Iron Man", "kills": 12, "deaths": 5, "assists": 10, "damage": 11200, "healing": 0, "blocked": 1800},
        {"id": 3, "username": "Player3", "hero": "Doctor Strange", "kills": 8, "deaths": 4, "assists": 15, "damage": 8900, "healing": 7500, "blocked": 0},
        {"id": 4, "username": "Player4", "hero": "Mantis", "kills": 5, "deaths": 6, "assists": 18, "damage": 6200, "healing": 12000, "blocked": 0},
        {"id": 5, "username": "Player5", "hero": "Magneto", "kills": 7, "deaths": 4, "assists": 12, "damage": 9800, "healing": 0, "blocked": 4500},
        {"id": 6, "username": "Player6", "hero": "Groot", "kills": 3, "deaths": 8, "assists": 16, "damage": 5100, "healing": 3200, "blocked": 8900}
    ],
    "team2_players": [
        {"id": 7, "username": "Player7", "hero": "Wolverine", "kills": 18, "deaths": 7, "assists": 6, "damage": 14200, "healing": 0, "blocked": 1200},
        {"id": 8, "username": "Player8", "hero": "Hawkeye", "kills": 14, "deaths": 6, "assists": 9, "damage": 13100, "healing": 0, "blocked": 900},
        {"id": 9, "username": "Player9", "hero": "Wanda", "kills": 10, "deaths": 5, "assists": 12, "damage": 10800, "healing": 6800, "blocked": 0},
        {"id": 10, "username": "Player10", "hero": "Luna Snow", "kills": 4, "deaths": 7, "assists": 20, "damage": 5800, "healing": 14500, "blocked": 0},
        {"id": 11, "username": "Player11", "hero": "Doctor Doom", "kills": 9, "deaths": 6, "assists": 11, "damage": 11200, "healing": 0, "blocked": 5200},
        {"id": 12, "username": "Player12", "hero": "Peni Parker", "kills": 2, "deaths": 9, "assists": 18, "damage": 4900, "healing": 2800, "blocked": 9800}
    ],
    "series_score_team1": 0,
    "series_score_team2": 0,
    "team1_score": 75,
    "team2_score": 63,
    "current_map": 1,
    "total_maps": 3,
    "maps": {
        "1": {"team1Score": 75, "team2Score": 63, "status": "active", "winner": null},
        "2": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null},
        "3": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null}
    },
    "status": "live",
    "timestamp": '$(date +%s000)'
}'

UPDATE_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches/$TEST_MATCH_ID/update-live-stats" "$LIVE_STATS_UPDATE" "$ACCESS_TOKEN")
UPDATE_SUCCESS=$(echo "$UPDATE_RESPONSE" | jq -r '.success // false')

test_result "Live Stats Update" "$UPDATE_SUCCESS" "$(echo "$UPDATE_RESPONSE" | jq -r '.message // "Unknown error"')"

# Step 4: Verify data persistence
log "💾 Verifying data persistence..."
MATCH_FETCH_RESPONSE=$(make_request "GET" "$BACKEND_URL/api/matches/$TEST_MATCH_ID")
MATCH_DATA_EXISTS=$(echo "$MATCH_FETCH_RESPONSE" | jq -r 'if (.data.id // .id) then "true" else "false" end')

test_result "Data Persistence" "$MATCH_DATA_EXISTS" "Could not fetch match data"

if [ "$MATCH_DATA_EXISTS" = "true" ]; then
    TEAM1_SCORE=$(echo "$MATCH_FETCH_RESPONSE" | jq -r '.data.team1_score // .team1_score // 0')
    TEAM2_SCORE=$(echo "$MATCH_FETCH_RESPONSE" | jq -r '.data.team2_score // .team2_score // 0')
    STATUS=$(echo "$MATCH_FETCH_RESPONSE" | jq -r '.data.status // .status // "unknown"')
    log "   Team 1 Score: $TEAM1_SCORE"
    log "   Team 2 Score: $TEAM2_SCORE"
    log "   Status: $STATUS"
fi

# Step 5: Test Best of 3 map progression
log "🗺️ Testing BO3 map progression..."

# Map 1 completion
MAP1_WIN_UPDATE='{
    "series_score_team1": 1,
    "series_score_team2": 0,
    "current_map": 2,
    "maps": {
        "1": {"team1Score": 100, "team2Score": 87, "status": "completed", "winner": 1},
        "2": {"team1Score": 0, "team2Score": 0, "status": "active", "winner": null},
        "3": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null}
    },
    "team1_score": 0,
    "team2_score": 0,
    "timestamp": '$(date +%s000)'
}'

MAP1_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches/$TEST_MATCH_ID/update-live-stats" "$MAP1_WIN_UPDATE" "$ACCESS_TOKEN")
MAP1_SUCCESS=$(echo "$MAP1_RESPONSE" | jq -r '.success // false')

test_result "Map 1 Completion & Progression" "$MAP1_SUCCESS" "Map progression failed"

# Map 2 completion
MAP2_WIN_UPDATE='{
    "series_score_team1": 1,
    "series_score_team2": 1,
    "current_map": 3,
    "maps": {
        "1": {"team1Score": 100, "team2Score": 87, "status": "completed", "winner": 1},
        "2": {"team1Score": 78, "team2Score": 100, "status": "completed", "winner": 2},
        "3": {"team1Score": 0, "team2Score": 0, "status": "active", "winner": null}
    },
    "team1_score": 0,
    "team2_score": 0,
    "timestamp": '$(date +%s000)'
}'

MAP2_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches/$TEST_MATCH_ID/update-live-stats" "$MAP2_WIN_UPDATE" "$ACCESS_TOKEN")
MAP2_SUCCESS=$(echo "$MAP2_RESPONSE" | jq -r '.success // false')

test_result "Series Progression (1-1)" "$MAP2_SUCCESS" "Series progression failed"

# Match completion
MATCH_COMPLETE_UPDATE='{
    "series_score_team1": 2,
    "series_score_team2": 1,
    "current_map": 3,
    "maps": {
        "1": {"team1Score": 100, "team2Score": 87, "status": "completed", "winner": 1},
        "2": {"team1Score": 78, "team2Score": 100, "status": "completed", "winner": 2},
        "3": {"team1Score": 100, "team2Score": 92, "status": "completed", "winner": 1}
    },
    "status": "completed",
    "timestamp": '$(date +%s000)'
}'

COMPLETE_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches/$TEST_MATCH_ID/update-live-stats" "$MATCH_COMPLETE_UPDATE" "$ACCESS_TOKEN")
COMPLETE_SUCCESS=$(echo "$COMPLETE_RESPONSE" | jq -r '.success // false')

test_result "Match Completion (2-1)" "$COMPLETE_SUCCESS" "Match completion failed"

# Step 6: Test score distinction (create BO5 match for this)
log "🎯 Testing series scores vs current map scores distinction..."

BO5_MATCH_DATA='{
    "team1_id": 3,
    "team2_id": 4,
    "event_id": 1,
    "scheduled_at": "'$(date -u +%Y-%m-%dT%H:%M:%S.000Z)'",
    "format": "BO5",
    "status": "live",
    "maps": [
        {"map_name": "Tokyo 2099", "mode": "Convoy", "team1_score": 0, "team2_score": 0},
        {"map_name": "New York 2099", "mode": "Domination", "team1_score": 0, "team2_score": 0},
        {"map_name": "Shanghai 2099", "mode": "Push", "team1_score": 0, "team2_score": 0},
        {"map_name": "Midtown", "mode": "Convoy", "team1_score": 0, "team2_score": 0},
        {"map_name": "Sanctum Sanctorum", "mode": "Domination", "team1_score": 0, "team2_score": 0}
    ],
    "allow_past_date": true
}'

BO5_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches" "$BO5_MATCH_DATA" "$ACCESS_TOKEN")
BO5_MATCH_ID=$(echo "$BO5_RESPONSE" | jq -r '.data.id // empty')

if [ ! -z "$BO5_MATCH_ID" ] && [ "$BO5_MATCH_ID" != "null" ]; then
    log "   Created BO5 test match: $BO5_MATCH_ID"
    
    # Test distinction between series scores (map wins) and current map scores (rounds)
    SCORE_DISTINCTION_UPDATE='{
        "series_score_team1": 0,
        "series_score_team2": 0,
        "team1_score": 87,
        "team2_score": 45,
        "current_map": 1,
        "total_maps": 5,
        "maps": {
            "1": {"team1Score": 87, "team2Score": 45, "status": "active", "winner": null},
            "2": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null},
            "3": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null},
            "4": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null},
            "5": {"team1Score": 0, "team2Score": 0, "status": "pending", "winner": null}
        },
        "timestamp": '$(date +%s000)'
    }'
    
    DISTINCTION_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches/$BO5_MATCH_ID/update-live-stats" "$SCORE_DISTINCTION_UPDATE" "$ACCESS_TOKEN")
    DISTINCTION_SUCCESS=$(echo "$DISTINCTION_RESPONSE" | jq -r '.success // false')
    
    test_result "Score Distinction Update" "$DISTINCTION_SUCCESS" "Score distinction update failed"
    
    # Verify the distinction is maintained
    if [ "$DISTINCTION_SUCCESS" = "true" ]; then
        VERIFY_RESPONSE=$(make_request "GET" "$BACKEND_URL/api/matches/$BO5_MATCH_ID")
        SERIES_SCORE=$(echo "$VERIFY_RESPONSE" | jq -r '.data.team1_score // .team1_score // 0')
        
        # Extract current map score from maps data
        MAPS_DATA=$(echo "$VERIFY_RESPONSE" | jq -r '.data.maps_data // .maps_data // "[]"')
        if [ "$MAPS_DATA" != "[]" ] && [ "$MAPS_DATA" != "null" ]; then
            CURRENT_MAP_SCORE=$(echo "$MAPS_DATA" | jq -r '.[0].team1_score // 0')
        else
            CURRENT_MAP_SCORE=0
        fi
        
        if [ "$SERIES_SCORE" = "0" ] && [ "$CURRENT_MAP_SCORE" = "87" ]; then
            test_result "Score Distinction Verification" "true" ""
            log "   Series Score (Map Wins): Team1=$SERIES_SCORE"
            log "   Current Map Score (Rounds): Team1=$CURRENT_MAP_SCORE"
        else
            test_result "Score Distinction Verification" "false" "Series: $SERIES_SCORE, Map: $CURRENT_MAP_SCORE"
        fi
    fi
else
    test_result "BO5 Match Creation" "false" "Could not create BO5 test match"
fi

# Step 7: Test real-time synchronization with rapid updates
log "⚡ Testing real-time synchronization..."
RAPID_UPDATES=(
    '{"team1_score": 10, "team2_score": 8, "timestamp": '$(date +%s000)'}'
    '{"team1_score": 15, "team2_score": 12, "timestamp": '$(( $(date +%s) + 1 ))000'}'
    '{"team1_score": 23, "team2_score": 18, "timestamp": '$(( $(date +%s) + 2 ))000'}'
    '{"team1_score": 31, "team2_score": 25, "timestamp": '$(( $(date +%s) + 3 ))000'}'
)

ALL_RAPID_UPDATES_SUCCESS=true
for update in "${RAPID_UPDATES[@]}"; do
    RAPID_RESPONSE=$(make_request "POST" "$BACKEND_URL/api/admin/matches/$TEST_MATCH_ID/update-live-stats" "$update" "$ACCESS_TOKEN")
    RAPID_SUCCESS=$(echo "$RAPID_RESPONSE" | jq -r '.success // false')
    
    if [ "$RAPID_SUCCESS" != "true" ]; then
        ALL_RAPID_UPDATES_SUCCESS=false
        break
    fi
    sleep 0.1  # Small delay to simulate real-time updates
done

test_result "Real-time Synchronization" "$ALL_RAPID_UPDATES_SUCCESS" "One or more rapid updates failed"

# Generate final report
echo ""
echo "==============================================================="
echo "🏆 TOURNAMENT LIVE SCORING SYSTEM TEST RESULTS"
echo "==============================================================="
echo "Overall Status: $( [ $TESTS_FAILED -eq 0 ] && echo "✅ PASSED" || echo "❌ FAILED" )"
echo "Test Match ID: $TEST_MATCH_ID"
echo "Tests Passed: $TESTS_PASSED"
echo "Tests Failed: $TESTS_FAILED"
echo ""

if [ ${#ERRORS[@]} -gt 0 ]; then
    echo "❌ Errors Encountered:"
    for i in "${!ERRORS[@]}"; do
        echo "  $((i+1)). ${ERRORS[i]}"
    done
    echo ""
fi

# Recommendations based on failures
if [ $TESTS_FAILED -gt 0 ]; then
    echo "💡 Recommendations:"
    if [[ " ${ERRORS[@]} " =~ "Live Stats Update" ]]; then
        echo "  1. Fix live stats update endpoint - check validation and database persistence"
    fi
    if [[ " ${ERRORS[@]} " =~ "Map" ]]; then
        echo "  2. Fix map progression logic - ensure proper state transitions"
    fi
    if [[ " ${ERRORS[@]} " =~ "Score Distinction" ]]; then
        echo "  3. Fix score distinction handling - separate series scores from map scores"
    fi
    if [[ " ${ERRORS[@]} " =~ "Real-time" ]]; then
        echo "  4. Optimize real-time synchronization - check for race conditions"
    fi
fi

echo "==============================================================="

# Save report to file
REPORT_FILE="/var/www/mrvl-backend/tournament_live_scoring_test_report_$(date +%s).json"
cat > "$REPORT_FILE" << EOF
{
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "testMatchId": "$TEST_MATCH_ID",
    "overallStatus": "$( [ $TESTS_FAILED -eq 0 ] && echo "PASSED" || echo "FAILED" )",
    "testsPassed": $TESTS_PASSED,
    "testsFailed": $TESTS_FAILED,
    "errors": [$(printf '"%s",' "${ERRORS[@]}" | sed 's/,$//')],
    "backendUrl": "$BACKEND_URL"
}
EOF

echo "📄 Test report saved to: $REPORT_FILE"

# Exit with appropriate code
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1