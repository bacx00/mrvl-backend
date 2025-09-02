<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\GmailService;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        try {
            // Delete old tokens
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            // Create new token
            $token = Str::random(64);
            
            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => Carbon::now()
            ]);

            // Create reset URL
            $resetUrl = config('app.frontend_url', 'https://staging.mrvl.net') . 
                       '/#reset-password?token=' . $token . '&email=' . urlencode($request->email);

            // Send email using simple PHP mail function to bypass SSL issues
            $to = $request->email;
            $subject = 'Password Reset Request - MRVL Tournament Platform';
            $headers = "From: MRVL Tournament Platform <m4rvl.net@gmail.com>\r\n";
            $headers .= "Reply-To: m4rvl.net@gmail.com\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $message = $this->getEmailTemplate($resetUrl, $request->email);
            
            // Configure mail transport to bypass SSL verification
            config([
                'mail.mailers.smtp.stream' => [
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]
            ]);

            // Set stream context for SSL bypass
            stream_context_set_default([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            // Send email using Gmail service
            $emailSent = GmailService::sendPasswordResetEmail($request->email, $resetUrl);
            
            if (!$emailSent) {
                \Log::warning('Email could not be sent, but reset link was generated for: ' . $request->email);
            }

            // Check if email was sent successfully
            if ($emailSent) {
                $response = [
                    'success' => true,
                    'message' => 'Password reset link sent to your email address. Please check your inbox.'
                ];
            } else {
                // If email failed, include the link
                $response = [
                    'success' => true,
                    'message' => 'Email delivery failed. Please use the link below to reset your password:',
                    'reset_link' => $resetUrl,
                    'note' => 'Copy this link to reset your password. Link expires in 60 minutes.'
                ];
            }
            
            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Password reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link. Please try again later.'
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed'
        ]);

        try {
            // Find the token
            $tokenRecord = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$tokenRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Check if token matches
            if (!Hash::check($request->token, $tokenRecord->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Check if token is expired (60 minutes)
            if (Carbon::parse($tokenRecord->created_at)->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Reset token has expired. Please request a new one.'
                ], 400);
            }

            // Update the user's password
            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Password reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    private function getEmailTemplate($resetUrl, $email)
    {
        return '
<!DOCTYPE html>
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