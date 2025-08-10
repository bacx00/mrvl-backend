<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class SensitiveOperationRateLimit
{
    /**
     * Handle an incoming request and apply rate limiting for sensitive operations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $operation
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $operation = 'sensitive', int $maxAttempts = 10, int $decayMinutes = 60)
    {
        $user = auth('api')->user();
        $key = $operation . '_' . ($user ? $user->id : $request->ip());
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            // Log rate limit exceeded
            Log::warning('Rate limit exceeded for sensitive operation', [
                'operation' => $operation,
                'user_id' => $user ? $user->id : null,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'route' => $request->route()->getName() ?? $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in ' . $seconds . ' seconds.',
                'retry_after' => $seconds
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        return $next($request);
    }
}