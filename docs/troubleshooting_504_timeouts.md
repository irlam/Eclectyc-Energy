# Troubleshooting 504 Gateway Timeouts During Imports

## Overview

This guide addresses 504 Gateway Timeout errors that can occur during large CSV imports. These timeouts happen when the web server (Apache/Nginx) or PHP-FPM process times out before the import completes.

## Understanding the Issue

A 504 Gateway Timeout occurs when:
1. The import takes longer than the web server timeout (typically 60-120 seconds)
2. PHP execution time limit is exceeded (default: 30-300 seconds)
3. The server is overloaded and cannot respond in time

## Solution 1: Enable Import Throttling

Import throttling is **disabled by default** to maximize import speed. However, for large imports (>10,000 rows), enabling throttling prevents server overload and timeouts.

### Enable Throttling via Database

```sql
-- Connect to your database
mysql -u your_username -p your_database

-- Enable throttling
UPDATE system_settings 
SET setting_value = 'true' 
WHERE setting_key = 'import_throttle_enabled';

-- Verify it's enabled
SELECT * FROM system_settings WHERE setting_key LIKE 'import_throttle%';
```

### Adjust Throttling Settings

For very large files, you may need to adjust these settings:

```sql
-- Process 50 rows at a time (instead of 100)
UPDATE system_settings 
SET setting_value = '50' 
WHERE setting_key = 'import_throttle_batch_size';

-- Increase delay between batches to 200ms (instead of 100ms)
UPDATE system_settings 
SET setting_value = '200' 
WHERE setting_key = 'import_throttle_delay_ms';

-- Increase max execution time to 600 seconds (10 minutes)
UPDATE system_settings 
SET setting_value = '600' 
WHERE setting_key = 'import_max_execution_time';
```

### How Throttling Works

When enabled, the import system:
1. Processes N rows (batch_size, default: 100)
2. Pauses for X milliseconds (delay_ms, default: 100)
3. Continues to next batch
4. Repeats until complete

**Example**: With default settings (100 rows, 100ms delay)
- Processes 100 rows
- Pauses 0.1 seconds
- Processes next 100 rows
- Pauses 0.1 seconds
- ...continues

This prevents the server from being overwhelmed by continuous processing.

### Performance Impact

| Configuration | Speed | Server Load | Timeout Risk |
|--------------|-------|-------------|--------------|
| Throttling OFF | ~500-1000 rows/sec | High | High |
| Default throttling | ~50-100 rows/sec | Medium | Low |
| Conservative throttling | ~25-50 rows/sec | Low | Very Low |

## Solution 2: Use Async Imports

For very large files, use the async import feature instead of synchronous imports.

### How to Use Async Import

1. Navigate to `/admin/imports`
2. Upload your CSV file
3. **Check the "Async" checkbox** before submitting
4. Submit the form

The import will be queued and processed in the background. You can:
- Close the browser window (import continues)
- Track progress at `/admin/imports/jobs`
- Monitor status at `/admin/imports/status/{batchId}`

### Benefits of Async Import

- **No timeout risk**: Process runs independently of web server
- **Progress tracking**: Real-time progress updates
- **Resumable**: Can retry failed jobs
- **Background processing**: Doesn't block other operations

## Solution 3: Increase Server Timeouts

If you have server access, you can increase timeout limits.

### PHP Configuration

Edit `php.ini` or add to `.htaccess`:

```ini
# Maximum execution time (seconds)
max_execution_time = 600

# Maximum input time (seconds)  
max_input_time = 600

# Memory limit
memory_limit = 512M
```

### Apache Configuration

In your Apache virtual host or `.htaccess`:

```apache
# Timeout for CGI/FastCGI scripts
FcgidIOTimeout 600
FcgidBusyTimeout 600

# ProxyTimeout (if using proxy)
ProxyTimeout 600
```

### Nginx Configuration

In your Nginx site configuration:

```nginx
location ~ \.php$ {
    # FastCGI timeouts
    fastcgi_read_timeout 600;
    fastcgi_send_timeout 600;
}

# Proxy timeouts (if applicable)
proxy_read_timeout 600;
proxy_send_timeout 600;
```

## Solution 4: Split Large Files

For extremely large files (>100,000 rows), consider splitting into smaller files:

```bash
# Split CSV into files of 10,000 rows each
split -l 10000 large_file.csv split_file_

# This creates: split_file_aa, split_file_ab, split_file_ac, etc.

# Add CSV header to each file (Unix/Linux/Mac)
for file in split_file_*; do
    (head -1 large_file.csv; cat "$file") > temp && mv temp "$file"
done
```

Then import each file separately.

## Monitoring Import Progress

### View Import Job Status

1. Navigate to `/admin/imports/jobs`
2. Click on the job to see detailed status
3. Progress bar shows:
   - Percentage complete
   - Rows processed / total rows
   - Processing speed (rows/sec)
   - Estimated time remaining (ETA)

### Check System Health

1. Navigate to `/tools/system-health`
2. Check for:
   - Recent import activity
   - Database performance
   - Server memory/disk usage

### Check Logs

1. Navigate to `/tools/logs`
2. Look for import-related errors
3. Filter by log level or search term

## Troubleshooting Checklist

If you're experiencing 504 timeouts:

- [ ] **Enable throttling** via database settings
- [ ] **Use async import** for large files
- [ ] **Check PHP timeout** settings in `php.ini`
- [ ] **Check web server timeout** (Apache/Nginx)
- [ ] **Monitor system resources** (CPU, memory, disk)
- [ ] **Check database performance** (slow queries, locks)
- [ ] **Review import logs** for errors
- [ ] **Split large files** if >50,000 rows
- [ ] **Optimize CSV format** (remove unnecessary columns)
- [ ] **Check database indexes** on meter_readings table

## Where is Throttling Shown in the GUI?

Currently, throttling settings are **not visible in the GUI**. They must be configured via database queries (shown above).

### Current Import Status Display

The import status page (`/admin/imports/status/{batchId}`) shows:

1. **Progress Bar**: Visual progress with percentage
2. **Processing Speed**: Rows per second being processed
3. **ETA**: Estimated time to completion
4. **Success Rate**: Percentage of successful imports

**Note**: The processing speed will be lower when throttling is enabled, which is expected behavior.

### Future Enhancement

A System Settings page is planned for future releases to allow GUI-based configuration of:
- Import throttling settings
- Execution time limits
- Memory limits
- Other system-wide settings

## Common Scenarios

### Scenario 1: Small File (<5,000 rows)

**Recommendation**: 
- Throttling: Disabled
- Import method: Synchronous (normal upload)
- Expected time: <1 minute

### Scenario 2: Medium File (5,000-20,000 rows)

**Recommendation**:
- Throttling: Enabled with default settings
- Import method: Either synchronous or async
- Expected time: 2-5 minutes

### Scenario 3: Large File (20,000-100,000 rows)

**Recommendation**:
- Throttling: Enabled with conservative settings
- Import method: **Async only**
- Expected time: 5-30 minutes

### Scenario 4: Very Large File (>100,000 rows)

**Recommendation**:
- Split file into smaller chunks
- Throttling: Enabled
- Import method: **Async only**
- Expected time: 30+ minutes per chunk

## Getting Help

If you continue to experience 504 timeouts after following this guide:

1. Check system health: `/tools/system-health`
2. Review error logs: `/tools/logs`
3. Contact your system administrator
4. Review database performance (check for slow queries)
5. Consider upgrading server resources

## Related Documentation

- [Import Progress, SFTP & Throttling Features](import_progress_sftp_throttling.md)
- [Import Troubleshooting](import_troubleshooting.md)
- [Operationalizing Async Systems](operationalizing_async_systems.md)
- [System Health Troubleshooting](troubleshooting_system_degraded.md)
