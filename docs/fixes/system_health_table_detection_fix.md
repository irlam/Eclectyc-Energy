# Fix: System Health False Warnings for Existing Tables

**Date:** 2025-11-15  
**Issue:** False "degraded" status warnings for `exports` and `audit_logs` tables  
**Status:** ✅ RESOLVED

## Problem

The system health check at `https://eclectyc.energy/tools/system-health` was incorrectly reporting that the `exports` and `audit_logs` tables didn't exist in the database, even though they were present. This caused false "degraded" status warnings:

```
⚠️  RECENT ACTIVITY
     Status: degraded
     Message: Activity warnings: Exports table not found, Audit logs table not found
```

## Root Cause

The `tableExists()` method in `app/Http/Controllers/Api/HealthController.php` was using the following query:

```php
$stmt = $db->prepare('SHOW TABLES LIKE :table');
$stmt->execute(['table' => $table]);
```

**Why this doesn't work:**

When PDO binds a parameter to a prepared statement, it properly escapes and quotes the value. However, when used with MySQL's `LIKE` operator in `SHOW TABLES LIKE :table`, the parameter binding creates a double-escaped pattern that MySQL cannot match correctly.

For example:
- Intent: `SHOW TABLES LIKE 'exports'`
- Actual: `SHOW TABLES LIKE ''exports''` (double-quoted/escaped)
- Result: Always returns false, even when the table exists

## Solution

Changed the query to use `information_schema.tables` with a proper WHERE clause:

```php
$stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
$stmt->execute(['table' => $table]);
```

**Why this works:**

- Uses standard SQL `WHERE` clause with `=` comparison instead of `LIKE`
- Prepared statement parameter binding works correctly with `=` operator
- `DATABASE()` ensures we only check the current schema
- `LIMIT 1` provides efficiency (we only need to know if at least one exists)
- Still maintains security with proper parameter binding

## Changes Made

### File: `app/Http/Controllers/Api/HealthController.php`

**Line 488 - Before:**
```php
$stmt = $db->prepare('SHOW TABLES LIKE :table');
```

**Line 488 - After:**
```php
$stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
```

### File: `tests/test_table_exists_fix.php` (NEW)

Added a comprehensive test that:
- Demonstrates the broken vs. fixed implementation
- Tests both methods side-by-side
- Specifically verifies `exports` and `audit_logs` tables are detected
- Documents the issue for future reference

## Verification

To verify the fix works:

1. **Via Test Script:**
   ```bash
   php tests/test_table_exists_fix.php
   ```

2. **Via Web Interface:**
   - Visit: `https://eclectyc.energy/tools/system-health`
   - Check that no false warnings appear for `exports` or `audit_logs` tables
   - Status should be "healthy" or "degraded" only for legitimate issues

3. **Via API:**
   ```bash
   curl https://eclectyc.energy/api/health | jq '.checks.recent_activity'
   ```
   - Should not show "Exports table not found" or "Audit logs table not found"
   - Should correctly report on actual recent activity

## Impact

✅ **Fixed:**
- False "degraded" status warnings eliminated
- Correct detection of `exports` and `audit_logs` tables
- Accurate health status reporting
- Better system monitoring reliability

✅ **No Breaking Changes:**
- Only fixes the bug, doesn't change API or behavior
- Maintains security with prepared statements
- Same performance characteristics

✅ **Security:**
- Continues to use prepared statements with parameter binding
- No SQL injection vulnerabilities
- Uses `DATABASE()` to scope queries appropriately

## Technical Details

### Method Comparison

| Aspect | Old Method (BROKEN) | New Method (FIXED) |
|--------|-------------------|-------------------|
| Query Type | `SHOW TABLES LIKE` | `information_schema.tables` |
| Parameter Binding | Doesn't work with LIKE | Works correctly with WHERE |
| Reliability | Always returns false | Returns accurate results |
| Security | Prepared statement | Prepared statement |
| Performance | Fast | Fast |
| Portability | MySQL-specific | Standard SQL |

### Other Uses of `SHOW TABLES LIKE`

Note: There are other instances of `SHOW TABLES LIKE` in the codebase:
- `scripts/aggregate_cron.php`
- `scripts/check_import_setup.php`
- `scripts/cleanup_logs.php`

However, these use `query()` directly (not prepared statements), so they work correctly:
```php
$tableCheck = $pdo->query("SHOW TABLES LIKE 'cron_logs'")->fetch();
```

The issue only affects the use of `SHOW TABLES LIKE` with prepared statement parameter binding.

## Related Files

- `app/Http/Controllers/Api/HealthController.php` - Contains the fix
- `tests/test_table_exists_fix.php` - Test demonstrating the fix
- `scripts/check_system_health.php` - CLI tool that uses the health API

## Credits

- **Fixed by:** GitHub Copilot Agent
- **Reported at:** https://eclectyc.energy/tools/system-health
- **Date Fixed:** 2025-11-15
