<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Password;

echo "\n📧 Testing Forgot Password Functionality\n";
echo "=========================================\n\n";

$email = 'jhonnyaraya7@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ User not found: $email\n";
    exit(1);
}

echo "✅ User found: {$user->email}\n";
echo "📤 Sending password reset email...\n\n";

try {
    $status = Password::sendResetLink(['email' => $email]);
    
    if ($status === Password::RESET_LINK_SENT) {
        echo "✅ Password reset email sent successfully!\n";
        echo "📧 Check inbox for: $email\n";
        echo "📨 From: MRVL Tournament Platform\n";
        echo "🔗 The email contains a reset link valid for 60 minutes\n";
    } else {
        echo "❌ Failed to send reset email\n";
        echo "Status: $status\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";