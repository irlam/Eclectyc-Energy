# Operationalizing Async Ingestion and Aggregation

This document describes how to deploy and operationalize the async ingestion and aggregation systems in production.

## Overview

The Eclectyc Energy platform includes robust async ingestion and aggregation capabilities with:

- **Retry Logic**: Automatic retry of failed imports with exponential backoff
- **Monitoring**: Health checks and performance metrics
- **Alerting**: Email and Slack notifications for failures and issues
- **Cleanup**: Automated retention policies for old jobs
- **Worker Management**: Multiple deployment options (cron, supervisor, systemd)

## Database Setup

First, run the migration to add retry and monitoring fields:

```bash
cd /path/to/eclectyc-energy
php scripts/migrate.php
```

This applies migration `005_enhance_import_jobs.sql` which adds:
- Retry mechanism fields (retry_count, max_retries, retry_at)
- Attribution fields (notes, priority, tags, metadata)
- Alerting fields (alert_sent, alert_sent_at)

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Admin email for alerts
ADMIN_EMAIL=admin@example.com

# Mail configuration (for email alerts)
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=your-smtp-password
MAIL_FROM=noreply@eclectyc.energy

# Optional: Slack webhook for alerts
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

## Worker Deployment Options

Choose one of these deployment methods based on your infrastructure:

### Option 1: Cron Job (Recommended for Plesk)

Simple and reliable. Add to your crontab:

```bash
crontab -e
```

Add this line:

```cron
* * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1
```

**Pros:**
- Simple to set up
- Works on all hosting environments
- Automatic retries if worker crashes

**Cons:**
- 1-minute delay between job checks
- New process spawned each minute

### Option 2: Supervisor (Recommended for VPS/Dedicated Servers)

Long-running process with automatic restart:

1. Copy the configuration:
```bash
sudo cp deployment/supervisor-import-worker.conf /etc/supervisor/conf.d/eclectyc-import-worker.conf
```

2. Update the paths in the config file:
```bash
sudo nano /etc/supervisor/conf.d/eclectyc-import-worker.conf
# Change /path/to/eclectyc-energy to your actual path
```

3. Start the worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start eclectyc-import-worker
```

4. Check status:
```bash
sudo supervisorctl status eclectyc-import-worker
```

**Pros:**
- Immediate job processing
- Automatic restart on failure
- Easy to manage

**Cons:**
- Requires supervisor installation
- More complex setup

### Option 3: Systemd (Recommended for Modern Linux Servers)

Native systemd service:

1. Copy the service file:
```bash
sudo cp deployment/systemd-import-worker.service /etc/systemd/system/
```

2. Update the paths in the service file:
```bash
sudo nano /etc/systemd/system/eclectyc-import-worker.service
# Change /path/to/eclectyc-energy to your actual path
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

5. View logs:
```bash
sudo journalctl -u eclectyc-import-worker -f
```

**Pros:**
- Native Linux service
- Excellent logging
- Automatic startup on boot

**Cons:**
- Only available on systemd-based systems
- Requires root access

## Monitoring Setup

Set up automated monitoring to catch issues early:

### Health Checks (Every 15 Minutes)

Add to crontab:

```cron
*/15 * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/monitor_import_system.php --handle-stuck --send-alerts >> logs/import_monitor.log 2>&1
```

This will:
- Check for stuck jobs and mark them as failed
- Detect high failure rates
- Monitor queue depth
- Send alerts when issues are found

### Manual Monitoring

Check system health anytime:

```bash
# Basic health check
php scripts/monitor_import_system.php

# Detailed health check with stats
php scripts/monitor_import_system.php --verbose

# Handle stuck jobs and send alerts
php scripts/monitor_import_system.php --handle-stuck --send-alerts
```

## Cleanup and Retention

### Automated Cleanup (Weekly)

Add to crontab:

```cron
0 2 * * 0 cd /path/to/eclectyc-energy && /usr/bin/php scripts/cleanup_import_jobs.php --days 30 --verbose >> logs/import_cleanup.log 2>&1
```

This runs every Sunday at 2 AM and removes:
- Completed jobs older than 30 days
- Failed jobs older than 30 days
- Cancelled jobs older than 30 days
- Orphaned CSV files

### Manual Cleanup

```bash
# Clean up jobs older than 30 days
php scripts/cleanup_import_jobs.php --days 30 --verbose

# Dry run to see what would be deleted
php scripts/cleanup_import_jobs.php --days 30 --dry-run

# More aggressive cleanup (7 days)
php scripts/cleanup_import_jobs.php --days 7 --verbose
```

## Retry Configuration

### Default Retry Behavior

- Maximum retries: 3
- Retry delay: Exponential backoff (1 min, 2 min, 4 min, max 60 min)
- Alerts sent: Only after all retries exhausted

### Customizing Retries

When creating import jobs programmatically:

```php
$jobService = new ImportJobService($pdo);

$batchId = $jobService->createJob(
    filename: 'readings.csv',
    filePath: '/path/to/file.csv',
    importType: 'hh',
    userId: $userId,
    dryRun: false,
    notes: 'Monthly import from supplier X',
    priority: 'high',
    tags: ['supplier-x', 'monthly'],
    metadata: ['source' => 'sftp', 'schedule' => 'monthly'],
    maxRetries: 5  // Custom retry count
);
```

### Manual Retry

Retry a failed job manually:

```php
$jobService = new ImportJobService($pdo);

// Retry immediately
$jobService->retryJob($batchId);

// Retry after 10 minutes
$jobService->retryJob($batchId, 600);
```

## Alert Configuration

### Email Alerts

Email alerts are sent for:
- Individual job failures (after all retries)
- Batch failures (multiple jobs)
- Stuck jobs
- High failure rates
- Queue backlogs

Configure in `.env`:
```env
ADMIN_EMAIL=admin@example.com
MAIL_HOST=smtp.example.com
# ... other mail settings
```

### Slack Alerts

Add Slack webhook URL to `.env`:
```env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

Alerts will be sent to Slack with color-coded severity:
- Red (danger): Critical failures, high failure rates
- Yellow (warning): Stuck jobs, queue backlogs

## Performance Tuning

### Worker Concurrency

For high-volume imports, you can run multiple workers:

```bash
# Cron approach - stagger the workers
* * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once --limit=5 >> logs/worker1.log 2>&1
* * * * * sleep 30 && cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once --limit=5 >> logs/worker2.log 2>&1
```

```bash
# Supervisor approach - multiple instances
[program:eclectyc-import-worker]
process_name=%(program_name)s_%(process_num)02d
numprocs=2
# ... rest of config
```

### Batch Size

Adjust the number of jobs processed per iteration:

```bash
php scripts/process_import_jobs.php --limit=20
```

### Priority-Based Processing

Jobs are processed in priority order:
1. High priority
2. Normal priority
3. Low priority

Within each priority, jobs are processed FIFO (first in, first out).

## Monitoring Dashboards

### Web Interface

- **Job List**: `/admin/imports/jobs` - View all jobs with filtering
- **Job Status**: `/admin/imports/status/{batchId}` - Detailed job view

### API Endpoints

```bash
# Get all jobs
curl https://eclectyc.energy/api/import/jobs

# Get failed jobs only
curl https://eclectyc.energy/api/import/jobs?status=failed

# Get specific job
curl https://eclectyc.energy/api/import/jobs/{batchId}
```

## Troubleshooting

### Worker Not Processing Jobs

1. Check if worker is running:
```bash
# For cron
tail -f logs/import_worker_cron.log

# For supervisor
sudo supervisorctl status eclectyc-import-worker

# For systemd
sudo systemctl status eclectyc-import-worker
```

2. Test worker manually:
```bash
php scripts/process_import_jobs.php --once
```

3. Check for database connection issues:
```bash
php scripts/migrate.php --check
```

### High Failure Rate

1. Check recent failures:
```bash
php scripts/monitor_import_system.php --verbose
```

2. Review error logs:
```bash
tail -f logs/import_worker.log
```

3. Check common issues:
- Database connectivity
- File permissions on storage/imports/
- CSV format changes
- Missing meters in database

### Stuck Jobs

1. Check for stuck jobs:
```bash
php scripts/monitor_import_system.php --verbose
```

2. Automatically mark as failed:
```bash
php scripts/monitor_import_system.php --handle-stuck
```

3. Investigate the cause:
- Check if worker crashed during processing
- Look for memory issues in logs
- Verify database isn't locked

### Queue Backlog

1. Check queue depth:
```bash
php scripts/monitor_import_system.php --verbose
```

2. Process queue faster:
```bash
# Temporarily run additional workers
php scripts/process_import_jobs.php --limit=50 --once
```

3. Consider scaling:
- Add more worker processes
- Increase worker frequency (cron)
- Optimize CSV ingestion performance

## Aggregation Orchestration

The aggregation system is already operationalized with telemetry and alerting.

### Automated Aggregation

Add to crontab:

```cron
# Daily aggregation at 1:30 AM
30 1 * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/aggregate_orchestrated.php --all --verbose >> logs/aggregation.log 2>&1
```

This runs all aggregation ranges (daily, weekly, monthly, annual) with:
- Telemetry tracking in `scheduler_executions` table
- Email alerts for failures
- Detailed execution metrics

### Manual Aggregation

```bash
# Run all ranges
php scripts/aggregate_orchestrated.php --all --verbose

# Run specific range
php scripts/aggregate_orchestrated.php --range daily --date 2025-11-06

# Run for specific date
php scripts/aggregate_orchestrated.php --all --date 2025-11-01 --verbose
```

## Complete Cron Setup

For a complete production setup, use this crontab:

```cron
# Import processing (every minute)
* * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1

# Import monitoring (every 15 minutes)
*/15 * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/monitor_import_system.php --handle-stuck --send-alerts >> logs/import_monitor.log 2>&1

# Import cleanup (Sundays at 2 AM)
0 2 * * 0 cd /path/to/eclectyc-energy && /usr/bin/php scripts/cleanup_import_jobs.php --days 30 --verbose >> logs/import_cleanup.log 2>&1

# Aggregation (daily at 1:30 AM)
30 1 * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/aggregate_orchestrated.php --all --verbose >> logs/aggregation.log 2>&1

# Data quality checks (daily at 2:00 AM)
0 2 * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/run_data_quality_checks.php --verbose >> logs/data_quality.log 2>&1
```

## Security Considerations

1. **File Permissions**: Ensure storage/imports/ is writable by web server user
2. **Log Rotation**: Set up log rotation to prevent disk space issues
3. **Email Credentials**: Store mail credentials securely in .env
4. **API Access**: Restrict import API endpoints to authenticated admin users
5. **Worker User**: Run workers as www-data or dedicated user, not root

## Support and Maintenance

### Regular Checks (Weekly)

- Review import failure logs
- Check alert emails/Slack
- Verify worker is running
- Monitor queue depth
- Check disk space in logs/ and storage/

### Regular Tasks (Monthly)

- Review retry statistics
- Analyze failure patterns
- Optimize slow imports
- Update retention policies if needed
- Review and acknowledge alerts

### Performance Metrics

Monitor these KPIs:
- Import success rate (target: >95%)
- Average import duration (track trends)
- Queue depth (target: <10 at any time)
- Retry rate (target: <10% of jobs)
- Worker uptime (target: >99%)

## Conclusion

With this setup, your async ingestion and aggregation systems are fully operationalized with:

✅ Automatic retry with exponential backoff
✅ Health monitoring and alerting
✅ Automated cleanup and retention
✅ Multiple deployment options
✅ Comprehensive logging and metrics
✅ Production-ready worker management

For questions or issues, consult the troubleshooting section or check application logs.
