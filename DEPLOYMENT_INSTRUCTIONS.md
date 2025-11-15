# DEPLOYMENT INSTRUCTIONS

## Overview
This PR fixes two critical issues:
1. **aggregate_annual error**: Column not found 'year_start' 
2. **CSV Import - Sites**: Added Sites CSV import with date validation

## Database Changes Required

### IMPORTANT: Execute these SQL scripts in phpMyAdmin

#### 1. Fix Annual Aggregations Table
Execute the SQL in file: `PHPMYADMIN_FIX_ANNUAL_AGGREGATIONS.sql`

This will:
- Convert the `year` column (YEAR type) to `year_start` and `year_end` (DATE columns)
- Preserve existing data by converting YEAR to date ranges (Jan 1 - Dec 31)
- Update indexes and constraints

#### 2. Add Sites Import Type
Execute the SQL in file: `PHPMYADMIN_ADD_SITES_IMPORT_TYPE.sql`

This will:
- Add 'sites' to the import_type enum in `import_jobs` table
- Add 'sites' to the import_type enum in `sftp_configurations` table

## Code Changes

### New Files
- `app/Domain/Ingestion/SitesCsvImportService.php` - Sites CSV import processor
- `database/migrations/018_fix_annual_aggregations_schema.sql` - Migration script
- `PHPMYADMIN_FIX_ANNUAL_AGGREGATIONS.sql` - Direct SQL for phpMyAdmin
- `PHPMYADMIN_ADD_SITES_IMPORT_TYPE.sql` - Direct SQL for phpMyAdmin
- `SITES_IMPORT_README.md` - Documentation
- `Test_Sites_Data.csv` - Sample valid CSV
- `Test_Sites_Row46_Error.csv` - Sample CSV with error at row 46
- `tests/test_sites_csv_validation.php` - Validation test script

### Modified Files
- `database/database.sql` - Updated annual_aggregations table and import_type enums
- `app/Domain/Ingestion/CsvIngestionService.php` - Added 'sites' import support
- `app/Http/Controllers/Admin/ImportController.php` - Added 'sites' validation
- `scripts/import_csv.php` - Added 'sites' help text and validation

## Testing

### Test Annual Aggregations Fix
After running the SQL:
```bash
php scripts/aggregate_annual.php -d 2025-01-01
```
Expected: No "year_start" column error

### Test Sites CSV Import
```bash
# Valid import (dry run)
php scripts/import_csv.php -f Test_Sites_Data.csv -t sites --dry-run

# Test validation with errors
php tests/test_sites_csv_validation.php
```

Expected output for row 46 error:
```
Row 47, Column 3: Invalid date format in created_at field
```
(Row 47 = row 46 of data + 1 header row)

## Using Sites CSV Import

### CLI
```bash
php scripts/import_csv.php -f /path/to/sites.csv -t sites
```

### Web UI
1. Navigate to Admin > Imports
2. Select "Sites" from import type dropdown
3. Upload CSV file
4. Click Import

## CSV Format

### Required Field
- `name` - Site name

### Optional Fields  
- `company_id` - Numeric
- `region_id` - Numeric
- `address` - Text
- `postcode` - UK postcode
- `site_type` - office, warehouse, retail, industrial, residential, or other
- `floor_area` - Numeric (square meters)
- `latitude` - Numeric (-90 to 90)
- `longitude` - Numeric (-180 to 180)
- `is_active` - 1/true/yes/active for active
- `created_at` - Multiple formats supported:
  - Y-m-d H:i:s (2025-01-15 10:30:00)
  - Y-m-d (2025-01-15)
  - d/m/Y (15/01/2025)
  - d-m-Y (15-01-2025)
  - m/d/Y (01/15/2025)
  - Y/m/d (2025/01/15)

## Rollback (if needed)

If issues occur after deployment:

### Rollback Annual Aggregations
```sql
-- This will lose year_start/year_end data!
ALTER TABLE annual_aggregations DROP COLUMN year_start;
ALTER TABLE annual_aggregations DROP COLUMN year_end;
ALTER TABLE annual_aggregations ADD COLUMN year YEAR NOT NULL;
-- Then restore from backup
```

### Rollback Sites Import Type
```sql
ALTER TABLE import_jobs 
MODIFY COLUMN import_type ENUM('hh','daily') 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hh';

ALTER TABLE sftp_configurations 
MODIFY COLUMN import_type ENUM('hh','daily') 
COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hh';
```

## Verification

After deployment, verify:

1. ✓ Annual aggregations runs without error
2. ✓ Sites CSV import accepts 'sites' type
3. ✓ Invalid date formats are caught and reported with row/column numbers
4. ✓ Existing 'hh' and 'daily' imports still work

## Security Summary

✓ No security vulnerabilities detected by CodeQL
✓ All user inputs are validated and sanitized
✓ SQL injection prevented through prepared statements
✓ File uploads validated for CSV format
✓ Numeric boundaries checked (latitude, longitude)
✓ Enum values validated against allowed types

## Support

For issues or questions, see:
- `SITES_IMPORT_README.md` - Full Sites import documentation
- Test files in repository root for examples
