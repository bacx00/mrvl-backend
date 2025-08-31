<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
        $this->middleware('auth:api');
    }

    /**
     * Get current 2FA status for the authenticated user
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $user->hasTwoFactorEnabled(),
                'confirmed' => $user->isTwoFactorConfirmed(),
                'recovery_codes_count' => $this->twoFactorService->getRemainingRecoveryCodesCount($user),
                'is_required' => $user->mustUseTwoFactor(),
                'verified_in_session' => $this->twoFactorService->isVerifiedInSession($user)
            ]
        ]);
    }

    /**
     * Setup 2FA - generate QR code and secret
     */
    public function setup(): JsonResponse
    {
        $user = Auth::user();

        // Only admins can setup 2FA
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is only available for admin accounts'
            ], 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is already enabled for this account'
            ], 400);
        }

        $setupData = $this->twoFactorService->setupTwoFactor($user);

        return response()->json([
            'success' => true,
            'message' => '2FA setup initiated. Scan the QR code with your authenticator app.',
            'data' => [
                'secret' => $setupData['secret'],
                'qr_code_url' => $setupData['qr_code_url'],
                'qr_code_image' => $setupData['qr_code_image']
            ]
        ]);
    }

    /**
     * Enable 2FA after verifying the setup code
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|min:6|max:6'
        ]);

        $user = Auth::user();

        // Only admins can enable 2FA
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is only available for admin accounts'
            ], 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is already enabled for this account'
            ], 400);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Please setup 2FA first by calling the setup endpoint'
            ], 400);
        }

        $success = $this->twoFactorService->enableTwoFactor($user, $request->code);

        if (!$success) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid']
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '2FA has been successfully enabled',
            'data' => [
                'recovery_codes' => $user->two_factor_recovery_codes,
                'recovery_codes_count' => count($user->two_factor_recovery_codes)
            ]
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this account'
            ], 400);
        }

        // Verify current code or recovery code before disabling
        if (!$this->twoFactorService->verifyLoginCode($user, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid']
            ]);
        }

        // Allow admins to disable 2FA (removed restriction)
        $this->twoFactorService->disableTwoFactor($user);

        return response()->json([
            'success' => true,
            'message' => '2FA has been successfully disabled'
        ]);
    }

    /**
     * Verify 2FA code (for login or session verification)
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this account'
            ], 400);
        }

        $success = $this->twoFactorService->verifyLoginCode($user, $request->code);

        if (!$success) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid']
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '2FA code verified successfully',
            'data' => [
                'verified' => true,
                'expires_at' => now()->addHours(24)->toISOString()
            ]
        ]);
    }

    /**
     * Get recovery codes
     */
    public function getRecoveryCodes(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this account'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'recovery_codes' => $user->two_factor_recovery_codes ?? [],
                'recovery_codes_count' => count($user->two_factor_recovery_codes ?? [])
            ]
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this account'
            ], 400);
        }

        // Verify current code before regenerating recovery codes
        if (!$this->twoFactorService->verifyLoginCode($user, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid']
            ]);
        }

        $recoveryCodes = $this->twoFactorService->regenerateRecoveryCodes($user);

        return response()->json([
            'success' => true,
            'message' => 'Recovery codes regenerated successfully',
            'data' => [
                'recovery_codes' => $recoveryCodes,
                'recovery_codes_count' => count($recoveryCodes)
            ]
        ]);
    }

    /**
     * Check if user needs 2FA verification
     */
    public function needsVerification(): JsonResponse
    {
        $user = Auth::user();

        $needsVerification = $this->twoFactorService->needsVerification($user);

        return response()->json([
            'success' => true,
            'data' => [
                'needs_verification' => $needsVerification,
                'is_required' => $user->mustUseTwoFactor(),
                'enabled' => $user->hasTwoFactorEnabled()
            ]
        ]);
    }
}