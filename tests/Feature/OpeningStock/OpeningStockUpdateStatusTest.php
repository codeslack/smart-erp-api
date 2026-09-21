<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Services\OpeningStockService;
use App\Core\Enums\DocumentStatusEnum;
use App\Core\Exceptions\BusinessException;


class OpeningStockUpdateStatusTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    public function test_confirmed_opening_stock_cannot_be_updated(): void
    {
        $this->createTestTenant();

        $warehouse = $this->createWarehouse();

        $product = $this->createProduct();

        $openingStock = app(OpeningStockService::class)->create([
            'warehouse_id' => $warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Original',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_cost' => 100,
                ],
            ],
        ]);

        $openingStock->update([
            'status' => DocumentStatusEnum::CONFIRMED->value,
        ]);

        $this->expectException(BusinessException::class);

        app(OpeningStockService::class)->update(
            $openingStock,
            [
                'warehouse_id' => $warehouse->id,
                'opening_date' => now()->toDateString(),
                'remarks' => 'Should not update',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 20,
                        'unit_cost' => 100,
                    ],
                ],
            ]
        );
    }
}