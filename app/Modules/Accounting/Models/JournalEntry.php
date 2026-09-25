<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Core\Models\TenantModel;

use App\Modules\User\Models\User;

class JournalEntry extends TenantModel
{
    protected $table = 'journal_entries';

    protected $fillable = [

        'voucher_no',
        'voucher_type',

        'reference_type',
        'reference_id',
        'reversal_of_journal_entry_id',

        'entry_date',

        'description',

        'status',

        'created_by',
    ];

    protected $attributes = [
        'status' => JournalEntryStatusEnum::DRAFT->value,
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'voucher_type' => JournalVoucherTypeEnum::class,
                'status' => JournalEntryStatusEnum::class,
                'entry_date' => 'date',
            ]
        );
    }

    public function lines(): HasMany
    {
        return $this->hasMany(
            JournalEntryLine::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(
            AccountLedger::class
        );
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reversal_of_journal_entry_id'
        );
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(
            self::class,
            'reversal_of_journal_entry_id'
        );
    }
}
