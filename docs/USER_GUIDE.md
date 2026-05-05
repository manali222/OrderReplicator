# MageClone Order Replicator — User Guide

This guide walks you through every feature of the Order Replicator module with step-by-step instructions and screenshots.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Replicating a Single Order](#replicating-a-single-order)
3. [CSV Bulk Replication](#csv-bulk-replication)
4. [Replication Log](#replication-log)
5. [Configuration](#configuration)
6. [Troubleshooting](#troubleshooting)

---

## Getting Started

After installation, enable the module at **Stores > Configuration > Sales > Order Replicator** and set **Enable Module** to **Yes**.

The module adds two buttons to the native Magento **Sales Order View** page and two menu entries under **Sales > Order Replicator**.

---

## Replicating a Single Order

### Step 1: Open the Source Order

Navigate to **Sales > Orders** and click on the order you want to replicate.

You will see two new buttons in the button bar: **Replicate Order** and **CSV Bulk Replicate**.

![Order View with Replicate Buttons](screenshots/01-order-view-buttons.png)

### Step 2: Click "Replicate Order"

This opens the replication form pre-loaded with the source order's details.

![Replicate Order Form](screenshots/02-replicate-order-form.png)

### Step 3: Fill in the Replication Form

The form is divided into sections:

**Source Order Summary** — Shows the original order number, customer name, email, grand total, and status. This is read-only for reference.

**Order Items** — Lists every item from the source order. You can modify:
- **New SKU** — Replace with a different product SKU
- **Price** — Override the unit price
- **Qty** — Change the quantity

**New Customer Details** (required):
- **Email** — The new customer's email address
- **First Name** — Customer's first name
- **Last Name** — Customer's last name

**Billing Address** — Leave blank to copy from the source order, or enter a new billing address.

**Shipping Address** — Leave blank to copy from the source order, or enter a new shipping address.

**Shipping & Payment Method** — Override the shipping carrier/method and payment method, or leave blank to use the source order's methods.

### Step 4: Click "Replicate Order"

The system will:
1. Load the source order
2. Create or find the customer account (if auto-create is enabled)
3. Build a new cart with the specified items
4. Apply addresses, shipping, and payment
5. Place the new order
6. Log the result

You will see a success message with a link to the newly created order.

---

## CSV Bulk Replication

Use this feature to replicate one source order for many customers at once.

### Step 1: Open the CSV Upload Page

**Option A:** From any order view, click **"CSV Bulk Replicate"**. The source order ID is automatically prefilled.

**Option B:** Go to **Sales > Order Replicator > CSV Bulk Replication** and enter the source order ID manually.

![CSV Upload with Prefilled Order ID](screenshots/03-csv-upload-prefilled.png)

### Step 2: Download the CSV Template

Click **"Download CSV Template"** to get a pre-formatted CSV file with all supported columns.

### Step 3: Fill in the CSV

Each row represents one new order. Required columns:

| Column | Description |
|--------|-------------|
| `customer_email` | New customer's email address |
| `customer_firstname` | First name |
| `customer_lastname` | Last name |

Optional columns allow you to override addresses, shipping/payment methods, and item details per row. See the column reference on the upload page for the full list.

**Example CSV:**
```csv
customer_email,customer_firstname,customer_lastname,billing_street,billing_city,billing_region,billing_region_id,billing_postcode,billing_country_id,billing_telephone
user_1@example.com,John,Doe,123 Main St,New York,New York,43,10001,US,555-123-4567
user_2@example.com,Jane,Smith,456 Oak Ave,Los Angeles,California,12,90001,US,555-987-6543
```

### Step 4: Upload and Process

1. Drag and drop your CSV file onto the upload zone, or click to browse
2. Click **"Process CSV & Create Orders"**
3. A progress bar shows the status of each order being created
4. When complete, you'll see a summary with:
   - Total orders created successfully
   - Any failed orders with error details
   - Links to view the new orders

---

## Replication Log

Go to **Sales > Order Replicator > Replication Log** to view the full audit trail of all replication attempts.

![Replication Log](screenshots/04-replication-log.png)

The log grid shows:

| Column | Description |
|--------|-------------|
| **Log ID** | Unique identifier for each replication attempt |
| **Source Order #** | The original order that was replicated |
| **New Order #** | The newly created order number |
| **Customer Email** | Email of the new customer |
| **Status** | completed, failed, or pending |
| **Error** | Error message if the replication failed |
| **CSV File** | CSV filename if created via bulk upload |
| **Created At** | Timestamp of the replication |

Use the grid's built-in filters, sorting, and search to find specific replication records.

---

## Configuration

Navigate to **Stores > Configuration > Sales > Order Replicator**.

### General Settings

| Setting | Default | Description |
|---------|---------|-------------|
| **Enable Module** | No | Must be set to Yes to use the module |
| **Send Order Confirmation Email** | No | Send email to the new customer when order is created |
| **Default Order Status** | Pending | Status assigned to newly replicated orders |
| **Default Payment Method** | `checkmo` (Check/Money Order) | Fallback payment method if none specified |
| **Auto-Create Customer Account** | No | Automatically create a customer account if the email doesn't exist |

### CSV Settings

| Setting | Default | Description |
|---------|---------|-------------|
| **Maximum CSV Rows** | 500 | Maximum number of rows allowed per CSV upload |
| **CSV Delimiter** | `,` | Column separator character in CSV files |

---

## Admin Menu Location

```
Sales
├── Orders                       (native Magento — each order has Replicate buttons)
└── Order Replicator
    ├── CSV Bulk Replication      (upload CSV page)
    └── Replication Log           (audit log grid)
```

---

## ACL Permissions

Control access per admin role at **System > Permissions > User Roles > [Role] > Role Resources**.

| Permission | What It Controls |
|------------|-----------------|
| `Order Replicator` | Parent — grants access to the module |
| `View Orders & Log` | View replication forms and log |
| `Replicate Orders` | Execute single order replication |
| `CSV Upload` | Upload CSV files for bulk replication |
| `Configuration` | Access module settings |

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Buttons don't appear on order view | Flush cache: `bin/magento cache:flush`. Check ACL permissions for your admin role. |
| "Module is disabled" error | Enable at Stores > Config > Sales > Order Replicator. |
| Product SKU not found | Ensure the SKU exists, is enabled, and is in the same store/website. |
| CSV upload fails | Verify the file is valid CSV, under the max row limit, and has the 3 required columns. |
| Order total is $0 | Check that products have prices and are saleable (in stock, enabled). |
| Customer not created | Enable "Auto-Create Customer Account" in module configuration. |
| Replicate buttons missing after upgrade | Run `bin/magento setup:di:compile` then `bin/magento cache:flush`. |
