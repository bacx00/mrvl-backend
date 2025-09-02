#!/usr/bin/php
<?php
// This runs from CLI where email works
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get unsent emails from queue
$emails = DB::table('email_queue')
    ->where('sent', false)
    ->orderBy('created_at', 'asc')
    ->limit(10)
    ->get();

foreach ($emails as $email) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'm4rvl.net@gmail.com';
        $mail->Password   = 'eruj qhms jhaa mhyp';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->SMTPDebug = 0;
        
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
        $mail->addAddress($email->to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $email->subject;
        $mail->Body    = $email->body;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Add plain text alternative for maximum compatibility
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $email->body));
        $mail->AltBody = $plainText;
        
        $mail->send();
        
        // Mark as sent
        DB::table('email_queue')
            ->where('id', $email->id)
            ->update([
                'sent' => true,
                'sent_at' => now()
            ]);
            
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Sent email to: {$email->to}\n";
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ Failed to send to {$email->to}: {$mail->ErrorInfo}\n";
    }
}

if ($emails->isEmpty()) {
    echo "[" . date('Y-m-d H:i:s') . "] No emails in queue\n";
}