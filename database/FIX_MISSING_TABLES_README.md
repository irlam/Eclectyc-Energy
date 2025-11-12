# Database Fix for Missing Tables

## Issue
The system health check at `https://eclectyc.energy/tools/system-health` reports:
- ⚠️ "Exports table not found"
- ⚠️ "Audit logs table not found"

## Solution
Run the SQL script to create these tables with all required columns.

## How to Apply This Fix

### Option 1: Using phpMyAdmin (Recommended for Live Server)

1. **Access phpMyAdmin**
   - Log into your web hosting control panel (cPanel, Plesk, etc.)
   - Open phpMyAdmin
   - Select your database (usually named `energy_platform` or similar)

2. **Run the SQL Script**
   - Click on the **SQL** tab at the top
   - Open the file `database/fix_missing_tables.sql`
   - Copy the **entire contents** of the file
   - Paste it into the SQL query box
   - Click **Go** to execute

3. **Verify the Results**
   - The script will show verification messages at the end
   - You should see:
     - ✓ exports table exists
     - ✓ audit_logs table exists
   - The script also shows the column structure of both tables

### Option 2: Using MySQL Command Line

```bash
# Connect to your MySQL database
mysql -u your_username -p your_database_name

# Run the SQL file
source /path/to/Eclectyc-Energy/database/fix_missing_tables.sql

# Or pipe the file directly
mysql -u your_username -p your_database_name < /path/to/Eclectyc-Energy/database/fix_missing_tables.sql
```

## What This Script Does

1. **Creates the `exports` table** if it doesn't exist
   - Tracks file exports from the system
   - Includes status tracking (pending, processing, completed, failed)
   - Links to the user who created the export

2. **Creates the `audit_logs` table** if it doesn't exist
   - Tracks all system activities for audit purposes
   - Includes status, retry_count, and parent_batch_id columns
   - Used for import tracking and system auditing

3. **Adds missing columns** to existing tables
   - If the tables exist but are missing columns (e.g., from incomplete migrations)
   - The script safely adds: `status`, `retry_count`, `parent_batch_id`

4. **Creates indexes** for performance
   - Ensures all necessary indexes are in place
   - Optimizes queries for health checks and reporting

## Verification

After running the script, verify the fix:

1. **Via phpMyAdmin**
   - Check that both `exports` and `audit_logs` tables appear in the left sidebar
   - Click on each table to see its structure

2. **Via System Health Page**
   - Visit: `https://eclectyc.energy/tools/system-health`
   - Refresh the page
   - The warnings about missing tables should be gone
   - Status should improve from "DEGRADED" to "HEALTHY" (if no other issues)

## Safety Notes

- ✅ This script is **safe to run multiple times**
- ✅ Uses `CREATE TABLE IF NOT EXISTS` - won't overwrite existing tables
- ✅ Checks for existing columns before adding them
- ✅ Won't delete or modify existing data
- ✅ Creates proper foreign key relationships to the `users` table

## Troubleshooting

### Error: "Cannot add foreign key constraint"
**Cause**: The `users` table doesn't exist yet

**Solution**: Run the base migrations first:
```sql
source /path/to/Eclectyc-Energy/database/migrations/001_create_tables.sql
```

### Error: "Table already exists"
**Cause**: The tables exist but the script is showing an error

**Solution**: The script uses `IF NOT EXISTS`, so this shouldn't happen. If it does, the tables are already there and you can ignore this error.

### Still showing "Table not found" in health check
**Possible causes**:
1. Wrong database selected in phpMyAdmin
2. Application is configured to use a different database
3. Database connection issue

**Solution**: 
- Check your `.env` file and verify `DB_DATABASE` matches the database where you ran the script
- Verify database connection settings: `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`

## Related Files

- `database/migrations/001_create_tables.sql` - Initial table creation
- `database/migrations/002_add_audit_logs_status.sql` - Adds status columns to audit_logs
- `app/Http/Controllers/Api/HealthController.php` - Health check implementation

## After Applying Fix

Once the tables are created, the system will:
- Track all data imports in the `audit_logs` table
- Track file exports in the `exports` table
- Show improved health status on the monitoring page
- Enable full functionality of the import/export features
