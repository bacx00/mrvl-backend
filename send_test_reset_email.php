<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;

echo "\n📧 Sending Password Reset Email Test\n";
echo "=====================================\n\n";

// Create or find test user
$testEmail = 'm4rvl.net@gmail.com'; // Sending to your own email for testing
$user = User::where('email', $testEmail)->first();

if (!$user) {
    $user = User::create([
        'name' => 'MRVL Test',
        'email' => $testEmail,
        'username' => 'mrvl_test_' . time(),
        'password' => bcrypt('TestPassword123!'),
        'email_verified_at' => now()
    ]);
    echo "✅ Created test user: {$testEmail}\n";
} else {
    echo "✅ Using existing user: {$testEmail}\n";
}

// Send password reset email
echo "\n📮 Sending password reset email...\n";

$status = Password::sendResetLink(['email' => $testEmail]);

if ($status === Password::RESET_LINK_SENT) {
    echo "✅ Password reset email sent successfully to {$testEmail}!\n";
    echo "\n📬 Check your Gmail inbox for:\n";
    echo "   • From: MRVL Tournament Platform\n";
    echo "   • Subject: Password Reset Request\n";
    echo "   • The email contains a reset link valid for 60 minutes\n";
    echo "\n✨ The email has been sent via Gmail SMTP!\n";
} else {
    echo "❌ Failed to send email. Status: " . $status . "\n";
    echo "   Check error logs: storage/logs/laravel.log\n";
}

echo "\n";