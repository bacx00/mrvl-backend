<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

echo "\n🔄 =====================================\n";
echo "   RESENDING PASSWORD RESET EMAIL\n";
echo "======================================\n\n";

$email = 'jhonnyaraya7@gmail.com';

// Verify configuration
echo "📋 Verifying Configuration\n";
echo "-----------------------------------\n";
echo "APP_URL: " . config('app.url') . "\n";
echo "FRONTEND_URL: " . config('app.frontend_url') . "\n";

if (strpos(config('app.frontend_url'), 'localhost') !== false) {
    echo "\n⚠️  WARNING: Frontend URL still contains localhost!\n";
    echo "   Attempting to fix...\n";
    
    // Force correct configuration
    config(['app.frontend_url' => 'https://staging.mrvl.net']);
    echo "✅ Frontend URL corrected to: " . config('app.frontend_url') . "\n";
}

// Find user
$user = User::where('email', $email)->first();
if (!$user) {
    echo "❌ User not found!\n";
    exit(1);
}

echo "\n✅ User found: {$user->email}\n";

// Clear existing tokens
DB::table('password_reset_tokens')->where('email', $email)->delete();
echo "✅ Cleared existing tokens\n";

// Send new email
echo "\n📮 SENDING NEW PASSWORD RESET EMAIL...\n";
echo "   To: {$email}\n";
echo "   With correct URL: https://staging.mrvl.net\n\n";

$status = Password::sendResetLink(['email' => $email]);

if ($status === Password::RESET_LINK_SENT) {
    echo "🎉 =======================================\n";
    echo "   ✅ EMAIL SENT SUCCESSFULLY!\n";
    echo "=======================================\n\n";
    
    echo "📧 CHECK YOUR INBOX AGAIN!\n";
    echo "   A new email has been sent with the CORRECT links\n";
    echo "   The reset button will take you to:\n";
    echo "   https://staging.mrvl.net/reset-password\n";
    
    // Get the actual token
    $tokenRecord = DB::table('password_reset_tokens')
        ->where('email', $email)
        ->first();
    
    if ($tokenRecord) {
        // Show what the correct link should be
        echo "\n🔗 The link in your email should look like:\n";
        echo "   https://staging.mrvl.net/reset-password?token=...&email={$email}\n";
        echo "\n   NOT localhost:3000!\n";
    }
    
    echo "\n✅ New email sent with corrected URLs!\n";
    echo "   Please check jhonnyaraya7@gmail.com\n";
} else {
    echo "\n❌ Failed to send email\n";
    echo "   Status: " . $status . "\n";
}

echo "\n======================================\n";