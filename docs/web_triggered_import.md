# Web-Triggered CSV Import Feature

## Overview

The Eclectyc Energy platform now supports web-triggered CSV imports with background processing capabilities. This allows users to upload CSV files through the web interface and track their progress in real-time, with the ability to close the browser while imports are processing in the background.

## Features

### 1. Synchronous Import (Default)
- Upload CSV files directly through the web interface
- Get immediate feedback on import results
- Suitable for small to medium-sized files
- Browser must stay open during processing

### 2. Asynchronous Import (Background Processing)
- Upload CSV files and queue them for background processing
- Close the browser and check status later
- Suitable for large files that take time to process
- Track progress in real-time
- Multiple imports can be queued and processed sequentially

### 3. Job Tracking
- View all import jobs (queued, processing, completed, failed)
- Real-time progress updates
- Detailed status information including:
  - Rows processed, imported, and failed
  - Progress percentage
  - Error messages
  - Processing timestamps

## How to Use

### Web Interface

1. **Navigate to Imports Page**
   - Go to `/admin/imports` in your browser
   - You must be logged in with admin privileges

2. **Upload a CSV File**
   - Click "Choose File" and select your CSV file
   - Select the import type:
     - **Half-hourly (HH)**: For files with 48 half-hourly periods per day
     - **Daily**: For files with single daily totals
   - Optionally check "Dry run" to validate without saving data
   - Optionally check "Process in background" for async processing

3. **Process the Import**
   - Click "Upload & Process"
   - For synchronous imports: wait for results to display
   - For async imports: you'll be redirected to the job status page

4. **Track Import Jobs**
   - Visit `/admin/imports/jobs` to see all import jobs
   - Filter by status (queued, processing, completed, failed)
   - Click "View" on any job to see detailed status
   - Active jobs auto-refresh every 5 seconds

### Background Worker

The background worker processes queued import jobs. It must be running for async imports to work.

**Start the worker manually:**
```bash
cd /path/to/eclectyc-energy
php scripts/process_import_jobs.php
```

**Process jobs once and exit:**
```bash
php scripts/process_import_jobs.php --once
```

**Limit the number of jobs per iteration:**
```bash
php scripts/process_import_jobs.php --limit=5
```

### Setting Up as a Cron Job (Recommended)

Add to your crontab to process imports continuously:

```bash
# Process import jobs every minute
* * * * * cd /path/to/eclectyc-energy && php scripts/process_import_jobs.php --once >> logs/import_worker.log 2>&1
```

Or run as a long-running process with supervisord:

```ini
[program:eclectyc-import-worker]
command=/usr/bin/php /path/to/eclectyc-energy/scripts/process_import_jobs.php
directory=/path/to/eclectyc-energy
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/eclectyc-energy/logs/import_worker.log
```

### API Endpoints

**Get list of import jobs:**
```bash
GET /api/import/jobs
GET /api/import/jobs?status=processing
GET /api/import/jobs?limit=50
```

**Get specific job status:**
```bash
GET /api/import/jobs/{batchId}
```

Example response:
```json
{
    "id": 123,
    "batch_id": "550e8400-e29b-41d4-a716-446655440000",
    "filename": "readings_november.csv",
    "import_type": "hh",
    "status": "processing",
    "dry_run": false,
    "total_rows": 1000,
    "processed_rows": 450,
    "imported_rows": 445,
    "failed_rows": 5,
    "progress_percent": 45.0,
    "queued_at": "2025-11-07 10:30:00",
    "started_at": "2025-11-07 10:30:15",
    "completed_at": null,
    "user_name": "Admin User",
    "user_email": "admin@eclectyc.energy"
}
```

## Database Schema

The import jobs are tracked in the `import_jobs` table:

```sql
CREATE TABLE import_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(36) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NULL,
    import_type ENUM('hh', 'daily') NOT NULL DEFAULT 'hh',
    status ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    dry_run BOOLEAN DEFAULT FALSE,
    
    -- Progress tracking
    total_rows INT UNSIGNED NULL,
    processed_rows INT UNSIGNED DEFAULT 0,
    imported_rows INT UNSIGNED DEFAULT 0,
    failed_rows INT UNSIGNED DEFAULT 0,
    
    -- Timestamps
    queued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Results
    error_message TEXT NULL,
    summary JSON NULL,
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_queued_at (queued_at),
    INDEX idx_completed_at (completed_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## CSV File Format

The system supports two CSV formats:

### Half-Hourly (HH) Format
```csv
MPAN, Date, HH01, HH02, ..., HH48
1234567890123, 2025-11-07, 1.5, 1.6, ..., 2.1
```

### Daily Format
```csv
MPAN, Date, Reading
1234567890123, 2025-11-07, 45.6
```

The system automatically detects delimiters (comma, tab, semicolon, pipe) and recognizes various header aliases (e.g., MeterCode, ReadDate, etc.).

## Troubleshooting

### Import job stuck in "queued" status
- Check if the background worker is running
- Start the worker manually: `php scripts/process_import_jobs.php --once`
- Check worker logs for errors

### File not found error
- Ensure the `storage/imports` directory exists and is writable
- Check file permissions: `chmod -R 755 storage/imports`

### Import job failed
- View the job details at `/admin/imports/status/{batchId}`
- Check the error message for specific issues
- Common issues:
  - Invalid CSV format
  - Missing MPAN/meter identifier column
  - Database connection issues
  - File corruption

### Progress not updating
- Ensure the job status page is refreshing (auto-refresh every 5 seconds for active jobs)
- Check if the worker is processing the job
- Manually refresh the page

## Maintenance

### Cleanup Old Jobs

Old completed jobs can be cleaned up to save database space:

```php
// In your cleanup script or maintenance task
$jobService = new ImportJobService($pdo);
$deleted = $jobService->cleanupOldJobs(30); // Remove jobs older than 30 days
echo "Deleted $deleted old import jobs\n";
```

### Storage Cleanup

Uploaded CSV files are stored in `storage/imports/`. Failed or cancelled job files should be manually reviewed and deleted if no longer needed. Completed job files are automatically cleaned up.

## Security Considerations

- Import functionality requires admin authentication
- Files are validated before processing
- File paths are sanitized to prevent directory traversal
- Uploaded files are stored outside the web root
- Background worker processes files with same permissions as web server

## Future Enhancements

- Email notifications when imports complete
- Webhook support for import completion events
- Parallel processing of multiple jobs
- Import scheduling
- File size limits and validation
- Support for Excel files (.xlsx)
- Import templates and presets
