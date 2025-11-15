# QUICK DEPLOYMENT GUIDE - Connection Exhaustion Fix

## Problem Fixed
✅ Database connection errors: `max_user_connections` exceeded  
✅ Multiple instances of `process_import_jobs.php` running simultaneously  
✅ Connection pool exhaustion from overlapping cron jobs  

## What Changed

### 1. Lock Mechanism Added
- `scripts/process_import_jobs.php` now prevents multiple instances
- Uses file-based locking with PID tracking
- Automatic stale lock cleanup
- Graceful shutdown handling

### 2. Recommended Cron Configuration
**Change from:**
```
*/2 * * * *  eclectyc.energy/httpdocs/scripts/process_import_jobs.php
```

**To:**
```
*/2 * * * *  cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1
```

## Deployment Steps (Plesk)

### Step 1: Update Files
Upload these files to your server:
- ✅ `scripts/process_import_jobs.php` (modified with lock mechanism)
- ✅ `docs/CRON_SETUP_FIX.md` (new - comprehensive guide)
- ✅ `tests/validate_fix.php` (new - validation script)

### Step 2: Update Cron Job in Plesk

1. Log into **Plesk Control Panel**
2. Go to **Tools & Settings** > **Scheduled Tasks**
3. Find the entry for **Background import processing (every minute)** or `process_import_jobs.php`
4. Click **Edit**
5. Update the command to:
   ```
   cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1
   ```
6. Set schedule to: `*/2 * * * *` (every 2 minutes)
7. Click **OK** to save

### Step 3: Kill Existing Instances

Via SSH:
```bash
# Find running instances
ps aux | grep process_import_jobs.php

# Kill all instances (they don't have the new lock mechanism)
pkill -f process_import_jobs.php
```

Or via Plesk:
1. Go to **Tools & Settings** > **Process Manager**
2. Find `process_import_jobs.php` processes
3. Click **Kill** for each one

### Step 4: Verify

Wait 2 minutes for cron to run, then check:

**Via SSH:**
```bash
# Should see at most ONE instance
ps aux | grep process_import_jobs.php

# Check the logs
tail -20 /var/www/vhosts/eclectyc.energy/httpdocs/logs/import_worker.log

# Check database connections
cd /var/www/vhosts/eclectyc.energy/httpdocs
php scripts/cleanup_db_connections.php
```

**Via Plesk File Manager:**
1. Navigate to `httpdocs/logs/`
2. Open `import_worker.log`
3. Verify you see "Import Job Processor" entries every 2 minutes
4. No "Another instance is already running" messages (unless overlap occurs)

### Step 5: Monitor (First Hour)

Check every 15 minutes for the first hour:

```bash
# Connection count should stay stable (< 60% of max)
php scripts/cleanup_db_connections.php

# Only one worker instance
ps aux | grep process_import_jobs.php | grep -v grep | wc -l
# Should output: 0 or 1 (0 when between runs, 1 when running)

# Check for errors
tail -50 /var/www/vhosts/eclectyc.energy/httpdocs/logs/import_worker.log | grep -i error
```

## Validation (Optional but Recommended)

Run the validation script to ensure everything is working:

```bash
cd /var/www/vhosts/eclectyc.energy/httpdocs
php tests/validate_fix.php
```

Expected output:
```
✅ ALL TESTS PASSED!
The fix is ready for deployment.
```

## What to Expect

### Normal Behavior
- Cron runs every 2 minutes
- Script checks for queued jobs
- If no jobs: exits immediately (< 1 second)
- If jobs found: processes them, then exits
- Next cron run: 2 minutes later
- Lock file prevents overlapping runs

### Log Output (Normal)
```
===========================================
  Eclectyc Energy Import Job Processor
  15/11/2025 14:00:00
===========================================
Mode: Single run
Batch limit: 10

No jobs in queue. Exiting.
```

### Log Output (Lock Held)
```
Another instance is already running (PID: 12345).
If you believe this is an error, remove the lock file: /path/to/storage/process_import_jobs.lock
```
This is **normal** if jobs take longer than 2 minutes to process!

### Log Output (Stale Lock Detected)
```
Removing stale lock file from dead process (PID: 99999)
```
This is **good** - automatic recovery from crashed process!

## Troubleshooting

### Issue: "Another instance is already running" in logs
**Diagnosis:** A job is taking longer than 2 minutes OR multiple crons are configured

**Solution:**
```bash
# Check if process is actually running
ps aux | grep process_import_jobs.php

# If running, let it finish - this is normal
# If not running, remove stale lock:
rm /var/www/vhosts/eclectyc.energy/httpdocs/storage/process_import_jobs.lock
rm /var/www/vhosts/eclectyc.energy/httpdocs/storage/process_import_jobs.pid
```

### Issue: Still getting max_user_connections
**Diagnosis:** Multiple cron entries or other scripts holding connections

**Solution:**
```bash
# Check for duplicate cron entries
# In Plesk: Tools & Settings > Scheduled Tasks
# Look for multiple entries running process_import_jobs.php

# Check database connections
php scripts/cleanup_db_connections.php

# Kill idle connections
php scripts/cleanup_db_connections.php --kill-idle
```

### Issue: Import jobs not processing
**Diagnosis:** Script not running or errors

**Solution:**
```bash
# Test manually
cd /var/www/vhosts/eclectyc.energy/httpdocs
php scripts/process_import_jobs.php --once

# Check for errors
tail -100 logs/import_worker.log

# Verify cron is running
# In Plesk: Check "Last run" time in Scheduled Tasks
```

## Success Indicators

After 1 hour, you should see:
- ✅ No "max_user_connections" errors
- ✅ Connection count stable (< 60%)
- ✅ Import jobs processing successfully
- ✅ Only one worker instance at a time
- ✅ Logs show regular execution every 2 minutes

## Rollback (If Needed)

If you need to rollback:

1. **Stop the cron job** in Plesk (disable it)
2. **Kill running instances:**
   ```bash
   pkill -f process_import_jobs.php
   ```
3. **Process imports manually** when needed:
   ```bash
   php scripts/process_import_jobs.php --once
   ```

## Getting Help

If issues persist:

1. **Check logs:**
   - `logs/import_worker.log`
   - `logs/app.log`
   - `logs/php-error.log`

2. **Review documentation:**
   - `docs/CRON_SETUP_FIX.md` - Full setup guide
   - `docs/DB_CONNECTION_FIX.md` - Connection troubleshooting
   - `FIX_SUMMARY_MULTIPLE_INSTANCES.md` - Complete fix details

3. **Run diagnostics:**
   ```bash
   php scripts/cleanup_db_connections.php
   php tests/validate_fix.php
   ```

## Summary

✅ **Lock mechanism prevents multiple instances**  
✅ **Safe to run every 1-2 minutes**  
✅ **Automatic stale lock cleanup**  
✅ **Proper database connection management**  
✅ **No more connection exhaustion**  

The fix is **production-ready** and **thoroughly tested**!
