# Tests and Sample Data

This directory contains test scripts and sample CSV files for the Eclectyc Energy platform.

## Prerequisites

Before running tests, ensure you have:
1. Installed dependencies: `composer install`
2. Configured `.env` file with database credentials
3. Run migrations: `php scripts/migrate.php`

## Test Scripts

### test_import_jobs.php
Integration test for the import job functionality. Validates:
- Class loading and autoloading
- Required methods exist
- File permissions and directory structure
- PHP syntax of worker scripts
- Template files exist

**Run the test:**
```bash
php tests/test_import_jobs.php
```

### test_retry_and_monitoring.php
Integration test for retry logic and monitoring services. Validates:
- Database schema updates (migration 005)
- Service instantiation (ImportJobService, ImportMonitoringService, ImportAlertService)
- Required methods exist on all services
- System health monitoring functionality
- Deployment configuration files exist
- Documentation completeness
- PHP syntax of new scripts

**Run the test:**
```bash
php tests/test_retry_and_monitoring.php
```

## Sample Data Files

### sample_hh_data.csv
Sample half-hourly meter reading data with 48 periods per day.
- Format: MPAN, Date, HH01-HH48
- Contains 2 meters with readings for one day

### sample_daily_data.csv
Sample daily meter reading data with single daily totals.
- Format: MPAN, Date, Reading
- Contains 2 meters with 5 days of readings

## Testing the Import Feature

### 1. Test CLI Import
```bash
# Test half-hourly import (dry-run)
php scripts/import_csv.php -f tests/sample_hh_data.csv -t hh --dry-run

# Test daily import (dry-run)
php scripts/import_csv.php -f tests/sample_daily_data.csv -t daily --dry-run
```

### 2. Test Web Interface
1. Start the PHP development server:
   ```bash
   php -S localhost:8080 -t public
   ```

2. Visit http://localhost:8080/admin/imports

3. Upload one of the sample CSV files

4. Check the "Process in background" option (requires background worker)

### 3. Test Background Worker
```bash
# Process any queued jobs once and exit
php scripts/process_import_jobs.php --once
```

### 4. Test Monitoring and Alerting
```bash
# Check import system health
php scripts/monitor_import_system.php --verbose

# Test cleanup (dry run)
php scripts/cleanup_import_jobs.php --days 30 --dry-run
```

## Notes

- These test files use fictional MPAN numbers (1234567890123, 1234567890124)
- Before importing actual data, ensure the meters exist in the database
- Always test with dry-run mode first to validate your data format
- Sample files are intentionally small for quick testing
