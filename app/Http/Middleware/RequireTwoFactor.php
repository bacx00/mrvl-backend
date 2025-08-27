<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\TwoFactorService;

class RequireTwoFactor
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // For admin users, 2FA is enforced at login level
        // This middleware just ensures they have completed the 2FA flow
        if ($user->mustUseTwoFactor() && !$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '2FA must be enabled for admin accounts. Please login again to set up 2FA.',
                'error_code' => '2FA_REQUIRED',
                'data' => [
                    'requires_login' => true
                ]
            ], 403);
        }

        return $next($request);
    }
}