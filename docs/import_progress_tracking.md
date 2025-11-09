# Import Progress Tracking System

## Overview

The Eclectyc Energy platform supports both synchronous and asynchronous CSV imports with progress tracking capabilities.

## How Progress Tracking Works

### For Synchronous Imports

When a user uploads a CSV file directly through the web interface:

1. **Progress Callback**: A callback function is passed to the `CsvIngestionService`
2. **Logging**: Progress is logged to error_log after each row is processed
3. **No Real-Time UI Updates**: Synchronous imports don't support real-time progress in the UI
4. **Final Summary**: After completion, a summary is displayed showing total records processed

**Progress Callback Example:**
```php
$progressCallback = function (int $processed, int $imported, int $warnings) use ($batchId) {
    error_log(sprintf(
        '[Import Progress] Batch: %s | Processed: %d | Imported: %d | Warnings: %d',
        substr($batchId, 0, 8),
        $processed,
        $imported,
        $warnings
    ));
};
```

### For Asynchronous Imports (Recommended for Large Files)

When using async imports, the system provides full real-time progress tracking:

1. **Job Creation**: Import job is created and queued in `import_jobs` table
2. **Background Processing**: The `process_import_jobs.php` script processes queued jobs
3. **Progress Updates**: Database is updated after each row via the progress callback:
   ```php
   $progressCallback = function (int $processed, int $imported, int $warnings) use ($jobService, $batchId) {
       $failed = $processed - $imported;
       $jobService->updateProgress($batchId, $processed, $imported, $failed);
   };
   ```
4. **Real-Time UI**: The import status page auto-refreshes every 5 seconds to show:
   - Progress bar with percentage
   - Rows processed / total rows
   - Processing speed (rows/sec)
   - Estimated time remaining (ETA)
   - Success rate

### Database Schema

The `import_jobs` table tracks progress:
```sql
processed_rows INT    -- Number of rows processed so far
imported_rows INT     -- Number of rows successfully imported
failed_rows INT       -- Number of rows that failed
total_rows INT        -- Total rows in the file (estimated)
```

## Import Status Page Features

The `/admin/imports/status/{batchId}` page displays:

- **Progress Bar**: Visual representation of completion percentage
- **Statistics**: Real-time counts of processed, imported, and failed rows
- **Performance Metrics**: Rows per second and estimated time remaining
- **Auto-Refresh**: Page automatically refreshes every 5 seconds for active jobs
- **Error Details**: Sample of errors if any occurred

## How Throttling Affects Progress

Import throttling (configurable in System Settings) impacts progress speed:

- **Batch Size**: Number of rows processed before pausing
- **Delay**: Milliseconds to wait between batches
- **Effect**: Reduces server load but increases total import time

See the System Settings page for preset configurations based on import size.

## Monitoring Import Jobs

### Via Web Interface
1. Navigate to `/admin/imports/jobs` to view all import jobs
2. Click on a specific job to view detailed status
3. Active jobs will show real-time progress updates

### Via Command Line
```bash
# Process queued jobs once and exit
php scripts/process_import_jobs.php --once

# Continuous processing (recommended for production)
php scripts/process_import_jobs.php

# Limit number of jobs processed per iteration
php scripts/process_import_jobs.php --limit=5
```

## Best Practices

1. **Small Imports (<5,000 rows)**: Use synchronous import for immediate feedback
2. **Medium to Large Imports (>5,000 rows)**: Use async import for better performance
3. **Very Large Imports (>100,000 rows)**: Always use async with appropriate throttling
4. **Background Worker**: Keep `process_import_jobs.php` running as a service/cron job

## Troubleshooting

### Progress Not Updating
- **Check**: Is the background worker running?
- **Solution**: Run `php scripts/process_import_jobs.php --once` to process queued jobs

### Import Stuck at 0%
- **Check**: Has the file been found?
- **Solution**: Verify file exists in `storage/imports/` directory

### Slow Progress
- **Check**: Throttling settings in System Settings
- **Solution**: Adjust batch size and delay for better performance (lower delay = faster import)

## Related Documentation

- [Throttling Configuration](troubleshooting_504_timeouts.md)
- [Import System Overview](IMPLEMENTATION_SUMMARY_IMPORT_MANAGEMENT.md)
