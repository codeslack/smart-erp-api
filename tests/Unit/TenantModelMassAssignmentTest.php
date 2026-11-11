<?php

namespace Tests\Unit;

use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\Area\Models\Area;
use App\Modules\Brand\Models\Brand;
use App\Modules\Category\Models\Category;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseCharge;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseItemBatch;
use App\Modules\Purchase\Models\PurchaseItemSerial;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItem;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItemBatch;
use App\Modules\Rbac\Models\Role;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleItemBatch;
use App\Modules\Sales\Models\SaleItemSerial;
use App\Modules\SalesReturn\Models\SaleReturnItemBatch;
use App\Modules\SalesReturn\Models\SaleReturnItemSerial;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;
use Tests\TestCase;

class TenantModelMassAssignmentTest extends TestCase
{
    public function test_tenant_ownership_is_not_mass_assignable_on_tenant_owned_models(): void
    {
        $models = [
            AdvanceAllocation::class,
            Area::class,
            Brand::class,
            Category::class,
            Payment::class,
            PaymentAllocation::class,
            Purchase::class,
            PurchaseCharge::class,
            PurchaseItem::class,
            PurchaseItemBatch::class,
            PurchaseItemSerial::class,
            PurchaseReturn::class,
            PurchaseReturnItem::class,
            PurchaseReturnItemBatch::class,
            Role::class,
            Sale::class,
            SaleItem::class,
            SaleItemBatch::class,
            SaleItemSerial::class,
            SaleReturnItemBatch::class,
            SaleReturnItemSerial::class,
            SalesReturn::class,
            SalesReturnItem::class,
            Unit::class,
            Warehouse::class,
        ];

        foreach ($models as $modelClass) {
            $this->assertFalse(
                (new $modelClass())->isFillable('tenant_id'),
                "{$modelClass} must not allow tenant_id mass assignment."
            );
        }
    }
}
