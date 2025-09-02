<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class MailService
{
    /**
     * Send password reset link with SSL bypass
     */
    public static function sendPasswordResetLink($email)
    {
        // Set stream context to bypass SSL verification
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Apply the context globally for this request
        stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Send the password reset link
        return Password::sendResetLink(['email' => $email]);
    }
}