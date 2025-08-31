<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

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
    echo "   Password reset to: {$password}\n";
} else {
    $user = User::create([
        'name' => 'Jhonny Araya',
        'email' => $email,
        'username' => $username,
        'password' => bcrypt($password),
        'email_verified_at' => now()
    ]);
    echo "✅ New user created!\n";
    echo "   Email: {$email}\n";
    echo "   Username: {$username}\n";
    echo "   Password: {$password}\n";
}

echo "\n📋 Step 2: Testing Login\n";
echo "-----------------------------------\n";

// Test login to verify user works
$loginTest = auth()->attempt([
    'email' => $email,
    'password' => $password
]);

if ($loginTest) {
    echo "✅ Login test successful!\n";
    auth()->logout();
} else {
    echo "⚠️  Login test failed - but continuing with password reset\n";
}

echo "\n📋 Step 3: Sending Password Reset Email\n";
echo "-----------------------------------\n";

// Clear any existing tokens for this user
DB::table('password_reset_tokens')->where('email', $email)->delete();
echo "✅ Cleared any existing reset tokens\n";

// Send password reset email
echo "📮 Sending password reset email to: {$email}\n";
echo "   Please wait...\n";

$status = Password::sendResetLink(['email' => $email]);

if ($status === Password::RESET_LINK_SENT) {
    echo "\n✅ PASSWORD RESET EMAIL SENT SUCCESSFULLY!\n";
    echo "\n📬 Check your Gmail inbox (jhonnyaraya7@gmail.com) for:\n";
    echo "   • From: MRVL Tournament Platform (m4rvl.net@gmail.com)\n";
    echo "   • Subject: Password Reset Request\n";
    echo "   • The email contains a reset button/link\n";
    echo "   • Link is valid for 60 minutes\n";
    
    // Get the token for reference
    $tokenRecord = DB::table('password_reset_tokens')
        ->where('email', $email)
        ->first();
    
    if ($tokenRecord) {
        echo "\n🔗 Reset Link Format:\n";
        echo "   https://staging.mrvl.net/reset-password?token=TOKEN&email={$email}\n";
        echo "\n⏰ Token expires at: " . date('Y-m-d H:i:s', strtotime('+60 minutes')) . "\n";
    }
    
    echo "\n✨ EMAIL HAS BEEN SENT! Check your inbox now!\n";
} else {
    echo "\n❌ Failed to send email!\n";
    echo "   Status: " . $status . "\n";
    echo "   Check logs: /var/www/mrvl-backend/storage/logs/laravel.log\n";
}

echo "\n📋 Step 4: How to Reset Your Password\n";
echo "-----------------------------------\n";
echo "1. Check your email (jhonnyaraya7@gmail.com)\n";
echo "2. Click the 'Reset Password' button in the email\n";
echo "3. You'll be redirected to: https://staging.mrvl.net/reset-password\n";
echo "4. Enter your new password (min 8 chars, 1 uppercase, 1 number, 1 special)\n";
echo "5. Click 'Reset Password' button\n";
echo "6. You'll be redirected to login with your new password\n";

echo "\n🎯 Current Login Credentials:\n";
echo "   Email: {$email}\n";
echo "   Password: {$password}\n";
echo "   (This will change after you reset it)\n";

echo "\n======================================\n";