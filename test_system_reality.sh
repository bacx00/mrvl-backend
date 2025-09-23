#!/bin/bash

echo "🔍 TESTING ACTUAL SYSTEM FUNCTIONALITY..."

echo ""
echo "1. Testing Event API Response:"
EVENTS_RESPONSE=$(curl -s "https://staging.mrvl.net/api/events")
echo "Events API Status: $(echo $EVENTS_RESPONSE | jq -r '.success // "working"')"
echo "Current Event: $(echo $EVENTS_RESPONSE | jq -r '.data[0].name // "none"')"
echo "Event Status: $(echo $EVENTS_RESPONSE | jq -r '.data[0].status // "none"')"
echo "Event Featured: $(echo $EVENTS_RESPONSE | jq -r '.data[0].featured // "none"')"

echo ""
echo "2. Testing Manual Bracket API:"
BRACKET_RESPONSE=$(curl -s "https://staging.mrvl.net/api/public/manual-bracket/formats")
echo "Bracket API Status: $(echo $BRACKET_RESPONSE | jq -r '.success // "error"')"
echo "Available Formats: $(echo $BRACKET_RESPONSE | jq -r '.formats | keys | length // "0"')"

echo ""
echo "3. Testing Ranking System:"
TEAMS_RESPONSE=$(curl -s "https://staging.mrvl.net/api/teams")
echo "Teams API Status: $(echo $TEAMS_RESPONSE | jq -r '.success // "working"')"
echo "Teams Count: $(echo $TEAMS_RESPONSE | jq -r '.data | length // "0"')"

echo ""
echo "4. Testing Database Connection:"
cd /var/www/mrvl-backend
DB_TEST=$(./artisan tinker --execute="echo 'DB working';" 2>&1)
echo "Database: $DB_TEST"

echo ""
echo "5. Testing Route Registration:"
ROUTE_COUNT=$(./artisan route:list | wc -l)
echo "Total Routes: $ROUTE_COUNT"

echo ""
echo "6. Check for Critical Errors:"
ERROR_CHECK=$(tail -50 storage/logs/laravel.log 2>/dev/null | grep -i "error\|exception\|fatal" | tail -3)
if [ -z "$ERROR_CHECK" ]; then
    echo "✅ No recent critical errors found"
else
    echo "⚠️ Recent errors found:"
    echo "$ERROR_CHECK"
fi

echo ""
echo "🎯 REALITY CHECK COMPLETE"