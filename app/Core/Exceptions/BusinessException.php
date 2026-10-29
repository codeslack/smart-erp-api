<?php

namespace App\Core\Exceptions;

class BusinessException extends BaseApiException
{
    public function __construct(
        string $message,
        ?string $errorCode = 'BUSINESS_ERROR',
        ?array $errors = null
    ) {
        parent::__construct(
            $message,
            422,
            $errorCode ?? 'BUSINESS_ERROR',
            $errors
        );
    }
}
