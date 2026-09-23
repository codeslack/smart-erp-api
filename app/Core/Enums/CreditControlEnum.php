<?php

namespace App\Core\Enums;

enum CreditControlEnum: string
{
    case NONE = 'none';

    case WARN = 'warn';

    case BLOCK = 'block';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'No Control',
            self::WARN => 'Warning',
            self::BLOCK => 'Block',
        };
    }

    public function shouldWarn(): bool
    {
        return $this === self::WARN;
    }

    public function shouldBlock(): bool
    {
        return $this === self::BLOCK;
    }
}