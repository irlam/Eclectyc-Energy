# Cron Job Logging & Monitoring Guide

## Overview

The Eclectyc Energy platform includes comprehensive cron job logging and monitoring capabilities to help you track scheduled tasks, diagnose issues, and maintain system health.

## Cron Logs Table

All cron job executions are now tracked in the `cron_logs` table, which provides:

- **Job identification**: Track which job ran and what type it was
- **Execution timing**: Record start time, end time, and duration
- **Status tracking**: See if jobs completed successfully or failed
- **Performance metrics**: Track records processed, errors, and warnings
- **Detailed logging**: Store structured log data for debugging

### Table Schema

```sql
CREATE TABLE `cron_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) NOT NULL COMMENT 'Name/type of cron job',
  `job_type` enum('daily','weekly','monthly','annual','import','export','cleanup','other'),
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `status` enum('running','completed','failed','timeout'),
  `exit_code` int DEFAULT NULL,
  `records_processed` int DEFAULT '0',
  `records_failed` int DEFAULT '0',
  `errors_count` int DEFAULT '0',
  `warnings_count` int DEFAULT '0',
  `error_message` text,
  `log_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_name` (`job_name`),
  KEY `idx_job_type` (`job_type`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`)
);
```

## Viewing Cron Logs

### Query Recent Job Executions

```sql
-- View last 20 cron job executions
SELECT 
    job_name,
    job_type,
    start_time,
    duration_seconds,
    status,
    records_processed,
    errors_count
FROM cron_logs
ORDER BY start_time DESC
LIMIT 20;
```

### Find Failed Jobs

```sql
-- Find all failed jobs in the last 7 days
SELECT 
    job_name,
    start_time,
    error_message,
    log_data
FROM cron_logs
WHERE status = 'failed'
  AND start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY start_time DESC;
```

### Monitor Job Performance

```sql
-- Average duration by job type
SELECT 
    job_type,
    COUNT(*) as executions,
    ROUND(AVG(duration_seconds), 2) as avg_duration,
    MIN(duration_seconds) as min_duration,
    MAX(duration_seconds) as max_duration
FROM cron_logs
WHERE status = 'completed'
  AND duration_seconds IS NOT NULL
GROUP BY job_type;
```

## Automated Log Cleanup

The platform includes an automated log cleanup script (`scripts/cleanup_logs.php`) that:

1. **Archives old cron_logs** (older than 30 days by default)
2. **Backs up audit_logs** before deletion
3. **Rotates large log files** (> 10 MB)
4. **Compresses old backups** to save disk space
5. **Removes old backup files** (keeps last 10)

### Running Log Cleanup

```bash
# Run cleanup with default 30-day retention
php scripts/cleanup_logs.php

# Dry run to see what would be deleted
php scripts/cleanup_logs.php --dry-run

# Verbose output
php scripts/cleanup_logs.php --verbose

# Custom retention period (60 days)
php scripts/cleanup_logs.php --retention-days=60
```

### Automated Scheduling

Add to your crontab to run cleanup weekly:

```cron
# Run log cleanup every Sunday at 2 AM
0 2 * * 0 cd /path/to/eclectyc-energy && php scripts/cleanup_logs.php
```

## Integration with Existing Cron Jobs

The aggregation cron script (`scripts/aggregate_cron.php`) automatically logs to the `cron_logs` table. When you run:

```bash
php scripts/aggregate_cron.php -r daily
```

It will:
1. Execute the daily aggregation
2. Log the execution to `audit_logs` (existing behavior)
3. **NEW**: Also log to `cron_logs` with detailed metrics

## Monitoring Dashboard

You can create a simple monitoring dashboard by querying the `cron_logs` table:

### Recent Failures

```sql
SELECT 
    DATE(start_time) as date,
    job_name,
    COUNT(*) as failures
FROM cron_logs
WHERE status = 'failed'
  AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(start_time), job_name
ORDER BY date DESC, failures DESC;
```

### Job Success Rate

```sql
SELECT 
    job_name,
    COUNT(*) as total_runs,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    ROUND(100.0 * SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*), 2) as success_rate
FROM cron_logs
WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY job_name
ORDER BY success_rate ASC;
```

## Best Practices

### 1. Regular Monitoring
- Check `cron_logs` daily for failures
- Set up alerts for repeated failures
- Monitor job duration for performance degradation

### 2. Log Retention
- Keep at least 30 days of cron logs for troubleshooting
- Archive older logs to backup storage
- Run cleanup script weekly to prevent table bloat

### 3. Error Investigation
- Always check `error_message` for failed jobs
- Review `log_data` JSON for detailed context
- Cross-reference with `audit_logs` for data changes

### 4. Performance Optimization
- Monitor `duration_seconds` trends
- Investigate jobs taking longer than usual
- Consider adding indexes if queries are slow

## Troubleshooting

### Cron Logs Table Not Found

If you see errors about the `cron_logs` table not existing, run the migration:

```bash
mysql -u username -p database_name < database/migrations/012_create_cron_logs_table.sql
```

Or import the complete database schema:

```bash
mysql -u username -p database_name < database/database.sql
```

### No Logs Appearing

1. Check that your cron jobs are actually running
2. Verify database connection in `.env` file
3. Check file permissions on scripts
4. Review system logs: `tail -f logs/php-error.log`

### Log File Too Large

If `logs/php-error.log` or `logs/app.log` grows too large:

```bash
# Manual rotation
php scripts/cleanup_logs.php

# Or compress and clear
gzip logs/php-error.log
> logs/php-error.log
```

## Migration Notes

When migrating from the old system:

1. **Old logs are preserved**: The `audit_logs` table still contains all historical aggregation logs
2. **Dual logging**: New runs log to both `audit_logs` and `cron_logs`
3. **No data loss**: All existing audit data remains intact
4. **Backwards compatible**: Old scripts continue to work

## API Access

You can also access cron logs programmatically:

```php
use App\Config\Database;

$pdo = Database::getConnection();
$stmt = $pdo->prepare('
    SELECT * FROM cron_logs 
    WHERE job_name = :job_name 
    AND start_time >= :since
    ORDER BY start_time DESC
    LIMIT 100
');

$stmt->execute([
    'job_name' => 'aggregate_daily',
    'since' => date('Y-m-d H:i:s', strtotime('-7 days'))
]);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

## Support

For issues or questions about cron logging:

1. Check the system health dashboard at `/tools/system-health`
2. Review application logs at `/tools/logs`
3. Contact your system administrator
4. Refer to the main README.md for general troubleshooting

---

**Last Updated**: 10/11/2025  
**Version**: 1.0
