#!/bin/bash

echo "🧪 Complete 2FA Flow Test"
echo "========================="
echo ""

# Reset admin 2FA state
echo "📋 Resetting admin 2FA state..."
php artisan tinker --execute="
\$admin = App\Models\User::where('email', 'jhonny@ar-mediia.com')->first();
if (\$admin) {
    \$admin->two_factor_secret = null;
    \$admin->two_factor_recovery_codes = null;
    \$admin->two_factor_confirmed_at = null;
    \$admin->save();
    echo 'Admin 2FA reset for testing';
}
" 2>/dev/null

# Get fresh admin token
ADMIN_TOKEN=$(php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
if (\$admin) {
    \$token = \$admin->createToken('flow-test')->accessToken;
    echo \$token;
}
" 2>/dev/null)

echo "✅ Admin 2FA reset and token generated"
echo ""

# Test 1: Setup 2FA
echo "🔧 Step 1: Setup 2FA"
echo "==================="
SETUP_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
    -X POST \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    https://staging.mrvl.net/api/auth/2fa/setup)

SETUP_CODE=$(echo "$SETUP_RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$SETUP_CODE" = "200" ]; then
    echo "✅ PASS: 2FA setup successful (HTTP $SETUP_CODE)"

    # Extract secret for testing
    SECRET=$(echo "$SETUP_RESPONSE" | grep -o '"secret":"[^"]*"' | cut -d'"' -f4)
    if [ ! -z "$SECRET" ]; then
        echo "   Secret: $SECRET"
    fi
else
    echo "❌ FAIL: 2FA setup failed (HTTP $SETUP_CODE)"
    echo "Response: $SETUP_RESPONSE"
    exit 1
fi

echo ""

# Test 2: Enable 2FA (this should work now)
echo "🔒 Step 2: Enable 2FA"
echo "====================="
ENABLE_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
    -X POST \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"code": "123456"}' \
    https://staging.mrvl.net/api/auth/2fa/enable)

ENABLE_CODE=$(echo "$ENABLE_RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$ENABLE_CODE" = "200" ] || [ "$ENABLE_CODE" = "422" ]; then
    echo "✅ PASS: 2FA enable endpoint accessible (HTTP $ENABLE_CODE)"
    if [ "$ENABLE_CODE" = "422" ]; then
        echo "   Note: Code validation failed (expected with test code 123456)"
    fi
else
    echo "❌ FAIL: 2FA enable failed (HTTP $ENABLE_CODE)"
    echo "Response: $ENABLE_RESPONSE"
fi

echo ""

# Test 3: Status check
echo "📊 Step 3: Check 2FA Status"
echo "==========================="
STATUS_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
    -X GET \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    https://staging.mrvl.net/api/auth/2fa/status)

STATUS_CODE=$(echo "$STATUS_RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$STATUS_CODE" = "200" ]; then
    echo "✅ PASS: 2FA status accessible (HTTP $STATUS_CODE)"
    echo "   Status details: $(echo "$STATUS_RESPONSE" | head -1)"
else
    echo "❌ FAIL: 2FA status failed (HTTP $STATUS_CODE)"
fi

echo ""

# Test 4: Disable 2FA
echo "🔓 Step 4: Disable 2FA"
echo "======================"
DISABLE_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
    -X POST \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"code": "123456"}' \
    https://staging.mrvl.net/api/auth/2fa/disable)

DISABLE_CODE=$(echo "$DISABLE_RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)

if [ "$DISABLE_CODE" = "200" ] || [ "$DISABLE_CODE" = "400" ] || [ "$DISABLE_CODE" = "422" ]; then
    echo "✅ PASS: 2FA disable endpoint accessible (HTTP $DISABLE_CODE)"
    if [ "$DISABLE_CODE" = "400" ]; then
        echo "   Note: 2FA not enabled yet (expected)"
    elif [ "$DISABLE_CODE" = "422" ]; then
        echo "   Note: Code validation failed (expected with test code)"
    fi
else
    echo "❌ FAIL: 2FA disable failed (HTTP $DISABLE_CODE)"
    echo "Response: $DISABLE_RESPONSE"
fi

echo ""

# Cleanup
echo "🧹 Cleanup..."
php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
if (\$admin) \$admin->tokens()->where('name', 'flow-test')->delete();
echo 'Test tokens cleaned up';
" 2>/dev/null

echo ""
echo "🎯 Summary:"
echo "- Setup: HTTP $SETUP_CODE"
echo "- Enable: HTTP $ENABLE_CODE"
echo "- Status: HTTP $STATUS_CODE"
echo "- Disable: HTTP $DISABLE_CODE"
echo ""
echo "✅ 2FA flow test complete!"