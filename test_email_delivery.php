<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "\n📧 Testing Email Delivery for Password Reset\n";
echo "============================================\n\n";

$email = 'jhonnyaraya7@gmail.com';

// Configure mail to bypass SSL
config([
    'mail.mailers.smtp.stream' => [
        'ssl' => [
            'allow_self_signed' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]
]);

// Set stream context
stream_context_set_default([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

try {
    $testMessage = '
    <html>
    <body>
        <h1>Test Email</h1>
        <p>This is a test email to verify mail delivery is working.</p>
        <p>If you receive this, the password reset emails should work too.</p>
    </body>
    </html>';

    Mail::html($testMessage, function($msg) use ($email) {
        $msg->to($email)
            ->subject('Test Email - MRVL Platform')
            ->from('m4rvl.net@gmail.com', 'MRVL Tournament Platform');
    });

    echo "✅ Test email sent successfully to: $email\n";
    echo "📬 Check your inbox (and spam folder)\n";

} catch (\Exception $e) {
    echo "❌ Error sending email: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}