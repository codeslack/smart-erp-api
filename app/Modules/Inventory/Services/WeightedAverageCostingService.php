<?php

namespace App\Modules\Inventory\Services;

use App\Core\Exceptions\BusinessException;

class WeightedAverageCostingService
{
    /*
    |--------------------------------------------------------------------------
    | Calculate New Average Cost
    |--------------------------------------------------------------------------
    |
    | Used when stock is received.
    |
    | Formula:
    |
    | (
    |     Current Quantity × Current Average Cost
    |     +
    |     Incoming Quantity × Incoming Unit Cost
    | )
    | /
    | (
    |     Current Quantity + Incoming Quantity
    | )
    |
    */

    public function calculateAverageCost(
        float $currentQuantity,
        float $currentAverageCost,
        float $incomingQuantity,
        float $incomingUnitCost
    ): float {

        if ($incomingQuantity <= 0) {
            throw new BusinessException(
                'Incoming quantity must be greater than zero.'
            );
        }

        if ($currentAverageCost < 0) {
            throw new BusinessException(
                'Current average cost cannot be negative.'
            );
        }

        if ($incomingUnitCost < 0) {
            throw new BusinessException(
                'Incoming unit cost cannot be negative.'
            );
        }

        $newQuantity = bcadd(
            (string) $currentQuantity,
            (string) $incomingQuantity,
            4
        );

        $quantityComparison = bccomp(
            $newQuantity,
            '0',
            4
        );

        // Preserve the existing valuation while the balance remains negative.
        if ($quantityComparison < 0) {
            return $currentAverageCost;
        }

        // The negative balance has been completely cleared.
        if ($quantityComparison === 0) {
            return 0.0;
        }

        $currentValue = bcmul(
            (string) $currentQuantity,
            (string) $currentAverageCost,
            8
        );

        $incomingValue = bcmul(
            (string) $incomingQuantity,
            (string) $incomingUnitCost,
            8
        );

        $totalValue = bcadd(
            $currentValue,
            $incomingValue,
            8
        );

        return (float) bcdiv(
            $totalValue,
            $newQuantity,
            4
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Outgoing Cost
    |--------------------------------------------------------------------------
    |
    | Weighted Average uses the current average cost.
    |
    | COGS:
    |
    | Quantity × Current Average Cost
    |
    */

    public function calculateOutgoingCost(
        float $quantity,
        float $averageCost
    ): float {

        if ($quantity <= 0) {
            throw new BusinessException(
                'Outgoing quantity must be greater than zero.'
            );
        }

        if ($averageCost < 0) {
            throw new BusinessException(
                'Average cost cannot be negative.'
            );
        }

        return (float) bcmul(
            (string) $quantity,
            (string) $averageCost,
            4
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Average Cost From Value
    |--------------------------------------------------------------------------
    */

    public function calculateAverageFromValue(
        float $quantity,
        float $totalValue
    ): float {

        if ($quantity <= 0) {
            return 0.0;
        }

        if ($totalValue < 0) {
            throw new BusinessException(
                'Total inventory value cannot be negative.'
            );
        }

        return (float) bcdiv(
            (string) $totalValue,
            (string) $quantity,
            4
        );
    }
}