# Fix Summary: Database Connection Exhaustion from process_import_jobs.php

**Date:** 2025-11-15  
**Issue:** `SQLSTATE[HY000] [1203] User k87747_eclectyc already has more than 'max_user_connections' active connections`

## Root Cause

The `process_import_jobs.php` script was running in multiple instances simultaneously due to the cron configuration (`*/2 * * * *` without the `--once` flag). Each instance held database connections, leading to connection pool exhaustion.

### Why This Happened

1. **Cron started new instances every 2 minutes** regardless of whether previous instances were still running
2. **No lock mechanism** to prevent multiple instances
3. **Long-running jobs** could take more than 2 minutes to complete
4. **Connection accumulation** from overlapping instances exceeded the database's `max_user_connections` limit

## The Fix

### 1. Added Lock Mechanism to process_import_jobs.php

**File:** `scripts/process_import_jobs.php`

**Changes:**
- Added file-based lock using `flock()` with `LOCK_EX | LOCK_NB` (non-blocking exclusive lock)
- Created PID file to track running process
- Implemented stale lock detection for automatic recovery from crashed processes
- Added signal handlers for graceful shutdown (SIGTERM, SIGINT)
- Cleanup function removes lock and PID files on exit

**How It Works:**
```php
// Try to acquire exclusive lock
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is running - exit immediately
    exit(0);
}

// Check if PID in file is actually running
if (file_exists($pidFile)) {
    $runningPid = file_get_contents($pidFile);
    if (!posix_kill($runningPid, 0)) {
        // Stale lock - clean up and continue
    }
}

// Write current PID
file_put_contents($pidFile, getmypid());

// Register cleanup on shutdown
register_shutdown_function($cleanup);
```

### 2. Updated Cron Recommendation

**Old Configuration (Problematic):**
```cron
*/2 * * * *  eclectyc.energy/httpdocs/scripts/process_import_jobs.php
```

**New Configuration (Recommended):**
```cron
*/2 * * * *  cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1
```

**Key Differences:**
- ✅ Uses `--once` flag for single-run execution
- ✅ Full path to PHP binary
- ✅ Proper logging with stdout and stderr
- ✅ Lock mechanism prevents overlapping runs
- ✅ Safe to run every 1-2 minutes without issues

### 3. Created Comprehensive Documentation

**New Files:**
- `docs/CRON_SETUP_FIX.md` - Complete guide for cron setup with multiple options
- `tests/test_lock_basic.php` - Basic lock mechanism validation
- `tests/test_import_job_lock.php` - Comprehensive lock testing with edge cases

**Updated Files:**
- `README.md` - Added note about lock mechanism
- `docs/DB_CONNECTION_FIX.md` - Updated with new cron recommendations

## Testing

### 1. Basic Lock Test
```bash
$ php tests/test_lock_basic.php
✓ Successfully acquired exclusive lock
✓ PID file created
✓ Second instance correctly blocked from acquiring lock
✓ Lock and PID files removed
✓ Cleanup verified
✅ All basic lock mechanism tests passed!
```

### 2. Multiple Instance Prevention
When attempting to run a second instance while the first is running:
```
Another instance is already running (PID: 12345).
If you believe this is an error, remove the lock file: /path/to/storage/process_import_jobs.lock
```

### 3. Stale Lock Detection
If a process crashes leaving a stale lock:
```
Removing stale lock file from dead process (PID: 99999)
```
Then continues normally with a fresh lock.

## Benefits

1. ✅ **No more max_user_connections errors** - Only one instance runs at a time
2. ✅ **Automatic recovery** - Stale locks cleaned up automatically
3. ✅ **Safe cron frequency** - Can run every minute without issues
4. ✅ **Graceful shutdown** - Proper cleanup on termination signals
5. ✅ **Better logging** - Recommended config includes proper log redirection
6. ✅ **Easy troubleshooting** - Clear messages when lock is held

## Deployment Instructions

### Step 1: Update the Script
Deploy the updated `scripts/process_import_jobs.php` file to production.

### Step 2: Update Cron Configuration in Plesk

1. Go to **Tools & Settings** > **Scheduled Tasks** (or **Websites & Domains** > **Cron Jobs**)
2. Find the entry for `process_import_jobs.php`
3. Update it to:
   ```
   */2 * * * *  cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1
   ```
4. Save the changes

### Step 3: Kill Existing Instances

```bash
# Find any running instances
ps aux | grep process_import_jobs.php

# Kill them (they don't have the new lock mechanism)
pkill -f process_import_jobs.php
```

### Step 4: Verify

Wait for the cron to trigger (2 minutes), then check:

```bash
# Should see at most ONE instance
ps aux | grep process_import_jobs.php

# Check logs
tail -f /path/to/eclectyc-energy/logs/import_worker.log

# Check connections
php scripts/cleanup_db_connections.php
```

### Step 5: Monitor

Over the next hour, verify:
- Only one instance runs at a time
- No "max_user_connections" errors
- Connection count stays stable
- Import jobs are processed successfully

## Rollback Plan

If issues occur, rollback by:

1. **Revert cron to long-running mode:**
   ```bash
   @reboot cd /path && php scripts/process_import_jobs.php >> logs/import_worker.log 2>&1 &
   */5 * * * * pgrep -f process_import_jobs.php || (cd /path && php scripts/process_import_jobs.php >> logs/import_worker.log 2>&1 &)
   ```

2. **Or temporarily disable:**
   ```bash
   # Comment out the cron job
   # Process imports manually when needed
   php scripts/process_import_jobs.php --once
   ```

## Additional Resources

- **Full Cron Setup Guide:** `docs/CRON_SETUP_FIX.md`
- **Database Connection Guide:** `docs/DB_CONNECTION_FIX.md`
- **Import Troubleshooting:** `docs/TROUBLESHOOTING_IMPORTS.md`
- **Test Scripts:** `tests/test_lock_basic.php`, `tests/test_import_job_lock.php`

## Success Criteria

The fix is successful when:

- ✅ Only one instance of `process_import_jobs.php` runs at any time
- ✅ No "max_user_connections" database errors
- ✅ Database connection count remains stable (< 60% of max)
- ✅ Import jobs process successfully
- ✅ Lock files created and cleaned up properly
- ✅ Stale locks automatically recovered

## Notes

- The lock mechanism uses file locking, which is supported on all Unix-like systems
- Lock files are stored in `storage/process_import_jobs.lock` and `storage/process_import_jobs.pid`
- The script will create the storage directory if it doesn't exist
- Signal handling requires `pcntl` extension (optional, graceful degradation if not available)
- Lock is automatically released when the process exits (even on crash)

## Contact

If you continue to experience issues after this fix:

1. Check `logs/import_worker.log` for errors
2. Run `php scripts/cleanup_db_connections.php` to diagnose connection issues
3. Verify only one cron entry exists for `process_import_jobs.php`
4. Check for hung PHP processes: `ps aux | grep php`
5. Review `docs/CRON_SETUP_FIX.md` for alternative setups
