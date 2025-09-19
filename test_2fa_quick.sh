#!/bin/bash

echo "=== Quick 2FA Functionality Test ==="
echo ""

BASE_URL="https://staging.mrvl.net/api"

# Test admin user
echo "Testing 2FA for admin: jhonny@ar-mediia.com"
echo "==========================================="

# 1. Login
echo "1. Login..."
response=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"jhonny@ar-mediia.com", "password":"password123"}')

token=$(echo "$response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$token" ]; then
    echo "   ❌ Login failed"
    echo "   Response: $response"
    exit 1
else
    echo "   ✅ Login successful"
fi

# 2. Check 2FA status
echo "2. Check 2FA status..."
status_response=$(curl -s -X GET "$BASE_URL/auth/2fa/status" \
    -H "Authorization: Bearer $token")

echo "   $status_response"

# 3. Check if 2FA is already enabled
if echo "$status_response" | grep -q '"enabled":true'; then
    echo "   ℹ️  2FA is already enabled"

    # Test disable
    echo "3. Testing 2FA disable (enter any 6 digits for test)..."
    read -p "   Enter 2FA code to disable: " disable_code

    disable_response=$(curl -s -X POST "$BASE_URL/auth/2fa/disable" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $token" \
        -d "{\"code\":\"$disable_code\"}")

    echo "   Disable response: $disable_response"

    if echo "$disable_response" | grep -q '"success":true'; then
        echo "   ✅ 2FA disabled successfully"
    else
        echo "   ℹ️  2FA disable response received (expected if wrong code)"
    fi
else
    echo "   ℹ️  2FA is not enabled"

    # Test setup
    echo "3. Testing 2FA setup..."
    setup_response=$(curl -s -X POST "$BASE_URL/auth/2fa/setup" \
        -H "Authorization: Bearer $token")

    if echo "$setup_response" | grep -q '"success":true'; then
        echo "   ✅ 2FA setup successful"
        secret=$(echo "$setup_response" | grep -o '"secret":"[^"]*"' | cut -d'"' -f4)
        echo "   Secret: $secret"
        echo "   Add this to your authenticator app and enter the 6-digit code:"
        read -p "   Enter 2FA code: " enable_code

        # Test enable
        enable_response=$(curl -s -X POST "$BASE_URL/auth/2fa/enable" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $token" \
            -d "{\"code\":\"$enable_code\"}")

        echo "   Enable response: $enable_response"

        if echo "$enable_response" | grep -q '"success":true'; then
            echo "   ✅ 2FA enabled successfully"
        else
            echo "   ℹ️  2FA enable response received (expected if wrong code)"
        fi
    else
        echo "   ❌ 2FA setup failed"
        echo "   Response: $setup_response"
    fi
fi

# 4. Test user access restriction
echo ""
echo "4. Testing user access restriction..."
user_response=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"test-user@test.com", "password":"password123"}')

user_token=$(echo "$user_response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ ! -z "$user_token" ]; then
    user_status=$(curl -s -X GET "$BASE_URL/auth/2fa/status" \
        -H "Authorization: Bearer $user_token")

    if echo "$user_status" | grep -q '403\|forbidden\|only available for admin'; then
        echo "   ✅ User correctly denied 2FA access"
    else
        echo "   ❌ User should be denied 2FA access"
        echo "   Response: $user_status"
    fi
else
    echo "   ℹ️  Could not test user restrictions (login failed)"
fi

echo ""
echo "=== Quick 2FA Test Complete ==="