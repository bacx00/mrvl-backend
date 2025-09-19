#!/bin/bash

cd /var/www/mrvl-backend

echo "Getting fresh token..."
php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
\$token = \$admin->createToken('simple')->accessToken;
file_put_contents('/tmp/token.txt', \$token);
echo 'Token saved';
" 2>/dev/null

TOKEN=$(cat /tmp/token.txt)

echo "Setup test:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" https://staging.mrvl.net/api/auth/2fa/setup | grep "HTTP:"

echo ""
echo "Enable test:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"code":"123456"}' https://staging.mrvl.net/api/auth/2fa/enable | grep "HTTP:"

rm -f /tmp/token.txt