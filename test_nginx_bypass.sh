#!/bin/bash

cd /var/www/mrvl-backend

# Reset and setup
php artisan tinker --execute="
\$admin = App\Models\User::where('role', 'admin')->first();
\$admin->two_factor_secret = null;
\$admin->two_factor_recovery_codes = null;
\$admin->two_factor_confirmed_at = null;
\$admin->save();
\$token = \$admin->createToken('nginx-bypass')->accessToken;
file_put_contents('/tmp/nginx_token.txt', \$token);
" 2>/dev/null

TOKEN=$(cat /tmp/nginx_token.txt)

# Test via nginx (staging.mrvl.net)
echo "=== Via NGINX (current issue) ==="
curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" https://staging.mrvl.net/api/auth/2fa/setup > /dev/null
echo "Setup via nginx:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" https://staging.mrvl.net/api/auth/2fa/setup | grep -o "HTTP:.*"

echo "Enable via nginx:"
curl -s -w "HTTP:%{http_code}" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"code":"123456"}' https://staging.mrvl.net/api/auth/2fa/enable | grep -o "HTTP:.*"

echo ""
echo "=== Testing route exists ==="
./artisan route:list | grep -E "(setup|enable)" | grep 2fa

rm -f /tmp/nginx_token.txt