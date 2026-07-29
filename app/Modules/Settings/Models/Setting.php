<?php

namespace App\Modules\Settings\Models;

use App\Core\Models\TenantModel;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends TenantModel
{
    protected $table = 'settings';

    public const GROUP_COMPANY = 'company';

    public const GROUP_INVENTORY = 'inventory';

    public const GROUP_SALES = 'sales';

    public const GROUP_PURCHASE = 'purchase';

    public const GROUP_ACCOUNTING = 'accounting';

    public const GROUP_TAX = 'tax';

    protected function casts(): array
    {
        return [];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}
