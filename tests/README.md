# Tests and Sample Data

This directory contains test scripts and sample CSV files for the Eclectyc Energy platform.

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

## Notes

- These test files use fictional MPAN numbers (1234567890123, 1234567890124)
- Before importing actual data, ensure the meters exist in the database
- Always test with dry-run mode first to validate your data format
- Sample files are intentionally small for quick testing
