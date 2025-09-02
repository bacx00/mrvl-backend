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
        // Outlook-compatible HTML email template
        // Uses tables for layout (required for Outlook)
        // Inline styles only (no style tags for Outlook)
        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Password Reset - MRVL Tournament Platform</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: Arial, sans-serif;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <!-- Main Container Table -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px;">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding: 40px 20px; background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); background-color: #dc2626; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: bold;">MRVL Tournament Platform</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 18px;">Password Reset Request</p>
                        </td>
                    </tr>
                    
                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="color: #333333; font-size: 16px; line-height: 24px;">
                                        <p style="margin: 0 0 20px 0;">Hi there,</p>
                                        <p style="margin: 0 0 30px 0;">We received a request to reset the password for your account associated with <strong style="color: #000000;">' . htmlspecialchars($email) . '</strong></p>
                                    </td>
                                </tr>
                                
                                <!-- Button -->
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . htmlspecialchars($resetUrl) . '" style="height:50px;v-text-anchor:middle;width:250px;" arcsize="10%" stroke="f" fillcolor="#dc2626">
                                            <w:anchorlock/>
                                            <center>
                                        <![endif]-->
                                        <a href="' . htmlspecialchars($resetUrl) . '" style="background-color: #dc2626; color: #ffffff; display: inline-block; font-size: 16px; font-weight: bold; line-height: 50px; text-align: center; text-decoration: none; width: 250px; -webkit-text-size-adjust: none; border-radius: 5px;">Reset Password</a>
                                        <!--[if mso]>
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                    </td>
                                </tr>
                                
                                <!-- Alternative Link -->
                                <tr>
                                    <td style="color: #666666; font-size: 14px; line-height: 20px; padding-top: 20px;">
                                        <p style="margin: 0 0 10px 0;">If the button doesn\'t work, copy and paste this link into your browser:</p>
                                        <p style="margin: 0 0 20px 0; word-break: break-all; color: #dc2626; font-size: 12px;">
                                            <a href="' . htmlspecialchars($resetUrl) . '" style="color: #dc2626; text-decoration: underline;">' . htmlspecialchars($resetUrl) . '</a>
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Warning -->
                                <tr>
                                    <td style="color: #333333; font-size: 14px; line-height: 20px; padding-top: 20px; border-top: 1px solid #eeeeee;">
                                        <p style="margin: 20px 0 0 0;"><strong>This link will expire in 60 minutes.</strong></p>
                                        <p style="margin: 10px 0 0 0; color: #666666;">If you didn\'t request this password reset, you can safely ignore this email.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 30px; background-color: #f9fafb; border-radius: 0 0 8px 8px;">
                            <p style="margin: 0; color: #999999; font-size: 12px;">&copy; 2025 MRVL Tournament Platform. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}