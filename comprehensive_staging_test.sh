#!/bin/bash

echo "====================================="
echo "COMPREHENSIVE STAGING TEST"
echo "====================================="
echo ""

BASE_URL="https://staging.mrvl.net"
PASS=0
FAIL=0

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test function
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local data=$4
    local auth=$5
    
    echo -n "Testing: $description... "
    
    if [ "$method" = "GET" ]; then
        if [ -n "$auth" ]; then
            response=$(curl -s -X GET "${BASE_URL}${endpoint}" -H "Authorization: Bearer $auth")
        else
            response=$(curl -s -X GET "${BASE_URL}${endpoint}")
        fi
    else
        if [ -n "$auth" ]; then
            response=$(curl -s -X POST "${BASE_URL}${endpoint}" -H "Content-Type: application/json" -H "Authorization: Bearer $auth" -d "$data")
        else
            response=$(curl -s -X POST "${BASE_URL}${endpoint}" -H "Content-Type: application/json" -d "$data")
        fi
    fi
    
    # Check if response contains success or data
    if echo "$response" | grep -q '"success":true\|"data":\[' 2>/dev/null; then
        echo -e "${GREEN}✅ PASSED${NC}"
        ((PASS++))
        return 0
    else
        echo -e "${RED}❌ FAILED${NC}"
        echo "   Response: $(echo $response | head -c 100)..."
        ((FAIL++))
        return 1
    fi
}

echo "📋 FORUM SYSTEM TESTS"
echo "------------------------"

# Test Forum Categories
test_endpoint "GET" "/api/forums/categories" "Forum Categories"

# Test Forum Threads
test_endpoint "GET" "/api/forums/threads?sort=latest" "Forum Threads List"

# Test specific thread
test_endpoint "GET" "/api/forums/threads/2" "Single Forum Thread"

echo ""
echo "📰 NEWS SYSTEM TESTS"
echo "------------------------"

# Test News Categories
test_endpoint "GET" "/api/news/categories" "News Categories"

# Test News Articles
test_endpoint "GET" "/api/news?category=all&sort=latest" "News Articles List"

# Test Single News
test_endpoint "GET" "/api/news/1" "Single News Article"

echo ""
echo "👥 MENTIONS SYSTEM TESTS"
echo "------------------------"

# Test Mention Search
test_endpoint "GET" "/api/public/mentions/search?q=a&type=all&limit=10" "Mention Search"

# Test Popular Mentions
test_endpoint "GET" "/api/public/mentions/popular?limit=8" "Popular Mentions"

echo ""
echo "📅 DATE FORMAT TESTS"
echo "------------------------"

# Check date format in forums
echo -n "Checking forum date format... "
forum_date=$(curl -s "${BASE_URL}/api/forums/threads?sort=latest" | grep -o '"created_at":"[^"]*"' | head -1)
if echo "$forum_date" | grep -q 'T'; then
    echo -e "${GREEN}✅ ISO 8601 format detected${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ Invalid date format${NC}"
    ((FAIL++))
fi

# Check date format in news
echo -n "Checking news date format... "
news_date=$(curl -s "${BASE_URL}/api/news?category=all&sort=latest" | grep -o '"published_at":"[^"]*"' | head -1)
if echo "$news_date" | grep -q 'T'; then
    echo -e "${GREEN}✅ ISO 8601 format detected${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ Invalid date format${NC}"
    ((FAIL++))
fi

echo ""
echo "🔧 ADMIN ENDPOINTS TESTS"
echo "------------------------"

# Test Admin News Moderation (double api path)
test_endpoint "GET" "/api/api/admin/news-moderation?page=1&limit=50" "Admin News Moderation"

# Test Admin News Categories
test_endpoint "GET" "/api/api/admin/news-moderation/categories" "Admin News Categories"

echo ""
echo "📊 VOTING SYSTEM TESTS"
echo "------------------------"

# Note: These require authentication, so we'll just check if endpoints exist
echo -n "Checking forum voting endpoint... "
response=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${BASE_URL}/api/forums/posts/3/vote" -H "Content-Type: application/json" -d '{"vote_type":"upvote"}')
if [ "$response" = "401" ] || [ "$response" = "409" ] || [ "$response" = "200" ]; then
    echo -e "${GREEN}✅ Endpoint exists${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ Endpoint not found (HTTP $response)${NC}"
    ((FAIL++))
fi

echo -n "Checking news voting endpoint... "
response=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${BASE_URL}/api/user/votes/" -H "Content-Type: application/json" -d '{"votable_type":"news","votable_id":1,"vote_type":"upvote"}')
if [ "$response" = "401" ] || [ "$response" = "200" ]; then
    echo -e "${GREEN}✅ Endpoint exists${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ Endpoint not found (HTTP $response)${NC}"
    ((FAIL++))
fi

echo ""
echo "====================================="
echo "TEST RESULTS SUMMARY"
echo "====================================="
echo -e "${GREEN}Passed: $PASS${NC}"
echo -e "${RED}Failed: $FAIL${NC}"
TOTAL=$((PASS + FAIL))
if [ $TOTAL -gt 0 ]; then
    SUCCESS_RATE=$((PASS * 100 / TOTAL))
    echo "Success Rate: ${SUCCESS_RATE}%"
    
    if [ $SUCCESS_RATE -eq 100 ]; then
        echo ""
        echo -e "${GREEN}🎉 ALL TESTS PASSED! System is working perfectly!${NC}"
    elif [ $SUCCESS_RATE -ge 80 ]; then
        echo ""
        echo -e "${GREEN}✅ System is mostly functional (${SUCCESS_RATE}% pass rate)${NC}"
    else
        echo ""
        echo -e "${RED}⚠️ System needs attention (${SUCCESS_RATE}% pass rate)${NC}"
    fi
fi