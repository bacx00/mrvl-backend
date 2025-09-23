#!/bin/bash

echo "Testing Event Status System..."

# Get the first event ID
EVENT_ID=$(curl -s -H "Authorization: Bearer YOUR_TOKEN" "https://staging.mrvl.net/api/events" | jq -r '.data[0].id')

if [ "$EVENT_ID" = "null" ] || [ -z "$EVENT_ID" ]; then
    echo "❌ No events found for testing"
    exit 1
fi

echo "✅ Found event ID: $EVENT_ID"

# Test updating event status to live/featured
echo "🔄 Testing event status update to live/featured..."
curl -X PUT "https://staging.mrvl.net/api/admin/events/${EVENT_ID}/status-enhanced" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -d '{
         "status": "ongoing",
         "featured": true
     }' | jq '.'

echo "🔄 Checking if event is now featured and live..."
curl -s "https://staging.mrvl.net/api/events" | jq '.data[] | select(.featured == true)'

echo "✅ Event status system test complete!"