<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SimpleMailService
{
    public static function sendPasswordReset($email, $resetUrl)
    {
        try {
            // First, try using PHPMailer if available
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return self::sendWithPHPMailer($email, $resetUrl);
            }
            
            // Fallback to simple mail
            return self::sendWithMail($email, $resetUrl);
            
        } catch (\Exception $e) {
            \Log::error('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private static function sendWithPHPMailer($email, $resetUrl)
    {
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
            $mail->SMTPDebug = 0; // Disable debug output
            
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
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - MRVL Tournament Platform';
            $mail->Body    = self::getEmailTemplate($resetUrl, $email);
            
            $mail->send();
            \Log::info('Email sent successfully via PHPMailer to: ' . $email);
            return true;
            
        } catch (Exception $e) {
            \Log::error('PHPMailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
    
    private static function sendWithMail($email, $resetUrl)
    {
        $to = $email;
        $subject = 'Password Reset Request - MRVL Tournament Platform';
        $message = self::getEmailTemplate($resetUrl, $email);
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: MRVL Tournament Platform <m4rvl.net@gmail.com>',
            'Reply-To: m4rvl.net@gmail.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            \Log::info('Email sent successfully via mail() to: ' . $email);
        } else {
            \Log::error('Failed to send email via mail() to: ' . $email);
        }
        
        return $result;
    }
    
    private static function getEmailTemplate($resetUrl, $email)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 20px; text-align: center; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>MRVL Tournament Platform</h1>
            <p>Password Reset Request</p>
        </div>
        <div class="content">
            <p>Hi there,</p>
            <p>We received a request to reset the password for your account associated with <strong>' . htmlspecialchars($email) . '</strong>.</p>
            <p>Click the button below to reset your password:</p>
            <div style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset Password</a>
            </div>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #dc2626;">' . htmlspecialchars($resetUrl) . '</p>
            <p><strong>This link will expire in 60 minutes.</strong></p>
            <p>If you didn\'t request this reset, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 MRVL Tournament Platform. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }
}