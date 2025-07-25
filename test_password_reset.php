<?php

echo "Password Reset Test Script\n";
echo "==========================\n\n";

// Test the forgot password endpoint
echo "1. Testing forgot password endpoint:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://staging.mrvl.net/api/auth/forgot-password");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'test@example.com' // Replace with a valid email from your database
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response Code: $httpCode\n";
echo "Response: $response\n\n";

// Instructions for manual testing
echo "Manual Testing Instructions:\n";
echo "============================\n\n";
echo "1. Update the email configuration in .env file:\n";
echo "   - For Gmail: Use your Gmail address and App Password\n";
echo "   - For other providers: Update MAIL_HOST, MAIL_PORT accordingly\n\n";

echo "2. Clear Laravel cache:\n";
echo "   php artisan config:clear\n";
echo "   php artisan cache:clear\n\n";

echo "3. Test with curl:\n";
echo "   # Request password reset\n";
echo '   curl -X POST https://staging.mrvl.net/api/auth/forgot-password \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d \'{"email":"your-email@example.com"}\'' . "\n\n";

echo "4. Check email/logs:\n";
echo "   - If MAIL_MAILER=smtp, check your email inbox\n";
echo "   - If MAIL_MAILER=log, check storage/logs/laravel.log\n\n";

echo "5. Reset password with token:\n";
echo '   curl -X POST https://staging.mrvl.net/api/auth/reset-password \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d \'{
       "token": "your-reset-token",
       "email": "your-email@example.com",
       "password": "newpassword123",
       "password_confirmation": "newpassword123"
     }\'' . "\n\n";

echo "Frontend Integration:\n";
echo "=====================\n";
echo "The reset link in the email will direct users to:\n";
echo "https://staging.mrvl.net/reset-password?token=TOKEN&email=EMAIL\n\n";
echo "Your frontend should:\n";
echo "1. Create a page at /reset-password route\n";
echo "2. Extract token and email from URL parameters\n";
echo "3. Show a form with password and password_confirmation fields\n";
echo "4. POST to /api/auth/reset-password with all required data\n";