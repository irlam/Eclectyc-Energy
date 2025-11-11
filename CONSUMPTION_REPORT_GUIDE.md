# Consumption Report Troubleshooting Guide

## Understanding "Unable to load consumption data right now"

The consumption report at `/reports/consumption` may show an error message for several reasons. This guide explains why this happens and how to resolve it.

## Common Causes and Solutions

### 1. Database Connection Issues

**Symptom:** Error message "Database connection not available."

**Cause:** The application cannot connect to the database.

**Solution:**
- Check your `.env` file has correct database credentials
- Verify the database server is running
- Test database connection manually

```bash
# Check if MySQL/MariaDB is running
sudo systemctl status mysql
# or
sudo systemctl status mariadb

# Test connection
mysql -u [username] -p[password] -h [host] [database_name]
```

### 2. Missing Database Tables

**Symptom:** Error message "Unable to load consumption data right now."

**Cause:** Required tables (`sites`, `meters`, `daily_aggregations`) don't exist.

**Solution:** Run the database migrations to create the required tables:

```bash
# Navigate to the database directory
cd database/migrations

# Run migrations in order
mysql -u [username] -p[password] [database_name] < 001_create_initial_schema.sql
mysql -u [username] -p[password] [database_name] < 002_create_meter_readings.sql
# ... (run all migration files in order)
```

### 3. No Data Imported

**Symptom:** Report loads but shows "No meter readings found for the selected period."

**Cause:** No data has been imported into the system yet.

**Solution:** Import meter reading data:

**Option A: Via Web Interface**
1. Log in as an admin user
2. Navigate to `/admin/imports`
3. Upload a CSV file with meter readings
4. Wait for import to complete

**Option B: Via Command Line**
```bash
# Use the import script
php scripts/import_meter_data.php /path/to/your/data.csv
```

**CSV Format Required:**
```csv
mpan,reading_date,reading_time,period_number,reading_value,reading_type
1234567890123,2024-01-01,00:30,1,15.5,actual
1234567890123,2024-01-01,01:00,2,14.8,actual
```

### 4. Data Not Aggregated

**Symptom:** Data imported but report shows no consumption.

**Cause:** The daily aggregation process hasn't run. The consumption report relies on the `daily_aggregations` table which is populated by a scheduled process.

**Solution:** Run the aggregation process:

```bash
# Manual aggregation
php scripts/aggregate_daily_consumption.php

# Or set up cron job for automatic daily aggregation
0 2 * * * cd /path/to/eclectyc-energy && php scripts/aggregate_daily_consumption.php
```

### 5. User Has No Site Access

**Symptom:** Report loads but shows no data (for non-admin users).

**Cause:** The user account doesn't have access to any sites.

**Solution:** Grant site access to the user:

1. Log in as an admin
2. Navigate to `/admin/users`
3. Click on the user
4. Click "Manage Access" button
5. Grant access to:
   - Specific companies (user can see all sites in those companies)
   - Specific regions (user can see all sites in those regions)
   - Specific sites (user can see only those sites)

**Note:** Admin users automatically have access to all sites.

### 6. SQL Query Error

**Symptom:** Error message "Unable to load consumption data right now."

**Cause:** SQL query failed due to:
- Missing columns in database tables
- Incompatible MySQL/MariaDB version
- Database schema out of date

**Solution:** 
1. Check the error logs: `logs/app.log` or `logs/php_errors.log`
2. Verify table structure matches expected schema
3. Run schema updates if needed:

```bash
# Check current schema
mysql -u [username] -p[password] [database_name] -e "SHOW TABLES;"
mysql -u [username] -p[password] [database_name] -e "DESCRIBE daily_aggregations;"
mysql -u [username] -p[password] [database_name] -e "DESCRIBE meters;"
mysql -u [username] -p[password] [database_name] -e "DESCRIBE sites;"
```

## How to Populate Data

### Step-by-Step Data Population Process

#### 1. Create Site and Meter Structure

```sql
-- Example: Create a company
INSERT INTO companies (name, created_at, updated_at) 
VALUES ('Example Company', NOW(), NOW());

-- Example: Create a site
INSERT INTO sites (name, company_id, is_active, created_at, updated_at)
VALUES ('Example Site', 1, 1, NOW(), NOW());

-- Example: Create a meter
INSERT INTO meters (mpan, site_id, is_active, created_at, updated_at)
VALUES ('1234567890123', 1, 1, NOW(), NOW());
```

#### 2. Import Meter Readings

**Via Web Interface:**
1. Navigate to `/admin/imports`
2. Select your CSV file (must have columns: mpan, reading_date, reading_time, period_number, reading_value, reading_type)
3. Click "Upload"
4. Monitor import progress

**Via API:**
```bash
# Using curl to upload file
curl -X POST http://your-domain.com/admin/imports \
  -H "Cookie: your-session-cookie" \
  -F "file=@/path/to/readings.csv"
```

#### 3. Run Daily Aggregation

After importing readings, run aggregation to populate the consumption report:

```bash
php scripts/aggregate_daily_consumption.php
```

This process:
- Sums up all half-hourly readings for each meter per day
- Stores results in `daily_aggregations` table
- Enables the consumption report to display data efficiently

#### 4. Verify Data

```sql
-- Check if readings were imported
SELECT COUNT(*) FROM meter_readings;

-- Check if aggregations were created
SELECT COUNT(*) FROM daily_aggregations;

-- View sample consumption data
SELECT 
    s.name AS site_name,
    m.mpan,
    da.date,
    da.total_consumption
FROM daily_aggregations da
JOIN meters m ON da.meter_id = m.id
JOIN sites s ON m.site_id = s.id
ORDER BY da.date DESC
LIMIT 10;
```

## Report Features

The consumption report provides:

- **Total consumption** across all accessible sites
- **Per-site breakdown** showing consumption for each site
- **Date range filtering** to view specific time periods
- **Meter count** per site
- **Percentage of total** for each site
- **Data range** showing first and last reading dates
- **Per-metric analysis** (optional) if meters have metric variables configured

## Troubleshooting Checklist

- [ ] Database connection working
- [ ] All required tables exist (sites, meters, meter_readings, daily_aggregations)
- [ ] Sites created in the database
- [ ] Meters created and linked to sites
- [ ] Meter readings imported
- [ ] Daily aggregation process run
- [ ] User has access to sites (or is admin)
- [ ] Date range selected has data available
- [ ] Error logs checked for specific errors

## Getting Help

If you continue to experience issues:

1. Check the application logs: `logs/app.log`
2. Check PHP error logs: `logs/php_errors.log`
3. Verify database schema is up to date
4. Contact system administrator with error details

## Quick Reference: Required Tables

```sql
-- Sites table
CREATE TABLE sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Meters table
CREATE TABLE meters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mpan VARCHAR(13) NOT NULL UNIQUE,
    site_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- Meter readings table
CREATE TABLE meter_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT NOT NULL,
    reading_date DATE NOT NULL,
    reading_time TIME NOT NULL,
    period_number INT NOT NULL,
    reading_value DECIMAL(10,2) NOT NULL,
    reading_type ENUM('actual', 'estimated') DEFAULT 'actual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id),
    INDEX idx_meter_date (meter_id, reading_date)
);

-- Daily aggregations table
CREATE TABLE daily_aggregations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT NOT NULL,
    date DATE NOT NULL,
    total_consumption DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id),
    UNIQUE KEY unique_meter_date (meter_id, date),
    INDEX idx_date (date)
);
```
