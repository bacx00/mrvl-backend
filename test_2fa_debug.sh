#!/bin/bash

echo "🐛 2FA Debug Test"
echo "================"

# Reset admin 2FA
php artisan tinker --execute="
\$admin = App\Models\User::where('email', 'jhonny@ar-mediia.com')->first();
if (\$admin) {
    \$admin->two_factor_secret = null;
    \$admin->two_factor_recovery_codes = null;
    \$admin->two_factor_confirmed_at = null;
    \$admin->save();
}
" 2>/dev/null

echo "Step 1: Fresh token + Setup"
ADMIN_TOKEN=$(php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
\$token = \$admin->createToken('debug1')->accessToken;
echo \$token;
" 2>/dev/null)

curl -s -w "\nSETUP_HTTP:%{http_code}\n" \
    -X POST \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    https://staging.mrvl.net/api/auth/2fa/setup | grep "SETUP_HTTP"

echo ""
echo "Step 2: FRESH token + Enable (right after setup)"
ADMIN_TOKEN2=$(php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
\$token = \$admin->createToken('debug2')->accessToken;
echo \$token;
" 2>/dev/null)

ENABLE_RESP=$(curl -s -w "\nENABLE_HTTP:%{http_code}" \
    -X POST \
    -H "Authorization: Bearer $ADMIN_TOKEN2" \
    -H "Content-Type: application/json" \
    -d '{"code": "123456"}' \
    https://staging.mrvl.net/api/auth/2fa/enable)

echo "$ENABLE_RESP" | grep "ENABLE_HTTP"
echo ""

# Check if it's an HTML redirect
if echo "$ENABLE_RESP" | grep -q "DOCTYPE"; then
    echo "❌ Got HTML redirect instead of JSON"
    echo "First few lines:"
    echo "$ENABLE_RESP" | head -5
else
    echo "✅ Got proper JSON response"
    echo "$ENABLE_RESP"
fi

echo ""
echo "Step 3: Test same token on working endpoint"
curl -s -w "\nSTATUS_HTTP:%{http_code}\n" \
    -X GET \
    -H "Authorization: Bearer $ADMIN_TOKEN2" \
    -H "Content-Type: application/json" \
    https://staging.mrvl.net/api/auth/2fa/status | grep "STATUS_HTTP"

# Cleanup
php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
\$admin->tokens()->where('name', 'LIKE', 'debug%')->delete();
" 2>/dev/null