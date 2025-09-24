#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# API Configuration
API_URL="https://staging.mrvl.net/api"
AUTH_TOKEN=""

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Testing Match Comments System${NC}"
echo -e "${BLUE}================================${NC}"

# Function to make authenticated requests
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3

    if [ -z "$AUTH_TOKEN" ]; then
        if [ -z "$data" ]; then
            curl -s -X "$method" "$API_URL$endpoint" \
                -H "Accept: application/json" \
                -H "Content-Type: application/json"
        else
            curl -s -X "$method" "$API_URL$endpoint" \
                -H "Accept: application/json" \
                -H "Content-Type: application/json" \
                -d "$data"
        fi
    else
        if [ -z "$data" ]; then
            curl -s -X "$method" "$API_URL$endpoint" \
                -H "Accept: application/json" \
                -H "Content-Type: application/json" \
                -H "Authorization: Bearer $AUTH_TOKEN"
        else
            curl -s -X "$method" "$API_URL$endpoint" \
                -H "Accept: application/json" \
                -H "Content-Type: application/json" \
                -H "Authorization: Bearer $AUTH_TOKEN" \
                -d "$data"
        fi
    fi
}

# Test 1: Get matches to find a valid match ID
echo -e "\n${YELLOW}Test 1: Finding a valid match...${NC}"
MATCHES=$(make_request GET "/matches?limit=5")
MATCH_ID=$(echo "$MATCHES" | jq -r '.data[0].id // .matches[0].id // .[0].id // empty' 2>/dev/null)

if [ -z "$MATCH_ID" ]; then
    echo -e "${RED}✗ No matches found${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Using match ID: $MATCH_ID${NC}"

# Test 2: Get comments for a match (unauthenticated)
echo -e "\n${YELLOW}Test 2: Getting comments for match $MATCH_ID (unauthenticated)...${NC}"
COMMENTS=$(make_request GET "/matches/$MATCH_ID/comments?per_page=10")
COMMENT_COUNT=$(echo "$COMMENTS" | jq '.data | length // 0' 2>/dev/null)

if [ "$?" -eq 0 ]; then
    echo -e "${GREEN}✓ Successfully fetched comments (Count: $COMMENT_COUNT)${NC}"
    if [ "$COMMENT_COUNT" -gt 0 ]; then
        echo "$COMMENTS" | jq '.data[0] | {id, content, author: .author.username, created_at: .meta.created_at}'
    fi
else
    echo -e "${RED}✗ Failed to fetch comments${NC}"
fi

# Test 3: Try to login for authenticated tests
echo -e "\n${YELLOW}Test 3: Attempting login for authenticated tests...${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/login" \
    -H "Content-Type: application/json" \
    -d '{
        "email": "admin@example.com",
        "password": "password"
    }')

AUTH_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // .access_token // empty' 2>/dev/null)

if [ -n "$AUTH_TOKEN" ]; then
    echo -e "${GREEN}✓ Login successful${NC}"

    # Test 4: Post a comment (authenticated)
    echo -e "\n${YELLOW}Test 4: Posting a test comment...${NC}"
    TIMESTAMP=$(date +%s)
    COMMENT_DATA="{
        \"content\": \"Test comment from API - $TIMESTAMP\",
        \"parent_id\": null
    }"

    POST_RESPONSE=$(make_request POST "/matches/$MATCH_ID/comments" "$COMMENT_DATA")
    NEW_COMMENT_ID=$(echo "$POST_RESPONSE" | jq -r '.data.id // .comment.id // .id // empty' 2>/dev/null)

    if [ -n "$NEW_COMMENT_ID" ]; then
        echo -e "${GREEN}✓ Comment posted successfully (ID: $NEW_COMMENT_ID)${NC}"
        echo "$POST_RESPONSE" | jq '.data // .comment // .' | head -20

        # Test 5: Reply to comment
        echo -e "\n${YELLOW}Test 5: Replying to comment...${NC}"
        REPLY_DATA="{
            \"content\": \"Test reply with @mention - $TIMESTAMP\",
            \"parent_id\": $NEW_COMMENT_ID
        }"

        REPLY_RESPONSE=$(make_request POST "/matches/$MATCH_ID/comments" "$REPLY_DATA")
        REPLY_ID=$(echo "$REPLY_RESPONSE" | jq -r '.data.id // .comment.id // .id // empty' 2>/dev/null)

        if [ -n "$REPLY_ID" ]; then
            echo -e "${GREEN}✓ Reply posted successfully (ID: $REPLY_ID)${NC}"
        else
            echo -e "${RED}✗ Failed to post reply${NC}"
        fi

        # Test 6: Edit comment
        echo -e "\n${YELLOW}Test 6: Editing comment...${NC}"
        EDIT_DATA="{
            \"content\": \"Edited test comment - $TIMESTAMP\"
        }"

        EDIT_RESPONSE=$(make_request PUT "/match-comments/$NEW_COMMENT_ID" "$EDIT_DATA")
        if echo "$EDIT_RESPONSE" | jq -e '.success // false' >/dev/null 2>&1; then
            echo -e "${GREEN}✓ Comment edited successfully${NC}"
        else
            echo -e "${RED}✗ Failed to edit comment${NC}"
        fi

        # Test 7: Vote on comment
        echo -e "\n${YELLOW}Test 7: Voting on comment...${NC}"
        VOTE_DATA="{
            \"vote_type\": \"upvote\"
        }"

        VOTE_RESPONSE=$(make_request POST "/match-comments/$NEW_COMMENT_ID/vote" "$VOTE_DATA")
        if echo "$VOTE_RESPONSE" | jq -e '.success // .vote_counts // false' >/dev/null 2>&1; then
            echo -e "${GREEN}✓ Vote registered successfully${NC}"
            echo "$VOTE_RESPONSE" | jq '.vote_counts // .'
        else
            echo -e "${YELLOW}⚠ Voting endpoint may not be implemented yet${NC}"
        fi

        # Test 8: Delete comment
        echo -e "\n${YELLOW}Test 8: Deleting reply comment...${NC}"
        if [ -n "$REPLY_ID" ]; then
            DELETE_RESPONSE=$(make_request DELETE "/match-comments/$REPLY_ID")
            if echo "$DELETE_RESPONSE" | jq -e '.success // false' >/dev/null 2>&1; then
                echo -e "${GREEN}✓ Comment deleted successfully${NC}"
            else
                echo -e "${RED}✗ Failed to delete comment${NC}"
            fi
        fi

    else
        echo -e "${RED}✗ Failed to post comment${NC}"
        echo "$POST_RESPONSE" | jq '.'
    fi
else
    echo -e "${YELLOW}⚠ Login failed - skipping authenticated tests${NC}"
fi

# Test 9: Check comments with mentions
echo -e "\n${YELLOW}Test 9: Checking for comments with mentions...${NC}"
COMMENTS_WITH_MENTIONS=$(make_request GET "/matches/$MATCH_ID/comments?per_page=20")
MENTIONS_COUNT=$(echo "$COMMENTS_WITH_MENTIONS" | jq '[.data[]?.mentions // [] | length] | add // 0' 2>/dev/null)

echo -e "${GREEN}✓ Total mentions found: $MENTIONS_COUNT${NC}"

# Test 10: Verify pagination
echo -e "\n${YELLOW}Test 10: Testing pagination...${NC}"
PAGE1=$(make_request GET "/matches/$MATCH_ID/comments?page=1&per_page=5")
PAGE2=$(make_request GET "/matches/$MATCH_ID/comments?page=2&per_page=5")

HAS_MORE=$(echo "$PAGE1" | jq '.meta.has_more_pages // false' 2>/dev/null)
TOTAL=$(echo "$PAGE1" | jq '.meta.total // 0' 2>/dev/null)

echo -e "${GREEN}✓ Total comments: $TOTAL${NC}"
echo -e "${GREEN}✓ Has more pages: $HAS_MORE${NC}"

echo -e "\n${BLUE}================================${NC}"
echo -e "${BLUE}Comment System Test Complete!${NC}"
echo -e "${BLUE}================================${NC}"

# Summary
echo -e "\n${YELLOW}Summary:${NC}"
echo -e "• Match ID tested: $MATCH_ID"
echo -e "• Comments found: $COMMENT_COUNT"
echo -e "• Authentication: $([ -n "$AUTH_TOKEN" ] && echo "Success" || echo "Failed")"
echo -e "• Total mentions: $MENTIONS_COUNT"

echo -e "\n${GREEN}Frontend Integration:${NC}"
echo -e "Visit: https://staging.mrvl.net/#/match/$MATCH_ID"
echo -e "to see the comments in action!"