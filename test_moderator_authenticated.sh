#!/bin/bash

# Test Moderator Actions with Admin Authentication
API_BASE="http://staging.mrvl.net/api"

echo "=== Authenticating as Admin ==="
AUTH_RESPONSE=$(curl -sL -X POST "$API_BASE/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "jhonny@ar-mediia.com",
    "password": "password123"
  }')

TOKEN=$(echo $AUTH_RESPONSE | jq -r '.token // .access_token // .data.token // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo "Authentication failed:"
    echo $AUTH_RESPONSE | jq '.' 2>/dev/null || echo $AUTH_RESPONSE
    exit 1
fi

echo "✅ Authentication successful"
AUTH_HEADER="Authorization: Bearer $TOKEN"

echo -e "\n=== Testing Forum Moderator Actions ==="

echo "1. Testing Thread Lock (Admin Forums Moderation)"
curl -sL -X PUT "$API_BASE/admin/forums-moderation/threads/12" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "locked"}' | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n2. Testing Thread Pin (Admin Forums Moderation)"
curl -sL -X PUT "$API_BASE/admin/forums-moderation/threads/12" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"is_pinned": true}' | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n3. Testing Alternative Thread Lock (Admin Forums)"
curl -sL -X POST "$API_BASE/admin/forums/threads/12/lock" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n4. Testing Alternative Thread Pin (Admin Forums)"
curl -sL -X POST "$API_BASE/admin/forums/threads/12/pin" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n5. Testing Moderator Thread Lock"
curl -sL -X POST "$API_BASE/moderator/forums/threads/12/lock" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n=== Testing News Moderator Actions ==="

echo -e "\n6. Testing News Comment Moderation"
curl -sL -X PUT "$API_BASE/admin/news-moderation/comments/1/moderate" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"action": "approve", "reason": "Test moderation"}' | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n7. Testing News Bulk Operations"
curl -sL -X POST "$API_BASE/admin/news-moderation/bulk" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"action": "bulk_approve", "ids": [1]}' | jq '.success // .message' 2>/dev/null || echo "Request failed"

echo -e "\n8. Testing Get Forum Posts (Moderator View)"
curl -sL -X GET "$API_BASE/moderator/forums/posts/reported" \
  -H "$AUTH_HEADER" \
  -H "Accept: application/json" | jq '.success // .data // .message' 2>/dev/null || echo "Request failed"

echo -e "\n9. Testing Get News Comments (Moderator View)"
curl -sL -X GET "$API_BASE/moderator/news/comments/reported" \
  -H "$AUTH_HEADER" \
  -H "Accept: application/json" | jq '.success // .data // .message' 2>/dev/null || echo "Request failed"

echo -e "\n=== Testing Admin Dashboards ==="

echo -e "\n10. Testing Admin Forums Dashboard"
curl -sL -X GET "$API_BASE/admin/forums-moderation/dashboard" \
  -H "$AUTH_HEADER" \
  -H "Accept: application/json" | jq '.success // .data // .message' 2>/dev/null || echo "Request failed"

echo -e "\n11. Testing Admin News Dashboard"
curl -sL -X GET "$API_BASE/admin/news-moderation" \
  -H "$AUTH_HEADER" \
  -H "Accept: application/json" | jq '.success // .data // .message' 2>/dev/null || echo "Request failed"

echo -e "\n12. Testing Forum Categories (Admin)"
curl -sL -X GET "$API_BASE/admin/forums-moderation/categories" \
  -H "$AUTH_HEADER" \
  -H "Accept: application/json" | jq '.success // .data // .message' 2>/dev/null || echo "Request failed"

echo -e "\n13. Testing News Categories (Admin)"
curl -sL -X GET "$API_BASE/admin/news-categories" \
  -H "$AUTH_HEADER" \
  -H "Accept: application/json" | jq '.success // .data // .message' 2>/dev/null || echo "Request failed"