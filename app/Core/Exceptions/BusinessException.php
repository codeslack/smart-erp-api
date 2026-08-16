<?php

namespace App\Core\Exceptions;

class BusinessException extends BaseApiException
{
    public function __construct(
        string $message,
        ?string $errorCode = null
    ) {
        parent::__construct(
            $message,
            422,
            $errorCode
        );
    }
}