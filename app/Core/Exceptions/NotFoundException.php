<?php

namespace App\Core\Exceptions;

class NotFoundException extends BaseApiException
{
    public function __construct(
        string $message = 'Resource not found.',
        ?string $errorCode = null
    ) {
        parent::__construct(
            $message,
            404,
            $errorCode
        );
    }
}