<?php

namespace App\Core\Exceptions;

use Throwable;
use App\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ApiExceptionHandler
{
    use ApiResponse;

    public function render(
        Throwable $exception
    ): JsonResponse {

        if ($exception instanceof ValidationException) {
            return $this->error(
                'Validation failed.',
                $exception->errors(),
                422
            );
        }

        if ($exception instanceof BusinessException) {
            return $this->error(
                $exception->getMessage(),
                null,
                422
            );
        }

        if ($exception instanceof NotFoundException) {
            return $this->error(
                $exception->getMessage(),
                null,
                404
            );
        }

        return $this->error(
            config('app.debug')
                ? $exception->getMessage()
                : 'Server error.',
            null,
            500
        );
    }
}