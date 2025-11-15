# Database Connection Management Fix

## Problem

The application was experiencing "max_user_connections" errors that would lock users out of the website. The root cause was that database connections were not being properly closed, leading to connection pool exhaustion.

## Root Causes Identified

1. **Persistent connections enabled globally**: `PDO::ATTR_PERSISTENT => true` was set in both `app/Config/Database.php` and `public/index.php`, causing connections to remain open indefinitely.

2. **Long-running script without cleanup**: The `process_import_jobs.php` script runs continuously in the background but never closed its database connection between iterations.

3. **No explicit connection cleanup**: There was no explicit cleanup of database connections in the application lifecycle.

4. **Cleanup script failed when needed most**: The `cleanup_db_connections.php` script would itself fail to connect when the connection pool was exhausted, making it unable to diagnose the problem.

## Solutions Implemented

### 1. Removed Persistent Connections

**Files Modified:**
- `app/Config/Database.php`
- `public/index.php`

**Changes:**
- Removed `PDO::ATTR_PERSISTENT => true` from connection options
- Moved `PDO::ATTR_TIMEOUT => 30` into the connection options array for cleaner code
- Connections now close automatically when the PHP request/script ends

**Why this fixes it:**
- Non-persistent connections are automatically closed when the PDO object is destroyed
- Each web request gets a fresh connection that is properly cleaned up
- Prevents connection accumulation over time

### 2. Added Connection Cleanup to process_import_jobs.php

**Changes:**
- Added `Database::closeConnection()` before sleep periods when no jobs are in queue
- Added reconnection logic after sleep to get a fresh connection
- Added `Database::closeConnection()` between job processing iterations
- Added shutdown handler to ensure cleanup on script termination
- Added cleanup in exception handlers

**Why this fixes it:**
- The script can run indefinitely without accumulating connections
- Each iteration gets a fresh connection
- Prevents stale connections from timing out in the database

### 3. Enhanced cleanup_db_connections.php

**Changes:**
- Added detailed emergency instructions when the script can't connect due to max_user_connections
- Provides alternative manual cleanup options:
  - Restart PHP-FPM/web server
  - Manually kill connections via MySQL as admin
  - Restart MySQL (last resort)

**Why this helps:**
- When the connection pool is exhausted, the script provides actionable guidance
- User knows what to do without having to figure it out

### 4. Added Validation Tests

**New Files:**
- `scripts/test_connection_cleanup.php` - Quick validation script
- `tests/test_db_connection_cleanup.php` - Comprehensive test suite

**Tests verify:**
- Connections can be established
- Connections can be closed
- Connections can be re-established after closing
- Persistent connections are disabled
- Connection count stays within normal range

## Testing the Fix

### Quick Test

```bash
php scripts/test_connection_cleanup.php
```

This will:
1. Open a database connection
2. Count current connections
3. Close the connection
4. Reconnect and verify count
5. Verify persistent connections are disabled

### Comprehensive Test

```bash
php tests/test_db_connection_cleanup.php
```

This runs a full test suite with 5 tests covering all aspects of connection management.

### Monitor Connections

```bash
# Check current connection status
php scripts/cleanup_db_connections.php

# Kill idle connections (if needed)
php scripts/cleanup_db_connections.php --kill-idle
```

## Deployment Instructions

### 1. Deploy the Code Changes

Deploy all modified files to production:
- `app/Config/Database.php`
- `public/index.php`
- `scripts/process_import_jobs.php`
- `scripts/cleanup_db_connections.php`
- `scripts/test_connection_cleanup.php` (optional)
- `tests/test_db_connection_cleanup.php` (optional)

### 2. Restart the Import Job Processor

If `process_import_jobs.php` is running as a cron job or background process:

```bash
# Find the process
ps aux | grep process_import_jobs.php

# Kill the old process
kill <pid>

# Wait a moment for cron to restart it, or start it manually
php scripts/process_import_jobs.php &
```

### 3. Monitor Connection Count

After deployment, monitor the connection count:

```bash
# Run this several times over a few minutes
php scripts/cleanup_db_connections.php
```

You should see:
- Connection count stays stable (not increasing over time)
- No warnings about high connection usage
- "Persistent connections are DISABLED" in test output

### 4. If Connections Are Still High

If you're still seeing high connection counts after deployment:

```bash
# Kill idle connections
php scripts/cleanup_db_connections.php --kill-idle --max-idle=180

# Check for multiple instances of the import processor
ps aux | grep process_import_jobs.php

# Make sure only ONE instance is running
# Kill any duplicates
```

## Preventing Future Issues

### 1. Ensure Only One Import Processor

**✨ UPDATE (2025-11-15):** The script now includes a built-in lock mechanism to prevent multiple instances from running simultaneously. This makes cron setup much simpler and safer.

**Recommended cron configuration:**

```bash
# RECOMMENDED - Use --once flag with lock mechanism (safe to run frequently)
*/2 * * * * cd /path/to/eclectyc-energy && /usr/local/php84/bin/php scripts/process_import_jobs.php --once >> logs/import_worker.log 2>&1

# The lock mechanism ensures only one instance runs at a time, even if cron
# tries to start a new one while the previous is still running
```

**Alternative configurations:**

```bash
# Good - Long-running daemon with monitoring
@reboot cd /path/to/eclectyc-energy && /usr/local/php84/bin/php scripts/process_import_jobs.php >> logs/import_worker.log 2>&1 &
*/5 * * * * pgrep -f process_import_jobs.php || (cd /path/to/eclectyc-energy && /usr/local/php84/bin/php scripts/process_import_jobs.php >> logs/import_worker.log 2>&1 &)

# Bad - Old approach without --once flag (NOT RECOMMENDED)
*/2 * * * * /usr/local/php84/bin/php /path/to/scripts/process_import_jobs.php
```

For detailed cron setup instructions and troubleshooting, see [CRON_SETUP_FIX.md](CRON_SETUP_FIX.md).

### 2. Monitor Connection Count

Set up a cron job to monitor connections:

```bash
# Every 5 minutes
*/5 * * * * /usr/local/php84/bin/php /path/to/scripts/cleanup_db_connections.php >> /path/to/logs/connection-monitor.log 2>&1
```

### 3. Set Up Connection Pool Alerts

Monitor the connection usage and alert when it exceeds 60%:

```bash
# Check and alert if usage > 60%
*/10 * * * * /usr/local/php84/bin/php /path/to/scripts/cleanup_db_connections.php | grep -q "CAUTION\|WARNING" && echo "High connection usage detected" | mail -s "DB Connection Alert" admin@eclectyc.energy
```

## Technical Details

### Why Persistent Connections Can Cause Issues

Persistent connections (`PDO::ATTR_PERSISTENT => true`) are designed to improve performance by reusing database connections across requests. However, they can cause issues:

1. **Connection pooling complexity**: Each PHP-FPM worker maintains its own pool of persistent connections
2. **No automatic cleanup**: Persistent connections stay open until the worker process terminates
3. **Resource exhaustion**: With many workers and high traffic, you can quickly exhaust the database connection limit
4. **Long-running processes**: Scripts like `process_import_jobs.php` hold connections indefinitely

### When to Use Persistent Connections

Persistent connections are useful when:
- You have a small, predictable number of PHP workers
- Database connection overhead is significant
- You have a high connection limit on the database
- You understand and can manage the connection pool

For most applications (including this one), non-persistent connections are safer and more predictable.

### Connection Lifecycle

**Before fix:**
```
Web Request → Create PDO (persistent) → Use connection → Request ends → Connection stays open
Web Request → Reuse same connection → Use connection → Request ends → Connection stays open
... (connection stays open indefinitely)
```

**After fix:**
```
Web Request → Create PDO (non-persistent) → Use connection → Request ends → Connection closes
Web Request → Create new PDO → Use connection → Request ends → Connection closes
... (clean slate each time)
```

## Troubleshooting

### Still getting max_user_connections errors?

1. **Check connection limit**: Verify your database user's connection limit
   ```sql
   SHOW VARIABLES LIKE 'max_user_connections';
   ```

2. **Check for hung PHP processes**:
   ```bash
   ps aux | grep php
   # Look for processes that have been running for a long time
   # Kill any that shouldn't be running
   kill -9 <pid>
   ```

3. **Check for multiple import processors**:
   ```bash
   ps aux | grep process_import_jobs.php
   # Should only see ONE instance
   ```

4. **Restart PHP-FPM** (if using):
   ```bash
   sudo systemctl restart php-fpm
   # or
   sudo systemctl restart php8.4-fpm
   ```

5. **Check MySQL processlist**:
   ```sql
   SHOW FULL PROCESSLIST;
   -- Look for sleeping connections or long-running queries
   ```

### Emergency Recovery

If you're completely locked out:

```bash
# Option 1: Restart PHP-FPM (preferred)
sudo systemctl restart php-fpm

# Option 2: Kill connections via MySQL (as admin/root)
mysql -u root -p
SELECT * FROM information_schema.processlist WHERE User = 'k87747_eclectyc';
KILL <connection_id>;  # Repeat for each connection

# Option 3: Restart MySQL (last resort)
sudo systemctl restart mysql
```

## Summary

The fix addresses database connection exhaustion by:
1. ✅ Removing persistent connections to allow automatic cleanup
2. ✅ Adding explicit cleanup in long-running scripts
3. ✅ Providing better diagnostics and recovery options
4. ✅ Adding validation tests to verify the fix

This should completely resolve the "max_user_connections" errors and prevent future lockouts.
