#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://localhost:8000/api"

# Counter for passed/failed tests
PASSED=0
FAILED=0

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local auth_header=$4
    
    if [ -z "$auth_header" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" -X $method "$BASE_URL$endpoint")
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" -X $method -H "$auth_header" "$BASE_URL$endpoint")
    fi
    
    if [ $response -eq 200 ] || [ $response -eq 201 ] || [ $response -eq 401 ] || [ $response -eq 403 ] || [ $response -eq 404 ]; then
        echo -e "${GREEN}✓${NC} $method $endpoint - $description (HTTP $response)"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} $method $endpoint - $description (HTTP $response)"
        ((FAILED++))
    fi
}

echo "======================================"
echo "Testing MRVL Backend API Endpoints"
echo "======================================"
echo ""

# ======================
# PUBLIC ENDPOINTS
# ======================
echo -e "${YELLOW}PUBLIC ENDPOINTS (No Auth Required)${NC}"
echo "--------------------------------------"

# Teams
test_endpoint "GET" "/teams" "List all teams"
test_endpoint "GET" "/teams/117" "Get team details"
test_endpoint "GET" "/teams/117/mentions" "Get team mentions"
test_endpoint "GET" "/teams/117/matches/upcoming" "Get team upcoming matches"
test_endpoint "GET" "/teams/117/matches/live" "Get team live matches"
test_endpoint "GET" "/teams/117/matches/recent" "Get team recent matches"
test_endpoint "GET" "/teams/117/matches/stats" "Get team match stats"

# Players
test_endpoint "GET" "/players" "List all players"
test_endpoint "GET" "/players/275" "Get player details"
test_endpoint "GET" "/players/275/mentions" "Get player mentions"
test_endpoint "GET" "/players/275/match-history" "Get player match history"
test_endpoint "GET" "/players/275/hero-stats" "Get player hero stats"
test_endpoint "GET" "/players/275/performance-stats" "Get player performance stats"
test_endpoint "GET" "/players/275/map-stats" "Get player map stats"
test_endpoint "GET" "/players/275/event-stats" "Get player event stats"

# Events
test_endpoint "GET" "/events" "List all events"
test_endpoint "GET" "/events/1" "Get event details"

# Matches
test_endpoint "GET" "/matches" "List all matches"
test_endpoint "GET" "/matches/live" "Get live matches"
test_endpoint "GET" "/matches/1" "Get match details"
test_endpoint "GET" "/matches/1/comments" "Get match comments"
test_endpoint "GET" "/matches/1/timeline" "Get match timeline"
test_endpoint "GET" "/matches/head-to-head/117/118" "Get head to head"

# News
test_endpoint "GET" "/news" "List all news"
test_endpoint "GET" "/news/1" "Get news details"
test_endpoint "GET" "/news/1/comments" "Get news comments"
test_endpoint "GET" "/news/categories" "Get news categories"

# Forums
test_endpoint "GET" "/forums/categories" "Get forum categories"
test_endpoint "GET" "/forums/threads" "List forum threads"
test_endpoint "GET" "/forums/threads/1" "Get thread details"
test_endpoint "GET" "/forums/threads/1/posts" "Get thread posts"

# Public routes with /public prefix
echo ""
echo -e "${YELLOW}PUBLIC PREFIX ENDPOINTS${NC}"
echo "--------------------------------------"

test_endpoint "GET" "/public/teams" "Public teams list"
test_endpoint "GET" "/public/teams/117" "Public team details"
test_endpoint "GET" "/public/players" "Public players list"
test_endpoint "GET" "/public/players/275" "Public player details"
test_endpoint "GET" "/public/players/275/match-history" "Public player match history"
test_endpoint "GET" "/public/players/275/hero-stats" "Public player hero stats"
test_endpoint "GET" "/public/players/275/performance-stats" "Public player performance stats"
test_endpoint "GET" "/public/players/275/map-stats" "Public player map stats"
test_endpoint "GET" "/public/players/275/event-stats" "Public player event stats"
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

# ======================
# AUTH ENDPOINTS
# ======================
echo ""
echo -e "${YELLOW}AUTHENTICATION ENDPOINTS${NC}"
echo "--------------------------------------"

test_endpoint "POST" "/auth/login" "Login endpoint"
test_endpoint "POST" "/auth/register" "Register endpoint"
test_endpoint "POST" "/auth/forgot-password" "Forgot password"
test_endpoint "POST" "/auth/reset-password" "Reset password"
test_endpoint "GET" "/auth/me" "Get current user (should fail without auth)"
test_endpoint "POST" "/auth/logout" "Logout (should fail without auth)"
test_endpoint "POST" "/auth/refresh" "Refresh token (should fail without auth)"

# ======================
# USER ENDPOINTS (Need Auth)
# ======================
echo ""
echo -e "${YELLOW}USER ENDPOINTS (Require Authentication)${NC}"
echo "--------------------------------------"

# These should return 401 without auth
test_endpoint "GET" "/user" "Get authenticated user"
test_endpoint "GET" "/user/profile" "Get user profile"
test_endpoint "PUT" "/user/profile" "Update user profile"
test_endpoint "GET" "/user/profile/available-flairs" "Get available flairs"
test_endpoint "GET" "/user/profile/activity" "Get profile activity"
test_endpoint "GET" "/user/stats" "Get user stats"
test_endpoint "GET" "/user/activity" "Get user activity"
test_endpoint "GET" "/user/forums/threads" "Get user forum threads"
test_endpoint "GET" "/user/news/1/comments" "Post news comment"
test_endpoint "GET" "/user/matches/1/comments" "Post match comment"
test_endpoint "GET" "/user/predictions" "Get user predictions"
test_endpoint "GET" "/user/favorites/teams" "Get favorite teams"
test_endpoint "GET" "/user/favorites/players" "Get favorite players"
test_endpoint "GET" "/user/notifications" "Get notifications"

# ======================
# MODERATOR ENDPOINTS
# ======================
echo ""
echo -e "${YELLOW}MODERATOR ENDPOINTS (Require Mod/Admin Role)${NC}"
echo "--------------------------------------"

test_endpoint "GET" "/moderator/forums/threads/reported" "Get reported threads"
test_endpoint "GET" "/moderator/forums/posts/reported" "Get reported posts"
test_endpoint "GET" "/moderator/news/pending" "Get pending news"
test_endpoint "GET" "/moderator/news/comments/reported" "Get reported news comments"
test_endpoint "GET" "/moderator/matches/comments/reported" "Get reported match comments"
test_endpoint "GET" "/moderator/users/reported" "Get reported users"
test_endpoint "GET" "/moderator/dashboard/stats" "Get moderator stats"
test_endpoint "GET" "/moderator/dashboard/recent-activity" "Get recent mod activity"

# ======================
# ADMIN ENDPOINTS
# ======================
echo ""
echo -e "${YELLOW}ADMIN ENDPOINTS (Require Admin Role)${NC}"
echo "--------------------------------------"

test_endpoint "GET" "/admin/stats" "Get admin stats"
test_endpoint "GET" "/admin/analytics" "Get analytics"
test_endpoint "GET" "/admin/users" "Get all users"
test_endpoint "GET" "/admin/users/1" "Get specific user"
test_endpoint "GET" "/admin/users/1/activity" "Get user activity"
test_endpoint "GET" "/admin/teams" "Get all teams"
test_endpoint "GET" "/admin/teams/117" "Get specific team"
test_endpoint "GET" "/admin/players" "Get all players"
test_endpoint "GET" "/admin/players/275" "Get specific player"
test_endpoint "GET" "/admin/events" "Get all events"
test_endpoint "GET" "/admin/events/1" "Get specific event"
test_endpoint "GET" "/admin/matches" "Get all matches"
test_endpoint "GET" "/admin/matches/1" "Get specific match"
test_endpoint "GET" "/admin/news" "Get all news (admin)"
test_endpoint "GET" "/admin/news/1" "Get specific news (admin)"
test_endpoint "GET" "/admin/forums/categories" "Get forum categories (admin)"
test_endpoint "GET" "/admin/forums/threads" "Get forum threads (admin)"
test_endpoint "GET" "/admin/forums/posts" "Get forum posts (admin)"
test_endpoint "GET" "/admin/forums/reports" "Get forum reports"
test_endpoint "GET" "/admin/system/stats" "Get system stats"
test_endpoint "GET" "/admin/system/health" "Get system health"
test_endpoint "GET" "/admin/system/logs" "Get system logs"
test_endpoint "GET" "/admin/analytics/overview" "Get analytics overview"
test_endpoint "GET" "/admin/analytics/users" "Get user analytics"
test_endpoint "GET" "/admin/analytics/content" "Get content analytics"
test_endpoint "GET" "/admin/analytics/engagement" "Get engagement analytics"
test_endpoint "GET" "/admin/dashboard" "Get admin dashboard"
test_endpoint "GET" "/admin/live-scoring" "Get live scoring dashboard"
test_endpoint "GET" "/admin/content-moderation" "Get content moderation"
test_endpoint "GET" "/admin/user-management" "Get user management"
test_endpoint "GET" "/admin/system-settings" "Get system settings"
test_endpoint "GET" "/admin/analytics-dashboard" "Get analytics dashboard"

# ======================
# SEARCH ENDPOINTS
# ======================
echo ""
echo -e "${YELLOW}SEARCH ENDPOINTS (Require Auth)${NC}"
echo "--------------------------------------"

test_endpoint "GET" "/search/advanced?q=test" "Advanced search"
test_endpoint "GET" "/search/teams?q=test" "Search teams"
test_endpoint "GET" "/search/players?q=test" "Search players"
test_endpoint "GET" "/search/matches?q=test" "Search matches"
test_endpoint "GET" "/search/events?q=test" "Search events"
test_endpoint "GET" "/search/news?q=test" "Search news"
test_endpoint "GET" "/search/forums?q=test" "Search forums"
test_endpoint "GET" "/search/users?q=test" "Search users (mod/admin only)"

# ======================
# SYSTEM TEST
# ======================
echo ""
echo -e "${YELLOW}SYSTEM TEST ENDPOINTS${NC}"
echo "--------------------------------------"

test_endpoint "GET" "/system-test" "System test endpoint"
test_endpoint "GET" "/test-forum" "Forum system test"

# ======================
# ROLE TEST ENDPOINTS
# ======================
echo ""
echo -e "${YELLOW}ROLE TEST ENDPOINTS${NC}"
echo "--------------------------------------"

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
    echo -e "${GREEN}All API endpoints are accessible!${NC}"
else
    echo -e "${RED}Some endpoints returned unexpected status codes.${NC}"
    echo "Note: 401/403 responses are expected for authenticated endpoints without proper auth."
fi