# Application Logging Guide

## Overview

The Eclectyc Energy platform uses multiple logging mechanisms to capture different types of events and errors. This guide explains where logs are stored and how to access them.

## Log Types and Locations

### 1. PHP Application Logs (`logs/php-error.log`)

**What's logged**: PHP errors, warnings, notices, and `error_log()` calls from application code.

**Location**: `/home/k87747/eclectyc.energy/logs/php-error.log`

**Configured in**: `public/index.php`
```php
// Production mode
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/php-error.log');
```

**View in Browser**: Navigate to `/tools/logs`

**Examples of what's logged**:
- Database connection errors
- Failed tariff calculations
- Import validation errors
- Custom application errors via `error_log()`

**Sample entries**:
```
[09-Nov-2025 12:34:56 Europe/London] Failed to get consumption data: SQLSTATE[HY093]: Invalid parameter number
[09-Nov-2025 12:35:01 Europe/London] Database connection failed: Access denied for user
[09-Nov-2025 12:36:15 Europe/London] Import batch abc-123: Processed 1000 rows
```

### 2. Web Server Logs (Apache/Nginx)

**What's logged**: HTTP requests, server errors, PHP-FPM errors, and `mod_fcgid` stderr output.

**Location (Apache)**: 
- Access: `/var/www/vhosts/system/eclectyc.energy/logs/access_ssl_log`
- Error: `/var/www/vhosts/system/eclectyc.energy/logs/error_log`

**Location (Nginx)**:
- Access: `/var/log/nginx/access.log`
- Error: `/var/log/nginx/error.log`

**Access via Plesk**:
1. Log into Plesk control panel
2. Navigate to Websites & Domains → eclectyc.energy
3. Click "Logs" in the left sidebar
4. View error_log or access_ssl_log

**Examples of what's logged**:
- 404 Not Found errors
- 500 Internal Server Errors
- 504 Gateway Timeouts
- PHP Fatal Errors (stderr output)
- mod_fcgid process errors

**Sample entries**:
```
[Sat Nov 09 12:34:56.123456 2025] [fcgid:warn] [pid 12345] mod_fcgid: stderr: Failed to get consumption data: SQLSTATE[HY093]: Invalid parameter number, referer: https://eclectyc.energy/admin/tariff-switching
[Sat Nov 09 12:35:00.123456 2025] [fcgid:error] [pid 12346] [client 192.168.1.100:12345] Premature end of script headers: index.php
```

### 3. Database Query Logs (MySQL)

**What's logged**: Slow queries, errors, and general query log (if enabled).

**Location**: 
- Error: `/var/lib/mysql/error.log` or as configured in my.cnf
- Slow query: `/var/lib/mysql/slow-query.log`

**Access via Plesk**:
1. Plesk → Databases → your_database
2. Click "Logs" or "phpMyAdmin"

**Enable slow query log** (optional):
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;  -- Log queries taking >2 seconds
```

### 4. Import Job Logs (Database)

**What's logged**: Import job progress, errors, and metadata.

**Location**: Stored in database tables:
- `import_jobs` - Job status, progress, errors
- `audit_logs` - Detailed import history

**View in Browser**: 
- Navigate to `/admin/imports/jobs`
- Click on a specific job to see detailed logs

**Columns of interest**:
- `error_message` - Error that caused job to fail
- `summary` - JSON with import details
- `processed_rows`, `imported_rows`, `failed_rows` - Statistics

### 5. Audit Logs (Database)

**What's logged**: User actions, data changes, and system events.

**Location**: `audit_logs` table in database

**Columns**:
- `action` - Type of action (e.g., 'import_csv', 'user_login', 'meter_update')
- `user_id` - Who performed the action
- `old_values` - State before change (JSON)
- `new_values` - State after change (JSON)
- `created_at` - Timestamp

**Query examples**:
```sql
-- Recent import actions
SELECT * FROM audit_logs 
WHERE action = 'import_csv' 
ORDER BY created_at DESC 
LIMIT 10;

-- Failed imports
SELECT * FROM audit_logs 
WHERE action = 'import_csv' 
AND JSON_EXTRACT(new_values, '$.status') = 'failed'
ORDER BY created_at DESC;
```

## Why Don't I See Logs in /tools/logs?

If the `/tools/logs` page shows "Log file does not exist", it means:

1. **No errors have occurred yet** - The log file is only created when PHP logs an error
2. **Logs are in a different location** - Check web server logs (see section 2 above)
3. **Permissions issue** - The `logs/` directory may not be writable

### Creating a Test Log Entry

To verify logging is working, you can create a test entry:

```php
// Add this to any PHP file (e.g., public/index.php) temporarily
error_log('Test log entry from Eclectyc Energy at ' . date('Y-m-d H:i:s'));
```

Then visit any page on the site, and check `/tools/logs` - you should see the entry.

### Making Logs Directory Writable

If you get permission errors:

```bash
# SSH into server
cd /home/k87747/eclectyc.energy
chmod 755 logs/
```

Or via Plesk File Manager:
1. Right-click on `logs` folder
2. Change Permissions
3. Set to 755 (rwxr-xr-x)

## Understanding the Error from Problem Statement

The error mentioned:
```
mod_fcgid: stderr: Failed to get consumption data: SQLSTATE[HY093]: Invalid parameter number, referer: https://eclectyc.energy/admin/tariff-switching
```

**Where this appears**: Apache error log (web server log), NOT application log

**Why**: PHP's `error_log()` output goes to stderr, which Apache captures in its error log when running under mod_fcgid

**Fixed**: This specific error has been fixed in the `TariffSwitchingAnalyzer.php` file by correcting the SQL parameter binding.

**To view**: Check Apache error log via Plesk (see Section 2 above)

## Log Rotation and Maintenance

### Automatic Rotation

Most servers automatically rotate logs:
- Apache/Nginx logs: Rotated daily/weekly by `logrotate`
- PHP error log: May grow indefinitely unless manually rotated

### Manual Cleanup

You can clear the PHP error log via `/tools/logs`:
1. Navigate to `/tools/logs`
2. Click "Clear Logs" button
3. A backup is created before clearing

Or manually:
```bash
# Backup current log
cp logs/php-error.log logs/php-error.log.backup-$(date +%Y%m%d)

# Clear the log
> logs/php-error.log
```

### Monitoring Log Size

The `/tools/logs` page shows current log file size. If it exceeds 10MB, consider:
1. Reviewing recent errors
2. Fixing recurring issues
3. Clearing the log after backup

## Viewing Logs from Command Line

If you have SSH access:

```bash
# View last 100 lines of PHP error log
tail -100 /home/k87747/eclectyc.energy/logs/php-error.log

# Follow log in real-time
tail -f /home/k87747/eclectyc.energy/logs/php-error.log

# Search for specific error
grep "Failed to get consumption" /home/k87747/eclectyc.energy/logs/php-error.log

# View Apache error log
tail -100 /var/www/vhosts/system/eclectyc.energy/logs/error_log

# Search Apache logs for 504 errors
grep "504" /var/www/vhosts/system/eclectyc.energy/logs/error_log
```

## Common Log Messages and What They Mean

### "Failed to get consumption data: SQLSTATE[HY093]"
- **Severity**: Error
- **Cause**: SQL parameter binding issue (FIXED)
- **Action**: Update to latest code

### "Database connection failed"
- **Severity**: Critical
- **Cause**: Database credentials incorrect or MySQL down
- **Action**: Check .env file, verify MySQL is running

### "504 Gateway Timeout"
- **Severity**: Warning
- **Cause**: Import or process taking too long
- **Action**: Enable throttling, use async imports (see [troubleshooting_504_timeouts.md](troubleshooting_504_timeouts.md))

### "Premature end of script headers"
- **Severity**: Error
- **Cause**: PHP script crashed or timed out
- **Action**: Check PHP error log for details

### "Memory limit exceeded"
- **Severity**: Error
- **Cause**: Script using too much memory (large imports)
- **Action**: Increase `memory_limit` in php.ini or use async imports

## Debug Mode

For development/troubleshooting, enable debug mode:

**In .env file**:
```env
APP_ENV=development
APP_DEBUG=true
```

**Effect**:
- Detailed error messages shown on screen
- Full stack traces
- SQL queries logged (if query logging enabled)
- More verbose logging

**⚠️ Warning**: Never enable debug mode in production - it exposes sensitive information!

## Log Levels

PHP supports different error levels:

```php
// Log different severity levels
error_log('INFO: Import started');           // Informational
error_log('WARNING: High memory usage');     // Warning
error_log('ERROR: Failed to save data');     // Error
error_log('CRITICAL: Database unreachable'); // Critical
```

## Getting Help

If you can't find logs or errors:

1. **Check all log locations** (sections 1-5 above)
2. **Verify file permissions** on logs directory
3. **Test logging** with manual error_log() call
4. **Check Plesk logs** for web server errors
5. **Review system health** at `/tools/system-health`
6. **Contact support** with specific error messages

## Related Documentation

- [Troubleshooting System Degraded](troubleshooting_system_degraded.md)
- [Troubleshooting 504 Timeouts](troubleshooting_504_timeouts.md)
- [Import Troubleshooting](import_troubleshooting.md)

---

**Last Updated**: November 2025  
**Version**: 1.0
