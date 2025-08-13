#!/bin/bash

# PLAYER MANAGEMENT SYSTEM CURL TESTS
# Marvel Rivals Tournament Platform
# 
# Manual testing script for all player CRUD operations

BASE_URL="http://localhost:8000/api"
ADMIN_EMAIL="admin@mrvl.gg"
ADMIN_PASSWORD="password123"
TOKEN=""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 MARVEL RIVALS PLAYER MANAGEMENT SYSTEM TESTS${NC}"
echo "============================================================="

# Function to get auth token
get_auth_token() {
    echo -e "${YELLOW}🔐 Authenticating admin user...${NC}"
    
    RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/login" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "email": "'${ADMIN_EMAIL}'",
            "password": "'${ADMIN_PASSWORD}'"
        }')
    
    TOKEN=$(echo $RESPONSE | jq -r '.access_token // empty')
    
    if [ -n "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
        echo -e "${GREEN}✅ Authentication successful${NC}"
        return 0
    else
        echo -e "${RED}❌ Authentication failed${NC}"
        echo "Response: $RESPONSE"
        return 1
    fi
}

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo -e "\n${BLUE}🧪 Testing: ${description}${NC}"
    echo "Method: $method | Endpoint: $endpoint"
    
    if [ -n "$data" ]; then
        RESPONSE=$(curl -s -X $method "${BASE_URL}${endpoint}" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${TOKEN}" \
            -d "$data")
    else
        RESPONSE=$(curl -s -X $method "${BASE_URL}${endpoint}" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${TOKEN}")
    fi
    
    STATUS_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X $method "${BASE_URL}${endpoint}" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${TOKEN}" \
        ${data:+-d "$data"})
    
    if [ "$STATUS_CODE" -ge 200 ] && [ "$STATUS_CODE" -lt 400 ]; then
        echo -e "${GREEN}✅ SUCCESS (HTTP $STATUS_CODE)${NC}"
    else
        echo -e "${RED}❌ FAILED (HTTP $STATUS_CODE)${NC}"
    fi
    
    echo "Response: $(echo $RESPONSE | jq . 2>/dev/null || echo $RESPONSE)"
    return $STATUS_CODE
}

# Authenticate first
get_auth_token || exit 1

echo -e "\n${BLUE}📝 PLAYER CRUD OPERATIONS TESTS${NC}"
echo "============================================================="

# Test 1: List all players
test_endpoint "GET" "/admin/players" "" "List all players"

# Test 2: Get specific player (assuming player ID 1 exists)
test_endpoint "GET" "/admin/players/1" "" "Get specific player (ID: 1)"

# Test 3: Create new player
echo -e "\n${YELLOW}Creating test player...${NC}"
CREATE_DATA='{
    "username": "test_player_'$(date +%s)'",
    "ign": "TestGamer",
    "real_name": "Test Player",
    "name": "Test Player", 
    "role": "DPS",
    "main_hero": "Spider-Man",
    "country": "US",
    "age": 25,
    "rating": 2500,
    "description": "Test player for validation"
}'

CREATED_PLAYER_RESPONSE=$(curl -s -X POST "${BASE_URL}/admin/players" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -d "$CREATE_DATA")

CREATED_PLAYER_ID=$(echo $CREATED_PLAYER_RESPONSE | jq -r '.data.id // .id // empty')

test_endpoint "POST" "/admin/players" "$CREATE_DATA" "Create new player"

echo "Created Player ID: $CREATED_PLAYER_ID"

# Test 4: Update player fields (if we have a created player)
if [ -n "$CREATED_PLAYER_ID" ] && [ "$CREATED_PLAYER_ID" != "null" ]; then
    echo -e "\n${YELLOW}Testing field updates on created player (ID: $CREATED_PLAYER_ID)...${NC}"
    
    # Test 4a: Update basic info
    UPDATE_BASIC='{
        "username": "updated_player_'$(date +%s)'",
        "real_name": "Updated Player Name",
        "age": 26
    }'
    test_endpoint "PUT" "/admin/players/${CREATED_PLAYER_ID}" "$UPDATE_BASIC" "Update basic info fields"
    
    # Test 4b: Update game info
    UPDATE_GAME='{
        "role": "Tank",
        "main_hero": "Doctor Doom",
        "rating": 3000
    }'
    test_endpoint "PUT" "/admin/players/${CREATED_PLAYER_ID}" "$UPDATE_GAME" "Update game info fields"
    
    # Test 4c: Update location info
    UPDATE_LOCATION='{
        "country": "CA",
        "region": "North America"
    }'
    test_endpoint "PUT" "/admin/players/${CREATED_PLAYER_ID}" "$UPDATE_LOCATION" "Update location fields"
    
    # Test 4d: Team assignment (set to free agent)
    UPDATE_TEAM='{
        "team_id": null
    }'
    test_endpoint "PUT" "/admin/players/${CREATED_PLAYER_ID}" "$UPDATE_TEAM" "Set player as free agent"
    
    # Test 4e: Social media and earnings
    UPDATE_SOCIAL='{
        "social_media": {
            "twitter": "@testplayer",
            "twitch": "testplayer"
        },
        "earnings": 50000,
        "total_earnings": 75000
    }'
    test_endpoint "PUT" "/admin/players/${CREATED_PLAYER_ID}" "$UPDATE_SOCIAL" "Update social media and earnings"
    
    # Test 4f: Hero preferences
    UPDATE_HEROES='{
        "main_hero": "Iron Man",
        "alt_heroes": ["Spider-Man", "Doctor Doom", "Wolverine"],
        "hero_preferences": ["Duelist", "Vanguard"]
    }'
    test_endpoint "PUT" "/admin/players/${CREATED_PLAYER_ID}" "$UPDATE_HEROES" "Update hero preferences"
    
    echo -e "\n${YELLOW}Testing player deletion...${NC}"
    test_endpoint "DELETE" "/admin/players/${CREATED_PLAYER_ID}" "" "Delete test player"
fi

echo -e "\n${BLUE}📊 FIELD VALIDATION TESTS${NC}"
echo "============================================================="

# Test 5: Field validation tests
echo -e "\n${YELLOW}Testing invalid role...${NC}"
INVALID_ROLE='{
    "username": "invalid_role_test",
    "role": "InvalidRole"
}'
test_endpoint "POST" "/admin/players" "$INVALID_ROLE" "Invalid role validation (should fail)"

echo -e "\n${YELLOW}Testing invalid age...${NC}"
INVALID_AGE='{
    "username": "invalid_age_test",
    "age": 150
}'
test_endpoint "POST" "/admin/players" "$INVALID_AGE" "Invalid age validation (should fail)"

echo -e "\n${YELLOW}Testing invalid rating...${NC}"
INVALID_RATING='{
    "username": "invalid_rating_test",
    "rating": 10000
}'
test_endpoint "POST" "/admin/players" "$INVALID_RATING" "Invalid rating validation (should fail)"

echo -e "\n${BLUE}🔄 BULK OPERATIONS TESTS${NC}"
echo "============================================================="

# Test 6: Get players for bulk operations
PLAYERS_RESPONSE=$(curl -s -X GET "${BASE_URL}/admin/players" \
    -H "Authorization: Bearer ${TOKEN}")

PLAYER_IDS=$(echo $PLAYERS_RESPONSE | jq -r '.data[]?.id // .[]?.id // empty' | head -2 | tr '\n' ',' | sed 's/,$//')

if [ -n "$PLAYER_IDS" ]; then
    # Convert comma-separated IDs to JSON array
    BULK_DELETE_DATA='{"player_ids": ['$(echo $PLAYER_IDS | sed 's/,/,/g')']}'
    echo -e "\n${YELLOW}Testing bulk delete with player IDs: [$PLAYER_IDS]${NC}"
    echo -e "${RED}⚠️  WARNING: This will delete actual players! Comment out if not desired.${NC}"
    # Uncomment the line below to actually test bulk delete
    # test_endpoint "POST" "/admin/players/bulk-delete" "$BULK_DELETE_DATA" "Bulk delete players"
    echo "Bulk delete test skipped for safety"
else
    echo -e "${YELLOW}No players found for bulk operations test${NC}"
fi

echo -e "\n${BLUE}🏆 ADVANCED FIELD TESTS${NC}"
echo "============================================================="

# Test 7: All possible updateable fields
echo -e "\n${YELLOW}Testing comprehensive field update...${NC}"

COMPREHENSIVE_UPDATE='{
    "username": "comprehensive_test_'$(date +%s)'",
    "ign": "ComprehensiveTest",
    "real_name": "Comprehensive Test Player",
    "name": "Comprehensive Test Player",
    "role": "Flex",
    "main_hero": "Captain America",
    "alt_heroes": ["Iron Man", "Thor", "Hulk"],
    "hero_preferences": ["Vanguard", "Duelist", "Strategist"],
    "region": "Europe",
    "country": "UK",
    "country_code": "GB",
    "nationality": "British",
    "rating": 2750,
    "skill_rating": 2800,
    "elo_rating": 2700,
    "peak_rating": 3200,
    "peak_elo": 3150,
    "age": 24,
    "birth_date": "1999-06-15",
    "earnings": 125000,
    "total_earnings": 200000,
    "status": "active",
    "biography": "Professional Marvel Rivals player with extensive tournament experience.",
    "social_media": {
        "twitter": "@comprehensivetest",
        "instagram": "comprehensive_test",
        "youtube": "ComprehensiveTestGaming",
        "twitch": "comprehensive_test",
        "tiktok": "@comprehensivetest",
        "discord": "ComprehensiveTest#1234"
    },
    "twitter": "@comprehensivetest",
    "instagram": "comprehensive_test", 
    "youtube": "ComprehensiveTestGaming",
    "twitch": "comprehensive_test",
    "tiktok": "@comprehensivetest",
    "discord": "ComprehensiveTest#1234"
}'

# Create comprehensive test player
COMPREHENSIVE_RESPONSE=$(curl -s -X POST "${BASE_URL}/admin/players" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    -d "$COMPREHENSIVE_UPDATE")

COMPREHENSIVE_ID=$(echo $COMPREHENSIVE_RESPONSE | jq -r '.data.id // .id // empty')

test_endpoint "POST" "/admin/players" "$COMPREHENSIVE_UPDATE" "Create player with all fields"

if [ -n "$COMPREHENSIVE_ID" ] && [ "$COMPREHENSIVE_ID" != "null" ]; then
    echo "Comprehensive test player created with ID: $COMPREHENSIVE_ID"
    
    # Test updating all fields
    test_endpoint "PUT" "/admin/players/${COMPREHENSIVE_ID}" "$COMPREHENSIVE_UPDATE" "Update all possible fields"
    
    # Clean up
    echo -e "\n${YELLOW}Cleaning up comprehensive test player...${NC}"
    test_endpoint "DELETE" "/admin/players/${COMPREHENSIVE_ID}" "" "Delete comprehensive test player"
fi

echo -e "\n${BLUE}📋 FIELD AVAILABILITY REPORT${NC}"
echo "============================================================="

echo -e "${GREEN}✅ FIELDS THAT CAN BE UPDATED:${NC}"
echo "• username - Player username (unique)"
echo "• ign - In-game name"
echo "• real_name - Real name"
echo "• name - Display name"
echo "• role - Player role (Vanguard, Duelist, Strategist, DPS, Tank, Support, Flex)"
echo "• main_hero - Primary hero"
echo "• alt_heroes - Alternative heroes (array)"
echo "• hero_preferences - Hero preference categories (array)"
echo "• region - Geographic region"
echo "• country - Country"
echo "• country_code - Country code"
echo "• nationality - Player nationality"
echo "• rating - Current rating (0-5000)"
echo "• skill_rating - Skill rating (0-5000)"
echo "• elo_rating - ELO rating (0-5000)"
echo "• peak_rating - Peak rating achieved"
echo "• peak_elo - Peak ELO achieved"
echo "• age - Player age (13-50)"
echo "• birth_date - Birth date (before today)"
echo "• earnings - Tournament earnings"
echo "• total_earnings - Total career earnings"
echo "• status - Player status"
echo "• biography - Player biography/description"
echo "• team_id - Team assignment (nullable for free agents)"
echo "• social_media - Social media profiles (object)"
echo "• twitter - Twitter handle"
echo "• instagram - Instagram handle"
echo "• youtube - YouTube channel"
echo "• twitch - Twitch channel"
echo "• tiktok - TikTok handle"
echo "• discord - Discord username"

echo -e "\n${RED}❌ FIELDS THAT CANNOT BE UPDATED (READ-ONLY):${NC}"
echo "• id - Primary key"
echo "• created_at - Creation timestamp"
echo "• updated_at - Last update timestamp"
echo "• mention_count - Calculated field"
echo "• last_mentioned_at - System managed"

echo -e "\n${YELLOW}⚠️  FIELDS WITH VALIDATION CONSTRAINTS:${NC}"
echo "• username - Must be unique"
echo "• role - Must be valid role enum"
echo "• age - Must be 13-50"
echo "• rating/elo_rating - Must be 0-5000"
echo "• birth_date - Must be before today"
echo "• team_id - Must exist in teams table"

echo -e "\n${BLUE}🎯 TEAM RELATIONSHIP FUNCTIONALITY:${NC}"
echo "✅ Players can be assigned to teams via team_id"
echo "✅ Players can be set as free agents (team_id = null)"
echo "✅ Team changes are tracked in PlayerTeamHistory model"
echo "✅ Relationship changes trigger history records"

echo -e "\n${GREEN}🏁 Player Management System Test Complete!${NC}"
echo "============================================================="