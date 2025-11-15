# Cron Job Setup Guide for Eclectyc Energy

## Critical Fix: Preventing Multiple process_import_jobs.php Instances

### The Problem

The `process_import_jobs.php` script was causing database connection exhaustion errors:
```
Database connection failed: SQLSTATE[HY000] [1203] User k87747_eclectyc already has more than 'max_user_connections' active connections
```

**Root Cause:** The cron job was configured to run the script every 2 minutes (`*/2 * * * *`), causing multiple instances to run simultaneously. Each instance holds database connections, leading to connection pool exhaustion.

### The Solution

The script now includes:
1. **File-based lock mechanism** - Prevents multiple instances from running
2. **PID file tracking** - Tracks the running process
3. **Stale lock detection** - Automatically cleans up locks from dead processes
4. **Graceful shutdown** - Properly cleans up connections and lock files on exit

## Recommended Cron Setup

### Option 1: Use `--once` Flag (Recommended for Most Cases)

Run the script every 1-2 minutes with the `--once` flag. The lock mechanism prevents overlapping runs:

```bash
*/2 * * * * cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1
```

**Advantages:**
- Simple and reliable
- Built-in lock prevents overlapping runs
- Automatically retries jobs that are ready
- Low memory footprint
- Cron handles restart if script crashes

**How it works:**
- Checks for queued jobs
- Processes up to 10 jobs (configurable with `--limit`)
- Exits when done
- Lock file prevents multiple instances
- Cron starts it again 2 minutes later

### Option 2: Long-Running Daemon with Monitoring

Run the script once at boot/reboot as a daemon, with a monitoring job to ensure it's running:

**Start script (run once at reboot):**
```bash
@reboot cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php >> httpdocs/logs/import_worker.log 2>&1
```

**Monitor script (runs every 5 minutes to check if it's alive):**
```bash
*/5 * * * * pgrep -f "process_import_jobs.php" || (cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php >> httpdocs/logs/import_worker.log 2>&1 &)
```

**Advantages:**
- Processes jobs immediately (no 2-minute delay)
- Single persistent connection (efficient for high-volume imports)
- Automatically restarts if it crashes

**Disadvantages:**
- Slightly more complex setup
- Uses more memory over time
- Requires monitoring to ensure it stays running

### Option 3: Systemd Service (Best for Production)

For production deployments on servers with systemd:

**Create service file:** `/etc/systemd/system/eclectyc-import-worker.service`
```ini
[Unit]
Description=Eclectyc Energy Import Worker
After=mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/vhosts/eclectyc.energy/httpdocs
ExecStart=/usr/local/php84/bin/php /var/www/vhosts/eclectyc.energy/httpdocs/scripts/process_import_jobs.php
Restart=always
RestartSec=10
StandardOutput=append:/var/www/vhosts/eclectyc.energy/httpdocs/logs/import_worker.log
StandardError=append:/var/www/vhosts/eclectyc.energy/httpdocs/logs/import_worker_error.log

[Install]
WantedBy=multi-user.target
```

**Enable and start:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable eclectyc-import-worker
sudo systemctl start eclectyc-import-worker
```

**Advantages:**
- Automatic restart on failure
- Proper service management
- Logs managed by systemd
- Starts on boot automatically

## Current Cron Jobs Review

Based on your Plesk cron configuration, here are the recommended changes:

### ❌ Current Setup (INCORRECT)
```
*/2 * * * *  eclectyc.energy/httpdocs/scripts/process_import_jobs.php
```
**Problem:** Starts new instance every 2 minutes, causing multiple instances to run simultaneously.

### ✅ Recommended Setup
```
*/2 * * * *  cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1
```
**Benefits:** Uses lock mechanism, prevents multiple instances, proper logging.

### Other Cron Jobs (Keep These)

All other cron jobs are fine as they run at different times and don't overlap:

```bash
# Carbon intensity (every 30 minutes) - OK
*/30 * * * *  eclectyc.energy/httpdocs/scripts/setup_carbon_cron.sh

# Daily aggregation (1:00 AM) - OK
Daily (00:00)  eclectyc.energy/httpdocs/scripts/aggregate_daily.php

# Cleanup (runs once daily) - OK
Daily (00:00)  eclectyc.energy/httpdocs/scripts/cleanup_db_connections.php

# Weekly aggregation (Monday 2:00 AM) - OK
Weekly (Mon 01:00)  eclectyc.energy/httpdocs/scripts/aggregate_weekly.php

# Monthly aggregation (1st of month, 3:00 AM) - OK
Monthly (01 02:00)  eclectyc.energy/httpdocs/scripts/aggregate_monthly.php
```

## Monitoring and Troubleshooting

### Check if the Worker is Running

```bash
# Check for running instances
ps aux | grep process_import_jobs.php

# Should see at most ONE instance (plus the grep command)
```

### Check Lock Files

```bash
# View lock file location
ls -la /path/to/eclectyc-energy/storage/process_import_jobs.*

# If lock file exists and no process is running, it's a stale lock
# The script will automatically clean it up on next run
```

### Check Database Connections

```bash
# Monitor current connections
cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/cleanup_db_connections.php

# Kill idle connections if needed
cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/cleanup_db_connections.php --kill-idle
```

### View Worker Logs

```bash
# View recent activity
tail -f /path/to/eclectyc-energy/logs/import_worker.log

# Check for errors
grep -i error /path/to/eclectyc-energy/logs/import_worker.log
```

### Manual Testing

```bash
# Test single run
cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once

# Test with specific job limit
cd ${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once --limit=5
```

## Emergency Procedures

### If You're Locked Out Due to max_user_connections

**Option 1: Kill Hung Processes**
```bash
# Find hung PHP processes
ps aux | grep php

# Kill specific process
kill -9 <pid>

# Or kill all process_import_jobs instances
pkill -f process_import_jobs.php
```

**Option 2: Restart PHP-FPM**
```bash
# Restart PHP-FPM (adjust version as needed)
sudo systemctl restart php8.4-fpm
# or
sudo systemctl restart php-fpm
```

**Option 3: Kill Database Connections (as MySQL admin)**
```bash
mysql -u root -p
```
```sql
-- View all connections
SHOW FULL PROCESSLIST;

-- Kill specific connection
KILL <connection_id>;

-- Kill all connections for the app user (be careful!)
SELECT CONCAT('KILL ', id, ';') FROM information_schema.processlist 
WHERE User = 'k87747_eclectyc' AND Command = 'Sleep';
-- Copy and paste the output to execute
```

**Option 4: Restart MySQL (last resort)**
```bash
sudo systemctl restart mysql
```

## Best Practices

1. **Monitor connection usage regularly**
   - Schedule `cleanup_db_connections.php` to run every 5-10 minutes
   - Alert when usage exceeds 60%

2. **Use `--once` flag for cron jobs**
   - Simpler and more reliable
   - Built-in lock prevents issues
   - Easier to debug

3. **Ensure only one worker instance**
   - Check `ps aux | grep process_import_jobs.php` regularly
   - Kill duplicate instances immediately

4. **Review logs periodically**
   - Check for stuck jobs
   - Monitor processing times
   - Look for errors

5. **Test after deployment**
   - Always test cron setup after changes
   - Verify lock mechanism works
   - Monitor for first 24 hours

## Testing the Fix

### 1. Basic Lock Test
```bash
php tests/test_lock_basic.php
```

### 2. Full Lock Mechanism Test
```bash
php tests/test_import_job_lock.php
```

### 3. Connection Cleanup Test
```bash
php tests/test_db_connection_cleanup.php
```

All tests should pass before deploying to production.

## Summary

The key fix is ensuring only ONE instance of `process_import_jobs.php` runs at a time:

- ✅ **Lock mechanism added** - Prevents multiple instances
- ✅ **Use `--once` flag in cron** - Safe for frequent execution
- ✅ **Proper cleanup** - Connections and locks cleaned up on exit
- ✅ **Stale lock detection** - Automatically recovers from crashes

This completely resolves the "max_user_connections" errors caused by multiple simultaneous instances.
