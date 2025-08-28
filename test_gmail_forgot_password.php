<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

echo "\n";
echo "📧 =====================================\n";
echo "   GMAIL FORGOT PASSWORD TEST\n";
echo "======================================\n\n";

// Check current mail configuration
$mailer = config('mail.default');
$host = config('mail.mailers.smtp.host');
$username = config('mail.mailers.smtp.username');

echo "📋 Current Configuration:\n";
echo "   Mailer: " . $mailer . "\n";
echo "   Host: " . $host . "\n";
echo "   Username: " . $username . "\n";
echo "   From: " . config('mail.from.address') . "\n\n";

if ($mailer !== 'smtp' || $host !== 'smtp.gmail.com') {
    echo "⚠️  Gmail is not configured yet!\n";
    echo "   Run: ./setup_gmail.sh\n\n";
    exit(1);
}

// Test connection
echo "🔌 Testing Gmail Connection...\n";
try {
    $transport = Mail::mailer('smtp')->getSymfonyTransport();
    $transport->start();
    echo "✅ Connected to Gmail SMTP successfully!\n\n";
} catch (\Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Common issues:\n";
    echo "  1. Wrong App Password (not regular password)\n";
    echo "  2. 2FA not enabled on Gmail account\n";
    echo "  3. Spaces in App Password\n";
    echo "\n";
    exit(1);
}

// Ask if user wants to send a real password reset
echo "Would you like to send a real password reset email?\n";
echo "Enter email address (or press Enter to skip): ";
$handle = fopen("php://stdin", "r");
$email = trim(fgets($handle));

if (!empty($email)) {
    // Check if user exists
    $user = User::where('email', $email)->first();
    
    if (!$user) {
        echo "\n❌ User not found. Creating test user...\n";
        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => bcrypt('TestPassword123!'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        echo "✅ Test user created\n";
    }
    
    echo "\n📮 Sending password reset email to: $email\n";
    
    try {
        $status = Password::sendResetLink(['email' => $email]);
        
        if ($status === Password::RESET_LINK_SENT) {
            echo "✅ Password reset email sent successfully!\n";
            echo "\n";
            echo "📬 Check your email for:\n";
            echo "   • From: " . config('mail.from.address') . "\n";
            echo "   • Subject: Password Reset Request - MRVL Tournament Platform\n";
            echo "   • Reset link valid for 60 minutes\n";
            echo "\n";
            echo "⚠️  Check spam folder if not in inbox\n";
        } else {
            echo "❌ Failed to send: $status\n";
        }
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n📊 Gmail is configured and ready!\n";
    echo "   Use the forgot password feature at:\n";
    echo "   https://staging.mrvl.net\n";
}

echo "\n";
echo "📈 Gmail Limits:\n";
echo "   • 500 emails per day (free account)\n";
echo "   • 2000 emails per day (Workspace)\n";
echo "   • Check sent folder in Gmail to monitor\n";
echo "\n";