#!/bin/bash

# Marvel Rivals Test Match Creation via CURL
# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# API Configuration
API_URL="${API_URL:-https://staging.mrvl.net/api}"
echo -e "${BLUE}🎮 Marvel Rivals Test Match System${NC}"
echo -e "${BLUE}API URL: $API_URL${NC}"
echo ""

# Function to pretty print JSON
pretty_json() {
    if command -v jq &> /dev/null; then
        echo "$1" | jq '.'
    else
        echo "$1"
    fi
}

# 1. Test API connectivity
echo -e "${YELLOW}1. Testing API connectivity...${NC}"
HEALTH_CHECK=$(curl -s -X GET "$API_URL/health" -H "Accept: application/json")
echo -e "${GREEN}Response:${NC}"
pretty_json "$HEALTH_CHECK"
echo ""

# 2. Create test matches
echo -e "${YELLOW}2. Creating test matches (all formats: BO1, BO3, BO5, BO7, BO9)...${NC}"
CREATE_RESPONSE=$(curl -s -X POST "$API_URL/test/matches" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"clean": true}')

echo -e "${GREEN}Response:${NC}"
pretty_json "$CREATE_RESPONSE"
echo ""

# Extract match IDs if jq is available
if command -v jq &> /dev/null; then
    MATCH_IDS=$(echo "$CREATE_RESPONSE" | jq -r '.matches[]?.id' 2>/dev/null)
    LIVE_MATCH_ID=$(echo "$CREATE_RESPONSE" | jq -r '.live_matches[0]' 2>/dev/null)
    
    if [ ! -z "$LIVE_MATCH_ID" ] && [ "$LIVE_MATCH_ID" != "null" ]; then
        echo -e "${GREEN}✅ Live match created with ID: $LIVE_MATCH_ID${NC}"
        echo ""
        
        # 3. Get live match data
        echo -e "${YELLOW}3. Getting live match data...${NC}"
        MATCH_DATA=$(curl -s -X GET "$API_URL/test/matches/$LIVE_MATCH_ID/data" \
          -H "Accept: application/json")
        echo -e "${GREEN}Response:${NC}"
        pretty_json "$MATCH_DATA"
        echo ""
        
        # 4. Simulate score update
        echo -e "${YELLOW}4. Simulating score update...${NC}"
        SCORE_UPDATE=$(curl -s -X POST "$API_URL/test/matches/$LIVE_MATCH_ID/simulate" \
          -H "Content-Type: application/json" \
          -H "Accept: application/json" \
          -d '{"type": "score"}')
        echo -e "${GREEN}Response:${NC}"
        pretty_json "$SCORE_UPDATE"
        echo ""
        
        # 5. Simulate hero swap
        echo -e "${YELLOW}5. Simulating hero swap...${NC}"
        HERO_SWAP=$(curl -s -X POST "$API_URL/test/matches/$LIVE_MATCH_ID/simulate" \
          -H "Content-Type: application/json" \
          -H "Accept: application/json" \
          -d '{"type": "hero_swap"}')
        echo -e "${GREEN}Response:${NC}"
        pretty_json "$HERO_SWAP"
        echo ""
        
        # 6. Simulate player stats update
        echo -e "${YELLOW}6. Simulating player stats update...${NC}"
        STATS_UPDATE=$(curl -s -X POST "$API_URL/test/matches/$LIVE_MATCH_ID/simulate" \
          -H "Content-Type: application/json" \
          -H "Accept: application/json" \
          -d '{"type": "player_stats"}')
        echo -e "${GREEN}Response:${NC}"
        pretty_json "$STATS_UPDATE"
        echo ""
        
        # 7. Get updated match data
        echo -e "${YELLOW}7. Getting updated match data...${NC}"
        UPDATED_DATA=$(curl -s -X GET "$API_URL/test/matches/$LIVE_MATCH_ID/data" \
          -H "Accept: application/json")
        echo -e "${GREEN}Response:${NC}"
        pretty_json "$UPDATED_DATA"
        echo ""
    fi
fi

# 8. Test match listing endpoint
echo -e "${YELLOW}8. Testing match listing endpoint...${NC}"
MATCHES_LIST=$(curl -s -X GET "$API_URL/matches?limit=5" \
  -H "Accept: application/json")
echo -e "${GREEN}Response:${NC}"
pretty_json "$MATCHES_LIST"
echo ""

# 9. Test live matches endpoint
echo -e "${YELLOW}9. Getting live matches...${NC}"
LIVE_MATCHES=$(curl -s -X GET "$API_URL/matches/live" \
  -H "Accept: application/json")
echo -e "${GREEN}Response:${NC}"
pretty_json "$LIVE_MATCHES"
echo ""

echo -e "${BLUE}✨ Test Summary:${NC}"
echo -e "- API URL: $API_URL"
echo -e "- Test matches created with all formats (BO1-BO9)"
echo -e "- Live scoring simulation tested"
echo -e "- Hero swap functionality tested"
echo -e "- Player stats tracking tested"
echo ""
echo -e "${GREEN}🚀 Next Steps:${NC}"
echo -e "1. Open the admin panel to view created matches"
echo -e "2. Access live scoring for any live match"
echo -e "3. Test real-time updates by opening multiple tabs"
echo -e "4. Use the match IDs above for further testing"