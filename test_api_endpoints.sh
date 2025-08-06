#!/bin/bash

echo "=== Testing API Endpoints for ELO, Earnings, Social Media, Rankings ==="
echo

# Create auth token
TOKEN=$(php artisan tinker --execute="
\$user = \App\Models\User::firstOrCreate(
    ['email' => 'test@admin.com'],
    ['name' => 'Test Admin', 'password' => bcrypt('password'), 'role' => 'admin']
);
echo \$user->createToken('test-token')->plainTextToken;
" 2>/dev/null | tail -1)

echo "Auth token generated: ${TOKEN:0:20}..."
echo

# Test 1: Get Player
echo "1. Testing Player GET:"
curl -s -X GET http://localhost/api/players/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | jq -r '.data | {id, username, rating, earnings}'
echo

# Test 2: Update Player
echo "2. Testing Player UPDATE (ELO, Earnings, Social):"
curl -s -X PUT http://localhost/api/players/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "rating": 5500,
    "peak_rating": 5500,
    "earnings": 300000,
    "social_media": {
      "twitter": "https://twitter.com/updated_player",
      "twitch": "https://twitch.tv/updated_player"
    }
  }' | jq -r '.data | {username, rating, earnings}'

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PUT http://localhost/api/players/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"rating": 5500}')
echo "   HTTP Status: $HTTP_CODE"
echo

# Test 3: Get Team
echo "3. Testing Team GET:"
curl -s -X GET http://localhost/api/teams/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | jq -r '.data | {id, name, rating, earnings}'
echo

# Test 4: Update Team
echo "4. Testing Team UPDATE (Rating, Earnings, Social):"
curl -s -X PUT http://localhost/api/teams/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "100 Thieves",
    "short_name": "100T",
    "region": "NA",
    "rating": 2500,
    "earnings": 500000,
    "social_media": {
      "twitter": "https://twitter.com/100thieves_updated",
      "youtube": "https://youtube.com/@100thieves_updated"
    }
  }' | jq -r '.data | {name, rating, earnings}' 2>/dev/null || echo "   Update response received"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PUT http://localhost/api/teams/1 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"100 Thieves","short_name":"100T","region":"NA","rating":2500}')
echo "   HTTP Status: $HTTP_CODE"
echo

# Test 5: Player Rankings
echo "5. Testing Player Rankings:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/rankings -H "Accept: application/json")
echo "   Player Rankings HTTP Status: $HTTP_CODE"
curl -s http://localhost/api/rankings -H "Accept: application/json" | jq -r '.data[:3] | .[] | {rank: .rank, username, rating, earnings}' 2>/dev/null || echo "   Rankings data retrieved"
echo

# Test 6: Team Rankings  
echo "6. Testing Team Rankings:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/rankings/teams -H "Accept: application/json")
echo "   Team Rankings HTTP Status: $HTTP_CODE"
curl -s http://localhost/api/rankings/teams -H "Accept: application/json" | jq -r '.data[:3] | .[] | {rank, name, rating, earnings}' 2>/dev/null || echo "   Team rankings data retrieved"
echo

# Test 7: Team Rankings by Region
echo "7. Testing Team Rankings by Region:"
for region in na eu asia; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/rankings/teams?region=$region" -H "Accept: application/json")
  echo "   Region $region: HTTP $HTTP_CODE"
done

echo
echo "=== API Testing Complete ===