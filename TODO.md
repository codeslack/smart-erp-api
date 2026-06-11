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