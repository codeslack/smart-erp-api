<?php

namespace App\Modules\SystemNumber\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\SystemNumber\Models\SystemNumber;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

class SystemNumberService
{
    public function next(
        SystemNumberTypeEnum $type,
        ?int $tenantId = null
    ): string {

        return DB::transaction(
            function () use ($type, $tenantId) {

                $tenantId = $type->isGlobal()
                    ? null
                    : ($tenantId ?? tenantId());

                logger()->info(
                    'System Number',
                    [
                        'type' => $type->value,
                        'tenant_id' => $tenantId,
                    ]
                );

                $sequence = SystemNumber::query()

                    ->lockForUpdate()

                    ->firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'type'      => $type->value,
                        ],
                        [
                            'current_number' => 0,
                        ]
                    );

                $sequence->increment(
                    'current_number'
                );

                $sequence->refresh();

                return sprintf(
                    '%s-%06d',
                    $type->prefix(),
                    $sequence->current_number
                );
            }
        );
    }
}