# ERP Design Going Forward
# Accounting Rules:
```bash
 --------------------------------
| Type      | Debit    | Credit   |
| --------- | -------- | -------- |
| Asset     | Increase | Decrease |
| Expense   | Increase | Decrease |
| Liability | Decrease | Increase |
| Equity    | Decrease | Increase |
| Income    | Decrease | Increase |

```

### Manual Journal
    reference_type = null
    reference_id   = null
    voucher_type   = journal

### Sale Journal
    reference_type = Sale
    reference_id   = 1
    voucher_type   = sale

### Purchase Journal
    reference_type = Purchase
    reference_id   = 5
    voucher_type   = purchase

### Customer Receipt Journal
    reference_type = CustomerReceipt
    reference_id   = 10
    voucher_type   = customer_receipt

### Supplier Payment Journal
    reference_type = SupplierPayment
    reference_id   = 10
    voucher_type   = supplier_receipt

# Account Ledger Now?
```bash
Date        Voucher     Debit    Credit    Balance
--------------------------------------------------
01-Jun      JV-001      5000      0        5000
02-Jun      JV-002         0    1000       4000
03-Jun      JV-003      2000      0        6000
```
# Journal
```bash
Cash            Dr 5000
Owner Equity    Cr 5000
```
# Account Ledger
```bash
Cash Ledger

Date         Voucher      Debit     Credit     Balance
------------------------------------------------------
25-Jun-2026  JV-000003    5000.00      0.00    5000.00
```

```bash
Owner Equity Ledger

Date         Voucher      Debit     Credit     Balance
------------------------------------------------------
25-Jun-2026  JV-000003       0.00   5000.00    5000.00
```

# Postman Request

## Step 1: Create Draft Journal Entry
#### Capital
```bash
01-Jun-2026

Cash          Dr  100,000
Owner Equity  Cr  100,000
```
    Based on your seeded accounts:

    1 = Cash
    6 = Owner Equity

```bash
POST /api/journal-entries

JSON
{
    "voucher_type": "journal",
    "entry_date": "2026-06-01",
    "description": "Owner invested capital",
    "lines": [
        {
            "chart_of_account_id": 1,
            "debit": 100000
        },
        {
            "chart_of_account_id": 6,
            "credit": 100000
        }
    ]
}
```

Step 2: Post Journal Entry (Approved)
```bash
POST /api/journal-entries/1/post

JSON
{
    "success": true,
    "message": "Journal Entry posted successfully"
}
```
#### 2nd Example: Capital
```bash
POST /api/journal-entries

JSON
{
    "voucher_type": "journal",
    "entry_date": "2026-06-3",
    "description": "Second Capital",
    "lines": [
        {
            "chart_of_account_id": 1,
            "debit": 5000
        },
        {
            "chart_of_account_id": 6,
            "credit": 5000
        }
    ]
}
```

Step 2: Post Journal Entry (Approved)
```bash
POST /api/journal-entries/2/post

JSON
{
    "success": true,
    "message": "Journal Entry posted successfully"
}
```
#### Create Purchase
```bash
05-Jun-2026

Purchase
50,000
```
```bash
POST /api/purchases

JSON
{
    "supplier_id": 1,
    "purchase_date": "2026-06-05",
    "notes": "Laptop Purchase",

    "items": [
        {
            "product_id": 1,
            "warehouse_id": 1,
            "quantity": 10,
            "unit_cost": 5000
        }
    ]
}
```
#### Purchase Entry (Approved)
```BASH
POST /api/purchases/1/approve

JSON
{
    "success": true,
    "message": "Purchase confirmed successfully"
}
```

#### Create Supplier Payment
```bash
06-Jun-2026

Pay supplier
20,000
```

```bash
POST /api/supplier-payments

JSON
{
    "supplier_id": 1,
    "payment_date": "2026-06-06",
    "payment_method": "cash",
    "reference_no": "PAY-001",
    "amount": 20000,
    "notes": "Partial supplier payment",
    "allocations": [
        {
            "purchase_id": 1,
            "allocated_amount": 20000
        }
    ]
}
```

#### Confirm Payment
```bash
POST /api/supplier-payments/1/confirm

JSON
{
    "success": true,
    "message": "Supplier Payment confirmed successfully"
}
```

#### Create Sale
```bash
10-Jun-2026

Sale
30,000
```
```bash
POST /api/sales

JSON
{
    "customer_id": 1,
    "sale_date": "2026-06-10",
    "notes": "Laptop Sale",
    "items": [
        {
            "product_id": 1,
            "warehouse_id": 1,
            "quantity": 5,
            "unit_price": 6000
        }
    ]
}
```
#### Sale Entry (Approved)
```bash
POST /api/sales/1/approve

JSON
{
    "success": true,
    "message": "Sale Approved successfully"
}
```
#### Create Customer Receipts
```bash
12-Jun-2026

Receive
15,000
```

```bash
POST /api/customer-receipts

JSON
{
    "customer_id": 1,
    "receipt_date": "2026-06-12",
    "payment_method": "cash",
    "reference_no": "CASH-001",
    "amount": 15000,
    "notes": "Partial payment",
    "allocations": [
        {
            "sale_id": 1,
            "allocated_amount": 15000
        }
    ]
}
```

#### Then: Confirm
```bash
POST /customer-receipts/{id}/confirm

JSON
{
    "success": true,
    "message": "Customer Receipt confirmed successfully"
}
```


# General Ledger Service
Example:
```bash
</> http
GET /api/general-ledger?account_id=1
```
Response:
```bash
</> JSON
{
  "account": "Cash",
  "opening_balance": 0,
  "transactions": [
    {
      "date": "2026-06-25",
      "voucher_no": "JV-000001",
      "debit": 10000,
      "credit": 0,
      "balance": 10000
    },
    {
      "date": "2026-06-25",
      "voucher_no": "JV-000002",
      "debit": 5000,
      "credit": 0,
      "balance": 15000
    }
  ]
}
```

# First Test

Cash Account:
```bash
GET /api/general-ledger?account_id=1
```
Expected structure:

```bash
</> JSON
{
  "success": true,
  "message": "General Ledger fetched successfully",
  "data": {
    "account": {
      "account_code": "1000",
      "account_name": "Cash"
    },
    "opening_balance": 0,
    "transactions": [
      ...
    ],
    "closing_balance": 22000
  }
}
```