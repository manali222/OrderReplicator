# MageClone Order Replicator — Plugin & Play Installation Guide

This guide enables **any developer** to clone this repo and have a working Order Replicator module running in minutes.

---

## Prerequisites

- Magento 2.4.x or Adobe Commerce (any 2.4.x version)
- PHP 8.1 or higher
- Composer 2.x
- MySQL 8.0 / MariaDB 10.4+
- Elasticsearch 7.x / OpenSearch 2.x

---

## Quick Start (5 Minutes)

### Step 1: Clone the Repository (Private — Requires Access)

```bash
# SSH (recommended if you have SSH key configured)
git clone git@gitlab.com:manali222/manali222-project.git

# Or HTTPS (requires GitLab Personal Access Token)
git clone https://gitlab.com/manali222/manali222-project.git

cd manali222-project
```

> **This is a private repository.** Contact the repo owner for access.

### Step 2: Copy to Your Magento Installation

```bash
# Create the module directory
mkdir -p /path/to/magento/app/code/MageClone/

# Copy the module
cp -r OrderReplicator /path/to/magento/app/code/MageClone/OrderReplicator
```

### Step 3: Enable and Install

```bash
cd /path/to/magento

# Enable the module
bin/magento module:enable MageClone_OrderReplicator

# Run database migrations (creates mageclone_replication_log table)
bin/magento setup:upgrade

# Compile DI
bin/magento setup:di:compile

# Deploy static content (production mode only)
bin/magento setup:static-content:deploy -f

# Flush cache
bin/magento cache:flush
```

### Step 4: Configure

1. Login to Magento Admin
2. Go to **Stores → Configuration → Sales → Order Replicator**
3. Set **Enable Module** = **Yes**
4. Configure defaults as needed
5. Save Config
6. Flush cache

### Step 5: Use It

- **Sales → Order Replicator → Replicate Orders** — pick an order, clone it
- **Sales → Order Replicator → CSV Bulk Replication** — bulk clone via CSV
- **Sales → Order Replicator → Replication Log** — audit trail

---

## Installation via Composer (Alternative)

If you prefer Composer-based installation:

### Option A: From Local Path

```bash
# In your Magento composer.json, add:
composer config repositories.mageclone path /path/to/mageclone-order-replicator/OrderReplicator

# Install
composer require mageclone/module-order-replicator:@dev

# Setup
bin/magento module:enable MageClone_OrderReplicator
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Option B: From Private GitLab Repository (SSH)

```bash
# Add the private repo via SSH
composer config repositories.mageclone '{"type": "vcs", "url": "git@gitlab.com:manali222/manali222-project.git"}'

# Install
composer require mageclone/module-order-replicator:^1.0

# Setup
bin/magento module:enable MageClone_OrderReplicator
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Option C: From Private GitLab Repository (HTTPS + Token)

```bash
# Configure GitLab auth token first
composer config --global --auth gitlab-token.gitlab.com YOUR_PERSONAL_ACCESS_TOKEN

# Add the private repo
composer config repositories.mageclone vcs https://gitlab.com/manali222/manali222-project.git

# Install
composer require mageclone/module-order-replicator:^1.0

# Setup
bin/magento module:enable MageClone_OrderReplicator
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

---

## Warden Local Development Setup

If you're using [Warden](https://warden.dev/) for local Magento development:

### Existing Warden Project

```bash
# From your Warden project directory
# Copy module into app/code
mkdir -p app/code/MageClone/
cp -r /path/to/OrderReplicator app/code/MageClone/OrderReplicator

# Enter the environment and install
warden env exec php-fpm bin/magento module:enable MageClone_OrderReplicator
warden env exec php-fpm bin/magento setup:upgrade
warden env exec php-fpm bin/magento setup:di:compile
warden env exec php-fpm bin/magento cache:flush
```

### New Warden Project from Scratch

```bash
# 1. Create project directory
mkdir ~/magento-orderreplicator && cd ~/magento-orderreplicator

# 2. Initialize Warden
warden env-init magento-orderreplicator magento2

# 3. Edit .env if needed (set PHP version, DB, etc.)
# Default is fine for most setups

# 4. Start environment
warden env up

# 5. Install Magento (inside container)
warden env exec php-fpm composer create-project \
    --repository-url=https://repo.magento.com/ \
    magento/project-community-edition .

# 6. Install Magento application
warden env exec php-fpm bin/magento setup:install \
    --db-host=db \
    --db-name=magento \
    --db-user=magento \
    --db-password=magento \
    --base-url=https://app.magento-orderreplicator.test/ \
    --admin-firstname=Admin \
    --admin-lastname=User \
    --admin-email=admin@example.com \
    --admin-user=admin \
    --admin-password=Admin123! \
    --language=en_US \
    --currency=USD \
    --timezone=America/New_York \
    --search-engine=opensearch \
    --opensearch-host=opensearch \
    --opensearch-port=9200

# 7. Copy the module
mkdir -p app/code/MageClone/
cp -r /path/to/OrderReplicator app/code/MageClone/OrderReplicator

# 8. Enable module
warden env exec php-fpm bin/magento module:enable MageClone_OrderReplicator
warden env exec php-fpm bin/magento setup:upgrade
warden env exec php-fpm bin/magento setup:di:compile
warden env exec php-fpm bin/magento setup:static-content:deploy -f
warden env exec php-fpm bin/magento cache:flush

# 9. Create sample data (optional, for testing)
warden env exec php-fpm bin/magento sampledata:deploy
warden env exec php-fpm bin/magento setup:upgrade

# 10. Access admin
# URL: https://app.magento-orderreplicator.test/admin
# User: admin / Admin123!
```

---

## Verify Installation

After installation, verify the module is active:

```bash
bin/magento module:status MageClone_OrderReplicator
# Should output: Module is enabled

bin/magento setup:db:status
# Should show no pending migrations
```

Check the admin menu:
- Login to admin panel
- Navigate to **Sales** in the left menu
- You should see **Order Replicator** submenu

---

## Running Tests

```bash
# From Magento root
vendor/bin/phpunit -c app/code/MageClone/OrderReplicator/phpunit.xml

# With Warden
warden env exec php-fpm vendor/bin/phpunit \
    -c app/code/MageClone/OrderReplicator/phpunit.xml

# With coverage
vendor/bin/phpunit -c app/code/MageClone/OrderReplicator/phpunit.xml \
    --coverage-html var/coverage/orderreplicator
```

---

## Coding Standards Check

```bash
# Magento 2 coding standard
vendor/bin/phpcs \
    --standard=Magento2 \
    app/code/MageClone/OrderReplicator/

# Auto-fix
vendor/bin/phpcbf \
    --standard=Magento2 \
    app/code/MageClone/OrderReplicator/
```

---

## Database

The module creates one table:

**`mageclone_replication_log`**

| Column | Type | Description |
|--------|------|-------------|
| `log_id` | INT (PK, AUTO) | Primary key |
| `source_order_id` | INT | Original order entity_id |
| `source_increment_id` | VARCHAR(50) | Original order number |
| `new_order_id` | INT | New order entity_id |
| `new_increment_id` | VARCHAR(50) | New order number |
| `customer_email` | VARCHAR(255) | Target customer email |
| `modifications_json` | TEXT | JSON of SKU/price changes |
| `status` | VARCHAR(20) | pending/processing/completed/failed |
| `error_message` | TEXT | Error details if failed |
| `csv_filename` | VARCHAR(255) | Source CSV file if bulk |
| `admin_user_id` | INT | Admin who initiated |
| `created_at` | TIMESTAMP | Created timestamp |
| `updated_at` | TIMESTAMP | Last updated timestamp |

---

## Uninstall

```bash
# Disable module
bin/magento module:disable MageClone_OrderReplicator

# Remove database table
bin/magento setup:upgrade

# Remove files
rm -rf app/code/MageClone/OrderReplicator

# Or via Composer
composer remove mageclone/module-order-replicator
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## File Checklist

Verify all files are present after cloning:

```
✓ registration.php
✓ composer.json
✓ phpunit.xml
✓ etc/module.xml
✓ etc/di.xml
✓ etc/acl.xml
✓ etc/db_schema.xml
✓ etc/adminhtml/routes.xml
✓ etc/adminhtml/menu.xml
✓ etc/adminhtml/system.xml
✓ Api/OrderReplicatorInterface.php
✓ Model/OrderReplicator.php
✓ Model/CsvProcessor.php
✓ Model/ReplicationLog.php
✓ Model/ReplicationLogFactory.php
✓ Model/ResourceModel/ReplicationLog.php
✓ Model/ResourceModel/ReplicationLog/Collection.php
✓ Controller/Adminhtml/Order/Index.php
✓ Controller/Adminhtml/Order/View.php
✓ Controller/Adminhtml/Order/Replicate.php
✓ Controller/Adminhtml/Csv/Upload.php
✓ Controller/Adminhtml/Csv/Process.php
✓ Controller/Adminhtml/Csv/DownloadTemplate.php
✓ Controller/Adminhtml/Log/Index.php
✓ Block/Adminhtml/Order/ViewOrder.php
✓ Block/Adminhtml/Csv/Upload.php
✓ Ui/Component/Listing/Column/Actions.php
✓ Helper/Config.php
✓ view/adminhtml/layout/orderreplicator_order_index.xml
✓ view/adminhtml/layout/orderreplicator_order_view.xml
✓ view/adminhtml/layout/orderreplicator_csv_upload.xml
✓ view/adminhtml/layout/orderreplicator_log_index.xml
✓ view/adminhtml/ui_component/mageclone_order_listing.xml
✓ view/adminhtml/ui_component/mageclone_replication_log_listing.xml
✓ view/adminhtml/templates/order/view.phtml
✓ view/adminhtml/templates/csv/upload.phtml
✓ view/adminhtml/web/css/order-replicator.css
✓ view/adminhtml/web/js/order-replicate.js
✓ view/adminhtml/web/js/csv-upload.js
✓ Test/Unit/Helper/ConfigTest.php
✓ Test/Unit/Model/CsvProcessorTest.php
✓ Test/Unit/Model/OrderReplicatorTest.php
✓ Test/Unit/Controller/Adminhtml/Order/ReplicateTest.php
```

---

## Support

For issues or feature requests, open a GitHub issue or contact dev@mageclone.com.
