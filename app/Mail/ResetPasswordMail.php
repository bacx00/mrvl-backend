<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;
    public $token;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param string $token
     */
    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
        $this->url = $this->generateResetUrl($token);
    }

    /**
     * Generate the password reset URL
     *
     * @param string $token
     * @return string
     */
    protected function generateResetUrl(string $token): string
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        
        // Use query parameters for better compatibility
        return sprintf(
            '%s/reset-password?token=%s&email=%s',
            $frontendUrl,
            urlencode($token),
            urlencode($this->user->email)
        );
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Password Reset Request - MRVL Tournament Platform')
            ->view('emails.auth.password-reset')
            ->with([
                'user' => $this->user,
                'url' => $this->url,
                'token' => $this->token,
                'expires_in' => config('auth.passwords.users.expire', 60)
            ]);
    }
}