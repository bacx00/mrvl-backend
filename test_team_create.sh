#!/bin/bash

TOKEN=$(cat /var/www/mrvl-backend/admin_token.txt)

curl -X POST https://staging.mrvl.net/api/admin/teams \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Test Team API",
    "short_name": "TTA",
    "region": "NA",
    "country": "United States",
    "rating": 2300,
    "earnings": 200000,
    "elo_rating": 2300,
    "coach": "Test Coach",
    "coach_name": "Test Coach",
    "coach_country": "US",
    "social_links": {
      "twitter": "https://twitter.com/testteam",
      "instagram": null,
      "youtube": null,
      "website": null,
      "discord": null,
      "tiktok": null
    }
  }' | python3 -m json.tool