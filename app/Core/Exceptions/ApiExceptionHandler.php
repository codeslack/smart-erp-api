<?php

namespace App\Core\Exceptions;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ApiExceptionHandler
{
    public function render(Throwable $exception): JsonResponse
    {
        // 401 Unauthenticated
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'code'    => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
                'errors'  => null,
            ], 401);
        }

        // 403 Forbidden / Unauthorized Action
        if (
            $exception instanceof AuthorizationException
            || $exception instanceof AccessDeniedHttpException
        ) {
            return response()->json([
                'success' => false,
                'code'    => 'FORBIDDEN',
                'message' => $exception->getMessage() ?: 'This action is unauthorized.',
                'errors'  => null,
            ], 403);
        }

        // 422 Validation Failure
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'code'    => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors'  => $exception->errors(),
            ], 422);
        }

        // Domain & Business Exceptions
        if ($exception instanceof BaseApiException) {
            return response()->json([
                'success' => false,
                'code'    => $exception->errorCode() ?? $this->defaultCodeForStatus($exception->status()),
                'message' => $exception->getMessage(),
                'errors'  => $exception->errors(),
            ], $exception->status());
        }

        // 404 Not Found (Route Model Binding or Eloquent ModelNotFoundException)
        if (
            $exception instanceof NotFoundHttpException
            || $exception instanceof ModelNotFoundException
        ) {
            return response()->json([
                'success' => false,
                'code'    => 'RESOURCE_NOT_FOUND',
                'message' => 'Resource not found.',
                'errors'  => null,
            ], 404);
        }

        // Generic HTTP Exceptions (e.g. abort(409), abort(429), MethodNotAllowedHttpException)
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return response()->json([
                'success' => false,
                'code'    => $this->defaultCodeForStatus($status),
                'message' => $exception->getMessage() ?: "HTTP error {$status}.",
                'errors'  => null,
            ], $status);
        }

        // 500 Internal Server Error
        return response()->json([
            'success' => false,
            'code'    => 'INTERNAL_SERVER_ERROR',
            'message' => config('app.debug')
                ? $exception->getMessage()
                : 'An unexpected error occurred.',
            'errors'  => null,
        ], 500);
    }

    protected function defaultCodeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'BUSINESS_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            default => 'ERROR',
        };
    }
}
