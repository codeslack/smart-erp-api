<?php

namespace App\Modules\SupplierOpeningBill\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Supplier\Models\Supplier;

class SupplierOpeningBill extends TenantModel
{
    use SoftDeletes;

    protected $table = 'supplier_opening_bills';

    protected $fillable = [

        'supplier_id',

        'bill_no',
        'bill_date',
        'due_date',

        'amount',
        'balance_amount',

        'balance_type',

        'notes',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [

                'bill_date' => 'date',
                'due_date'  => 'date',

                'amount' => 'decimal:4',
                'balance_amount' => 'decimal:4',

                'balance_type' => OpeningBalanceTypeEnum::class,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDebit(): bool
    {
        return $this->balance_type ===
            OpeningBalanceTypeEnum::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->balance_type ===
            OpeningBalanceTypeEnum::CREDIT;
    }

    public function isFullyOutstanding(): bool
    {
        return (float) $this->amount ===
            (float) $this->balance_amount;
    }

    public function paidAmount(): float
    {
        return (float) $this->amount
            - (float) $this->balance_amount;
    }

    public function isOutstanding(): bool
    {
        return (float) $this->balance_amount > 0;
    }
}
