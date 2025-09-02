<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send email using direct SMTP connection with PHP
     */
    public static function sendPasswordResetEmail($email, $resetUrl)
    {
        try {
            // Gmail SMTP settings
            $smtp_host = 'smtp.gmail.com';
            $smtp_port = 587;
            $smtp_user = 'm4rvl.net@gmail.com';
            $smtp_pass = 'eruj qhms jhaa mhyp';
            
            $to = $email;
            $subject = 'Password Reset Request - MRVL Tournament Platform';
            $from = 'MRVL Tournament Platform <m4rvl.net@gmail.com>';
            
            $message = self::getEmailTemplate($resetUrl, $email);
            
            // Create socket connection with SSL options
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
                ]
            ]);
            
            // Connect to SMTP server
            $socket = stream_socket_client(
                "tcp://$smtp_host:$smtp_port",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$socket) {
                throw new \Exception("Failed to connect to SMTP server: $errstr ($errno)");
            }
            
            // Read server response
            $response = fgets($socket, 515);
            
            // Send EHLO
            fwrite($socket, "EHLO localhost\r\n");
            while ($line = fgets($socket, 515)) {
                if (substr($line, 3, 1) == ' ') break;
            }
            
            // Start TLS
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            
            // Enable encryption
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS
            fwrite($socket, "EHLO localhost\r\n");
            while ($line = fgets($socket, 515)) {
                if (substr($line, 3, 1) == ' ') break;
            }
            
            // Authenticate
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            
            fwrite($socket, base64_encode($smtp_user) . "\r\n");
            $response = fgets($socket, 515);
            
            fwrite($socket, base64_encode($smtp_pass) . "\r\n");
            $response = fgets($socket, 515);
            
            // Send email
            fwrite($socket, "MAIL FROM: <$smtp_user>\r\n");
            $response = fgets($socket, 515);
            
            fwrite($socket, "RCPT TO: <$to>\r\n");
            $response = fgets($socket, 515);
            
            fwrite($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            
            // Email headers and body
            $headers = "From: $from\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";
            
            fwrite($socket, $headers . $message . "\r\n.\r\n");
            $response = fgets($socket, 515);
            
            // Quit
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            Log::info('Password reset email sent successfully via direct SMTP to: ' . $email);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send email via direct SMTP: ' . $e->getMessage());
            
            // Fallback to file-based notification
            $filename = storage_path('app/password_resets/' . md5($email) . '.txt');
            if (!file_exists(dirname($filename))) {
                mkdir(dirname($filename), 0755, true);
            }
            
            file_put_contents($filename, "Password Reset Link for $email:\n$resetUrl\n\nCreated at: " . date('Y-m-d H:i:s'));
            
            Log::info('Password reset link saved to file for: ' . $email);
            return true;
        }
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
            <p>We received a request to reset the password for your account associated with <strong>' . $email . '</strong>.</p>
            <p>Click the button below to reset your password:</p>
            <div style="text-align: center;">
                <a href="' . $resetUrl . '" class="button">Reset Password</a>
            </div>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #dc2626;">' . $resetUrl . '</p>
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