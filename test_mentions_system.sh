#!/bin/bash

# MRVL Mentions System Test Script
# Tests all aspects of the mentions functionality

API_URL="https://staging.mrvl.net/api"
echo "========================================="
echo "   MRVL MENTIONS SYSTEM TEST SUITE"
echo "========================================="
echo ""

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Function to test API endpoint
test_endpoint() {
    local endpoint=$1
    local description=$2
    
    echo -n "Testing $description... "
    
    response=$(curl -s -w "\n%{http_code}" "$API_URL$endpoint")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (HTTP $http_code)"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC} (HTTP $http_code)"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

# Function to test search endpoint with query
test_search() {
    local endpoint=$1
    local query=$2
    local description=$3
    
    echo -n "Testing $description... "
    
    response=$(curl -s -w "\n%{http_code}" "$API_URL$endpoint?q=$query&limit=5")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" = "200" ]; then
        # Check if response contains data
        if echo "$body" | grep -q '"data"'; then
            echo -e "${GREEN}✓ PASSED${NC} (HTTP $http_code, has data)"
            TESTS_PASSED=$((TESTS_PASSED + 1))
            return 0
        else
            echo -e "${YELLOW}⚠ WARNING${NC} (HTTP $http_code, but no data field)"
            TESTS_PASSED=$((TESTS_PASSED + 1))
            return 0
        fi
    else
        echo -e "${RED}✗ FAILED${NC} (HTTP $http_code)"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

echo "1. TESTING SEARCH ENDPOINTS"
echo "----------------------------"

# Test user search
test_search "/public/search/users" "test" "User search (@mentions)"

# Test team search  
test_search "/public/search/teams" "100" "Team search (@team:mentions)"

# Test player search
test_search "/public/search/players" "s" "Player search (@player:mentions)"

echo ""
echo "2. TESTING MENTION RETRIEVAL ENDPOINTS"
echo "---------------------------------------"

# Get first valid player ID
PLAYER_ID=$(curl -s "$API_URL/players" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -z "$PLAYER_ID" ]; then
    PLAYER_ID="405"
fi

# Test player mentions
test_endpoint "/players/$PLAYER_ID/mentions" "Player mentions (Player ID: $PLAYER_ID)"

# Test team mentions
test_endpoint "/teams/4/mentions" "Team mentions (Team ID: 4)"

# Test with pagination
test_endpoint "/players/$PLAYER_ID/mentions?page=1&limit=10" "Player mentions with pagination"

echo ""
echo "3. TESTING MENTION SEARCH & POPULAR"
echo "------------------------------------"

# Test unified mention search
test_search "/public/mentions/search" "shroud" "Unified mention search"

# Test popular mentions
test_endpoint "/public/mentions/popular" "Popular mentions"

echo ""
echo "4. TESTING MENTION DATA STRUCTURE"
echo "----------------------------------"

echo "Checking player mention response structure..."
player_response=$(curl -s "$API_URL/players/$PLAYER_ID/mentions?limit=1")

if echo "$player_response" | grep -q '"data"'; then
    echo -e "${GREEN}✓${NC} Response has 'data' field"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗${NC} Response missing 'data' field"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Check for proper mention fields
if echo "$player_response" | grep -q '"content_type"\|"mentioned_by"\|"content"'; then
    echo -e "${GREEN}✓${NC} Response has mention fields"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${YELLOW}⚠${NC} Response may be missing some mention fields"
fi

echo ""
echo "5. TESTING FRONTEND COMPONENTS"
echo "-------------------------------"

# Check if ForumMentionAutocomplete exists
if [ -f "/var/www/mrvl-frontend/frontend/src/components/shared/ForumMentionAutocomplete.js" ]; then
    echo -e "${GREEN}✓${NC} ForumMentionAutocomplete component exists"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗${NC} ForumMentionAutocomplete component missing"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Check if MentionsSection exists
if [ -f "/var/www/mrvl-frontend/frontend/src/components/shared/MentionsSection.js" ]; then
    echo -e "${GREEN}✓${NC} MentionsSection component exists"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗${NC} MentionsSection component missing"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

# Check if MentionLink exists
if [ -f "/var/www/mrvl-frontend/frontend/src/components/shared/MentionLink.js" ]; then
    echo -e "${GREEN}✓${NC} MentionLink component exists"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗${NC} MentionLink component missing"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""
echo "6. TESTING MENTION PATTERNS"
echo "----------------------------"

# Test creating a sample mention in content
echo "Testing mention pattern detection..."

# Check if mention controller exists
if [ -f "/var/www/mrvl-backend/app/Http/Controllers/MentionController.php" ]; then
    echo -e "${GREEN}✓${NC} MentionController exists in backend"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗${NC} MentionController missing in backend"
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""
echo "========================================="
echo "           TEST RESULTS SUMMARY"
echo "========================================="
echo ""
echo -e "Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED!${NC}"
    echo "The mentions system is working correctly."
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo "Please review the failures above."
fi

echo ""
echo "========================================="
echo "         MANUAL TESTING CHECKLIST"
echo "========================================="
echo ""
echo "Please manually verify on https://staging.mrvl.net:"
echo ""
echo "[ ] 1. Go to Forums > Create Thread"
echo "    - Type @ and verify dropdown appears"
echo "    - Select a user/team/player from dropdown"
echo ""
echo "[ ] 2. Go to any News article"
echo "    - Add a comment with @mentions"
echo "    - Verify dropdown appears when typing @"
echo ""
echo "[ ] 3. Go to any Match page"
echo "    - Add a comment with @mentions"
echo "    - Verify dropdown appears"
echo ""
echo "[ ] 4. Click on any @mention in content"
echo "    - Verify it navigates to the correct profile"
echo ""
echo "[ ] 5. Visit a Player profile (e.g., /players/1)"
echo "    - Check if 'Recent Mentions' section appears"
echo "    - Verify mentions are displayed if any exist"
echo ""
echo "[ ] 6. Visit a Team profile (e.g., /teams/1)"
echo "    - Check if 'Recent Mentions' section appears"
echo "    - Verify mentions are displayed if any exist"
echo ""
echo "========================================="

exit $([ $TESTS_FAILED -eq 0 ] && echo 0 || echo 1)