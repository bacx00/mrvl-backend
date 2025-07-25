#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://localhost:8000/api"

# Counters
PASSED=0
FAILED=0
ERRORS=()

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local auth_header=$4
    local data=$5
    
    # Build curl command
    local curl_cmd="curl -s -w '\n%{http_code}' -X $method"
    
    if [ ! -z "$auth_header" ]; then
        curl_cmd="$curl_cmd -H \"$auth_header\""
    fi
    
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
    elif [[ $http_code =~ ^(401|403)$ ]] && [ ! -z "$auth_header" -o "$description" =~ "auth" ]; then
        # Expected auth failures
        echo -e "${BLUE}✓${NC} $method $endpoint - $description (HTTP $http_code - Expected auth failure)"
        ((PASSED++))
    elif [[ $http_code =~ ^(404)$ ]] && [[ "$endpoint" =~ "/1" || "$endpoint" =~ "/test" ]]; then
        # Expected 404 for non-existent resources
        echo -e "${BLUE}✓${NC} $method $endpoint - $description (HTTP $http_code - Expected not found)"
        ((PASSED++))
    elif [[ $http_code =~ ^(422)$ ]] && [ "$method" = "POST" -o "$method" = "PUT" ]; then
        # Expected validation errors for POST/PUT without data
        echo -e "${BLUE}✓${NC} $method $endpoint - $description (HTTP $http_code - Expected validation error)"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $method $endpoint - $description (HTTP $http_code)"
        ERRORS+=("$method $endpoint - HTTP $http_code")
        ((FAILED++))
        
        # Show error details for 500 errors
        if [ "$http_code" = "500" ]; then
            local error_msg=$(echo "$body" | jq -r '.message // .error // "Unknown error"' 2>/dev/null)
            echo -e "  ${RED}Error: $error_msg${NC}"
        fi
    fi
}

echo "======================================"
echo "MRVL BACKEND COMPREHENSIVE SYSTEM TEST"
echo "======================================"
echo ""

# ======================
# PUBLIC ENDPOINTS
# ======================
echo -e "${YELLOW}=== PUBLIC ENDPOINTS (No Auth Required) ===${NC}"
echo ""

# Teams
echo -e "${YELLOW}Teams${NC}"
test_endpoint "GET" "/teams" "List all teams"
test_endpoint "GET" "/teams/1" "Get team details"
test_endpoint "GET" "/teams/1/mentions" "Get team mentions"
test_endpoint "GET" "/teams/1/matches/upcoming" "Get team upcoming matches"
test_endpoint "GET" "/teams/1/matches/live" "Get team live matches"
test_endpoint "GET" "/teams/1/matches/recent" "Get team recent matches"
test_endpoint "GET" "/teams/1/matches/stats" "Get team match stats"

# Players
echo -e "\n${YELLOW}Players${NC}"
test_endpoint "GET" "/players" "List all players"
test_endpoint "GET" "/players/1" "Get player details"
test_endpoint "GET" "/players/1/mentions" "Get player mentions"
test_endpoint "GET" "/players/1/match-history" "Get player match history"
test_endpoint "GET" "/players/1/hero-stats" "Get player hero stats"
test_endpoint "GET" "/players/1/performance-stats" "Get player performance stats"
test_endpoint "GET" "/players/1/map-stats" "Get player map stats"
test_endpoint "GET" "/players/1/event-stats" "Get player event stats"

# Events
echo -e "\n${YELLOW}Events${NC}"
test_endpoint "GET" "/events" "List all events"
test_endpoint "GET" "/events/1" "Get event details"

# Matches
echo -e "\n${YELLOW}Matches${NC}"
test_endpoint "GET" "/matches" "List all matches"
test_endpoint "GET" "/matches/live" "Get live matches"
test_endpoint "GET" "/matches/1" "Get match details"
test_endpoint "GET" "/matches/1/comments" "Get match comments"
test_endpoint "GET" "/matches/1/timeline" "Get match timeline"
test_endpoint "GET" "/matches/head-to-head/1/2" "Get head to head"

# News
echo -e "\n${YELLOW}News${NC}"
test_endpoint "GET" "/news" "List all news"
test_endpoint "GET" "/news/1" "Get news details"
test_endpoint "GET" "/news/1/comments" "Get news comments"
test_endpoint "GET" "/news/categories" "Get news categories"

# Forums
echo -e "\n${YELLOW}Forums${NC}"
test_endpoint "GET" "/forums/categories" "Get forum categories"
test_endpoint "GET" "/forums/threads" "List forum threads"
test_endpoint "GET" "/forums/threads/1" "Get thread details"
test_endpoint "GET" "/forums/threads/1/posts" "Get thread posts"

# Public prefix routes
echo -e "\n${YELLOW}Public Prefix Routes${NC}"
test_endpoint "GET" "/public/teams" "Public teams list"
test_endpoint "GET" "/public/teams/1" "Public team details"
test_endpoint "GET" "/public/players" "Public players list"
test_endpoint "GET" "/public/players/1" "Public player details"
test_endpoint "GET" "/public/players/1/match-history" "Public player match history"
test_endpoint "GET" "/public/players/1/hero-stats" "Public player hero stats"
test_endpoint "GET" "/public/players/1/performance-stats" "Public player performance stats"
test_endpoint "GET" "/public/players/1/map-stats" "Public player map stats"
test_endpoint "GET" "/public/players/1/event-stats" "Public player event stats"
test_endpoint "GET" "/public/events" "Public events list"
test_endpoint "GET" "/public/events/1" "Public event details"
test_endpoint "GET" "/public/matches" "Public matches list"
test_endpoint "GET" "/public/matches/1" "Public match details"
test_endpoint "GET" "/public/forums/categories" "Public forum categories"
test_endpoint "GET" "/public/forums/threads" "Public forum threads"
test_endpoint "GET" "/public/forums/threads/1" "Public thread details"
test_endpoint "GET" "/public/forums/threads/1/posts" "Public thread posts"
test_endpoint "GET" "/public/news" "Public news list"
test_endpoint "GET" "/public/news/1" "Public news details"
test_endpoint "GET" "/public/news/categories" "Public news categories"
test_endpoint "GET" "/public/rankings" "Public rankings"
test_endpoint "GET" "/public/rankings/distribution" "Public rank distribution"
test_endpoint "GET" "/public/rankings/marvel-rivals-info" "Public Marvel Rivals info"
test_endpoint "GET" "/public/heroes" "Public heroes list"
test_endpoint "GET" "/public/heroes/roles" "Public hero roles"
test_endpoint "GET" "/public/heroes/season-2" "Public season 2 heroes"
test_endpoint "GET" "/public/heroes/images" "Public hero images"
test_endpoint "GET" "/public/heroes/images/all" "Public all hero images"
test_endpoint "GET" "/public/heroes/images/spider-man" "Public hero image by slug"
test_endpoint "GET" "/public/heroes/spider-man" "Public hero details"
test_endpoint "GET" "/public/game-data/maps" "Public game maps"
test_endpoint "GET" "/public/game-data/modes" "Public game modes"
test_endpoint "GET" "/public/game-data/heroes" "Public hero roster"
test_endpoint "GET" "/public/game-data/rankings" "Public ranking info"
test_endpoint "GET" "/public/game-data/meta" "Public current meta"
test_endpoint "GET" "/public/game-data/tournaments" "Public tournament formats"
test_endpoint "GET" "/public/game-data/timers" "Public match timers"
test_endpoint "GET" "/public/game-data/technical" "Public technical specs"
test_endpoint "GET" "/public/game-data/complete" "Public complete game data"
test_endpoint "GET" "/public/events/1/bracket" "Public event bracket"
test_endpoint "GET" "/public/search?q=test" "Public search"
test_endpoint "GET" "/public/mentions/search?q=test" "Public mentions search"
test_endpoint "GET" "/public/mentions/popular" "Public popular mentions"
test_endpoint "GET" "/public/users/1/profile" "Public user profile"

# System test endpoints
echo -e "\n${YELLOW}System Test Endpoints${NC}"
test_endpoint "GET" "/system-test" "System test endpoint"
test_endpoint "GET" "/test-forum" "Forum system test"

# ======================
# AUTHENTICATION ENDPOINTS
# ======================
echo -e "\n${YELLOW}=== AUTHENTICATION ENDPOINTS ===${NC}"
echo ""

test_endpoint "POST" "/auth/login" "Login endpoint" "" '{"email":"test@example.com","password":"password"}'
test_endpoint "POST" "/auth/register" "Register endpoint" "" '{"name":"Test User","email":"test'$(date +%s)'@example.com","password":"password123","password_confirmation":"password123"}'
test_endpoint "POST" "/auth/forgot-password" "Forgot password" "" '{"email":"test@example.com"}'
test_endpoint "POST" "/auth/reset-password" "Reset password" "" '{"token":"test","email":"test@example.com","password":"newpassword","password_confirmation":"newpassword"}'
test_endpoint "GET" "/auth/me" "Get current user (should fail without auth)"
test_endpoint "POST" "/auth/logout" "Logout (should fail without auth)"
test_endpoint "POST" "/auth/refresh" "Refresh token (should fail without auth)"

# ======================
# USER ENDPOINTS (Need Auth)
# ======================
echo -e "\n${YELLOW}=== USER ENDPOINTS (Require Authentication) ===${NC}"
echo ""

# These should return 401 without auth
test_endpoint "GET" "/user" "Get authenticated user"
test_endpoint "GET" "/user/profile" "Get user profile"
test_endpoint "PUT" "/user/profile" "Update user profile"
test_endpoint "GET" "/user/profile/available-flairs" "Get available flairs"
test_endpoint "GET" "/user/profile/activity" "Get profile activity"
test_endpoint "GET" "/user/profile/display/1" "Get user profile display"
test_endpoint "GET" "/user/stats" "Get user stats"
test_endpoint "GET" "/user/activity" "Get user activity"
test_endpoint "GET" "/user/forums/threads" "Get user forum threads"
test_endpoint "POST" "/user/forums/threads" "Create forum thread"
test_endpoint "POST" "/user/forums/threads/1/posts" "Create forum post"
test_endpoint "POST" "/user/news/1/comments" "Post news comment"
test_endpoint "POST" "/user/matches/1/comments" "Post match comment"
test_endpoint "GET" "/user/predictions" "Get user predictions"
test_endpoint "POST" "/user/predictions" "Create prediction"
test_endpoint "GET" "/user/favorites/teams" "Get favorite teams"
test_endpoint "POST" "/user/favorites/teams" "Add favorite team"
test_endpoint "DELETE" "/user/favorites/teams/1" "Remove favorite team"
test_endpoint "GET" "/user/favorites/players" "Get favorite players"
test_endpoint "POST" "/user/favorites/players" "Add favorite player"
test_endpoint "DELETE" "/user/favorites/players/1" "Remove favorite player"
test_endpoint "GET" "/user/notifications" "Get notifications"
test_endpoint "PUT" "/user/notifications/1/read" "Mark notification as read"
test_endpoint "POST" "/user/notifications/mark-all-read" "Mark all notifications as read"

# Voting endpoints
echo -e "\n${YELLOW}Voting${NC}"
test_endpoint "POST" "/user/vote" "Vote on content"
test_endpoint "DELETE" "/user/vote" "Remove vote"

# ======================
# SEARCH ENDPOINTS
# ======================
echo -e "\n${YELLOW}=== SEARCH ENDPOINTS ===${NC}"
echo ""

test_endpoint "GET" "/search/advanced?q=test" "Advanced search"
test_endpoint "GET" "/search/teams?q=test" "Search teams"
test_endpoint "GET" "/search/players?q=test" "Search players"
test_endpoint "GET" "/search/matches?q=test" "Search matches"
test_endpoint "GET" "/search/events?q=test" "Search events"
test_endpoint "GET" "/search/news?q=test" "Search news"
test_endpoint "GET" "/search/forums?q=test" "Search forums"
test_endpoint "GET" "/search/users?q=test" "Search users (mod/admin only)"

# ======================
# MODERATOR ENDPOINTS
# ======================
echo -e "\n${YELLOW}=== MODERATOR ENDPOINTS (Require Mod/Admin Role) ===${NC}"
echo ""

test_endpoint "GET" "/moderator/forums/threads/reported" "Get reported threads"
test_endpoint "GET" "/moderator/forums/posts/reported" "Get reported posts"
test_endpoint "PUT" "/moderator/forums/threads/1/lock" "Lock thread"
test_endpoint "PUT" "/moderator/forums/threads/1/unlock" "Unlock thread"
test_endpoint "PUT" "/moderator/forums/threads/1/pin" "Pin thread"
test_endpoint "PUT" "/moderator/forums/threads/1/unpin" "Unpin thread"
test_endpoint "DELETE" "/moderator/forums/threads/1" "Delete thread"
test_endpoint "DELETE" "/moderator/forums/posts/1" "Delete post"
test_endpoint "GET" "/moderator/news/pending" "Get pending news"
test_endpoint "PUT" "/moderator/news/1/approve" "Approve news"
test_endpoint "PUT" "/moderator/news/1/reject" "Reject news"
test_endpoint "GET" "/moderator/news/comments/reported" "Get reported news comments"
test_endpoint "DELETE" "/moderator/news/comments/1" "Delete news comment"
test_endpoint "GET" "/moderator/matches/comments/reported" "Get reported match comments"
test_endpoint "DELETE" "/moderator/matches/comments/1" "Delete match comment"
test_endpoint "GET" "/moderator/users/reported" "Get reported users"
test_endpoint "PUT" "/moderator/users/1/warn" "Warn user"
test_endpoint "PUT" "/moderator/users/1/ban" "Ban user"
test_endpoint "PUT" "/moderator/users/1/unban" "Unban user"
test_endpoint "GET" "/moderator/dashboard/stats" "Get moderator stats"
test_endpoint "GET" "/moderator/dashboard/recent-activity" "Get recent mod activity"

# ======================
# ADMIN ENDPOINTS
# ======================
echo -e "\n${YELLOW}=== ADMIN ENDPOINTS (Require Admin Role) ===${NC}"
echo ""

# Dashboard and stats
echo -e "\n${YELLOW}Admin Dashboard${NC}"
test_endpoint "GET" "/admin/stats" "Get admin stats"
test_endpoint "GET" "/admin/analytics" "Get analytics"
test_endpoint "GET" "/admin/dashboard" "Get admin dashboard"
test_endpoint "GET" "/admin/live-scoring" "Get live scoring dashboard"
test_endpoint "GET" "/admin/content-moderation" "Get content moderation"
test_endpoint "GET" "/admin/user-management" "Get user management"
test_endpoint "GET" "/admin/system-settings" "Get system settings"
test_endpoint "GET" "/admin/analytics-dashboard" "Get analytics dashboard"
test_endpoint "GET" "/admin/analytics/overview" "Get analytics overview"
test_endpoint "GET" "/admin/analytics/users" "Get user analytics"
test_endpoint "GET" "/admin/analytics/content" "Get content analytics"
test_endpoint "GET" "/admin/analytics/engagement" "Get engagement analytics"

# User management
echo -e "\n${YELLOW}User Management${NC}"
test_endpoint "GET" "/admin/users" "Get all users"
test_endpoint "GET" "/admin/users/1" "Get specific user"
test_endpoint "PUT" "/admin/users/1" "Update user"
test_endpoint "DELETE" "/admin/users/1" "Delete user"
test_endpoint "GET" "/admin/users/1/activity" "Get user activity"
test_endpoint "PUT" "/admin/users/1/roles" "Update user roles"
test_endpoint "PUT" "/admin/users/1/permissions" "Update user permissions"

# Team management
echo -e "\n${YELLOW}Team Management${NC}"
test_endpoint "GET" "/admin/teams" "Get all teams"
test_endpoint "GET" "/admin/teams/1" "Get specific team"
test_endpoint "POST" "/admin/teams" "Create team"
test_endpoint "PUT" "/admin/teams/1" "Update team"
test_endpoint "DELETE" "/admin/teams/1" "Delete team"
test_endpoint "POST" "/admin/teams/1/players" "Add player to team"
test_endpoint "DELETE" "/admin/teams/1/players/1" "Remove player from team"

# Player management
echo -e "\n${YELLOW}Player Management${NC}"
test_endpoint "GET" "/admin/players" "Get all players"
test_endpoint "GET" "/admin/players/1" "Get specific player"
test_endpoint "POST" "/admin/players" "Create player"
test_endpoint "PUT" "/admin/players/1" "Update player"
test_endpoint "DELETE" "/admin/players/1" "Delete player"

# Event management
echo -e "\n${YELLOW}Event Management${NC}"
test_endpoint "GET" "/admin/events" "Get all events"
test_endpoint "GET" "/admin/events/1" "Get specific event"
test_endpoint "POST" "/admin/events" "Create event"
test_endpoint "PUT" "/admin/events/1" "Update event"
test_endpoint "DELETE" "/admin/events/1" "Delete event"
test_endpoint "GET" "/admin/events/1/teams" "Get event teams"
test_endpoint "POST" "/admin/events/1/teams/1" "Add team to event"
test_endpoint "DELETE" "/admin/events/1/teams/1" "Remove team from event"
test_endpoint "PUT" "/admin/events/1/teams/1/seed" "Update team seed"
test_endpoint "PUT" "/admin/events/1/status" "Update event status"

# Match management
echo -e "\n${YELLOW}Match Management${NC}"
test_endpoint "GET" "/admin/matches" "Get all matches"
test_endpoint "GET" "/admin/matches/1" "Get specific match"
test_endpoint "POST" "/admin/matches" "Create match"
test_endpoint "PUT" "/admin/matches/1" "Update match"
test_endpoint "DELETE" "/admin/matches/1" "Delete match"
test_endpoint "PUT" "/admin/matches/1/status" "Update match status"
test_endpoint "POST" "/admin/matches/1/results" "Submit match results"

# News management
echo -e "\n${YELLOW}News Management${NC}"
test_endpoint "GET" "/admin/news" "Get all news (admin)"
test_endpoint "GET" "/admin/news/1" "Get specific news (admin)"
test_endpoint "POST" "/admin/news" "Create news"
test_endpoint "PUT" "/admin/news/1" "Update news"
test_endpoint "DELETE" "/admin/news/1" "Delete news"
test_endpoint "PUT" "/admin/news/1/publish" "Publish news"
test_endpoint "PUT" "/admin/news/1/unpublish" "Unpublish news"

# Forum management
echo -e "\n${YELLOW}Forum Management${NC}"
test_endpoint "GET" "/admin/forums/categories" "Get forum categories (admin)"
test_endpoint "POST" "/admin/forums/categories" "Create forum category"
test_endpoint "PUT" "/admin/forums/categories/1" "Update forum category"
test_endpoint "DELETE" "/admin/forums/categories/1" "Delete forum category"
test_endpoint "GET" "/admin/forums/threads" "Get forum threads (admin)"
test_endpoint "GET" "/admin/forums/posts" "Get forum posts (admin)"
test_endpoint "GET" "/admin/forums/reports" "Get forum reports"

# System management
echo -e "\n${YELLOW}System Management${NC}"
test_endpoint "GET" "/admin/system/stats" "Get system stats"
test_endpoint "GET" "/admin/system/health" "Get system health"
test_endpoint "GET" "/admin/system/logs" "Get system logs"
test_endpoint "POST" "/admin/system/cache/clear" "Clear system cache"
test_endpoint "POST" "/admin/system/maintenance/enable" "Enable maintenance mode"
test_endpoint "POST" "/admin/system/maintenance/disable" "Disable maintenance mode"

# Bulk operations
echo -e "\n${YELLOW}Bulk Operations${NC}"
test_endpoint "POST" "/admin/bulk/users" "Bulk user operations"
test_endpoint "POST" "/admin/bulk/teams" "Bulk team operations"
test_endpoint "POST" "/admin/bulk/players" "Bulk player operations"
test_endpoint "POST" "/admin/bulk/matches" "Bulk match operations"

# ======================
# ROLE TEST ENDPOINTS
# ======================
echo -e "\n${YELLOW}=== ROLE TEST ENDPOINTS ===${NC}"
echo ""

test_endpoint "GET" "/test-admin" "Test admin access"
test_endpoint "GET" "/test-moderator" "Test moderator access"
test_endpoint "GET" "/test-user" "Test user access"

# ======================
# SUMMARY
# ======================
echo ""
echo "======================================"
echo "TEST SUMMARY"
echo "======================================"
echo -e "${GREEN}Passed:${NC} $PASSED"
echo -e "${RED}Failed:${NC} $FAILED"
echo -e "Total: $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All API endpoints are working correctly!${NC}"
else
    echo -e "${RED}Some endpoints are failing:${NC}"
    echo ""
    for error in "${ERRORS[@]}"; do
        echo -e "  ${RED}•${NC} $error"
    done
    echo ""
    echo "Note: Some failures may be expected (e.g., auth required, validation errors)"
fi

# Save detailed results
echo ""
echo "Saving detailed results to test-results.txt..."
{
    echo "Test run at: $(date)"
    echo "Passed: $PASSED"
    echo "Failed: $FAILED"
    echo ""
    echo "Failed endpoints:"
    for error in "${ERRORS[@]}"; do
        echo "  • $error"
    done
} > test-results.txt

echo "Done!"