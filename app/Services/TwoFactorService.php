<?php

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Cache;

class TwoFactorService
{
    protected Google2FA $google2fa;
    
    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a secret for a user
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Generate QR code URL for setup
     */
    public function generateQrCodeUrl(User $user, string $secret): string
    {
        $companyName = config('app.name', 'MRVL Platform');
        $companyEmail = $user->email;
        
        return $this->google2fa->getQRCodeUrl(
            $companyName,
            $companyEmail,
            $secret
        );
    }

    /**
     * Generate QR code as base64 image
     */
    public function generateQrCodeImage(User $user, string $secret): string
    {
        $companyName = config('app.name', 'MRVL Platform');
        $qrCodeUrl = $this->generateQrCodeUrl($user, $secret);
        
        $qrCode = new QrCode($qrCodeUrl);
        
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        return 'data:image/png;base64,' . base64_encode($result->getString());
    }

    /**
     * Verify a 2FA code
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        // Remove any spaces or formatting from the code
        $code = preg_replace('/\s+/', '', $code);
        
        return $this->google2fa->verifyKey($secret, $code, $window);
    }

    /**
     * Enable 2FA for a user
     */
    public function enableTwoFactor(User $user, string $code): bool
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        if (!$this->verifyCode($user->two_factor_secret, $code)) {
            return false;
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now()
        ]);

        // Generate recovery codes
        $user->generateRecoveryCodes();

        // Clear any cached 2FA data
        Cache::forget("2fa_setup_{$user->id}");

        return true;
    }

    /**
     * Disable 2FA for a user
     */
    public function disableTwoFactor(User $user): bool
    {
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null
        ]);

        // Clear cache
        Cache::forget("2fa_setup_{$user->id}");
        Cache::forget("2fa_verified_{$user->id}");

        return true;
    }

    /**
     * Setup 2FA for a user (generate secret but don't enable yet)
     */
    public function setupTwoFactor(User $user): array
    {
        $secret = $this->generateSecret();
        
        // Store the secret temporarily (not enabled yet)
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null
        ]);

        $qrCodeUrl = $this->generateQrCodeUrl($user, $secret);
        $qrCodeImage = $this->generateQrCodeImage($user, $secret);

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_image' => $qrCodeImage
        ];
    }

    /**
     * Verify 2FA during login (no session caching - required every time)
     */
    public function verifyLoginCode(User $user, string $code): bool
    {
        if (!$user->hasTwoFactorEnabled()) {
            return false;
        }

        // Try to verify with TOTP first
        if ($this->verifyCode($user->two_factor_secret, $code)) {
            return true;
        }

        // Try recovery code as fallback
        if ($user->useRecoveryCode($code)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has verified 2FA in current session
     */
    public function isVerifiedInSession(User $user): bool
    {
        return Cache::has("2fa_verified_{$user->id}");
    }

    /**
     * Clear 2FA verification for user session
     */
    public function clearSessionVerification(User $user): void
    {
        Cache::forget("2fa_verified_{$user->id}");
    }

    /**
     * Check if user needs 2FA verification
     */
    public function needsVerification(User $user): bool
    {
        if (!$user->hasTwoFactorEnabled()) {
            return false;
        }

        return !$this->isVerifiedInSession($user);
    }

    /**
     * Get remaining recovery codes count
     */
    public function getRemainingRecoveryCodesCount(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        return $user->generateRecoveryCodes();
    }
}