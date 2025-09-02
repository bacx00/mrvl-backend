<?php
// This script runs from CLI where email works
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\SimpleMailService;

// Check for pending password resets
$pendingResets = \DB::table('password_reset_tokens')
    ->where('email_sent', false)
    ->where('created_at', '>', now()->subHour())
    ->get();

foreach ($pendingResets as $reset) {
    $resetUrl = config('app.frontend_url', 'https://staging.mrvl.net') . 
                '/#reset-password?token=' . $reset->plain_token . 
                '&email=' . urlencode($reset->email);
    
    if (SimpleMailService::sendPasswordReset($reset->email, $resetUrl)) {
        \DB::table('password_reset_tokens')
            ->where('id', $reset->id)
            ->update(['email_sent' => true]);
        echo "✅ Sent email to: {$reset->email}\n";
    }
}