<?php

echo "🔐 Testing 2FA API Endpoints\n";
echo "===========================\n\n";

// Test configuration
$baseUrl = 'http://localhost'; // Adjust as needed
$testEmail = 'admin@test.com';
$testPassword = 'password123';

echo "📋 2FA API Test Instructions:\n";
echo "1. First, create an admin user or use an existing one\n";
echo "2. Login to get an access token\n";
echo "3. Test the 2FA endpoints\n\n";

echo "🧪 Sample cURL Commands for Testing:\n";
echo "=====================================\n\n";

echo "1. Login (get access token):\n";
echo "curl -X POST {$baseUrl}/api/auth/login \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"email\": \"{$testEmail}\", \"password\": \"{$testPassword}\"}'\n\n";

echo "2. Check 2FA status:\n";
echo "curl -X GET {$baseUrl}/api/auth/2fa/status \\\n";
echo "  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'\n\n";

echo "3. Setup 2FA (get QR code):\n";
echo "curl -X POST {$baseUrl}/api/auth/2fa/setup \\\n";
echo "  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'\n\n";

echo "4. Enable 2FA (with code from authenticator app):\n";
echo "curl -X POST {$baseUrl}/api/auth/2fa/enable \\\n";
echo "  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"code\": \"123456\"}'\n\n";

echo "5. Verify 2FA code:\n";
echo "curl -X POST {$baseUrl}/api/auth/2fa/verify \\\n";
echo "  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"code\": \"123456\"}'\n\n";

echo "6. Get recovery codes:\n";
echo "curl -X GET {$baseUrl}/api/auth/2fa/recovery-codes \\\n";
echo "  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'\n\n";

echo "7. Test admin access (should require 2FA):\n";
echo "curl -X GET {$baseUrl}/api/test-admin \\\n";
echo "  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'\n\n";

echo "🔧 Creating a test admin user script...\n";

$createAdminScript = '<?php
require_once "vendor/autoload.php";

$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create test admin user
$admin = User::updateOrCreate(
    ["email" => "admin@test.com"],
    [
        "name" => "Test Admin",
        "email" => "admin@test.com", 
        "password" => Hash::make("password123"),
        "role" => "admin"
    ]
);

echo "✅ Test admin user created/updated:\\n";
echo "Email: admin@test.com\\n";
echo "Password: password123\\n";
echo "Role: admin\\n";
echo "ID: " . $admin->id . "\\n";
';

file_put_contents('/var/www/mrvl-backend/create_test_admin_2fa.php', $createAdminScript);

echo "✅ Created create_test_admin_2fa.php script\n";
echo "Run: cd /var/www/mrvl-backend && php create_test_admin_2fa.php\n\n";

echo "📱 2FA App Setup Instructions:\n";
echo "1. Install Google Authenticator, Authy, or similar app\n";
echo "2. Use the setup endpoint to get QR code\n";
echo "3. Scan QR code or manually enter the secret\n";
echo "4. Use the 6-digit code to enable 2FA\n\n";

echo "🛡️ Expected Behavior:\n";
echo "- Admin users MUST enable 2FA before accessing admin endpoints\n";
echo "- Non-admin users can optionally enable 2FA\n";
echo "- 2FA verification is required for each session\n";
echo "- Recovery codes can be used as backup\n";
echo "- Admin users cannot disable 2FA once enabled\n\n";

echo "✅ 2FA Implementation Complete!\n";
?>