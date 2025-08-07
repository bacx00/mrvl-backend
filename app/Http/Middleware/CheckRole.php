<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request and check user role permissions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $roles)
    {
        // Check if user is authenticated
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please log in to access this resource.',
                'error' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::guard('api')->user();
        
        // Parse allowed roles (can be pipe-separated like 'admin|moderator')
        $allowedRoles = explode('|', $roles);
        
        // Check if user has required role
        if (!$user->hasAnyRole($allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. You need one of the following roles: ' . implode(', ', $allowedRoles),
                'error' => 'Forbidden',
                'required_roles' => $allowedRoles,
                'user_role' => $user->role
            ], 403);
        }

        return $next($request);
    }
}