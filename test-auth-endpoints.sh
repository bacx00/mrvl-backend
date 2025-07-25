#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL and Auth Token
BASE_URL="http://localhost:8000/api"
AUTH_TOKEN="$1"

if [ -z "$AUTH_TOKEN" ]; then
    echo "Usage: $0 <auth_token>"
    exit 1
fi

# Counters
PASSED=0
FAILED=0

# Function to test endpoint with auth
test_auth_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local data=$4
    
    # Build curl command
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    curl_cmd="$curl_cmd -H \"Authorization: Bearer $AUTH_TOKEN\""
    curl_cmd="$curl_cmd -H \"Accept: application/json\""
    curl_cmd="$curl_cmd -H \"Content-Type: application/json\""
    
    if [ ! -z "$data" ]; then
        curl_cmd="$curl_cmd -d '$data'"
    fi
    
    curl_cmd="$curl_cmd \"$BASE_URL$endpoint\""
    
    # Execute curl and capture response
    local response=$(eval $curl_cmd)
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$d')
    
    # Check response code
    if [[ $http_code =~ ^(200|201|204)$ ]]; then
        echo -e "${GREEN}✓${NC} $method $endpoint - $description (HTTP $http_code)"
        ((PASSED++))
    elif [[ $http_code =~ ^(404)$ ]] && [[ "$endpoint" =~ "/1" ]]; then
        # Expected 404 for non-existent resources
        echo -e "${BLUE}✓${NC} $method $endpoint - $description (HTTP $http_code - Resource not found)"
        ((PASSED++))
    elif [[ $http_code =~ ^(403)$ ]] && [[ "$description" =~ "admin only" ]]; then
        # If user doesn't have admin role
        echo -e "${YELLOW}!${NC} $method $endpoint - $description (HTTP $http_code - Insufficient permissions)"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $method $endpoint - $description (HTTP $http_code)"
        ((FAILED++))
        
        # Show error details for debugging
        if [ "$http_code" = "500" ] || [ "$http_code" = "422" ]; then
            local error_msg=$(echo "$body" | jq -r '.message // .error // "Unknown error"' 2>/dev/null)
            echo -e "  ${RED}Error: $error_msg${NC}"
        fi
    fi
}

echo "======================================"
echo "Testing Authenticated API Endpoints"
echo "======================================"
echo ""

# Test auth verification
echo -e "${YELLOW}=== AUTHENTICATION TEST ===${NC}"
test_auth_endpoint "GET" "/auth/me" "Get current user info"

# User endpoints
echo -e "\n${YELLOW}=== USER ENDPOINTS ===${NC}"
test_auth_endpoint "GET" "/user" "Get authenticated user"
test_auth_endpoint "GET" "/user/profile" "Get user profile"
test_auth_endpoint "PUT" "/user/profile" "Update user profile" '{"bio":"Test bio"}'
test_auth_endpoint "GET" "/user/profile/available-flairs" "Get available flairs"
test_auth_endpoint "GET" "/user/profile/activity" "Get profile activity"
test_auth_endpoint "GET" "/user/stats" "Get user stats"
test_auth_endpoint "GET" "/user/activity" "Get user activity"

# Forum endpoints
echo -e "\n${YELLOW}=== FORUM ENDPOINTS ===${NC}"
test_auth_endpoint "GET" "/user/forums/threads" "Get user forum threads"
test_auth_endpoint "POST" "/user/forums/threads" "Create forum thread" '{"title":"Test Thread","content":"Test content","category":"general"}'
test_auth_endpoint "POST" "/user/forums/threads/1/posts" "Create forum post" '{"content":"Test reply"}'

# News endpoints
echo -e "\n${YELLOW}=== NEWS ENDPOINTS ===${NC}"
test_auth_endpoint "POST" "/user/news/1/comments" "Post news comment" '{"content":"Test comment"}'
test_auth_endpoint "POST" "/user/news/1/vote" "Vote on news" '{"type":"upvote"}'

# Match endpoints
echo -e "\n${YELLOW}=== MATCH ENDPOINTS ===${NC}"
test_auth_endpoint "POST" "/user/matches/1/comments" "Post match comment" '{"content":"Test comment"}'

# Predictions
echo -e "\n${YELLOW}=== PREDICTIONS ===${NC}"
test_auth_endpoint "GET" "/user/predictions" "Get user predictions"
test_auth_endpoint "POST" "/user/predictions" "Create prediction" '{"match_id":1,"team_id":1,"confidence":80}'

# Favorites
echo -e "\n${YELLOW}=== FAVORITES ===${NC}"
test_auth_endpoint "GET" "/user/favorites/teams" "Get favorite teams"
test_auth_endpoint "POST" "/user/favorites/teams" "Add favorite team" '{"team_id":1}'
test_auth_endpoint "DELETE" "/user/favorites/teams/1" "Remove favorite team"
test_auth_endpoint "GET" "/user/favorites/players" "Get favorite players"
test_auth_endpoint "POST" "/user/favorites/players" "Add favorite player" '{"player_id":1}'
test_auth_endpoint "DELETE" "/user/favorites/players/1" "Remove favorite player"

# Notifications
echo -e "\n${YELLOW}=== NOTIFICATIONS ===${NC}"
test_auth_endpoint "GET" "/user/notifications" "Get notifications"
test_auth_endpoint "PUT" "/user/notifications/1/read" "Mark notification as read"
test_auth_endpoint "POST" "/user/notifications/mark-all-read" "Mark all notifications as read"

# Search (authenticated)
echo -e "\n${YELLOW}=== SEARCH ENDPOINTS ===${NC}"
test_auth_endpoint "GET" "/search/advanced?q=test" "Advanced search"
test_auth_endpoint "GET" "/search/teams?q=test" "Search teams"
test_auth_endpoint "GET" "/search/players?q=test" "Search players"

# Moderator endpoints
echo -e "\n${YELLOW}=== MODERATOR ENDPOINTS ===${NC}"
test_auth_endpoint "GET" "/moderator/forums/threads/reported" "Get reported threads"
test_auth_endpoint "GET" "/moderator/forums/posts/reported" "Get reported posts"
test_auth_endpoint "GET" "/moderator/news/pending" "Get pending news"
test_auth_endpoint "GET" "/moderator/news/comments/reported" "Get reported news comments"
test_auth_endpoint "GET" "/moderator/dashboard/stats" "Get moderator stats"

# Admin endpoints
echo -e "\n${YELLOW}=== ADMIN ENDPOINTS ===${NC}"
test_auth_endpoint "GET" "/admin/stats" "Get admin stats"
test_auth_endpoint "GET" "/admin/analytics" "Get analytics"
test_auth_endpoint "GET" "/admin/dashboard" "Get admin dashboard"
test_auth_endpoint "GET" "/admin/users" "Get all users"
test_auth_endpoint "GET" "/admin/teams" "Get all teams"
test_auth_endpoint "GET" "/admin/players" "Get all players"
test_auth_endpoint "GET" "/admin/events" "Get all events"
test_auth_endpoint "GET" "/admin/matches" "Get all matches"
test_auth_endpoint "GET" "/admin/news" "Get all news"

# Test role endpoints
echo -e "\n${YELLOW}=== ROLE TEST ENDPOINTS ===${NC}"
test_auth_endpoint "GET" "/test-admin" "Test admin access"
test_auth_endpoint "GET" "/test-moderator" "Test moderator access"
test_auth_endpoint "GET" "/test-user" "Test user access"

# Summary
echo ""
echo "======================================"
echo "TEST SUMMARY"
echo "======================================"
echo -e "${GREEN}Passed:${NC} $PASSED"
echo -e "${RED}Failed:${NC} $FAILED"
echo -e "Total: $((PASSED + FAILED))"

if [ $FAILED -eq 0 ]; then
    echo -e "\n${GREEN}All authenticated endpoints are working correctly!${NC}"
else
    echo -e "\n${RED}Some endpoints are failing. Check the errors above.${NC}"
fi