<?php

namespace App\Modules\Sales\Enums;

class SaleStatus
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';
    public const APPROVED = 'confirmed'; // backward compatibility
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
}
