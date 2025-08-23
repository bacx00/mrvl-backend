#!/bin/bash

# Forums & News Systems API Test Suite
# Run this script to verify all forum and news endpoints are working

BASE_URL="http://localhost/api"
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "======================================"
echo "Forums & News Systems API Test Suite"
echo "======================================"
echo ""

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local data=$4
    
    echo -n "Testing: $description - "
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -L "$BASE_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -L -X "$method" "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [[ "$http_code" -ge 200 && "$http_code" -lt 300 ]]; then
        echo -e "${GREEN}✓${NC} (HTTP $http_code)"
    else
        echo -e "${RED}✗${NC} (HTTP $http_code)"
        echo "  Response: $(echo $body | head -c 100)..."
    fi
}

echo "1. FORUM ENDPOINTS"
echo "-----------------"
test_endpoint "GET" "/forums/categories" "Get forum categories"
test_endpoint "GET" "/forums/threads" "Get all threads"
test_endpoint "GET" "/forums/threads?category=general" "Get threads by category"
test_endpoint "GET" "/forums/threads?sort=popular" "Get popular threads"
test_endpoint "GET" "/forums/threads?sort=hot" "Get hot threads"
test_endpoint "GET" "/forums/trending" "Get trending threads"
test_endpoint "GET" "/forums/hot" "Get hot threads (dedicated endpoint)"
test_endpoint "GET" "/forums/overview" "Get forum overview"
test_endpoint "GET" "/forums/threads/8" "Get specific thread"
test_endpoint "GET" "/forums/threads/8/posts" "Get thread posts"
test_endpoint "GET" "/forums/threads/8/exists" "Check thread exists"
test_endpoint "GET" "/forums/search?q=test" "Search forums"
test_endpoint "GET" "/forums/search/suggestions?q=test" "Get search suggestions"

echo ""
echo "2. NEWS ENDPOINTS"
echo "----------------"
test_endpoint "GET" "/news" "Get all news"
test_endpoint "GET" "/news?category=general" "Get news by category"
test_endpoint "GET" "/news?sort=popular" "Get popular news"
test_endpoint "GET" "/news?sort=trending" "Get trending news"
test_endpoint "GET" "/news/categories" "Get news categories"
test_endpoint "GET" "/news/7" "Get specific news article"
test_endpoint "GET" "/news/7/comments" "Get news comments"

echo ""
echo "3. USER PROFILE ENDPOINTS"
echo "------------------------"
test_endpoint "GET" "/users/1/forum-stats" "Get user forum stats"

echo ""
echo "4. MODERATION ENDPOINTS (Requires Auth)"
echo "---------------------------------------"
echo "Note: These require authentication tokens"
echo "- PUT /moderator/threads/{id}/pin"
echo "- PUT /moderator/threads/{id}/lock"
echo "- DELETE /moderator/threads/{id}"
echo "- DELETE /moderator/posts/{id}"
echo "- PUT /moderator/news/{id}/feature"
echo "- PUT /moderator/news/{id}/unfeature"

echo ""
echo "5. AUTHENTICATED ENDPOINTS"
echo "-------------------------"
echo "Note: These require authentication"
echo "- POST /forums/threads (Create thread)"
echo "- POST /forums/threads/{id}/posts (Reply to thread)"
echo "- POST /forums/threads/{id}/vote (Vote on thread)"
echo "- POST /news/{id}/comments (Comment on news)"
echo "- POST /news/{id}/vote (Vote on news)"
echo "- POST /news/comments/{id}/vote (Vote on comment)"

echo ""
echo "======================================"
echo "Test Summary Complete"
echo "======================================"