#!/bin/bash

echo "🧪 Testing 2FA Role Restrictions"
echo "================================="
echo ""

# Get user tokens for testing
echo "📋 Getting test user credentials..."
ADMIN_TOKEN=$(php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
if (\$admin) {
    \$token = \$admin->createToken('test-token')->accessToken;
    echo \$token;
}
" 2>/dev/null)

USER_TOKEN=$(php artisan tinker --execute="
\$user = App\Models\User::where('role', 'user')->first();
if (\$user) {
    \$token = \$user->createToken('test-token')->accessToken;
    echo \$token;
}
" 2>/dev/null)

MODERATOR_TOKEN=$(php artisan tinker --execute="
\$moderator = App\Models\User::where('role', 'moderator')->first();
if (\$moderator) {
    \$token = \$moderator->createToken('test-token')->accessToken;
    echo \$token;
}
" 2>/dev/null)

echo "✅ Tokens generated"
echo ""

# Test 1: Admin should have access to 2FA setup
echo "🔧 Test 1: Admin 2FA Setup Access"
echo "================================="
if [ ! -z "$ADMIN_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        https://staging.mrvl.net/api/auth/2fa/setup)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "200" ]; then
        echo "✅ PASS: Admin can access 2FA setup (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: Admin denied 2FA setup (HTTP $HTTP_CODE)"
    fi
else
    echo "❌ FAIL: No admin token available"
fi
echo ""

# Test 2: Regular user should be denied 2FA setup
echo "👤 Test 2: User 2FA Setup Denial"
echo "================================"
if [ ! -z "$USER_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $USER_TOKEN" \
        -H "Content-Type: application/json" \
        https://staging.mrvl.net/api/auth/2fa/setup)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "403" ]; then
        echo "✅ PASS: User denied 2FA setup (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: User should be denied 2FA setup (HTTP $HTTP_CODE)"
    fi
else
    echo "❌ FAIL: No user token available"
fi
echo ""

# Test 3: Moderator should be denied 2FA setup
echo "👥 Test 3: Moderator 2FA Setup Denial"
echo "====================================="
if [ ! -z "$MODERATOR_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $MODERATOR_TOKEN" \
        -H "Content-Type: application/json" \
        https://staging.mrvl.net/api/auth/2fa/setup)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "403" ]; then
        echo "✅ PASS: Moderator denied 2FA setup (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: Moderator should be denied 2FA setup (HTTP $HTTP_CODE)"
    fi
else
    echo "❌ FAIL: No moderator token available"
fi
echo ""

# Test 4: Test 2FA enable endpoint
echo "🔒 Test 4: 2FA Enable Endpoint"
echo "=============================="
echo "Admin 2FA enable:"
if [ ! -z "$ADMIN_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"code": "123456"}' \
        https://staging.mrvl.net/api/auth/2fa/enable)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "400" ]; then
        echo "✅ PASS: Admin can access enable endpoint (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: Admin denied enable endpoint (HTTP $HTTP_CODE)"
    fi
fi

echo "User 2FA enable:"
if [ ! -z "$USER_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $USER_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"code": "123456"}' \
        https://staging.mrvl.net/api/auth/2fa/enable)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "403" ]; then
        echo "✅ PASS: User denied enable endpoint (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: User should be denied enable endpoint (HTTP $HTTP_CODE)"
    fi
fi
echo ""

# Test 5: Test 2FA disable endpoint
echo "🔓 Test 5: 2FA Disable Endpoint"
echo "==============================="
echo "Admin 2FA disable:"
if [ ! -z "$ADMIN_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"code": "123456"}' \
        https://staging.mrvl.net/api/auth/2fa/disable)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "400" ]; then
        echo "✅ PASS: Admin can access disable endpoint (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: Admin denied disable endpoint (HTTP $HTTP_CODE)"
    fi
fi

echo "User 2FA disable:"
if [ ! -z "$USER_TOKEN" ]; then
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
        -X POST \
        -H "Authorization: Bearer $USER_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"code": "123456"}' \
        https://staging.mrvl.net/api/auth/2fa/disable)

    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

    if [ "$HTTP_CODE" = "403" ]; then
        echo "✅ PASS: User denied disable endpoint (HTTP $HTTP_CODE)"
    else
        echo "❌ FAIL: User should be denied disable endpoint (HTTP $HTTP_CODE)"
    fi
fi
echo ""

# Clean up tokens
echo "🧹 Cleaning up test tokens..."
php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
if (\$admin) \$admin->tokens()->where('name', 'test-token')->delete();

\$user = App\Models\User::where('role', 'user')->first();
if (\$user) \$user->tokens()->where('name', 'test-token')->delete();

\$moderator = App\Models\User::where('role', 'moderator')->first();
if (\$moderator) \$moderator->tokens()->where('name', 'test-token')->delete();

echo 'Tokens cleaned up';
" 2>/dev/null

echo ""
echo "🎯 Test Summary:"
echo "- Only admin users should have access to all 2FA endpoints"
echo "- Regular users and moderators should get 403 Forbidden"
echo "- Frontend should only show 2FA UI for admin users"
echo ""
echo "✅ 2FA restriction testing complete!"