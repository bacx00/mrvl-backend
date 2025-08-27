<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AuthController;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

echo "🔐 Direct 2FA Setup Test\n";
echo "========================\n\n";

try {
    // Create a test temp token 
    $tempToken = 'test-' . bin2hex(random_bytes(16));
    
    // Store temp token data
    Cache::put("temp_login_{$tempToken}", [
        'user_id' => 1,
        'email' => 'jhonny@ar-mediia.com',
        'requires_setup' => true
    ], now()->addMinutes(10));
    
    echo "✅ Created temp token: $tempToken\n\n";
    
    // Test TwoFactorService directly
    $service = new TwoFactorService();
    $secret = $service->generateSecret();
    
    echo "✅ Generated secret: $secret\n\n";
    
    // Test QR code generation
    $user = \App\Models\User::find(1);
    echo "✅ Found user: {$user->email}\n\n";
    
    $qrUrl = $service->generateQrCodeUrl($user, $secret);
    echo "✅ QR Code URL: $qrUrl\n\n";
    
    try {
        $qrImage = $service->generateQrCodeImage($user, $secret);
        echo "✅ QR Code Image generated (length: " . strlen($qrImage) . " chars)\n\n";
    } catch (Exception $e) {
        echo "❌ QR Code generation failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "🎉 All components working! The issue might be routing.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>