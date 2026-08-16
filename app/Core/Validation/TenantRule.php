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

        $rule = Rule::unique(
            $table,
            $column
        );

        if ($tenantId = tenant()?->id) {
            $rule->where(
                'tenant_id',
                $tenantId
            );
        }

        return $rule;
    }

    public static function exists(
        string $table,
        string $column = 'id'
    ): Exists {

        $rule = Rule::exists(
            $table,
            $column
        );

        if ($tenantId = tenant()?->id) {
            $rule->where(
                'tenant_id',
                $tenantId
            );
        }

        return $rule;
    }

    public static function uniqueIgnore(
        string $table,
        string $column,
        int|string|null $ignoreId
    ): Unique {

        $rule = Rule::unique(
            $table,
            $column
        );

        if ($tenantId = tenant()?->id) {
            $rule->where(
                'tenant_id',
                $tenantId
            );
        }

        if ($ignoreId) {
            $rule->ignore(
                $ignoreId
            );
        }

        return $rule;
    }
}