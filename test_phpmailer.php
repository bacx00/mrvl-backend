<?php
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "\n📧 Testing PHPMailer Email Delivery\n";
echo "=====================================\n\n";

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'm4rvl.net@gmail.com';
    $mail->Password   = 'eruj qhms jhaa mhyp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Disable SSL verification
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Recipients
    $mail->setFrom('m4rvl.net@gmail.com', 'MRVL Tournament Platform');
    $mail->addAddress('jhonnyaraya7@gmail.com');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from MRVL Platform';
    $mail->Body    = '<h1>Test Email</h1><p>If you receive this, PHPMailer is working!</p>';
    $mail->AltBody = 'Test email - If you receive this, PHPMailer is working!';
    
    $mail->send();
    echo "\n✅ Message has been sent successfully!\n";
    echo "📬 Check your inbox for the test email.\n";
    
} catch (Exception $e) {
    echo "\n❌ Message could not be sent.\n";
    echo "Mailer Error: {$mail->ErrorInfo}\n";
}