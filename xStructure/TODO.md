stock_batches
--------------
id
tenant_id
product_id
warehouse_id

batch_no
serial_no

manufacture_date
expiry_date

purchase_id

quantity

created_at
updated_at

# Phase 5 — GST Accounts

## Add COA accounts

    1400 Input CGST
    1410 Input SGST
    1420 Input IGST

    2400 Output CGST
    2410 Output SGST
    2420 Output IGST
3100 Retained Earnings

Future:
1000 Cash

1010 HDFC Bank
1020 ICICI Bank
1030 SBI Bank

# Database Roadmap

## After stabilization, I would add:
    products
    ├─ tax_rate
    ├─ tax_method

    purchase_items
    ├─ discount_type
    ├─ discount_value
    ├─ discount_amount
    ├─ tax_rate
    ├─ tax_amount

    sale_items
    ├─ discount_type
    ├─ discount_value
    ├─ discount_amount
    ├─ tax_rate
    ├─ tax_amount

    purchase_charge_items
    sale_charge_items    