<?php

namespace Tests\Unit\Inventory;

use App\Modules\Inventory\Services\WeightedAverageCostingService;
use Tests\TestCase;

class InventoryCostRoundingTest extends TestCase
{
    public function test_weighted_average_cost_rounds_half_up_to_four_decimals(): void
    {
        $service = new WeightedAverageCostingService();

        // (1.25 × 10.1234 + 0.75 × 20.5678) / 2 = 14.04005.
        $this->assertEquals(
            14.0401,
            $service->calculateAverageCost(
                currentQuantity: 1.25,
                currentAverageCost: 10.1234,
                incomingQuantity: 0.75,
                incomingUnitCost: 20.5678,
            ),
        );
    }

    public function test_weighted_average_outgoing_cost_rounds_half_up_to_four_decimals(): void
    {
        $service = new WeightedAverageCostingService();

        // 0.3333 × 12.3456 = 4.11478848.
        $this->assertEquals(
            4.1148,
            $service->calculateOutgoingCost(
                quantity: 0.3333,
                averageCost: 12.3456,
            ),
        );
    }
}
