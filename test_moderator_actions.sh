#!/bin/bash

# Test Moderator Actions for Forums and News
API_BASE="http://staging.mrvl.net/api"
TOKEN="Bearer 33|mBJJvE5nvFU5cXXvl1PQrTYl9qHW5dEBt4Av2xRD"

echo "=== Testing Forum Moderator Actions ==="

echo "1. Testing Thread Lock (Admin Forums Moderation)"
curl -sL -X PUT "$API_BASE/admin/forums-moderation/threads/12" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"status": "locked"}' | head -5

echo -e "\n2. Testing Thread Pin (Admin Forums Moderation)"
curl -sL -X PUT "$API_BASE/admin/forums-moderation/threads/12" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"is_pinned": true}' | head -5

echo -e "\n3. Testing Alternative Thread Lock (Admin Forums)"
curl -sL -X POST "$API_BASE/admin/forums/threads/12/lock" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | head -5

echo -e "\n4. Testing Alternative Thread Pin (Admin Forums)"
curl -sL -X POST "$API_BASE/admin/forums/threads/12/pin" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | head -5

echo -e "\n5. Testing Moderator Thread Lock"
curl -sL -X POST "$API_BASE/moderator/forums/threads/12/lock" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" | head -5

echo -e "\n=== Testing News Moderator Actions ==="

echo -e "\n6. Testing News Comment Moderation"
curl -sL -X PUT "$API_BASE/admin/news-moderation/comments/1/moderate" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"action": "approve", "reason": "Test moderation"}' | head -5

echo -e "\n7. Testing News Bulk Operations"
curl -sL -X POST "$API_BASE/admin/news-moderation/bulk" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"action": "bulk_approve", "ids": [1]}' | head -5

echo -e "\n8. Testing Get Forum Posts (Moderator View)"
curl -sL -X GET "$API_BASE/moderator/forums/posts/reported" \
  -H "Authorization: $TOKEN" \
  -H "Accept: application/json" | head -5

echo -e "\n9. Testing Get News Comments (Moderator View)"
curl -sL -X GET "$API_BASE/moderator/news/comments/reported" \
  -H "Authorization: $TOKEN" \
  -H "Accept: application/json" | head -5

echo -e "\n=== Testing Admin Endpoints ==="

echo -e "\n10. Testing Admin Forums Dashboard"
curl -sL -X GET "$API_BASE/admin/forums-moderation/dashboard" \
  -H "Authorization: $TOKEN" \
  -H "Accept: application/json" | head -5

echo -e "\n11. Testing Admin News Dashboard"
curl -sL -X GET "$API_BASE/admin/news-moderation" \
  -H "Authorization: $TOKEN" \
  -H "Accept: application/json" | head -5