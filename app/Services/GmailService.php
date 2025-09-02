<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class GmailService
{
    public static function sendPasswordResetEmail($to, $resetUrl)
    {
        // Queue the email instead of sending directly
        \DB::table('email_queue')->insert([
            'to' => $to,
            'subject' => 'Password Reset Request - MRVL Tournament Platform',
            'body' => self::getTemplate($resetUrl, $to),
            'sent' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        \Log::info('Password reset email queued for: ' . $to);
        return true;
    }
    
    private static function getTemplate($resetUrl, $email)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; }
        .header { text-align: center; margin-bottom: 30px; }
        .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #dc2626;">MRVL Tournament Platform</h1>
            <h2>Password Reset Request</h2>
        </div>
        <p>Hi there,</p>
        <p>We received a request to reset the password for: <strong>' . htmlspecialchars($email) . '</strong></p>
        <p style="text-align: center;">
            <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset Password</a>
        </p>
        <p>Or copy this link:</p>
        <p style="word-break: break-all; color: #dc2626; font-size: 12px;">' . htmlspecialchars($resetUrl) . '</p>
        <p><strong>This link expires in 60 minutes.</strong></p>
    </div>
</body>
</html>';
    }
}