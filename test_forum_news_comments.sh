#!/bin/bash

# Test Forum and News Comment APIs
API_BASE="http://staging.mrvl.net/api"
TOKEN="Bearer 33|mBJJvE5nvFU5cXXvl1PQrTYl9qHW5dEBt4Av2xRD"

echo "=== Testing Forum Post Creation ==="
curl -X POST "$API_BASE/user/forums/threads/12/posts" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "content": "Test post with @team:SEN mention",
    "parent_id": null
  }' 2>/dev/null | jq '.'

echo -e "\n=== Testing News Comment Creation ==="
curl -X POST "$API_BASE/public/news/12/comments" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "content": "Test comment with @team:SEN mention",
    "parent_id": null
  }' 2>/dev/null | jq '.'

echo -e "\n=== Testing Forum Post Vote ==="
curl -X POST "$API_BASE/user/forums/posts/1/vote" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "vote": 1
  }' 2>/dev/null | jq '.'

echo -e "\n=== Testing News Comment Vote ==="
curl -X POST "$API_BASE/public/news/comments/1/vote" \
  -H "Authorization: $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "vote": 1
  }' 2>/dev/null | jq '.'

echo -e "\n=== Testing Get Forum Posts ==="
curl -X GET "$API_BASE/public/forums/threads/12/posts" \
  -H "Authorization: $TOKEN" \
  -H "Accept: application/json" 2>/dev/null | jq '.success'

echo -e "\n=== Testing Get News Comments ==="
curl -X GET "$API_BASE/news/12/comments" \
  -H "Authorization: $TOKEN" \
  -H "Accept: application/json" 2>/dev/null | jq '.success'