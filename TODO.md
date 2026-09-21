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



"Act as an expert software engineer. We have a strict architectural constraint for this project: no single file can exceed 8,000 characters.Before you write, modify, or append any code:Check if the target file will exceed 8,000 characters with your changes.If it already exists and is close to or will exceed the limit, you must create a new, separate file (e.g., a new module, helper, or sub-component) and export/import the logic cleanly.Do not combine multiple features into one file. Keep everything modular. Propose the new file structure before writing the code."