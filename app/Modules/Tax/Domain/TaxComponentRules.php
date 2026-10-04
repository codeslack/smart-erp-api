<?php

namespace App\Modules\Tax\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Enums\TaxComponentTypeEnum;

class TaxComponentRules
{
    public function validateComponentType(
        string $componentType
    ): void {
        $validTypes = array_map(
            static fn (
                TaxComponentTypeEnum $type
            ) => $type->value,
            TaxComponentTypeEnum::cases()
        );

        if (! in_array(
            $componentType,
            $validTypes,
            true
        )) {
            throw new BusinessException(
                'Invalid tax component type.'
            );
        }
    }
}