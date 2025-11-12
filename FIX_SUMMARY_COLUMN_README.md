# Fix for Missing 'summary' Column Error

## Problem
When accessing `https://eclectyc.energy/admin/imports/status/{batch-id}`, you encountered the error:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'summary' in 'field list'
```

## Root Cause
The `import_jobs` table was missing the `summary` column. This column is used by the `ImportJobService::completeJob()` method to store JSON summary data about import results.

## Solution

### Quick Fix (For phpMyAdmin)
Copy and paste the following SQL command into phpMyAdmin:

```sql
ALTER TABLE `import_jobs`
ADD COLUMN `summary` JSON NULL COMMENT 'JSON summary of import results including errors and statistics' AFTER `error_message`;
```

**OR** you can execute the file `FIX_SUMMARY_COLUMN.sql` in phpMyAdmin.

### Files Updated

1. **database/migrations/017_add_summary_column_to_import_jobs.sql** - Migration file to add the column
2. **database/database.sql** - Updated the schema to include the summary column in the table definition
3. **FIX_SUMMARY_COLUMN.sql** - Simple SQL file you can run directly in phpMyAdmin

## Verification

After running the SQL command, you should be able to:
1. Access import status pages without errors
2. View completed import job summaries
3. See detailed statistics about import results

## What the summary Column Stores

The `summary` column stores a JSON object containing:
- `records_processed` - Total number of records processed
- `records_imported` - Number of successfully imported records
- `records_failed` - Number of failed records
- `errors` - Array of error messages
- Other metadata about the import job

This information is displayed on the import status page at `/admin/imports/status/{batch-id}`.
