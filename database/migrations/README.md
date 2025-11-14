# Database Migrations

This directory contains database migration scripts to update existing databases with new schema changes.

## Overview

The main database schema is in `/database/k87747_eclectyc.sql`. This file is used for fresh installations and contains all tables and columns in their most recent form.

For **existing databases** that need to be updated, use the migration scripts in this directory.

## Available Migrations

### 1. add_batch_id_columns.sql
**Purpose:** Add batch tracking to meters and meter_readings tables

**Changes:**
- Adds `batch_id` column to `meters` table
- Adds `batch_id` column to `meter_readings` table
- Adds indexes for performance

**When to apply:** If your database was created before November 8, 2025, and you want to track which imports created which data.

### 2. add_import_jobs_defaults.sql
**Purpose:** Add default site and tariff assignment to import jobs

**Changes:**
- Adds `default_site_id` column to `import_jobs` table
- Adds `default_tariff_id` column to `import_jobs` table
- Adds foreign key constraints and indexes

**When to apply:** If your database was created before November 8, 2025, and you want to support automatic site/tariff assignment during imports.

## How to Apply Migrations

### Method 1: Using MySQL Command Line

```bash
# Login to MySQL
mysql -u your_username -p your_database_name

# Run a migration
source /path/to/project/database/migrations/add_batch_id_columns.sql
source /path/to/project/database/migrations/add_import_jobs_defaults.sql
```

### Method 2: Using PHP Migration Script

```bash
# From project root
php scripts/migrate.php
```

### Method 3: Manual Application

1. Open your MySQL client (phpMyAdmin, MySQL Workbench, etc.)
2. Select your database
3. Copy and paste the SQL from each migration file
4. Execute the SQL

## Migration Order

Apply migrations in this order:

1. `add_batch_id_columns.sql` (adds batch tracking)
2. `add_import_jobs_defaults.sql` (adds default assignments)

## Checking if Migrations are Needed

To check if you need to apply migrations, run this query:

```sql
-- Check if batch_id exists in meters table
SHOW COLUMNS FROM meters LIKE 'batch_id';

-- Check if batch_id exists in meter_readings table
SHOW COLUMNS FROM meter_readings LIKE 'batch_id';

-- Check if default_site_id exists in import_jobs table
SHOW COLUMNS FROM import_jobs LIKE 'default_site_id';
```

If any of these queries return 0 rows, you need to apply the corresponding migration.

## Rollback

To rollback migrations (use with caution):

```sql
-- Rollback add_batch_id_columns.sql
ALTER TABLE `meters` DROP COLUMN `batch_id`;
ALTER TABLE `meter_readings` DROP COLUMN `batch_id`;

-- Rollback add_import_jobs_defaults.sql
ALTER TABLE `import_jobs` 
  DROP FOREIGN KEY `fk_import_jobs_site`,
  DROP FOREIGN KEY `fk_import_jobs_tariff`,
  DROP COLUMN `default_site_id`,
  DROP COLUMN `default_tariff_id`;
```

## Notes

- Always backup your database before applying migrations
- Test migrations on a development copy first
- Migrations are idempotent where possible (they check for existence before adding)
- Some migrations include commented-out foreign key constraints that you can enable if needed

## Support

If you encounter issues with migrations, check:
1. MySQL version compatibility (requires MySQL 5.7+)
2. User permissions (need ALTER, CREATE, INDEX privileges)
3. Existing data conflicts
4. Table engine compatibility (all tables should be InnoDB)
