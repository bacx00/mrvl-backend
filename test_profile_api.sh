#!/bin/bash

echo "=== Testing Profile API Endpoints ==="
echo ""

BASE_URL="https://staging.mrvl.net/api"

# Test users for each role
declare -A test_users
test_users["user"]="test-user@test.com"
test_users["moderator"]="test-moderator@test.com"
test_users["admin"]="test-admin@test.com"

for role in user moderator admin; do
    email="${test_users[$role]}"
    echo "Testing $role user: $email"
    echo "=================================="

    # Login to get token
    echo "1. Logging in..."
    response=$(curl -s -X POST "$BASE_URL/auth/login" \
        -H "Content-Type: application/json" \
        -d "{\"email\":\"$email\", \"password\":\"password123\"}")

    token=$(echo "$response" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

    if [ -z "$token" ]; then
        echo "   ❌ Login failed for $role"
        echo "   Response: $response"
        echo ""
        continue
    else
        echo "   ✅ Login successful"
    fi

    # Test password change
    echo "2. Testing password change..."
    pw_response=$(curl -s -X POST "$BASE_URL/user/profile/change-password" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $token" \
        -d '{"current_password":"password123", "new_password":"newpass123", "new_password_confirmation":"newpass123"}')

    if echo "$pw_response" | grep -q '"success":true'; then
        echo "   ✅ Password change successful"

        # Change back to original password
        curl -s -X POST "$BASE_URL/user/profile/change-password" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $token" \
            -d '{"current_password":"newpass123", "new_password":"password123", "new_password_confirmation":"password123"}' > /dev/null
        echo "   ✅ Password reverted"
    else
        echo "   ❌ Password change failed"
        echo "   Response: $pw_response"
    fi

    # Test email change
    echo "3. Testing email change..."
    new_email="new_$email"
    email_response=$(curl -s -X POST "$BASE_URL/user/profile/change-email" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $token" \
        -d "{\"password\":\"password123\", \"new_email\":\"$new_email\"}")

    if echo "$email_response" | grep -q '"success":true'; then
        echo "   ✅ Email change successful"

        # Change back to original email
        curl -s -X POST "$BASE_URL/user/profile/change-email" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $token" \
            -d "{\"password\":\"password123\", \"new_email\":\"$email\"}" > /dev/null
        echo "   ✅ Email reverted"
    else
        echo "   ❌ Email change failed"
        echo "   Response: $email_response"
    fi

    # Test username change
    echo "4. Testing username change..."
    new_name="Test ${role^} Updated"
    name_response=$(curl -s -X POST "$BASE_URL/user/profile/change-username" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $token" \
        -d "{\"password\":\"password123\", \"new_name\":\"$new_name\"}")

    if echo "$name_response" | grep -q '"success":true'; then
        echo "   ✅ Username change successful"

        # Change back to original name
        original_name="Test ${role^}"
        curl -s -X POST "$BASE_URL/user/profile/change-username" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $token" \
            -d "{\"password\":\"password123\", \"new_name\":\"$original_name\"}" > /dev/null
        echo "   ✅ Username reverted"
    else
        echo "   ❌ Username change failed"
        echo "   Response: $name_response"
    fi

    echo ""
done

echo "=== Profile API Testing Complete ==="