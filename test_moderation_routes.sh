#!/bin/bash

echo "=========================================="
echo "MODERATION ROUTES FINAL VERIFICATION TEST"
echo "=========================================="
echo ""

BASE_URL="https://staging.mrvl.net"
PASS=0
FAIL=0

# Function to test endpoint existence (expecting 302/401 due to auth requirement)
test_route_exists() {
    local endpoint=$1
    local description=$2
    
    echo -n "Testing: $description... "
    
    # Get HTTP status code
    status=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$endpoint")
    
    # Auth-protected routes should return 302 (redirect to login) or 401 (unauthorized)
    if [ "$status" = "302" ] || [ "$status" = "401" ] || [ "$status" = "200" ]; then
        echo "✅ EXISTS (HTTP $status)"
        ((PASS++))
        return 0
    else
        echo "❌ NOT FOUND (HTTP $status)"
        ((FAIL++))
        return 1
    fi
}

echo "🔧 FORUM MODERATION ENDPOINTS"
echo "------------------------------"

test_route_exists "/api/api/admin/forums-moderation/threads" "Forum Threads List"
test_route_exists "/api/api/admin/forums-moderation/categories" "Forum Categories"
test_route_exists "/api/api/admin/forums-moderation/statistics" "Forum Statistics"
test_route_exists "/api/api/admin/forums-moderation/dashboard" "Forum Dashboard"
test_route_exists "/api/api/admin/forums-moderation/reports" "Forum Reports"
test_route_exists "/api/api/admin/forums-moderation/posts" "Forum Posts Management"
test_route_exists "/api/api/admin/forums-moderation/users" "Forum User Management"
test_route_exists "/api/api/admin/forums-moderation/bulk-actions" "Forum Bulk Actions"
test_route_exists "/api/api/admin/forums-moderation/search" "Forum Search"
test_route_exists "/api/api/admin/forums-moderation/moderation-logs" "Forum Moderation Logs"

echo ""
echo "📰 NEWS MODERATION ENDPOINTS"
echo "-----------------------------"

test_route_exists "/api/api/admin/news-moderation" "News Articles List"
test_route_exists "/api/api/admin/news-moderation/categories" "News Categories"
test_route_exists "/api/api/admin/news-moderation/stats/overview" "News Statistics"
test_route_exists "/api/api/admin/news-moderation/pending/all" "Pending News"
test_route_exists "/api/api/admin/news-moderation/comments" "News Comments"
test_route_exists "/api/api/admin/news-moderation/search" "News Search"
test_route_exists "/api/api/admin/news-moderation/flags/all" "Flagged Content"
test_route_exists "/api/api/admin/news-moderation/bulk" "News Bulk Operations"

echo ""
echo "📊 ADVANCED ENDPOINTS"
echo "----------------------"

test_route_exists "/api/api/admin/news-moderation/comments/reported" "Reported Comments"
test_route_exists "/api/api/admin/forums-moderation/threads/1" "Single Forum Thread"
test_route_exists "/api/api/admin/news-moderation/1" "Single News Article"

echo ""
echo "=========================================="
echo "MODERATION ROUTES TEST SUMMARY"
echo "=========================================="

TOTAL=$((PASS + FAIL))
echo "Total Routes Tested: $TOTAL"
echo "✅ Routes Exist: $PASS"
echo "❌ Routes Missing: $FAIL"

if [ $TOTAL -gt 0 ]; then
    SUCCESS_RATE=$((PASS * 100 / TOTAL))
    echo "Success Rate: ${SUCCESS_RATE}%"
    
    if [ $SUCCESS_RATE -eq 100 ]; then
        echo ""
        echo "🎉 PERFECT! All moderation routes are properly configured!"
        echo "📋 Frontend can now access both forum and news moderation tabs"
    elif [ $SUCCESS_RATE -ge 90 ]; then
        echo ""
        echo "✅ EXCELLENT! Most routes are working (${SUCCESS_RATE}%)"
    else
        echo ""
        echo "⚠️ ATTENTION NEEDED! Only ${SUCCESS_RATE}% of routes are working"
    fi
fi

echo ""
echo "🔐 Note: All endpoints require authentication (302/401 responses are expected)"
echo "💻 Frontend should handle authentication and retry requests with valid tokens"