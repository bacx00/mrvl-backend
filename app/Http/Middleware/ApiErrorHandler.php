<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Throwable;

class ApiErrorHandler
{
    /**
     * Handle API errors and return consistent JSON responses
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            return $this->handleException($request, $exception);
        }
    }

    /**
     * Handle different types of exceptions and return appropriate JSON responses
     */
    private function handleException(Request $request, Throwable $exception): JsonResponse
    {
        // Log the error for debugging
        \Log::error('API Error: ' . $exception->getMessage(), [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth('api')->id(),
            'exception' => $exception,
            'trace' => $exception->getTraceAsString()
        ]);

        // Handle specific exception types
        if ($exception instanceof ValidationException) {
            return $this->handleValidationException($exception);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->handleAuthenticationException($exception);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->handleAuthorizationException($exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->handleModelNotFoundException($exception);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->handleNotFoundHttpException($exception);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->handleMethodNotAllowedException($exception);
        }

        if ($exception instanceof QueryException) {
            return $this->handleQueryException($exception);
        }

        // Handle general exceptions
        return $this->handleGeneralException($exception);
    }

    /**
     * Handle validation exceptions
     */
    private function handleValidationException(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $exception->errors(),
            'error_code' => 'VALIDATION_ERROR',
            'timestamp' => now()->toISOString()
        ], 422);
    }

    /**
     * Handle authentication exceptions
     */
    private function handleAuthenticationException(AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Authentication required. Please provide a valid Bearer token.',
            'error_code' => 'UNAUTHENTICATED',
            'timestamp' => now()->toISOString()
        ], 401);
    }

    /**
     * Handle authorization exceptions
     */
    private function handleAuthorizationException(AuthorizationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action.',
            'error_code' => 'UNAUTHORIZED',
            'timestamp' => now()->toISOString()
        ], 403);
    }

    /**
     * Handle model not found exceptions
     */
    private function handleModelNotFoundException(ModelNotFoundException $exception): JsonResponse
    {
        $model = class_basename($exception->getModel());
        
        return response()->json([
            'success' => false,
            'message' => "{$model} not found",
            'error_code' => 'RESOURCE_NOT_FOUND',
            'resource_type' => strtolower($model),
            'timestamp' => now()->toISOString()
        ], 404);
    }

    /**
     * Handle HTTP not found exceptions
     */
    private function handleNotFoundHttpException(NotFoundHttpException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Endpoint not found',
            'error_code' => 'ENDPOINT_NOT_FOUND',
            'timestamp' => now()->toISOString()
        ], 404);
    }

    /**
     * Handle method not allowed exceptions
     */
    private function handleMethodNotAllowedException(MethodNotAllowedHttpException $exception): JsonResponse
    {
        $allowedMethods = implode(', ', $exception->getAllowedMethods());
        
        return response()->json([
            'success' => false,
            'message' => 'Method not allowed',
            'error_code' => 'METHOD_NOT_ALLOWED',
            'allowed_methods' => $allowedMethods,
            'timestamp' => now()->toISOString()
        ], 405);
    }

    /**
     * Handle database query exceptions
     */
    private function handleQueryException(QueryException $exception): JsonResponse
    {
        $errorInfo = $exception->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? 'Unknown';
        
        // Common SQL error codes
        $errorMessages = [
            '23000' => 'Duplicate entry or constraint violation',
            '42S02' => 'Table or view not found',
            '42S22' => 'Column not found',
            '23502' => 'Not null constraint violation',
            '23503' => 'Foreign key constraint violation',
        ];

        $message = $errorMessages[$sqlState] ?? 'Database operation failed';
        
        // In production, don't expose SQL details
        $errorDetails = app()->environment('local') ? [
            'sql_state' => $sqlState,
            'sql_message' => $exception->getMessage(),
        ] : [];

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'DATABASE_ERROR',
            'sql_state' => $sqlState,
            'details' => $errorDetails,
            'timestamp' => now()->toISOString()
        ], 500);
    }

    /**
     * Handle general exceptions
     */
    private function handleGeneralException(Throwable $exception): JsonResponse
    {
        $statusCode = method_exists($exception, 'getStatusCode') 
            ? $exception->getStatusCode() 
            : 500;

        // Don't expose internal errors in production
        $message = app()->environment('local') 
            ? $exception->getMessage()
            : 'An unexpected error occurred';

        $errorDetails = app()->environment('local') ? [
            'exception_type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ] : [];

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'INTERNAL_ERROR',
            'details' => $errorDetails,
            'timestamp' => now()->toISOString()
        ], $statusCode);
    }
}