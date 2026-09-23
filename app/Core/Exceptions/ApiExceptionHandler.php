<?php

namespace App\Core\Exceptions;

use Throwable;
use Illuminate\Support\Facades\Log;
use App\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiExceptionHandler
{
    use ApiResponse;

    public function render(
        Throwable $exception
    ): JsonResponse {

        if ($exception instanceof AuthenticationException) {

            return response()->json([
                'success' => false,
                'code'    => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
                'errors'  => null,
            ], 401);
        }

        if ($exception instanceof ValidationException) {

            return response()->json([
                'success' => false,
                'code'    => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors'  => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof BaseApiException) {

            return response()->json([
                'success' => false,
                'code'    => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'errors'  => null,
            ], $exception->status());
        }

        if (
            $exception instanceof NotFoundHttpException &&
            $exception->getPrevious() instanceof ModelNotFoundException
        ) {

            return response()->json([
                'success' => false,
                'code'    => 'RESOURCE_NOT_FOUND',
                'message' => 'Resource not found.',
                'errors'  => null,
            ], 404);
        }

        Log::error($exception);

        return response()->json([
            'success' => false,
            'code'    => 'INTERNAL_SERVER_ERROR',
            'message' => config('app.debug')
                ? $exception->getMessage()
                : 'An unexpected error occurred.',
            'errors'  => null,
        ], 500);
    }
}