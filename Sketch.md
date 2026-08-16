
```
smart-erp-api
├─ # ERP_MASTER_PLAN.md
├─ .editorconfig
├─ .npmrc
├─ 100.md
├─ app
│  ├─ Console
│  │  └─ Commands
│  │     ├─ DatabaseBackup.php
│  │     └─ DatabaseRestore.php
│  ├─ Core
│  │  ├─ Enums
│  │  │  ├─ ApprovalStatusEnum.php
│  │  │  ├─ DocumentStatusEnum.php
│  │  │  └─ StatusEnum.php
│  │  ├─ Exceptions
│  │  │  ├─ ApiExceptionHandler.php
│  │  │  ├─ BusinessException.php
│  │  │  └─ NotFoundException.php
│  │  ├─ Models
│  │  │  ├─ BaseAuthenticatable.php
│  │  │  ├─ BaseModel.php
│  │  │  ├─ TenantAuthenticatable.php
│  │  │  └─ TenantModel.php
│  │  ├─ Modules
│  │  │  ├─ Contracts
│  │  │  │  └─ ModuleInterface.php
│  │  │  ├─ ModuleRegistry.php
│  │  │  └─ ModuleServiceProvider.php
│  │  ├─ Repositories
│  │  │  ├─ BaseRepository.php
│  │  │  └─ Contracts
│  │  │     └─ BaseRepositoryInterface.php
│  │  ├─ Requests
│  │  │  └─ BaseRequest.php
│  │  ├─ Scopes
│  │  │  └─ TenantScope.php
│  │  ├─ Services
│  │  │  └─ BaseService.php
│  │  ├─ Support
│  │  │  └─ helpers.php
│  │  ├─ Tenant
│  │  │  ├─ Middleware
│  │  │  │  ├─ SetPermissionTenant.php
│  │  │  │  └─ TenantMiddleware.php
│  │  │  ├─ Models
│  │  │  │  └─ TenantModel.php
│  │  │  ├─ TenantAuthenticatable.php
│  │  │  ├─ TenantManager.php
│  │  │  ├─ TenantMiddleware.php
│  │  │  ├─ TenantResolver.php
│  │  │  ├─ TenantScope.php
│  │  │  └─ TenantTeamResolver.php
│  │  ├─ Traits
│  │  │  ├─ ApiResponse.php
│  │  │  └─ HasUuidPrimaryKey.php
│  │  └─ Validation
│  │     └─ TenantRule.php
│  ├─ Http
│  │  └─ Controllers
│  │     ├─ ApiController.php
│  │     └─ Controller.php
│  ├─ Models
│  ├─ Modules
│  │  ├─ OpeningStock
│  │  │  ├─ Controllers
│  │  │  │  └─ OpeningStockController.php
│  │  │  ├─ Models
│  │  │  │  ├─ OpeningStock.php
│  │  │  │  └─ OpeningStockItem.php
│  │  │  ├─ OpeningStockModule.php
│  │  │  ├─ Providers
│  │  │  │  └─ OpeningStockServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ OpeningStockRepositoryInterface.php
│  │  │  │  └─ OpeningStockRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreOpeningStockRequest.php
│  │  │  │  └─ UpdateOpeningStockRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ OpeningStockItemResource.php
│  │  │  │  └─ OpeningStockResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     ├─ OpeningStockPostingService.php
│  │  │     └─ OpeningStockService.php
│  │  ├─ Product
│  │  │  ├─ Controllers
│  │  │  │  ├─ ProductController.php
│  │  │  │  ├─ ProductControllerOld.php
│  │  │  │  └─ ProductVariantController.php
│  │  │  ├─ Enums
│  │  │  │  ├─ BatchStatusEnum.php
│  │  │  │  ├─ InventoryTrackingTypeEnum.php
│  │  │  │  ├─ ProductStatusEnum.php
│  │  │  │  ├─ ProductTypeEnum.php
│  │  │  │  └─ SerialStatusEnum.php
│  │  │  ├─ Models
│  │  │  │  ├─ Product.php
│  │  │  │  ├─ ProductModel.php
│  │  │  │  ├─ ProductVariant.php
│  │  │  │  └─ ProductVariantAttribute.php
│  │  │  ├─ ProductModule.php
│  │  │  ├─ Providers
│  │  │  │  └─ ProductServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  ├─ ProductRepositoryInterface.php
│  │  │  │  │  └─ ProductVariantRepositoryInterface.php
│  │  │  │  ├─ ProductRepository.php
│  │  │  │  └─ ProductVariantRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ BaseProductRequest.php
│  │  │  │  ├─ StoreProductRequest.php
│  │  │  │  ├─ StoreProductVariantRequest.php
│  │  │  │  ├─ UpdateProductRequest.php
│  │  │  │  └─ UpdateProductVariantRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ ProductResource.php
│  │  │  │  ├─ ProductVariantAttributeResource.php
│  │  │  │  └─ ProductVariantResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  ├─ Services
│  │  │  │  ├─ ProductService.php
│  │  │  │  ├─ ProductServiceOld.php
│  │  │  │  └─ ProductVariantService.php
│  │  │  └─ Support
│  │  │     └─ ProductDefaults.php
│  │  ├─ Purchase
│  │  │  ├─ Controllers
│  │  │  │  └─ PurchaseController.php
│  │  │  ├─ Enums
│  │  │  │  └─ PurchaseStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ Purchase.php
│  │  │  │  └─ PurchaseItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ PurchaseServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ PurchaseRepositoryInterface.php
│  │  │  │  └─ PurchaseRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StorePurchaseRequest.php
│  │  │  │  └─ UpdatePurchaseRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ PurchaseItemResource.php
│  │  │  │  └─ PurchaseResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ PurchaseService.php
│  │  ├─ PurchaseOrder
│  │  │  ├─ Controllers
│  │  │  │  └─ PurchaseOrderController.php
│  │  │  ├─ Enums
│  │  │  │  └─ PurchaseOrderStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ PurchaseOrder.php
│  │  │  │  └─ PurchaseOrderItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ PurchaseOrderServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ PurchaseOrderRepositoryInterface.php
│  │  │  │  └─ PurchaseOrderRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StorePurchaseOrderRequest.php
│  │  │  │  └─ UpdatePurchaseOrderRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ PurchaseOrderItemResource.php
│  │  │  │  └─ PurchaseOrderResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ PurchaseOrderService.php
│  │  ├─ PurchaseReturn
│  │  │  ├─ Actions
│  │  │  ├─ Controllers
│  │  │  │  └─ PurchaseReturnController.php
│  │  │  ├─ Enums
│  │  │  │  ├─ PurchaseReturnCondition.php
│  │  │  │  ├─ PurchaseReturnRefundType.php
│  │  │  │  └─ PurchaseReturnStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ PurchaseReturn.php
│  │  │  │  └─ PurchaseReturnItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ PurchaseReturnServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ PurchaseReturnRepositoryInterface.php
│  │  │  │  └─ PurchaseReturnRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StorePurchaseReturnRequest.php
│  │  │  │  └─ UpdatePurchaseReturnRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ PurchaseReturnItemResource.php
│  │  │  │  └─ PurchaseReturnResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ PurchaseReturnService.php
│  │  ├─ Rbac
│  │  │  ├─ Controllers
│  │  │  │  ├─ RoleController.php
│  │  │  │  ├─ RolePermissionController.php
│  │  │  │  └─ UserRoleController.php
│  │  │  ├─ Enums
│  │  │  │  ├─ PermissionEnum.php
│  │  │  │  └─ RoleEnum.php
│  │  │  ├─ Models
│  │  │  │  ├─ Permission.php
│  │  │  │  └─ Role.php
│  │  │  ├─ Providers
│  │  │  │  └─ RbacServiceProvider.php
│  │  │  ├─ Requests
│  │  │  │  ├─ AssignPermissionsRequest.php
│  │  │  │  ├─ AssignRolesRequest.php
│  │  │  │  ├─ StoreRoleRequest.php
│  │  │  │  └─ UpdateRoleRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ PermissionResource.php
│  │  │  │  ├─ RoleResource.php
│  │  │  │  └─ UserPermissionResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  ├─ Seeders
│  │  │  │  ├─ PermissionSeeder.php
│  │  │  │  └─ RoleSeeder.php
│  │  │  └─ Services
│  │  │     ├─ PermissionSetupService.php
│  │  │     └─ RoleSetupService.php
│  │  ├─ Sales
│  │  │  ├─ Controllers
│  │  │  │  └─ SaleController.php
│  │  │  ├─ Enums
│  │  │  │  └─ SaleStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ Sale.php
│  │  │  │  └─ SaleItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ SaleServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SaleRepositoryInterface.php
│  │  │  │  └─ SaleRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreSaleRequest.php
│  │  │  │  └─ UpdateSaleRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ SaleItemResource.php
│  │  │  │  └─ SaleResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ SaleService.php
│  │  ├─ SalesOrder
│  │  │  ├─ Controllers
│  │  │  │  └─ SalesOrderController.php
│  │  │  ├─ Enums
│  │  │  │  └─ SalesOrderStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ SalesOrder.php
│  │  │  │  └─ SalesOrderItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ SalesOrderServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SalesOrderRepositoryInterface.php
│  │  │  │  └─ SalesOrderRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreSalesOrderRequest.php
│  │  │  │  └─ UpdateSalesOrderRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ SalesOrderItemResource.php
│  │  │  │  └─ SalesOrderResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ SalesOrderService.php
│  │  ├─ SalesQuotation
│  │  │  ├─ Controllers
│  │  │  │  └─ SalesQuotationController.php
│  │  │  ├─ Enums
│  │  │  │  └─ SalesQuotationStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ SalesQuotation.php
│  │  │  │  └─ SalesQuotationItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ SalesQuotationServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SalesQuotationRepositoryInterface.php
│  │  │  │  └─ SalesQuotationRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreSalesQuotationRequest.php
│  │  │  │  └─ UpdateSalesQuotationRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ SalesQuotationItemResource.php
│  │  │  │  └─ SalesQuotationResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ SalesQuotationService.php
│  │  ├─ SalesReturn
│  │  │  ├─ Controllers
│  │  │  │  └─ SalesReturnController.php
│  │  │  ├─ Enums
│  │  │  │  ├─ SalesReturnCondition.php
│  │  │  │  ├─ SalesReturnRefundType.php
│  │  │  │  └─ SalesReturnStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ SalesReturn.php
│  │  │  │  └─ SalesReturnItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ SalesReturnServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SalesReturnRepositoryInterface.php
│  │  │  │  └─ SalesReturnRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreSalesReturnRequest.php
│  │  │  │  └─ UpdateSalesReturnRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ SalesReturnItemResource.php
│  │  │  │  └─ SalesReturnResource.php
│  │  │  ├─ routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ SalesReturnService.php
│  │  ├─ Settings
│  │  │  ├─ Controllers
│  │  │  │  └─ SettingController.php
│  │  │  ├─ Database
│  │  │  │  └─ Seeders
│  │  │  │     └─ SettingsSeeder.php
│  │  │  ├─ Enums
│  │  │  │  ├─ InventoryCostingMethodEnum.php
│  │  │  │  └─ SettingGroupEnum.php
│  │  │  ├─ Models
│  │  │  │  └─ Setting.php
│  │  │  ├─ Providers
│  │  │  │  └─ SettingServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SettingRepositoryInterface.php
│  │  │  │  └─ SettingRepository.php
│  │  │  ├─ Requests
│  │  │  │  └─ UpdateSettingRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ SettingResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     ├─ SettingService.php
│  │  │     └─ SettingsSetupService.php
│  │  ├─ StockAdjustment
│  │  │  ├─ Controllers
│  │  │  │  └─ StockAdjustmentController.php
│  │  │  ├─ Enums
│  │  │  │  └─ StockAdjustmentStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ StockAdjustment.php
│  │  │  │  └─ StockAdjustmentItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ StockAdjustmentServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ StockAdjustmentRepositoryInterface.php
│  │  │  │  └─ StockAdjustmentRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreStockAdjustmentRequest.php
│  │  │  │  └─ UpdateStockAdjustmentRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ StockAdjustmentResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ StockAdjustmentService.php
│  │  ├─ StockTransfer
│  │  │  ├─ Controllers
│  │  │  │  └─ StockTransferController.php
│  │  │  ├─ Enums
│  │  │  │  └─ StockTransferStatus.php
│  │  │  ├─ Models
│  │  │  │  ├─ StockTransfer.php
│  │  │  │  └─ StockTransferItem.php
│  │  │  ├─ Providers
│  │  │  │  └─ StockTransferServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ StockTransferRepositoryInterface.php
│  │  │  │  └─ StockTransferRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreStockTransferRequest.php
│  │  │  │  └─ UpdateStockTransferRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ StockTransferResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ StockTransferService.php
│  │  ├─ Supplier
│  │  │  ├─ Controllers
│  │  │  │  └─ SupplierController.php
│  │  │  ├─ Models
│  │  │  │  └─ Supplier.php
│  │  │  ├─ Providers
│  │  │  │  └─ SupplierServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SupplierRepositoryInterface.php
│  │  │  │  └─ SupplierRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreSupplierRequest.php
│  │  │  │  └─ UpdateSupplierRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ SupplierResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     └─ SupplierService.php
│  │  ├─ SupplierPayment
│  │  │  ├─ Controllers
│  │  │  │  └─ SupplierPaymentController.php
│  │  │  ├─ Enums
│  │  │  │  ├─ SupplierPaymentStatus.php
│  │  │  │  └─ SupplierPaymentType.php
│  │  │  ├─ Models
│  │  │  │  ├─ SupplierPayment.php
│  │  │  │  └─ SupplierPaymentAllocation.php
│  │  │  ├─ Providers
│  │  │  │  └─ SupplierPaymentServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ SupplierPaymentRepositoryInterface.php
│  │  │  │  └─ SupplierPaymentRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreSupplierPaymentRequest.php
│  │  │  │  └─ UpdateSupplierPaymentRequest.php
│  │  │  ├─ Resources
│  │  │  │  ├─ SupplierPaymentAllocationResource.php
│  │  │  │  └─ SupplierPaymentResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  ├─ Services
│  │  │  │  └─ SupplierPaymentService.php
│  │  │  └─ Validation
│  │  │     └─ SupplierPaymentValidator.php
│  │  ├─ SystemNumber
│  │  │  ├─ Enums
│  │  │  │  └─ SystemNumberTypeEnum.php
│  │  │  ├─ Models
│  │  │  │  └─ SystemNumber.php
│  │  │  └─ Services
│  │  │     └─ SystemNumberService.php
│  │  ├─ Tenant
│  │  │  ├─ Contracts
│  │  │  │  └─ TenantSetupInterface.php
│  │  │  ├─ Controllers
│  │  │  │  └─ TenantController.php
│  │  │  ├─ Enums
│  │  │  │  └─ BusinessTypeEnum.php
│  │  │  ├─ Models
│  │  │  │  └─ Tenant.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ TenantRepositoryInterface.php
│  │  │  │  └─ TenantRepository.php
│  │  │  ├─ Requests
│  │  │  │  └─ StoreTenantRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ TenantResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     ├─ TenantService.php
│  │  │     └─ TenantSetupService.php
│  │  ├─ Unit
│  │  │  ├─ Controllers
│  │  │  │  └─ UnitController.php
│  │  │  ├─ Models
│  │  │  │  └─ Unit.php
│  │  │  ├─ Providers
│  │  │  │  └─ UnitServiceProvider.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ UnitRepositoryInterface.php
│  │  │  │  └─ UnitRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ StoreUnitRequest.php
│  │  │  │  └─ UpdateUnitRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ UnitResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  ├─ Services
│  │  │  │  ├─ UnitService.php
│  │  │  │  └─ UnitSetupService.php
│  │  │  └─ UnitModule.php
│  │  ├─ User
│  │  │  ├─ Controllers
│  │  │  │  ├─ AuthController.php
│  │  │  │  └─ UserController.php
│  │  │  ├─ Models
│  │  │  │  └─ User.php
│  │  │  ├─ Repositories
│  │  │  │  ├─ Contracts
│  │  │  │  │  └─ UserRepositoryInterface.php
│  │  │  │  └─ UserRepository.php
│  │  │  ├─ Requests
│  │  │  │  ├─ LoginRequest.php
│  │  │  │  ├─ StoreUserRequest.php
│  │  │  │  └─ UpdateUserRequest.php
│  │  │  ├─ Resources
│  │  │  │  └─ UserResource.php
│  │  │  ├─ Routes
│  │  │  │  └─ api.php
│  │  │  └─ Services
│  │  │     ├─ AuthService.php
│  │  │     └─ UserService.php
│  │  └─ Warehouse
│  │     ├─ Controllers
│  │     │  └─ WarehouseController.php
│  │     ├─ Models
│  │     │  └─ Warehouse.php
│  │     ├─ Providers
│  │     │  └─ WarehouseServiceProvider.php
│  │     ├─ Repositories
│  │     │  ├─ Contracts
│  │     │  │  └─ WarehouseRepositoryInterface.php
│  │     │  └─ WarehouseRepository.php
│  │     ├─ Requests
│  │     │  ├─ StoreWarehouseRequest.php
│  │     │  └─ UpdateWarehouseRequest.php
│  │     ├─ Resources
│  │     │  └─ WarehouseResource.php
│  │     ├─ Routes
│  │     │  └─ api.php
│  │     ├─ Services
│  │     │  ├─ WarehouseService.php
│  │     │  └─ WarehouseSetupService.php
│  │     └─ WarehouseModule.php
│  ├─ Providers
│  │  └─ AppServiceProvider.php
│  └─ Shared
├─ artisan
├─ bootstrap
│  ├─ app.php
│  ├─ cache
│  │  ├─ packages.php
│  │  └─ services.php
│  └─ providers.php
├─ composer.json
├─ composer.lock
├─ config
│  ├─ app.php
│  ├─ auth.php
│  ├─ cache.php
│  ├─ cors.php
│  ├─ database.php
│  ├─ erp_permissions.php
│  ├─ filesystems.php
│  ├─ logging.php
│  ├─ mail.php
│  ├─ modules.php
│  ├─ permission.php
│  ├─ queue.php
│  ├─ sanctum.php
│  ├─ services.php
│  └─ session.php
├─ database
│  ├─ factories
│  │  ├─ TenantFactory.php
│  │  ├─ UnitFactory.php
│  │  └─ UserFactory.php
│  ├─ migrations
│  │  ├─ 0000_01_01_000000_create_tenants_table.php
│  │  ├─ 0000_01_01_000001_create_users_table.php
│  │  ├─ 0000_01_01_000002_create_settings_table.php
│  │  ├─ 0000_01_01_000003_create_system_numbers_table.php
│  │  ├─ 0001_01_01_000001_create_cache_table.php
│  │  ├─ 0001_01_01_000002_create_jobs_table.php
│  │  ├─ 0001_01_01_000003_create_personal_access_tokens_table.php
│  │  ├─ 0001_01_01_000004_create_permission_tables.php
│  │  ├─ 0001_01_02_000001_create_core_accounts_table.php
│  │  ├─ 0001_01_02_000002_create_document_numbers_table.php
│  │  ├─ 0001_01_02_000003_create_areas_table.php
│  │  ├─ 0001_01_02_000004_create_warehouses_table.php
│  │  ├─ 0001_01_02_000005_create_suppliers_table.php
│  │  ├─ 0001_01_02_000006_create_customers_table.php
│  │  ├─ 0001_01_03_000000_create_brands_table.php
│  │  ├─ 0001_01_03_000001_create_categories_table.php
│  │  ├─ 0001_01_03_000002_create_units_table.php
│  │  ├─ 0001_01_03_000003_create_products_table.php
│  │  ├─ 0001_01_03_000004_create_product_variants_table.php
│  │  ├─ 0001_01_03_000005_create_product_batches_table.php
│  │  ├─ 0001_01_03_000006_create_product_serials_table.php
│  │  ├─ 0001_01_03_000007_create_product_stocks_table.php
│  │  ├─ 0001_01_03_000008_create_stock_ledgers_table.php
│  │  ├─ 0001_01_03_000009_create_opening_stocks_table.php
│  │  ├─ 0001_01_03_000010_create_opening_stock_items_table.php
│  │  ├─ 2026_06_15_094822_create_purchases_table.php
│  │  ├─ 2026_06_15_094920_create_purchase_items_table.php
│  │  ├─ 2026_06_16_193251_create_sales_table.php
│  │  ├─ 2026_06_16_193329_create_sale_items_table.php
│  │  ├─ 2026_06_17_184918_create_purchase_returns_table.php
│  │  ├─ 2026_06_17_185013_create_purchase_return_items_table.php
│  │  ├─ 2026_06_17_195741_create_sales_returns_table.php
│  │  ├─ 2026_06_17_195751_create_sales_return_items_table.php
│  │  ├─ 2026_06_18_160814_create_stock_adjustments_table.php
│  │  ├─ 2026_06_18_160820_create_stock_adjustment_items_table.php
│  │  ├─ 2026_06_18_182440_create_stock_transfers_table.php
│  │  ├─ 2026_06_18_182445_create_stock_transfer_items_table.php
│  │  ├─ 2026_06_18_194144_create_purchase_orders_table.php
│  │  ├─ 2026_06_18_194152_create_purchase_order_items_table.php
│  │  ├─ 2026_06_19_094124_create_sales_quotations_table.php
│  │  ├─ 2026_06_19_094134_create_sales_quotation_items_table.php
│  │  ├─ 2026_06_19_174112_create_sales_orders_table.php
│  │  ├─ 2026_06_19_174144_create_sales_order_items_table.php
│  │  ├─ 2026_06_19_190430_create_goods_receipt_notes_table.php
│  │  ├─ 2026_06_19_190438_create_goods_receipt_note_items_table.php
│  │  ├─ 2026_06_20_195351_create_delivery_notes_table.php
│  │  ├─ 2026_06_20_195411_create_delivery_note_items_table.php
│  │  ├─ 2026_06_22_185514_create_customer_receipts_table.php
│  │  ├─ 2026_06_22_185523_create_customer_receipt_allocations_table.php
│  │  ├─ 2026_06_23_173825_create_supplier_payments_table.php
│  │  ├─ 2026_06_23_174047_create_supplier_payment_allocations_table.php
│  │  └─ 2026_07_19_055056_create_advance_allocations_table.php
│  └─ seeders
│     ├─ AccountingSeeder.php
│     ├─ AdminUserSeeder.php
│     ├─ DatabaseSeeder.php
│     ├─ PermissionSeeder.php
│     └─ TenantSeeder.php
├─ docs
│  └─ intro.md
├─ IntelephenseHelper.php
├─ make-module-new.sh
├─ make-module-repo.sh
├─ make-module.sh
├─ Makefile
├─ package.json
├─ phpunit.xml
├─ public
│  ├─ .htaccess
│  ├─ favicon.ico
│  ├─ index.php
│  └─ robots.txt
├─ README.md
├─ resources
│  ├─ css
│  │  └─ app.css
│  ├─ js
│  │  └─ app.js
│  └─ views
│     └─ welcome.blade.php
├─ routes
│  ├─ api.php
│  ├─ console.php
│  └─ web.php
├─ Skeleton.md
├─ storage
│  ├─ app
│  │  ├─ backups
│  │  │  ├─ erp_api_init.sql
│  │  │  ├─ initdb_p1.sql
│  │  │  ├─ initdb_p2.sql
│  │  │  └─ init_db.sql
│  │  ├─ private
│  │  │  └─ scribe
│  │  │     ├─ collection.json
│  │  │     └─ openapi.yaml
│  │  └─ public
│  ├─ framework
│  │  ├─ cache
│  │  │  └─ data
│  │  ├─ sessions
│  │  ├─ testing
│  │  └─ views
│  │     ├─ 00eaa5922b1cc8a9e96ef7ccc3230d94.php
│  │     ├─ 06177985e37f3b4b8ffe361ad2968169.php
│  │     ├─ 12a0917573a234811e57473addaa65c0.php
│  │     ├─ 13c23375403bc8c71c4cf3cea0deb602.php
│  │     ├─ 19bed28a256e79e373476d478c43eb55.php
│  │     ├─ 348c1fd90fed4b469ff7dfeae1191e60.php
│  │     ├─ 41902a96fb23d2a96f7e9d867e01a612.php
│  │     ├─ 4877978e5c7e4f7692ebc184c2cbe634.php
│  │     ├─ 6357cc78a3bceec6b6f77e9288753020.php
│  │     ├─ 751f9e99902f6ea58033d2f891611705.php
│  │     ├─ 991f5ef76dc55f5ee42bd951a322b00b.php
│  │     ├─ 994881c1ca37070914ce8dbccd610a15.php
│  │     ├─ c1c710a614025886663dc023ea8e515f.php
│  │     ├─ cd3dddb0c892955333a7dd6abb7a6228.php
│  │     ├─ e53ea8c82c765c3f312495caa879f64e.php
│  │     └─ e9dffad6346413c4f86b6d6e9ef8c90e.php
│  └─ logs
├─ tests
│  ├─ Feature
│  │  ├─ ExampleTest.php
│  │  └─ UnitValidationTest.php
│  ├─ TestCase.php
│  └─ Unit
│     └─ ExampleTest.php
├─ todo-list.md
├─ TODO.md
├─ touch
│  └─ fineTouch.php
├─ vite.config.js
└─ xStructure
   ├─ Accounting Structure.md
   ├─ Customer Module Structure.md
   ├─ CustomerReceipt Module Structure.md
   ├─ DeliveryNote Module Structure.md
   ├─ ERP Accounting Module Structure.md
   ├─ GoodsReceiptNote Module Structure.md
   ├─ Inventory Module Structure.md
   ├─ pattern.md
   ├─ Postman Request.md
   ├─ Product Module Structure.md
   ├─ Purchase Module Structure.md
   ├─ PurchaseOrder Module Structure.md
   ├─ PurchaseReturn Module Structure.md
   ├─ RBAC Module Structure.md
   ├─ Sales Module Structure.md
   ├─ SalesOrder Module Structure.md
   ├─ SalesQuotation Module Structure.md
   ├─ SalesReturn Module Structure.md
   ├─ sql-query.php
   ├─ StockAdjustment Module Structure.md
   ├─ StockTransfer Module Structure.md
   ├─ Supplier Module.md
   ├─ SupplierPayment Module Structure.md
   └─ TODO.md

```