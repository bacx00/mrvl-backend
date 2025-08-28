<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SensitiveOperationRateLimit
{
    /**
     * Handle an incoming request for sensitive operations.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $key = 'default', int $maxAttempts = 5, int $decayMinutes = 1): Response
    {
        // Create a unique key for this user/IP and operation
        $rateLimitKey = sprintf(
            '%s:%s:%s',
            $key,
            $request->user() ? $request->user()->id : $request->ip(),
            $request->path()
        );

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Too many attempts. Please try again in %d seconds.',
                    $seconds
                ),
                'retry_after' => $seconds
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

        return $next($request);
    }
}