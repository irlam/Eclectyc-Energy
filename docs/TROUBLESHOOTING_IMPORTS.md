# Import System Troubleshooting Guide

This guide helps diagnose and fix common issues with the Eclectyc Energy import system.

## Quick Diagnosis

Run the diagnostic script to check your import system configuration:

```bash
cd /path/to/eclectyc-energy
php scripts/check_import_setup.php
```

This will identify:
- Database connectivity issues
- Missing tables or migrations
- Directory permission problems
- Whether the import worker is running
- Queued jobs that are stuck

## Common Issues

### Issue 1: Jobs Stuck in QUEUED Status

**Symptom:** Import jobs show "QUEUED" status indefinitely and never process.

**Cause:** The background import worker is not running.

**Solution:**

The import system requires a background worker to process queued jobs. Choose one option:

#### Option A: Start Worker Manually (for testing)

```bash
cd /path/to/eclectyc-energy
php scripts/process_import_jobs.php
```

Leave this terminal window open. The worker will continuously process jobs.

#### Option B: Set Up Cron Job (recommended for production)

1. Open your crontab:
```bash
crontab -e
```

2. Add this line (replace `/path/to/eclectyc-energy` with your actual path):
```cron
* * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1
```

3. Save and exit. The worker will now run every minute.

4. Verify it's working:
```bash
tail -f logs/import_worker_cron.log
```

#### Option C: Set Up Systemd Service (for VPS/dedicated servers)

1. Copy the service file:
```bash
sudo cp deployment/systemd-import-worker.service /etc/systemd/system/eclectyc-import-worker.service
```

2. Edit the file to update paths:
```bash
sudo nano /etc/systemd/system/eclectyc-import-worker.service
```

3. Enable and start the service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable eclectyc-import-worker
sudo systemctl start eclectyc-import-worker
```

4. Check status:
```bash
sudo systemctl status eclectyc-import-worker
```

### Issue 2: Files Not Uploading

**Symptom:** Upload fails or returns an error.

**Cause:** Directory permissions or missing directory.

**Solution:**

1. Create the storage directory if it doesn't exist:
```bash
mkdir -p storage/imports
chmod 755 storage/imports
```

2. Ensure the web server user can write to it:
```bash
# For Apache (typical)
sudo chown www-data:www-data storage/imports

# For nginx
sudo chown nginx:nginx storage/imports

# For Plesk
sudo chown your-username:psacln storage/imports
```

### Issue 3: "File not found" Errors During Processing

**Symptom:** Worker log shows "File not found: /path/to/file.csv"

**Cause:** Files were deleted or moved before processing.

**Solution:**

1. Check if files exist in `storage/imports/`
2. Ensure files aren't being deleted prematurely
3. Check the `file_path` column in the `import_jobs` table for accuracy

### Issue 4: Import Completed But No Data in Reports

**Symptom:** Import shows "completed" but data doesn't appear in reports.

**Cause:** Reports use aggregated data which needs to be generated.

**Solution:**

Run the aggregation script:
```bash
php scripts/aggregate_orchestrated.php --all --verbose
```

Or wait for the nightly cron job to run aggregations automatically.

### Issue 5: Database Connection Errors

**Symptom:** Worker or web interface shows database errors.

**Cause:** Incorrect `.env` configuration or database server down.

**Solution:**

1. Check database credentials in `.env`:
```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=energy_platform
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

2. Test database connection:
```bash
mysql -h 127.0.0.1 -u your_db_user -p energy_platform
```

3. Ensure database server is running:
```bash
sudo systemctl status mysql
# or
sudo systemctl status mariadb
```

### Issue 6: Migrations Not Applied

**Symptom:** Errors about missing columns or tables.

**Cause:** Database migrations haven't been run.

**Solution:**

Run the migration script:
```bash
cd /path/to/eclectyc-energy
php scripts/migrate.php
```

This will apply all pending migrations, including creating the `import_jobs` table.

## Monitoring and Maintenance

### Check Worker Health

Monitor the import system health:
```bash
php scripts/monitor_import_system.php --verbose
```

Enable automatic monitoring and alerts:
```bash
# Add to crontab to run every 15 minutes
*/15 * * * * cd /path/to/eclectyc-energy && php scripts/monitor_import_system.php --handle-stuck --send-alerts >> logs/import_monitor.log 2>&1
```

### Clean Up Old Jobs

Remove completed/failed jobs older than 30 days:
```bash
php scripts/cleanup_import_jobs.php --days 30 --verbose
```

Add to weekly cron job:
```bash
# Run Sundays at 2 AM
0 2 * * 0 cd /path/to/eclectyc-energy && php scripts/cleanup_import_jobs.php --days 30 --verbose >> logs/import_cleanup.log 2>&1
```

### View Import Logs

Check what the worker is doing:

```bash
# Real-time monitoring
tail -f logs/import_worker_cron.log

# Search for specific batch
grep "d9769f3b" logs/import_worker_cron.log

# Count completed imports today
grep "Status: COMPLETED" logs/import_worker_cron.log | grep "$(date +%Y-%m-%d)" | wc -l
```

## Getting Help

If you're still experiencing issues:

1. Run the diagnostic script and save the output:
```bash
php scripts/check_import_setup.php > diagnostic_output.txt
```

2. Check recent error logs:
```bash
tail -100 logs/import_worker_cron.log
tail -100 logs/import_worker_error.log
```

3. Check database for error details:
```sql
SELECT batch_id, filename, status, error_message, queued_at, completed_at
FROM import_jobs
WHERE status = 'failed'
ORDER BY queued_at DESC
LIMIT 10;
```

4. Include this information when reporting issues.

## Prevention Tips

1. **Always test with dry-run first** - Verify your CSV format before importing
2. **Monitor worker logs regularly** - Set up log rotation to prevent disk fill-up
3. **Enable alerting** - Configure email/Slack alerts for failed jobs
4. **Regular cleanup** - Schedule automatic cleanup of old jobs
5. **Database backups** - Regular backups before large imports
6. **Resource monitoring** - Ensure sufficient disk space and memory

## Reference

- Main documentation: [docs/operationalizing_async_systems.md](operationalizing_async_systems.md)
- Quick start guide: [docs/quick_start_import.md](quick_start_import.md)
- Complete feature guide: [docs/COMPLETE_GUIDE.md](COMPLETE_GUIDE.md)
