<?php

namespace App\Core\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class TenantRule
{
    public static function unique(
        string $table,
        string $column
    ): Unique {

        return Rule::unique(
            $table,
            $column
        )->where(
            'tenant_id',
            tenantId()
        );
    }

    public static function uniqueIgnore(
        string $table,
        string $column,
        int|string $ignoreId
    ): Unique {

        return Rule::unique(
            $table,
            $column
        )
            ->where(
                'tenant_id',
                tenantId()
            )
            ->ignore(
                $ignoreId
            );
    }

    public static function exists(
        string $table,
        string $column = 'id'
    ): Exists {

        return Rule::exists(
            $table,
            $column
        )->where(
            'tenant_id',
            tenantId()
        );
    }
}