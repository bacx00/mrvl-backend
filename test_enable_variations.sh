#!/bin/bash

cd /var/www/mrvl-backend

# Reset and get token
php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
\$admin->two_factor_secret = null;
\$admin->two_factor_recovery_codes = null;
\$admin->two_factor_confirmed_at = null;
\$admin->save();
\$token = \$admin->createToken('test-variations')->accessToken;
file_put_contents('/tmp/token2.txt', \$token);
echo 'Ready';
" 2>/dev/null

TOKEN=$(cat /tmp/token2.txt)

# Setup first
curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" https://staging.mrvl.net/api/auth/2fa/setup > /dev/null

echo "Testing enable endpoint variations:"
echo ""

echo "1. With valid JSON:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"code":"123456"}' https://staging.mrvl.net/api/auth/2fa/enable | grep "HTTP:"

echo ""
echo "2. Without body:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" https://staging.mrvl.net/api/auth/2fa/enable | grep "HTTP:"

echo ""
echo "3. With malformed JSON:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{invalid}' https://staging.mrvl.net/api/auth/2fa/enable | grep "HTTP:"

echo ""
echo "4. Different content type:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/x-www-form-urlencoded" -d 'code=123456' https://staging.mrvl.net/api/auth/2fa/enable | grep "HTTP:"

rm -f /tmp/token2.txt