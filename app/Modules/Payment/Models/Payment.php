<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

class Payment extends TenantModel
{
    use SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'payment_no',
        'payment_type',
        'payment_date',
        'party_type',
        'party_id',
        'account_id',
        'amount',
        'payment_method',
        'reference_no',
        'notes',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
        'payment_type' => PaymentTypeEnum::class,
        'party_type' => PaymentPartyTypeEnum::class,
        'payment_method' => PaymentMethodEnum::class,
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'party_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'party_id');
    }

    public function isReceipt(): bool
    {
        return $this->payment_type === PaymentTypeEnum::RECEIPT;
    }

    public function isPayment(): bool
    {
        return $this->payment_type === PaymentTypeEnum::PAYMENT;
    }
}
