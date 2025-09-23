#!/bin/bash

echo "🎮 Testing Live Events System..."

# Test getting live events
echo "📡 Testing live events API..."
curl -s "https://staging.mrvl.net/api/admin/events/live/all" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.YOUR_TOKEN" | jq '.'

echo ""
echo "📊 Testing public events API (should show featured=true)..."
curl -s "https://staging.mrvl.net/api/events" | jq '.data[] | {id: .id, name: .name, status: .status, featured: .featured}'

echo ""
echo "✅ Live events system test complete!"