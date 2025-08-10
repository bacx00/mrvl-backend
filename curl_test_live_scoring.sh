#!/bin/bash

# Live Scoring Endpoints Test Script
# Tests the four critical endpoints for live scoring functionality

BASE_URL="http://localhost:8000/api"
REPORT_FILE="live_scoring_curl_test_report_$(date +%s).json"

echo "🚀 Starting Live Scoring Endpoints Test Suite (CURL)"
echo "Testing endpoints for Marvel Rivals Live Scoring System"
echo ""

# Initialize JSON report
cat > $REPORT_FILE << 'EOF'
{
  "timestamp": "",
  "tests": {},
  "summary": {}
}
EOF

# Update timestamp in report
sed -i "s/\"timestamp\": \"\"/\"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%S.%3NZ)\"/" $REPORT_FILE

# Test results
declare -A test_results

# Function to update JSON report
update_report() {
    local test_name="$1"
    local status="$2"  
    local response_code="$3"
    local details="$4"
    
    # Escape quotes in details for JSON
    details=$(echo "$details" | sed 's/"/\\"/g')
    
    # Update the JSON file using jq if available, otherwise use sed
    if command -v jq &> /dev/null; then
        tmp=$(mktemp)
        jq ".tests[\"$test_name\"] = {\"status\": \"$status\", \"response_code\": $response_code, \"details\": \"$details\"}" $REPORT_FILE > $tmp && mv $tmp $REPORT_FILE
    else
        # Fallback to sed manipulation
        echo "Warning: jq not available, using basic reporting"
    fi
}

# Test 1: SSE Connection
echo "📡 Testing SSE Connection: GET /api/live-updates/2/stream"
sse_response=$(timeout 5 curl -s -w "%{http_code}" -o /dev/null "$BASE_URL/live-updates/2/stream" 2>/dev/null || echo "000")

if [ "$sse_response" = "200" ]; then
    echo "✅ SSE Connection: SUCCESS - Response Code: $sse_response"
    update_report "sse_connection" "success" $sse_response "SSE endpoint responds correctly"
elif [ "$sse_response" = "404" ]; then
    echo "❌ SSE Connection: NOT FOUND - Response Code: $sse_response"
    update_report "sse_connection" "not_found" $sse_response "Endpoint not found"
elif [ "$sse_response" = "000" ]; then
    echo "❌ SSE Connection: TIMEOUT/CONNECTION ERROR"
    update_report "sse_connection" "failed" 0 "Connection timeout or error"
else
    echo "⚠️ SSE Connection: UNEXPECTED RESPONSE - Response Code: $sse_response"
    update_report "sse_connection" "partial" $sse_response "Unexpected response code"
fi

# Test 2: Create Match (without auth first, then check if we need auth)
echo ""
echo "🆕 Testing Match Creation: POST /api/admin/matches"

# Test data
match_data='{
    "team1_id": 1,
    "team2_id": 2,
    "event_id": 1,
    "match_type": "tournament",
    "scheduled_at": "2025-08-10T18:00:00Z",
    "best_of": 3,
    "status": "upcoming"
}'

create_response=$(curl -s -w "%{http_code}" -o /tmp/create_response.json -X POST \
    -H "Content-Type: application/json" \
    -d "$match_data" \
    "$BASE_URL/admin/matches" 2>/dev/null)

if [ "$create_response" = "201" ]; then
    echo "✅ Match Creation: SUCCESS - Response Code: $create_response"
    update_report "create_match" "success" $create_response "Match created successfully"
elif [ "$create_response" = "401" ]; then
    echo "🔐 Match Creation: AUTH REQUIRED - Response Code: $create_response"
    update_report "create_match" "auth_required" $create_response "Authentication required"
elif [ "$create_response" = "422" ]; then
    echo "❌ Match Creation: VALIDATION ERROR - Response Code: $create_response"
    update_report "create_match" "validation_error" $create_response "Validation error"
elif [ "$create_response" = "404" ]; then
    echo "❌ Match Creation: NOT FOUND - Response Code: $create_response"
    update_report "create_match" "not_found" $create_response "Endpoint not found"
else
    echo "⚠️ Match Creation: UNEXPECTED RESPONSE - Response Code: $create_response"
    update_report "create_match" "partial" $create_response "Unexpected response code"
fi

# Test 3: Live Control 
echo ""
echo "🎮 Testing Live Control: PUT /api/admin/matches/2/live-control"

control_data='{
    "action": "start",
    "map_id": 1,
    "additional_data": {
        "round": 1,
        "timestamp": "2025-08-10T18:00:00Z"
    }
}'

control_response=$(curl -s -w "%{http_code}" -o /tmp/control_response.json -X PUT \
    -H "Content-Type: application/json" \
    -d "$control_data" \
    "$BASE_URL/admin/matches/2/live-control" 2>/dev/null)

if [ "$control_response" = "200" ]; then
    echo "✅ Live Control: SUCCESS - Response Code: $control_response"
    update_report "live_control" "success" $control_response "Live control executed successfully"
elif [ "$control_response" = "401" ]; then
    echo "🔐 Live Control: AUTH REQUIRED - Response Code: $control_response"
    update_report "live_control" "auth_required" $control_response "Authentication required"
elif [ "$control_response" = "404" ]; then
    echo "❌ Live Control: NOT FOUND - Response Code: $control_response"
    update_report "live_control" "not_found" $control_response "Match not found or endpoint not found"
elif [ "$control_response" = "422" ]; then
    echo "❌ Live Control: VALIDATION ERROR - Response Code: $control_response"
    update_report "live_control" "validation_error" $control_response "Validation error"
else
    echo "⚠️ Live Control: UNEXPECTED RESPONSE - Response Code: $control_response"
    update_report "live_control" "partial" $control_response "Unexpected response code"
fi

# Test 4: Update Stats
echo ""
echo "📊 Testing Stats Update: POST /api/admin/matches/2/update-live-stats"

stats_data='{
    "player_stats": [
        {
            "player_id": 1,
            "kills": 15,
            "deaths": 8,
            "damage_dealt": 2500,
            "healing_done": 1200,
            "hero_id": 1
        },
        {
            "player_id": 2,
            "kills": 12,
            "deaths": 10,
            "damage_dealt": 2100,
            "healing_done": 800,
            "hero_id": 2
        }
    ],
    "map_stats": {
        "map_id": 1,
        "duration": 450,
        "winner": "team1"
    },
    "match_stats": {
        "current_map": 1,
        "team1_score": 1,
        "team2_score": 0
    }
}'

stats_response=$(curl -s -w "%{http_code}" -o /tmp/stats_response.json -X POST \
    -H "Content-Type: application/json" \
    -d "$stats_data" \
    "$BASE_URL/admin/matches/2/update-live-stats" 2>/dev/null)

if [ "$stats_response" = "200" ]; then
    echo "✅ Stats Update: SUCCESS - Response Code: $stats_response"
    update_report "update_stats" "success" $stats_response "Stats updated successfully"
elif [ "$stats_response" = "401" ]; then
    echo "🔐 Stats Update: AUTH REQUIRED - Response Code: $stats_response"  
    update_report "update_stats" "auth_required" $stats_response "Authentication required"
elif [ "$stats_response" = "404" ]; then
    echo "❌ Stats Update: NOT FOUND - Response Code: $stats_response"
    update_report "update_stats" "not_found" $stats_response "Match not found or endpoint not found"
elif [ "$stats_response" = "422" ]; then
    echo "❌ Stats Update: VALIDATION ERROR - Response Code: $stats_response"
    update_report "update_stats" "validation_error" $stats_response "Validation error"
else
    echo "⚠️ Stats Update: UNEXPECTED RESPONSE - Response Code: $stats_response"
    update_report "update_stats" "partial" $stats_response "Unexpected response code"
fi

# Generate Summary Report
echo ""
echo "============================================================"
echo "📋 LIVE SCORING ENDPOINTS TEST REPORT (CURL)"
echo "============================================================"

# Count results 
success_count=0
partial_count=0
auth_count=0
failed_count=0

# Display results and count
for endpoint in "SSE Connection (/api/live-updates/2/stream)" "Create Match (POST /api/admin/matches)" "Live Control (PUT /api/admin/matches/2/live-control)" "Stats Update (POST /api/admin/matches/2/update-live-stats)"; do
    case $endpoint in
        "SSE Connection"*)
            code=$sse_response
            ;;
        "Create Match"*)
            code=$create_response
            ;;
        "Live Control"*)
            code=$control_response
            ;;
        "Stats Update"*)
            code=$stats_response
            ;;
    esac
    
    if [ "$code" = "200" ] || [ "$code" = "201" ]; then
        echo "✅ $endpoint"
        ((success_count++))
    elif [ "$code" = "401" ]; then
        echo "🔐 $endpoint (AUTH REQUIRED - Response: $code)"
        ((auth_count++))
    elif [ "$code" = "404" ]; then
        echo "❌ $endpoint (NOT FOUND - Response: $code)"
        ((failed_count++))
    elif [ "$code" = "422" ]; then
        echo "❌ $endpoint (VALIDATION ERROR - Response: $code)"
        ((failed_count++))
    elif [ "$code" = "000" ]; then
        echo "❌ $endpoint (CONNECTION ERROR)"
        ((failed_count++))
    else
        echo "⚠️ $endpoint (UNEXPECTED RESPONSE: $code)"
        ((partial_count++))
    fi
done

echo ""
echo "📊 SUMMARY:"
echo "   Total Tests: 4"
echo "   Successful: $success_count"
echo "   Auth Required: $auth_count"
echo "   Failed: $failed_count"
echo "   Partial: $partial_count"

# Update summary in report
if command -v jq &> /dev/null; then
    tmp=$(mktemp)
    jq ".summary = {\"total_tests\": 4, \"successful\": $success_count, \"auth_required\": $auth_count, \"failed\": $failed_count, \"partial\": $partial_count}" $REPORT_FILE > $tmp && mv $tmp $REPORT_FILE
fi

echo ""
echo "💾 Detailed report saved to: $REPORT_FILE"

# Clean up temporary files
rm -f /tmp/create_response.json /tmp/control_response.json /tmp/stats_response.json

echo ""
echo "✨ Test suite completed!"