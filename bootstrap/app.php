<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Trust proxies for reverse proxy setup
        $middleware->trustProxies(at: '*');
        $middleware->trustHosts(at: ['staging.mrvl.net']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please provide a valid authentication token.',
                    'error' => 'Authentication required'
                ], 401);
            }
        });
        
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson()) {
                // Log the actual error
                \Log::error('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                
                return response()->json([
                    'success' => false,
                    'message' => app()->environment('production') ? 'Server Error' : $e->getMessage(),
                    'error' => app()->environment('production') ? null : class_basename($e),
                    'file' => app()->environment('production') ? null : $e->getFile(),
                    'line' => app()->environment('production') ? null : $e->getLine()
                ], 500);
            }
        });
    })->create();
