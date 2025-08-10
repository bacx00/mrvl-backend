#!/bin/bash

# News Moderation Endpoints Test Script
BASE_URL="http://localhost:8000"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiMjg3ODUwM2RhZjAwNWRjYmQzMjNjYjlhZmExOTc1NTY5MDcwYTM3NGFiM2UyMWZiNTM5NTU2ZWI2NmU5NGYwMTQxZDYzNzRhZWY2OTUyMjIiLCJpYXQiOjE3NTQ4NTIzNzcuNTA1OTU5MDMzOTY2MDY0NDUzMTI1LCJuYmYiOjE3NTQ4NTIzNzcuNTA1OTY2OTAxNzc5MTc0ODA0Njg3NSwiZXhwIjoxNzg2Mzg4Mzc3LjQ4MjY3NzkzNjU1Mzk1NTA3ODEyNSwic3ViIjoiMTIiLCJzY29wZXMiOltdfQ.MKk905vcrtaCVC7KSVTafmdmktF3BE5z0p07GBmhxWm_BSk4JaAies7Imo_UomLXsPkJFxacc1lQLKvFwqn-sHiMGV-SGOvjwm2OD-3MvvUB-SbLvdEyI7MGRdNB-VtkvKoEIDBf5F5Pk6YcZlcFq4WWJTBHWpvW4q8WBPVJInj-yaqzbRjvSN6gAL0ZGP2lirHZMirZrk6e1Z4YuFzquTByPuRL0Js5ptDjwp6buvNg2JuBW-HGDjvyGtaxlkgsFXL6xkdEevFX0SwYqVBgpiV6ggK220P_boZG8RQHshQiGdJeV9SIbrVAnXNlyqpozGVNYcOSwCdmuYcVPW84vBmRVsOOO_YP6w9NnA1_ni75fic61Ew99kvaCT72gsQdQpWhjtqABySHrzmdBnH9O_jmlJhgb5YJRCdAM1FJUlonMFP97_pAi22o5j5VA77Nl6h2B0NiKucPHzrsVeluN_m3vXeqHqMxvqM3W799gzafssAQ4Xskz3nQoadCzoPcdgUwC8mSUb_EViRambipXlM5kB4E9CunZqmD4IU4Wm9lIxbzTBMpmN8u9cZGykVkWEfzeQZM4gAYantcbz0bwJL3WgbyJYd88YeXTzWVWWz_wTn-WEJ9w2gcttBAH_BNFYVbT4mqf-6ZUW-x9DH8Y_enTIh52w3Q3OcjoA3rk0s"

echo "=== NEWS MODERATION ENDPOINTS TEST ==="
echo "Testing all endpoints at: ${BASE_URL}/api/api/admin/news-moderation"
echo

# Helper function to test endpoint
test_endpoint() {
    local method="$1"
    local endpoint="$2"
    local data="$3"
    local description="$4"
    
    echo "Testing: $description"
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
    fi
    
    success=$(echo "$response" | jq -r '.success // false')
    message=$(echo "$response" | jq -r '.message // "No message"')
    
    if [ "$success" = "true" ]; then
        echo "✅ SUCCESS: $message"
    else
        echo "❌ FAILED: $message"
    fi
    echo
}

# 1. List articles with pagination
test_endpoint "GET" "/api/api/admin/news-moderation?page=1&limit=10" "" "List articles with pagination"

# 2. Categories endpoint
test_endpoint "GET" "/api/api/admin/news-moderation/categories" "" "Get news categories"

# 3. Statistics endpoint
test_endpoint "GET" "/api/api/admin/news-moderation/stats/overview" "" "Get statistics overview"

# 4. Search functionality
test_endpoint "GET" "/api/api/admin/news-moderation/search?query=test" "" "Search functionality"

# 5. Get pending articles
test_endpoint "GET" "/api/api/admin/news-moderation/pending/all" "" "Get pending articles"

# 6. Get flagged content
test_endpoint "GET" "/api/api/admin/news-moderation/flags/all" "" "Get flagged content"

# 7. Get comments for moderation
test_endpoint "GET" "/api/api/admin/news-moderation/comments" "" "Get news comments"

# 8. Get reported comments
test_endpoint "GET" "/api/api/admin/news-moderation/comments/reported" "" "Get reported comments"

# 9. Create new article
article_data='{
    "title": "Test Article for Moderation",
    "content": "This is a test article content for checking news moderation functionality.",
    "excerpt": "Test excerpt",
    "category_id": 1,
    "status": "draft",
    "tags": ["test", "moderation"]
}'
test_endpoint "POST" "/api/api/admin/news-moderation" "$article_data" "Create new article"

# 10. Get specific article (assuming article with ID 1 exists)
test_endpoint "GET" "/api/api/admin/news-moderation/1" "" "Get specific article"

# 11. Toggle feature status for article 1
test_endpoint "POST" "/api/api/admin/news-moderation/1/toggle-feature" "" "Toggle feature status"

# 12. Toggle publish status for article 1
test_endpoint "POST" "/api/api/admin/news-moderation/1/toggle-publish" "" "Toggle publish status"

# 13. Test bulk operations
bulk_data='{
    "action": "publish",
    "news_ids": [1]
}'
test_endpoint "POST" "/api/api/admin/news-moderation/bulk" "$bulk_data" "Bulk operation - publish"

# 14. Create a new category
category_data='{
    "name": "Test Category",
    "description": "Test category for moderation",
    "color": "#ff5722",
    "active": true
}'
test_endpoint "POST" "/api/api/admin/news-moderation/categories" "$category_data" "Create new category"

# 15. Get moderation history for article 1
test_endpoint "GET" "/api/api/admin/news-moderation/1/moderation-history" "" "Get moderation history"

echo "=== TEST SUMMARY ==="
echo "All major news moderation endpoints have been tested."
echo "Check the results above for any failures."
echo