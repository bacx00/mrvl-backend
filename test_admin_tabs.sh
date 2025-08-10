#!/bin/bash

# Test all admin tab endpoints
# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "Testing All Admin Tab Endpoints"
echo "========================================="

# Get auth token first
echo -e "${YELLOW}Getting authentication token...${NC}"
TOKEN=$(curl -s -X POST https://staging.mrvl.net/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"jhonny@ar-mediia.com","password":"password123"}' | \
  python3 -c "import sys, json; print(json.load(sys.stdin).get('token', ''))")

if [ -z "$TOKEN" ]; then
    echo -e "${RED}Failed to get authentication token${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Authentication successful${NC}\n"

# Function to test endpoint
test_endpoint() {
    local name=$1
    local endpoint=$2
    local method=${3:-GET}
    
    echo -e "${YELLOW}Testing: $name${NC}"
    echo "Endpoint: $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -X GET "$endpoint" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Accept: application/json")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$endpoint" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Accept: application/json" \
            -H "Content-Type: application/json")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        echo -e "${GREEN}✓ Status: $http_code${NC}"
        
        # Check if response is JSON
        if echo "$body" | python3 -m json.tool > /dev/null 2>&1; then
            # Parse and show key data
            echo "$body" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    if 'data' in data:
        if isinstance(data['data'], list):
            print(f'  Records: {len(data[\"data\"])}')
        elif isinstance(data['data'], dict):
            keys = list(data['data'].keys())[:5]
            print(f'  Keys: {keys}')
    elif 'success' in data:
        print(f'  Success: {data[\"success\"]}')
        if 'message' in data:
            print(f'  Message: {data[\"message\"]}')
except:
    pass
"
        else
            echo -e "${RED}  ✗ Invalid JSON response${NC}"
        fi
    else
        echo -e "${RED}✗ Status: $http_code${NC}"
        if [ ! -z "$body" ]; then
            echo "$body" | head -3
        fi
    fi
    echo ""
}

echo "========================================="
echo "1. ADMIN DASHBOARD (Overview)"
echo "========================================="
test_endpoint "Admin Stats" "https://staging.mrvl.net/api/admin/stats"
test_endpoint "Admin Analytics" "https://staging.mrvl.net/api/admin/analytics"
test_endpoint "Performance Metrics" "https://staging.mrvl.net/api/admin/performance-metrics"

echo "========================================="
echo "2. TEAMS MANAGEMENT"
echo "========================================="
test_endpoint "List Teams" "https://staging.mrvl.net/api/admin/teams"
test_endpoint "Teams with Pagination" "https://staging.mrvl.net/api/admin/teams?page=1&limit=10"

echo "========================================="
echo "3. PLAYERS MANAGEMENT"
echo "========================================="
test_endpoint "List Players" "https://staging.mrvl.net/api/admin/players"
test_endpoint "Players with Pagination" "https://staging.mrvl.net/api/admin/players?page=1&limit=10"

echo "========================================="
echo "4. MATCHES MODERATION"
echo "========================================="
test_endpoint "List Matches (Standard)" "https://staging.mrvl.net/api/admin/matches"
test_endpoint "List Matches (Moderation)" "https://staging.mrvl.net/api/admin/matches-moderation"
test_endpoint "Matches with Double API Path" "https://staging.mrvl.net/api/api/admin/matches-moderation?page=1&limit=10"

echo "========================================="
echo "5. EVENTS MANAGEMENT"
echo "========================================="
test_endpoint "List Events (Standard)" "https://staging.mrvl.net/api/admin/events"
test_endpoint "Events with Double API Path" "https://staging.mrvl.net/api/api/admin/events?page=1&limit=10"

echo "========================================="
echo "6. USERS MANAGEMENT"
echo "========================================="
test_endpoint "List Users (Standard)" "https://staging.mrvl.net/api/admin/users"
test_endpoint "Users with Double API Path" "https://staging.mrvl.net/api/api/admin/users?page=1&limit=10"

echo "========================================="
echo "7. NEWS MODERATION"
echo "========================================="
test_endpoint "List News (Standard)" "https://staging.mrvl.net/api/admin/news"
test_endpoint "News Moderation" "https://staging.mrvl.net/api/admin/news-moderation"
test_endpoint "News with Double API Path" "https://staging.mrvl.net/api/api/admin/news-moderation?page=1&limit=10"
test_endpoint "News Categories" "https://staging.mrvl.net/api/api/admin/news-moderation/categories"

echo "========================================="
echo "8. FORUMS MODERATION"
echo "========================================="
test_endpoint "Forum Threads" "https://staging.mrvl.net/api/admin/forums-moderation/threads"
test_endpoint "Forum with Double API Path" "https://staging.mrvl.net/api/api/admin/forums-moderation/threads"
test_endpoint "Forum Dashboard" "https://staging.mrvl.net/api/api/admin/forums-moderation/dashboard"
test_endpoint "Forum Statistics" "https://staging.mrvl.net/api/api/admin/forums-moderation/statistics"

echo "========================================="
echo "SUMMARY"
echo "========================================="
echo -e "${GREEN}Testing complete!${NC}"
echo "Check above for any ${RED}red errors${NC} that need fixing."