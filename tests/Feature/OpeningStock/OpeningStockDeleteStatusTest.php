<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Core\Enums\DocumentStatusEnum;
use App\Core\Exceptions\BusinessException;
use App\Modules\OpeningStock\Services\OpeningStockService;

class OpeningStockDeleteStatusTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    public function test_confirmed_opening_stock_cannot_be_deleted(): void
    {
        $this->createTestTenant();

        $warehouse = $this->createWarehouse();
        $product = $this->createProduct();

        $service = app(OpeningStockService::class);

        $openingStock = $service->create([
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

        $service->delete($openingStock);
    }
}