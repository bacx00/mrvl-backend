#!/bin/bash

echo "=== Testing Admin API Endpoints for ELO, Earnings, Social Media ==="
echo

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

# Test 1: Update Player
echo "1. Testing Player UPDATE via Admin API:"
RESPONSE=$(curl -s -X PUT http://localhost/api/admin/players/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "rating": 5600,
    "peak_rating": 5600,
    "earnings": 350000,
    "social_media": {
      "twitter": "https://twitter.com/admin_updated",
      "twitch": "https://twitch.tv/admin_updated"
    }
  }')

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PUT http://localhost/api/admin/players/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"rating": 5600}')

echo "   HTTP Status: $HTTP_CODE"
if [[ $HTTP_CODE == "200" ]]; then
  echo "   ✓ Player update successful"
else
  echo "   ✗ Player update failed"
  echo "   Response: $RESPONSE"
fi
echo

# Test 2: Update Team
echo "2. Testing Team UPDATE via Admin API:"
RESPONSE=$(curl -s -X PUT http://localhost/api/admin/teams/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "100 Thieves",
    "short_name": "100T",
    "region": "NA",
    "rating": 2600,
    "earnings": 550000,
    "social_media": {
      "twitter": "https://twitter.com/100thieves_admin",
      "youtube": "https://youtube.com/@100thieves_admin"
    }
  }')

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PUT http://localhost/api/admin/teams/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"100 Thieves","short_name":"100T","region":"NA","rating":2600}')

echo "   HTTP Status: $HTTP_CODE"
if [[ $HTTP_CODE == "200" ]]; then
  echo "   ✓ Team update successful"
else
  echo "   ✗ Team update failed"
  echo "   Response: $RESPONSE"
fi
echo

# Test 3: Public Rankings
echo "3. Testing Public Rankings Endpoints:"

# Player rankings
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/public/rankings/players -H "Accept: application/json")
echo "   Player Rankings: HTTP $HTTP_CODE"

# Team rankings
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/public/rankings/teams -H "Accept: application/json")
echo "   Team Rankings: HTTP $HTTP_CODE"

# Team rankings by region
for region in na eu asia oce; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/public/rankings/teams?region=$region" -H "Accept: application/json")
  echo "   Team Rankings ($region): HTTP $HTTP_CODE"
done

echo
echo "=== Admin API Testing Complete ==="