#!/bin/bash

echo "Testing team profile updates..."

# Test team update with achievements, social media, and rating
curl -X PUT "http://staging.mrvl.net/api/admin/teams/1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test-token" \
  -d '{
    "name": "Test Team Update",
    "region": "NA", 
    "rating": 1850,
    "elo_rating": 1850,
    "achievements": ["Championship Winner 2024", "Best Team NA"],
    "social_media": {
      "twitter": "https://twitter.com/testteam",
      "instagram": "https://instagram.com/testteam",
      "youtube": "https://youtube.com/testteam"
    }
  }' 2>/dev/null | head -20

echo -e "\n\nTesting team retrieval to verify updates..."

# Get team to verify updates persisted
curl -X GET "http://staging.mrvl.net/api/admin/teams/1" \
  -H "Authorization: Bearer test-token" 2>/dev/null | head -20
