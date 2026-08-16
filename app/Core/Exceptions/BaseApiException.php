<?php

namespace App\Core\Exceptions;

use Exception;

abstract class BaseApiException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 400,
        protected ?string $errorCode = null
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}