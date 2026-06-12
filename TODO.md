app/
└── Modules/
    └── User/
        ├── Controllers/
        ├── Models/
        ├── Requests/
        ├── Resources/
        ├── Services/
        ├── Repositories/
        └── Routes/

app/
└── Modules/
    └── User/
        ├── Controllers/
        │   └── AuthController.php
        │
        ├── Requests/
        │   ├── LoginRequest.php
        │   └── RegisterRequest.php
        │
        ├── Resources/
        │   └── UserResource.php
        │
        ├── Services/
        │   └── AuthService.php
        │
        ├── Repositories/
        │   ├── Contracts/
        │   │   └── UserRepositoryInterface.php
        │   └── UserRepository.php
        │
        └── Routes/
            └── api.php        

Build Order
    User Repository
    Auth Service
    Register Request
    Login Request
    User Resource
    Auth Controller
    Auth Routes
    Sanctum Token Generation
    /auth/me
    /auth/logout  

✓ Tenant
✓ User
✓ Auth
✓ RBAC Core

Next:
1. RBAC API
2. Customer
3. Supplier
4. Category
5. Brand
6. Unit
7. Warehouse
8. Product
9. Stock Adjustment
10. Stock Transfer
11. Purchase Order
12. Goods Receive Note
13. Sales Quotation
14. Sales Order
15. Invoice
16. Payment
17. Expense
18. Ledger
19. Journal
20. Reports              