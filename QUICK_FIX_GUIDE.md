# Quick Fix Guide: Import Jobs Stuck in QUEUED

If you're seeing import jobs stuck in QUEUED status, follow these steps:

## 1. Verify the Issue

Run the diagnostic tool:
```bash
cd /path/to/eclectyc-energy
php scripts/check_import_setup.php
```

This will tell you exactly what's wrong.

## 2. Most Likely Issue: Worker Not Running

If the diagnostic says "Import worker is NOT running", you need to set up the background worker.

### Fix via Plesk (Recommended)

1. Log into Plesk
2. Go to your domain → "Scheduled Tasks"
3. Click "Add Task"
4. Configure:
   - **Task type**: Run a PHP script
   - **Command**: `/usr/bin/php`
   - **Arguments**: `/path/to/eclectyc-energy/scripts/process_import_jobs.php --once`
   - **Schedule**: `* * * * *` (every minute)
   - **Description**: "Import Job Processor"
5. Click "OK"

### Fix via SSH (Alternative)

```bash
# Edit crontab
crontab -e

# Add this line (replace /path/to/eclectyc-energy with your actual path)
* * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1

# Save and exit
```

## 3. Process Stuck Jobs

Once the worker is set up, process any stuck jobs:

```bash
php scripts/process_import_jobs.php --once
```

Watch the output to see jobs being processed.

## 4. Verify It's Working

### Check logs:
```bash
tail -f logs/import_worker_cron.log
```

### Check job status in web interface:
1. Go to: https://your-domain/admin/imports/jobs
2. Jobs should change from QUEUED → PROCESSING → COMPLETED

## 5. Test with New Import

1. Upload a small test CSV at: https://your-domain/admin/imports
2. Watch it process in real-time
3. Check data appears in reports

## Still Having Issues?

See the full troubleshooting guide:
```bash
cat docs/TROUBLESHOOTING_IMPORTS.md
```

Or run diagnostics again:
```bash
php scripts/check_import_setup.php
```

## Common Mistakes

❌ Worker not scheduled to run every minute
❌ Wrong path in cron job
❌ PHP binary path incorrect (use `which php` to find it)
❌ Permissions issues on logs directory
❌ Database credentials incorrect in .env

✅ Double-check all paths are absolute
✅ Ensure .env file has correct database credentials
✅ Check logs for error messages
