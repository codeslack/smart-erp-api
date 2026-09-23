<?php

namespace App\Modules\CustomerOpeningBill\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Customer\Models\Customer;

class CustomerOpeningBill extends TenantModel
{
    use SoftDeletes;

    protected $table = 'customer_opening_bills';

    protected $fillable = [

        'customer_id',

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
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
}