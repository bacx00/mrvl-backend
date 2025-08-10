#!/bin/bash

# Test Fixed Endpoints
BASE_URL="http://localhost:8000"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIzIiwianRpIjoiMjg3ODUwM2RhZjAwNWRjYmQzMjNjYjlhZmExOTc1NTY5MDcwYTM3NGFiM2UyMWZiNTM5NTU2ZWI2NmU5NGYwMTQxZDYzNzRhZWY2OTUyMjIiLCJpYXQiOjE3NTQ4NTIzNzcuNTA1OTU5MDMzOTY2MDY0NDUzMTI1LCJuYmYiOjE3NTQ4NTIzNzcuNTA1OTY2OTAxNzc5MTc0ODA0Njg3NSwiZXhwIjoxNzg2Mzg4Mzc3LjQ4MjY3NzkzNjU1Mzk1NTA3ODEyNSwic3ViIjoiMTIiLCJzY29wZXMiOltdfQ.MKk905vcrtaCVC7KSVTafmdmktF3BE5z0p07GBmhxWm_BSk4JaAies7Imo_UomLXsPkJFxacc1lQLKvFwqn-sHiMGV-SGOvjwm2OD-3MvvUB-SbLvdEyI7MGRdNB-VtkvKoEIDBf5F5Pk6YcZlcFq4WWJTBHWpvW4q8WBPVJInj-yaqzbRjvSN6gAL0ZGP2lirHZMirZrk6e1Z4YuFzquTByPuRL0Js5ptDjwp6buvNg2JuBW-HGDjvyGtaxlkgsFXL6xkdEevFX0SwYqVBgpiV6ggK220P_boZG8RQHshQiGdJeV9SIbrVAnXNlyqpozGVNYcOSwCdmuYcVPW84vBmRVsOOO_YP6w9NnA1_ni75fic61Ew99kvaCT72gsQdQpWhjtqABySHrzmdBnH9O_jmlJhgb5YJRCdAM1FJUlonMFP97_pAi22o5j5VA77Nl6h2B0NiKucPHzrsVeluN_m3vXeqHqMxvqM3W799gzafssAQ4Xskz3nQoadCzoPcdgUwC8mSUb_EViRambipXlM5kB4E9CunZqmD4IU4Wm9lIxbzTBMpmN8u9cZGykVkWEfzeQZM4gAYantcbz0bwJL3WgbyJYd88YeXTzWVWWz_wTn-WEJ9w2gcttBAH_BNFYVbT4mqf-6ZUW-x9DH8Y_enTIh52w3Q3OcjoA3rk0s"

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

echo "=== TESTING FIXED ENDPOINTS ==="
echo

# Test toggle-publish (was failing before)
test_endpoint "POST" "/api/api/admin/news-moderation/1/toggle-publish" "" "Toggle publish status"

# Test flagged content (was failing due to missing table)
test_endpoint "GET" "/api/api/admin/news-moderation/flags/all" "" "Get flagged content"

# Test comments (was failing due to missing routes)
test_endpoint "GET" "/api/api/admin/news-moderation/comments" "" "Get news comments"

# Test reported comments (was failing due to missing route)
test_endpoint "GET" "/api/api/admin/news-moderation/comments/reported" "" "Get reported comments"

# Test search (should return empty results, not error)
test_endpoint "GET" "/api/api/admin/news-moderation/search?query=nonexistentarticle" "" "Search functionality with no results"

# Test moderation history (was failing due to missing columns)
test_endpoint "GET" "/api/api/admin/news-moderation/1/moderation-history" "" "Get moderation history"

echo "=== FIXED ENDPOINTS TEST COMPLETE ==="