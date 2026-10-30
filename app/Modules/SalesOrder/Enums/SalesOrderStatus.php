<?php

namespace App\Modules\SalesOrder\Enums;

class SalesOrderStatus
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';
    public const APPROVED = 'confirmed'; // backward compatibility
    public const PARTIALLY_DELIVERED = 'partially_delivered';
    public const DELIVERED = 'delivered';
    public const CONVERTED_TO_DELIVERY = 'partially_delivered'; // backward compatibility
    public const CONVERTED_TO_SALE = 'delivered'; // backward compatibility
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';
}
