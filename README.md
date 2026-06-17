# Laravel 13 Modular API Framework

A high-performance, **Headless API-Only Backend** architecture designed for scalability, transaction safety, and auditability.

## 🚀 Key Features

*   **Modular Monolith**: Organized by functional modules (Users, Products, Invoices).
*   **Job-Centric Logic**: All business actions are encapsulated in Transactional Jobs.
*   **API Versioning**: Native support for URL-based versioning (e.g., `/api/v1/`, `/api/v2/`).
*   **Database Idempotency**: Built-in protection against duplicate requests using `Idempotency-Key` headers.
*   **Automatic Audit Logging**: Tracks every field change and job execution status.
*   **Response Macros**: Unified JSON response structure for success, errors, and validation.

## 📂 Project Structure

```text
app/
├── Abstracts/             # Base Job, ModuleServiceProvider, and SearchJob
├── Http/
│   ├── Controllers/       # Base Controller with transformIndex
│   └── Middleware/        # IdempotencyMiddleware
├── Interfaces/            # Job Contexts (ShouldCreate, ShouldUpdate, ShouldDelete)
├── Modules/               # Functional Modules
│   └── [ModuleName]/
│       ├── Http/          # Versioned Controllers (V1, V2)
│       ├── Jobs/          # Business Logic
│       ├── Routes/        # Versioned Routes (v1.php, v2.php)
│       └── Providers/     # Module Service Provider
└── Traits/                # Reusable logic (Relationships, Jobs, Audit)
```
## 🛠 Installation
Clone the repository:
```bash
git clone <repository-url>
cd <project-folder>
```

## 2. Implementing a Job
Extend App\Abstracts\Job to ensure the logic is validated, authorized, and audited automatically.
```php
class CreateProduct extends Job implements ShouldCreate 
{
    public function rules(): array {
        return ['name' => 'required', 'price' => 'required|numeric'];
    }

    protected function execute() {
        return Product::create($this->request->all());
    }
}
```

*** Recommended ERP Modules ***
# Masters
    Customers
    Suppliers
    Products
    Categories
    Brands
    Units
    Warehouses

# Purchase
    Purchase Orders
    Goods Receive Notes
    Purchase Returns

# Sales
    Quotations
    Sales Orders
    Invoices
    Sales Returns

# Inventory
    Stock Movement
    Stock Transfer
    Stock Adjustment

# Accounting
    Chart of Accounts
    Journal
    Ledger
    Payments
    Expenses
    Bank Accounts

# Reports
    Trial Balance
    Profit & Loss
    Balance Sheet
    Cash Flow
    Stock Valuation
    Purchase Report
    Sales Report
    Tax Report

# Administration
    Users
    Roles
    Permissions
    Audit Logs
    Tenant Settings
