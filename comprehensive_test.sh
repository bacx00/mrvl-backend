#!/bin/bash

# Comprehensive News Moderation Endpoints Test
BASE_URL="http://localhost:8001"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiMzk2OTc4ZWZjZGJjODM0YzFkMWVkNGZlMDI1MWY2ZmMzYzQzMWM3MmYzYTdkMGNmNjJjYjM3ZTU1MzUyNTc2NTNmNWZiNzkyMDI5MWI1NjAiLCJpYXQiOjE3NTQ4NTM0OTguODA4MzUxMDM5ODg2NDc0NjA5Mzc1LCJuYmYiOjE3NTQ4NTM0OTguODA4MzU2MDQ2Njc2NjM1NzQyMTg3NSwiZXhwIjoxNzg2Mzg5NDk4Ljc5MTQ3MDA1MDgxMTc2NzU3ODEyNSwic3ViIjoiMTIiLCJzY29wZXMiOltdfQ.ROTyYpCOf6Fqj2WSSOuyjc3f0Chq-WsycpFeSNqbP2PYL8G_Lxck4KeltpvvEZseYP-BCUJqXwTe0_m_1tyX-ZKtu9p4_zxszv5xbOR8T3JFv5JRs_Qnlwtv9X2CXEvfHVd3uSjCkmZZzog2EF8ItKrHFJpYSnteHRACyFie8e9yyPLLQzEb6eczMIswVV2CUzg1YYwzndp0Oovn4LXGSf8wGc9plRVyH6aaejQR4oXfHEXqV4uUQW9f0MAMRTQl0cIKi_r1kZ5y5uMxCAKsGbhc_hkNZM-Y2IQlL78Om6yGL3tuUSSxUO4geoCBcyp6cwrxqQS3m7xL90f6E6SVLc6_ZdULlhLu4uR1WQSX5AI2a0M3BVr8jafgty3crHs-cfvIQYc5cKVlQ8gthrVzlL2ObxUiX10tu-3pj89gCgD9MPwTaRIRFSpMjerJuYLHLr6eltCeH-D7rl8LGCDHmhkf_X0eS9ycTQDzElouD84qJ3qFzQu3au50Mb9zkVgzziTmeFK6IOC2zbSWeidsCPOf_qobHXSNY1fLxSpJZTo5QLhbEHQbVz8GKI62joO7vEIsEgWjdD8wEs8FNOgXR1_NesPIm46yMCh1FanJ35IcXM9wvp9KTsOkV492vchq_6sklicAvoukDFqyJKfuwVXbzdg78v3jq6b81EfIqhc"

test_count=0
success_count=0
failed_count=0

# Helper function to test endpoint
test_endpoint() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    local description="$4"
    
    test_count=$((test_count + 1))
    echo "[$test_count] Testing: $description"
    echo "Endpoint: $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -X GET "${BASE_URL}${endpoint}" \
                    -H "Accept: application/json" \
                    -H "Authorization: Bearer $TOKEN")
    elif [ "$method" = "POST" ]; then
        response=$(curl -s -X POST "${BASE_URL}${endpoint}" \
                    -H "Accept: application/json" \
                    -H "Content-Type: application/json" \
                    -H "Authorization: Bearer $TOKEN" \
                    -d "$data")
    elif [ "$method" = "PUT" ]; then
        response=$(curl -s -X PUT "${BASE_URL}${endpoint}" \
                    -H "Accept: application/json" \
                    -H "Content-Type: application/json" \
                    -H "Authorization: Bearer $TOKEN" \
                    -d "$data")
    elif [ "$method" = "DELETE" ]; then
        response=$(curl -s -X DELETE "${BASE_URL}${endpoint}" \
                    -H "Accept: application/json" \
                    -H "Authorization: Bearer $TOKEN")
    fi
    
    success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
    message=$(echo "$response" | jq -r '.message // "No message"' 2>/dev/null)
    
    if [ "$success" = "true" ]; then
        echo "✅ SUCCESS: $message"
        success_count=$((success_count + 1))
    else
        echo "❌ FAILED: $message"
        failed_count=$((failed_count + 1))
        # Show first 200 chars of response for debugging
        echo "Response preview: $(echo "$response" | head -c 200)..."
    fi
    echo
}

echo "=== COMPREHENSIVE NEWS MODERATION TEST ==="
echo "Testing all endpoints at: ${BASE_URL}/api/api/admin/news-moderation"
echo

# ========================================
# CORE ARTICLE MANAGEMENT
# ========================================
echo "🔹 CORE ARTICLE MANAGEMENT"
test_endpoint "GET" "/api/api/admin/news-moderation?page=1&limit=10" "" "List articles with pagination"
test_endpoint "GET" "/api/api/admin/news-moderation/1" "" "Get specific article"
test_endpoint "PUT" "/api/api/admin/news-moderation/1" '{"title": "Updated Test Article", "excerpt": "Updated excerpt"}' "Update article"

# ========================================
# ARTICLE PUBLISHING & FEATURING
# ========================================
echo "🔹 ARTICLE PUBLISHING & FEATURING"
test_endpoint "POST" "/api/api/admin/news-moderation/1/toggle-publish" "" "Toggle publish status"
test_endpoint "POST" "/api/api/admin/news-moderation/1/toggle-feature" "" "Toggle feature status"
test_endpoint "POST" "/api/api/admin/news-moderation/1/approve" '{"publish_immediately": true, "featured": false}' "Approve article"

# ========================================
# CATEGORY MANAGEMENT
# ========================================
echo "🔹 CATEGORY MANAGEMENT"
test_endpoint "GET" "/api/api/admin/news-moderation/categories" "" "Get all categories"
test_endpoint "POST" "/api/api/admin/news-moderation/categories" '{"name": "Test Category API", "description": "API test category", "color": "#ff5722"}' "Create new category"

# ========================================
# BULK OPERATIONS
# ========================================
echo "🔹 BULK OPERATIONS"
test_endpoint "POST" "/api/api/admin/news-moderation/bulk" '{"action": "publish", "news_ids": [1, 2]}' "Bulk publish articles"
test_endpoint "POST" "/api/api/admin/news-moderation/bulk" '{"action": "feature", "news_ids": [2]}' "Bulk feature articles"

# ========================================
# STATISTICS & ANALYTICS
# ========================================
echo "🔹 STATISTICS & ANALYTICS"
test_endpoint "GET" "/api/api/admin/news-moderation/stats/overview" "" "Get statistics overview"

# ========================================
# SEARCH & FILTERING
# ========================================
echo "🔹 SEARCH & FILTERING"
test_endpoint "GET" "/api/api/admin/news-moderation/search?query=test" "" "Search articles (with results)"
test_endpoint "GET" "/api/api/admin/news-moderation/search?query=nonexistent123" "" "Search articles (no results)"
test_endpoint "GET" "/api/api/admin/news-moderation/pending/all" "" "Get pending articles"

# ========================================
# CONTENT MODERATION
# ========================================
echo "🔹 CONTENT MODERATION"
test_endpoint "GET" "/api/api/admin/news-moderation/flags/all" "" "Get flagged content"
test_endpoint "POST" "/api/api/admin/news-moderation/1/flag" '{"flag_type": "inappropriate", "reason": "Test flag", "priority": "medium"}' "Flag article"
test_endpoint "GET" "/api/api/admin/news-moderation/1/moderation-history" "" "Get moderation history"

# ========================================
# COMMENTS MODERATION
# ========================================
echo "🔹 COMMENTS MODERATION"
test_endpoint "GET" "/api/api/admin/news-moderation/comments" "" "Get all comments for moderation"
test_endpoint "GET" "/api/api/admin/news-moderation/comments/reported" "" "Get reported comments"

# ========================================
# TEST CREATION FOR FULL WORKFLOW
# ========================================
echo "🔹 FULL WORKFLOW TEST"
article_data='{
    "title": "Complete Workflow Test Article",
    "content": "This article tests the complete news moderation workflow from creation to publication.",
    "excerpt": "Testing complete workflow",
    "category_id": 1,
    "status": "draft",
    "tags": ["workflow", "test", "moderation"],
    "featured": false,
    "breaking": false
}'
test_endpoint "POST" "/api/api/admin/news-moderation" "$article_data" "Create workflow test article"

echo "=== TEST SUMMARY ==="
echo "Total tests run: $test_count"
echo "✅ Successful: $success_count"
echo "❌ Failed: $failed_count"
echo "Success rate: $(echo "scale=1; $success_count * 100 / $test_count" | bc)%"
echo

if [ $failed_count -eq 0 ]; then
    echo "🎉 ALL TESTS PASSED! News moderation system is fully functional."
else
    echo "⚠️  Some tests failed. Check the error messages above for details."
fi