#!/bin/bash

echo "=== Testing HTTPS API Endpoints for ELO, Earnings, Social Media, Rankings ==="
echo

# API Base URL
API_URL="https://1039tfjgievqa983.mrvl.net"

# Create admin token
TOKEN=$(php artisan tinker --execute="
\$user = \App\Models\User::firstOrCreate(
    ['email' => 'admin@test.com'],
    ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']
);
echo \$user->createToken('admin-token')->plainTextToken;
" 2>/dev/null | tail -1)

echo "Admin token generated"
echo

# Test 1: Update Player (ELO, Earnings, Social Media)
echo "1. Testing Player UPDATE via Admin API:"
RESPONSE=$(curl -s -X PUT "$API_URL/api/admin/players/1" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "rating": 5700,
    "peak_rating": 5700,
    "earnings": 400000,
    "social_media": {
      "twitter": "https://twitter.com/player_api_test",
      "twitch": "https://twitch.tv/player_api_test",
      "youtube": "https://youtube.com/@player_api_test"
    },
    "twitter": "https://twitter.com/player_direct",
    "instagram": "https://instagram.com/player_direct"
  }')

HTTP_CODE=$(echo "$RESPONSE" | grep -o '"status":[0-9]*' | sed 's/"status"://' | head -1)
if [[ -z "$HTTP_CODE" ]]; then
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PUT "$API_URL/api/admin/players/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"rating": 5700}')
fi

echo "   HTTP Status: $HTTP_CODE"
if [[ $HTTP_CODE == "200" ]]; then
  echo "   ✓ Player update successful"
  echo "$RESPONSE" | jq -r '.data | {username, rating, earnings}' 2>/dev/null || echo "   Response received"
else
  echo "   ✗ Player update failed"
  echo "   Response: ${RESPONSE:0:200}..."
fi
echo

# Test 2: Update Team (Rating, Earnings, Social Media)
echo "2. Testing Team UPDATE via Admin API:"
RESPONSE=$(curl -s -X PUT "$API_URL/api/admin/teams/1" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "100 Thieves",
    "short_name": "100T",
    "region": "NA",
    "rating": 2700,
    "peak": 2700,
    "earnings": 600000,
    "social_media": {
      "twitter": "https://twitter.com/100thieves_test",
      "youtube": "https://youtube.com/@100thieves_test",
      "discord": "https://discord.gg/100thieves"
    },
    "twitter": "https://twitter.com/100t_direct",
    "instagram": "https://instagram.com/100t_direct"
  }')

HTTP_CODE=$(echo "$RESPONSE" | grep -o '"status":[0-9]*' | sed 's/"status"://' | head -1)
if [[ -z "$HTTP_CODE" ]]; then
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PUT "$API_URL/api/admin/teams/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"name":"100 Thieves","short_name":"100T","region":"NA","rating":2700}')
fi

echo "   HTTP Status: $HTTP_CODE"
if [[ $HTTP_CODE == "200" ]]; then
  echo "   ✓ Team update successful"
  echo "$RESPONSE" | jq -r '.data | {name, rating, earnings}' 2>/dev/null || echo "   Response received"
else
  echo "   ✗ Team update failed"
  echo "   Response: ${RESPONSE:0:200}..."
fi
echo

# Test 3: Get Updated Player
echo "3. Verifying Player Updates:"
RESPONSE=$(curl -s -X GET "$API_URL/api/admin/players/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN")

if echo "$RESPONSE" | jq . >/dev/null 2>&1; then
  echo "$RESPONSE" | jq -r '.data | "   Username: \(.username)\n   Rating: \(.rating)\n   Earnings: \(.earnings)\n   Twitter: \(.twitter // .social_media.twitter // "N/A")"' 2>/dev/null
else
  echo "   Could not retrieve player data"
fi
echo

# Test 4: Get Updated Team
echo "4. Verifying Team Updates:"
RESPONSE=$(curl -s -X GET "$API_URL/api/admin/teams/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN")

if echo "$RESPONSE" | jq . >/dev/null 2>&1; then
  echo "$RESPONSE" | jq -r '.data | "   Name: \(.name)\n   Rating: \(.rating)\n   Earnings: \(.earnings)\n   Twitter: \(.twitter // .social_media.twitter // "N/A")"' 2>/dev/null
else
  echo "   Could not retrieve team data"
fi
echo

# Test 5: Public Rankings
echo "5. Testing Public Rankings Endpoints:"

# Player rankings
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api/public/rankings/players" -H "Accept: application/json")
echo "   Player Rankings: HTTP $HTTP_CODE"

# Team rankings
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api/public/rankings/teams" -H "Accept: application/json")
echo "   Team Rankings: HTTP $HTTP_CODE"

# Team rankings by region
for region in na eu asia oce; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_URL/api/public/rankings/teams?region=$region" -H "Accept: application/json")
  echo "   Team Rankings ($region): HTTP $HTTP_CODE"
done
echo

# Test 6: Verify Rankings Data
echo "6. Verifying Rankings Data:"

# Top 3 players
echo "   Top 3 Players:"
curl -s "$API_URL/api/public/rankings/players" -H "Accept: application/json" | \
  jq -r '.data[:3] | .[] | "     \(.rank). \(.username) - Rating: \(.rating), Earnings: $\(.earnings)"' 2>/dev/null || echo "     Could not retrieve player rankings"

echo
echo "   Top 3 Teams:"
curl -s "$API_URL/api/public/rankings/teams" -H "Accept: application/json" | \
  jq -r '.data[:3] | .[] | "     \(.rank). \(.name) - Rating: \(.rating), Earnings: $\(.earnings)"' 2>/dev/null || echo "     Could not retrieve team rankings"

echo
echo "=== HTTPS API Testing Complete ==="