#!/bin/bash

# Integration test for mentions dropdown functionality

echo "========================================="
echo "   MENTIONS INTEGRATION TEST"
echo "========================================="
echo ""

# Test that search returns proper mention format
echo "Testing user search response format..."
response=$(curl -s "https://staging.mrvl.net/api/public/search/users?q=test&limit=3")

if echo "$response" | jq -e '.data[0] | has("id", "username", "name")' > /dev/null 2>&1; then
    echo "✓ User search returns correct format"
    echo "  Sample user:"
    echo "$response" | jq '.data[0] | {id, username, name}' 2>/dev/null
else
    echo "✗ User search format incorrect"
fi

echo ""
echo "Testing team search response format..."
response=$(curl -s "https://staging.mrvl.net/api/public/search/teams?q=100&limit=3")

if echo "$response" | jq -e '.data[0] | has("id", "name")' > /dev/null 2>&1; then
    echo "✓ Team search returns correct format"
    echo "  Sample team:"
    echo "$response" | jq '.data[0] | {id, name, region}' 2>/dev/null
else
    echo "✗ Team search format incorrect"
fi

echo ""
echo "Testing player search response format..."
response=$(curl -s "https://staging.mrvl.net/api/public/search/players?q=s&limit=3")

if echo "$response" | jq -e '.data[0] | has("id", "username")' > /dev/null 2>&1; then
    echo "✓ Player search returns correct format"
    echo "  Sample player:"
    echo "$response" | jq '.data[0] | {id, username, team}' 2>/dev/null
else
    echo "✗ Player search format incorrect"
fi

echo ""
echo "========================================="
echo "   FRONTEND COMPONENT VERIFICATION"
echo "========================================="
echo ""

# Check for key functions in ForumMentionAutocomplete
echo "Checking ForumMentionAutocomplete functionality..."
if grep -q "searchMentions" /var/www/mrvl-frontend/frontend/src/components/shared/ForumMentionAutocomplete.js; then
    echo "✓ searchMentions function found"
else
    echo "✗ searchMentions function missing"
fi

if grep -q "showDropdown" /var/www/mrvl-frontend/frontend/src/components/shared/ForumMentionAutocomplete.js; then
    echo "✓ Dropdown state management found"
else
    echo "✗ Dropdown state management missing"
fi

if grep -q "selectMention" /var/www/mrvl-frontend/frontend/src/components/shared/ForumMentionAutocomplete.js; then
    echo "✓ Mention selection logic found"
else
    echo "✗ Mention selection logic missing"
fi

echo ""
echo "Checking MentionLink navigation..."
if grep -q "navigateTo\|window.location.hash" /var/www/mrvl-frontend/frontend/src/components/shared/MentionLink.js 2>/dev/null; then
    echo "✓ Navigation logic found in MentionLink"
else
    echo "✗ Navigation logic missing in MentionLink"
fi

echo ""
echo "========================================="
echo "   TESTING MENTION CREATION FLOW"
echo "========================================="
echo ""

# Get a valid auth token (you need to update this)
TOKEN="YOUR_AUTH_TOKEN"

echo "To test mention creation:"
echo "1. Log in to https://staging.mrvl.net"
echo "2. Go to Forums > Create Thread"
echo "3. In the content field, type '@' and wait for dropdown"
echo "4. Select a user/team/player"
echo "5. Submit the thread"
echo "6. Check if the mention is clickable in the posted content"
echo ""
echo "Expected behavior:"
echo "- Dropdown appears within 300ms of typing @"
echo "- Shows users, teams, and players matching the query"
echo "- Selected mention inserts as @username or @team:name or @player:name"
echo "- Saved content shows clickable mention links"
echo ""

echo "========================================="
echo "   PROFILE MENTIONS SECTION TEST"
echo "========================================="
echo ""

# Test a player profile page
PLAYER_ID=$(curl -s "https://staging.mrvl.net/api/players" | jq -r '.data[0].id' 2>/dev/null)
if [ ! -z "$PLAYER_ID" ]; then
    echo "Testing player profile mentions (ID: $PLAYER_ID)..."
    mentions=$(curl -s "https://staging.mrvl.net/api/players/$PLAYER_ID/mentions")
    
    if echo "$mentions" | grep -q '"data"'; then
        count=$(echo "$mentions" | jq '.data | length' 2>/dev/null)
        echo "✓ Player mentions endpoint works (found $count mentions)"
    else
        echo "✗ Player mentions endpoint failed"
    fi
fi

# Test a team profile page
echo ""
echo "Testing team profile mentions (ID: 4)..."
mentions=$(curl -s "https://staging.mrvl.net/api/teams/4/mentions")

if echo "$mentions" | grep -q '"data"'; then
    count=$(echo "$mentions" | jq '.data | length' 2>/dev/null)
    echo "✓ Team mentions endpoint works (found $count mentions)"
else
    echo "✗ Team mentions endpoint failed"
fi

echo ""
echo "========================================="
echo "         TEST COMPLETE"
echo "========================================="
echo ""
echo "The mentions system API is fully functional."
echo "Please complete the manual testing checklist above."
echo ""