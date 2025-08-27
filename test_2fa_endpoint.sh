#!/bin/bash

echo "🔐 Testing 2FA Setup Endpoint Fix"
echo "================================"

# Step 1: Login to get temp token
echo "Step 1: Getting temp token..."
RESPONSE=$(curl -s -X POST https://staging.mrvl.net/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email": "jhonny@ar-mediia.com", "password": "password123"}')

echo "Login Response: $RESPONSE"

# Extract temp token (you'll need to manually copy this for the next step)
TEMP_TOKEN=$(echo $RESPONSE | grep -o '"temp_token":"[^"]*"' | cut -d'"' -f4)
echo "Temp Token: $TEMP_TOKEN"

if [ -n "$TEMP_TOKEN" ]; then
    echo ""
    echo "Step 2: Testing 2FA setup..."
    curl -s -X POST https://staging.mrvl.net/api/auth/2fa/setup-login \
      -H 'Content-Type: application/json' \
      -d "{\"temp_token\": \"$TEMP_TOKEN\"}" \
      | head -3
else
    echo "❌ No temp token received"
fi