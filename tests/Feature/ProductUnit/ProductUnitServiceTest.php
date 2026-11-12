<?php

namespace Tests\Feature\ProductUnit;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesProduct;

use App\Core\Tenant\TenantManager;
use App\Core\Exceptions\BusinessException;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Unit\Models\Unit;
use App\Modules\Product\Models\Product;
use App\Modules\ProductUnit\Models\ProductUnit;

use App\Modules\Tenant\Services\TenantSetupService;
use App\Modules\ProductUnit\Services\ProductUnitService;

class ProductUnitServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesProduct;

    protected Tenant $tenant;
    protected Unit $baseUnit;
    protected Unit $alternateUnit;
    protected Product $product;

    protected ProductUnitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(TenantSetupService::class)
            ->setup($this->tenant);

        $units = Unit::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $this->assertCount(
            2,
            $units,
            'Tenant setup must provide at least two units for ProductUnit tests.'
        );

        $this->baseUnit = $units->get(0);
        $this->alternateUnit = $units->get(1);

        $this->product = $this->createProduct([
            'unit_id' => $this->baseUnit->id,
        ]);

        $this->service = app(ProductUnitService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Base Unit
    |--------------------------------------------------------------------------
    */

    public function test_sync_base_unit_creates_base_product_unit(): void
    {
        $productUnit = $this->service
            ->syncBaseUnit($this->product);

        $this->assertDatabaseHas('product_units', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->baseUnit->id,
            'conversion_factor' => 1,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        $this->assertEquals(
            $this->product->id,
            $productUnit->product_id
        );

        $this->assertEquals(
            $this->baseUnit->id,
            $productUnit->unit_id
        );

        $this->assertEquals(
            '1.00000000',
            $productUnit->conversion_factor
        );
    }

    public function test_sync_base_unit_is_idempotent(): void
    {
        $first = $this->service
            ->syncBaseUnit($this->product);

        $second = $this->service
            ->syncBaseUnit($this->product);

        $this->assertEquals(
            $first->id,
            $second->id
        );

        $this->assertEquals(
            1,
            ProductUnit::query()
                ->where('product_id', $this->product->id)
                ->where('unit_id', $this->baseUnit->id)
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Additional Units
    |--------------------------------------------------------------------------
    */

    public function test_additional_product_unit_can_be_created(): void
    {
        $this->service->syncBaseUnit($this->product);

        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
            'is_active' => true,
        ]);

        $this->assertEquals(
            $this->product->id,
            $productUnit->product_id
        );

        $this->assertEquals(
            $this->alternateUnit->id,
            $productUnit->unit_id
        );

        $this->assertEquals(
            '10.00000000',
            $productUnit->conversion_factor
        );

        $this->assertTrue(
            $productUnit->is_purchase_unit
        );

        $this->assertTrue(
            $productUnit->is_sales_unit
        );
    }

    public function test_duplicate_product_unit_is_rejected(): void
    {
        $this->service->syncBaseUnit($this->product);

        $this->expectException(BusinessException::class);

        $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->baseUnit->id,
            'conversion_factor' => 1,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Conversion Validation
    |--------------------------------------------------------------------------
    */

    public function test_conversion_factor_must_be_greater_than_zero(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 0,
        ]);
    }

    public function test_base_unit_must_have_conversion_factor_one(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->baseUnit->id,
            'conversion_factor' => 10,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Quantity Conversion
    |--------------------------------------------------------------------------
    */

    public function test_quantity_is_converted_to_base_quantity(): void
    {
        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
        ]);

        $baseQuantity = $this->service->toBaseQuantity(
            $productUnit,
            5
        );

        $this->assertEquals(
            50.0,
            $baseQuantity
        );
    }

    public function test_unit_cost_is_converted_to_base_unit_cost(): void
    {
        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
        ]);

        $baseUnitCost = $this->service->toBaseUnitCost(
            $productUnit,
            50000
        );

        $this->assertEquals(
            5000.0,
            $baseUnitCost
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Base Unit Protection
    |--------------------------------------------------------------------------
    */

    public function test_base_unit_change_is_blocked_when_product_units_exist(): void
    {
        $this->service->syncBaseUnit($this->product);

        $this->expectException(BusinessException::class);

        $this->service->ensureBaseUnitChangeAllowed(
            $this->product,
            $this->alternateUnit->id
        );
    }

    public function test_base_unit_change_is_allowed_when_no_product_units_exist(): void
    {
        $this->service->ensureBaseUnitChangeAllowed(
            $this->product,
            $this->alternateUnit->id
        );

        $this->assertTrue(true);
    }

    /*
    |--------------------------------------------------------------------------
    | Purchase / Sales Unit
    |--------------------------------------------------------------------------
    */

    public function test_purchase_unit_must_be_enabled_for_purchase(): void
    {
        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => false,
            'is_sales_unit' => true,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->ensurePurchaseUnit($productUnit);
    }

    public function test_sales_unit_must_be_enabled_for_sales(): void
    {
        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 10,
            'is_purchase_unit' => true,
            'is_sales_unit' => false,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->ensureSalesUnit($productUnit);
    }
    public function test_fractional_purchase_unit_quantity_cost_and_reverse_conversion(): void
    {
        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 12.5,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
        ]);

        $baseQuantity = $this->service->toBaseQuantity(
            $productUnit,
            0.3333,
        );

        $this->assertEqualsWithDelta(4.16625, $baseQuantity, 0.00000001);

        $this->assertEqualsWithDelta(
            0.3333,
            $this->service->fromBaseQuantity($productUnit, $baseQuantity),
            0.00000001,
        );

        $this->assertEqualsWithDelta(
            9.876536,
            $this->service->toBaseUnitCost($productUnit, 123.4567),
            0.00000001,
        );
    }

    public function test_fractional_unit_smaller_than_base_unit_converts_in_both_directions(): void
    {
        $productUnit = $this->service->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->alternateUnit->id,
            'conversion_factor' => 0.125,
            'is_purchase_unit' => true,
            'is_sales_unit' => true,
        ]);

        $baseQuantity = $this->service->toBaseQuantity(
            $productUnit,
            8.125,
        );

        $this->assertEqualsWithDelta(1.015625, $baseQuantity, 0.00000001);

        $this->assertEqualsWithDelta(
            8.125,
            $this->service->fromBaseQuantity($productUnit, $baseQuantity),
            0.00000001,
        );

        $this->assertEqualsWithDelta(
            18.7648,
            $this->service->toBaseUnitCost($productUnit, 2.3456),
            0.00000001,
        );
    }

}
