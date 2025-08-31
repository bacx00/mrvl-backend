<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "\n🔐 =====================================\n";
echo "   CREATE USER & TEST FORGOT PASSWORD\n";
echo "======================================\n\n";

$email = 'jhonnyaraya7@gmail.com';
$username = 'jhonnyaraya';
$password = 'TestPassword123!';

// Step 1: Create or update user
echo "📋 Step 1: Creating/Updating User\n";
echo "-----------------------------------\n";

$user = User::where('email', $email)->first();

if ($user) {
    echo "✅ User already exists with email: {$email}\n";
    echo "   Username: {$user->username}\n";
    echo "   Name: {$user->name}\n";
    
    // Update password so we know what it is
    $user->password = bcrypt($password);
    $user->save();
    echo "   Password updated to: {$password}\n";
} else {
    $user = User::create([
        'name' => 'Jhonny Araya',
        'email' => $email,
        'username' => $username,
        'password' => bcrypt($password),
        'email_verified_at' => now()
    ]);
    echo "✅ New user created successfully!\n";
    echo "   Email: {$email}\n";
    echo "   Username: {$username}\n";
    echo "   Password: {$password}\n";
}

echo "\n📋 Step 2: Verifying User\n";
echo "-----------------------------------\n";

// Verify password hash
if (Hash::check($password, $user->password)) {
    echo "✅ Password hash verified - login will work!\n";
} else {
    echo "⚠️  Password hash verification failed\n";
}

echo "✅ User ID: {$user->id}\n";
echo "✅ User verified at: " . ($user->email_verified_at ?? 'Not verified') . "\n";

echo "\n📋 Step 3: Sending Password Reset Email\n";
echo "-----------------------------------\n";

// Clear any existing tokens for this user
DB::table('password_reset_tokens')->where('email', $email)->delete();
echo "✅ Cleared any existing reset tokens\n";

// Send password reset email
echo "\n📮 SENDING PASSWORD RESET EMAIL NOW...\n";
echo "   To: {$email}\n";
echo "   Please wait...\n\n";

$status = Password::sendResetLink(['email' => $email]);

if ($status === Password::RESET_LINK_SENT) {
    echo "🎉 =======================================\n";
    echo "   ✅ EMAIL SENT SUCCESSFULLY!\n";
    echo "=======================================\n\n";
    
    echo "📬 CHECK YOUR INBOX NOW!\n";
    echo "   Email: jhonnyaraya7@gmail.com\n";
    echo "   From: MRVL Tournament Platform\n";
    echo "   Subject: Password Reset Request\n\n";
    
    // Get the token for reference
    $tokenRecord = DB::table('password_reset_tokens')
        ->where('email', $email)
        ->first();
    
    if ($tokenRecord) {
        echo "⏰ Token Information:\n";
        echo "   Created: " . date('Y-m-d H:i:s') . "\n";
        echo "   Expires: " . date('Y-m-d H:i:s', strtotime('+60 minutes')) . "\n";
        echo "   Valid for: 60 minutes\n";
    }
    
    echo "\n📧 THE EMAIL HAS BEEN SENT TO YOUR GMAIL!\n";
    echo "   Please check your inbox (and spam folder if needed)\n";
} else {
    echo "\n❌ Failed to send email!\n";
    echo "   Status: " . $status . "\n";
    
    if ($status === Password::INVALID_USER) {
        echo "   Error: User not found with this email\n";
    } elseif ($status === Password::RESET_THROTTLED) {
        echo "   Error: Too many attempts. Please wait before trying again.\n";
    }
    
    echo "   Check logs: /var/www/mrvl-backend/storage/logs/laravel.log\n";
}

echo "\n📋 How to Complete Password Reset:\n";
echo "-----------------------------------\n";
echo "1. ✉️  Open your email (jhonnyaraya7@gmail.com)\n";
echo "2. 🔘 Click the blue 'Reset Password' button\n";
echo "3. 🌐 You'll go to: https://staging.mrvl.net/reset-password\n";
echo "4. 🔐 Enter your new password\n";
echo "5. ✅ Click 'Reset Password' to save\n";
echo "6. 🎯 Login with your new password!\n";

echo "\n💡 Your Current Login Info:\n";
echo "   Site: https://staging.mrvl.net\n";
echo "   Email: {$email}\n";
echo "   Current Password: {$password}\n";

echo "\n======================================\n";