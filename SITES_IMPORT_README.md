# CSV Import - Sites Documentation

## Overview
The Sites CSV Import feature allows bulk import of site data from CSV files.

## Database Changes Required

### Step 1: Fix Annual Aggregations Table
Copy and paste this SQL into phpMyAdmin:

```sql
-- File: PHPMYADMIN_FIX_ANNUAL_AGGREGATIONS.sql
```
See full content in `PHPMYADMIN_FIX_ANNUAL_AGGREGATIONS.sql`

### Step 2: Add Sites Import Type
Copy and paste this SQL into phpMyAdmin:

```sql
-- File: PHPMYADMIN_ADD_SITES_IMPORT_TYPE.sql
```
See full content in `PHPMYADMIN_ADD_SITES_IMPORT_TYPE.sql`

## CSV Format

### Required Column
- `name`: Site name (required)

### Optional Columns
- `company_id`: Numeric company ID
- `region_id`: Numeric region ID
- `address`: Site address
- `postcode`: UK postcode
- `site_type`: One of: office, warehouse, retail, industrial, residential, other
- `floor_area`: Numeric floor area in square meters
- `latitude`: Numeric latitude (-90 to 90)
- `longitude`: Numeric longitude (-180 to 180)
- `is_active`: 1, true, yes, active for active; anything else for inactive
- `created_at`: Date in format: Y-m-d H:i:s, Y-m-d, d/m/Y, d-m-Y, m/d/Y, or Y/m/d

### Example CSV
```csv
name,company_id,region_id,address,postcode,site_type,floor_area,created_at
Main Office,1,1,123 Business St London,SW1A 1AA,office,500.50,2025-01-15
Warehouse A,1,2,456 Industrial Rd,M1 1AA,warehouse,2000,2025-02-20
```

## Usage

### CLI Import
```bash
php scripts/import_csv.php -f /path/to/sites.csv -t sites

# Dry run (validation only)
php scripts/import_csv.php -f /path/to/sites.csv -t sites --dry-run
```

### Web UI
1. Go to Admin > Imports
2. Select "Sites" as import type
3. Upload CSV file
4. Click Import

## Error Messages

The import will provide detailed error messages including:
- Row number (starting from 2, since row 1 is header)
- Column number
- Error description

Example:
```
Row 47, Column 3: Invalid date format in created_at field
```

## Validation Rules

1. **Name**: Required, cannot be empty
2. **Company ID**: Must be numeric if provided
3. **Region ID**: Must be numeric if provided
4. **Site Type**: Must be one of the valid types if provided
5. **Floor Area**: Must be numeric if provided
6. **Latitude**: Must be numeric and between -90 and 90
7. **Longitude**: Must be numeric and between -180 and 180
8. **Created At**: Must match one of the supported date formats

## Testing

A test script is available:
```bash
php tests/test_sites_csv_validation.php
```

Sample test files:
- `Test_Sites_Data.csv` - Valid data
- `Test_Sites_Invalid.csv` - Contains invalid data for testing
- `Test_Sites_Row46_Error.csv` - Demonstrates error at row 46, column 3
