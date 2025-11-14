# Import Progress, SFTP Integration & Throttling Features

## Overview

This document describes the enhancements made to the import system to address the following requirements:

1. **Enhanced Progress Display**: Visual progress bar with ETA for import jobs
2. **Sites Visibility Fix**: All active sites now show in consumption reports
3. **Import Throttling**: Configurable throttling to prevent server overload
4. **SFTP Integration**: Automated file retrieval from SFTP servers

## Features

### 1. Enhanced Import Progress Display

The import status page (`/admin/imports/status/{batchId}`) now displays:

- **Modern Progress Bar**: Animated gradient progress bar with shimmer effect
- **ETA Calculation**: Estimated time to completion based on current processing speed
- **Processing Speed**: Shows rows/second being processed
- **Success Rate**: Percentage of successfully imported rows
- **Auto-Refresh**: Page refreshes every 5 seconds for active jobs

#### Technical Implementation

- Enhanced `app/views/admin/import_status.twig` with new progress UI
- ETA calculated using: `remaining_rows / rows_per_second`
- Progress percentage: `(processed_rows / total_rows) * 100`
- Supports both queued and processing job statuses

### 2. Sites Visibility Fix

**Problem**: Only sites with meter readings were shown in consumption reports.

**Solution**: Modified the consumption report query to use LEFT JOIN instead of INNER JOIN.

```php
// Before: Only showed sites with data
FROM daily_aggregations da
JOIN meters m ON da.meter_id = m.id
JOIN sites s ON m.site_id = s.id

// After: Shows ALL active sites
FROM sites s
LEFT JOIN meters m ON s.id = m.site_id AND m.is_active = 1
LEFT JOIN daily_aggregations da ON m.id = da.meter_id
```

**Result**: All active sites now appear in the report, showing 0 consumption if no data exists.

### 3. Import Throttling

Prevents server overload and 504 timeout errors during large imports.

#### Configuration

**Via GUI (Recommended)**:
1. Navigate to `/admin/settings`
2. Toggle **Import Throttle Enabled** ON
3. Adjust batch size and delay as needed
4. Click **Save Settings**

**Via Database** (if GUI not available):

Throttling is stored in the `system_settings` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `import_throttle_enabled` | `false` | Enable/disable throttling |
| `import_throttle_batch_size` | `100` | Rows to process before pausing |
| `import_throttle_delay_ms` | `100` | Delay in milliseconds between batches |
| `import_max_execution_time` | `300` | Maximum execution time (seconds) |
| `import_max_memory_mb` | `256` | Maximum memory allocation (MB) |

#### How It Works

1. After processing every N rows (batch_size), the import pauses for X milliseconds (delay_ms)
2. This gives the server time to handle other requests and prevents timeouts
3. Settings are loaded lazily and cached for performance

#### Example

With `batch_size=100` and `delay_ms=100`:
- Processes 100 rows
- Pauses for 100ms
- Processes next 100 rows
- Pauses for 100ms
- Continues until complete

### 4. SFTP Integration

Automatically retrieve and import CSV files from remote SFTP servers.

#### Features

- **Multiple Configurations**: Support for multiple SFTP servers
- **Authentication**: Password or SSH private key authentication
- **Pattern Matching**: Filter files by pattern (e.g., `*.csv`, `data_*.csv`)
- **Auto-Import**: Optional automatic import of matching files
- **Connection Testing**: Test SFTP connections before use
- **File Management**: Browse, download, and delete remote files

#### Setup

1. **Navigate to Tools Section**
   ```
   Dashboard → Tools → SFTP Connections
   ```

2. **Create Configuration**
   - Click "New Configuration"
   - Fill in SFTP server details:
     - Name (friendly identifier)
     - Host (server address)
     - Port (default: 22)
     - Username
     - Password or Private Key Path
     - Remote Directory
     - File Pattern
     - Import Type (HH or Daily)
   - Enable Auto Import if desired
   - Save configuration

3. **Test Connection**
   - Click the test button (🔌) to verify connectivity
   - Check for any connection errors

4. **Browse Files**
   - Click the folder icon (📁) to view matching files
   - See file sizes and modification dates
   - Import files manually or rely on auto-import

#### Using SFTP Import

**Manual Import**:
1. Browse to `/tools/sftp/{id}/files`
2. Select a file to import
3. Click "Import" button
4. Track progress in import jobs page

**Automatic Import** (when enabled):
1. Configure SFTP with auto-import enabled
2. Schedule the import worker to run periodically
3. Worker will automatically:
   - Connect to SFTP server
   - List matching files
   - Download and import files
   - Optionally delete files after import

#### Security

- Passwords are encrypted in the database using AES-256-CBC
- Encryption key is stored in `.env` file (`APP_KEY`)
- SSH private keys are referenced by path, not stored in database
- All SFTP operations are logged with timestamps

## Database Changes

### New Tables

#### `sftp_configurations`
Stores SFTP server connection details.

```sql
CREATE TABLE sftp_configurations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT UNSIGNED DEFAULT 22,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(500) NULL,          -- Encrypted
    private_key_path VARCHAR(500) NULL,
    remote_directory VARCHAR(500) DEFAULT '/',
    file_pattern VARCHAR(255) DEFAULT '*.csv',
    import_type ENUM('hh', 'daily') DEFAULT 'hh',
    auto_import BOOLEAN DEFAULT FALSE,
    delete_after_import BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_connection_at TIMESTAMP NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `system_settings`
Stores system-wide configuration settings.

```sql
CREATE TABLE system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    is_editable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Migration

Run the migration to create these tables:

```bash
# Import the migration SQL file
mysql -u username -p database_name < database/migrations/008_create_sftp_configurations.sql
```

## API Reference

### SftpService

```php
use App\Domain\Sftp\SftpService;

$service = new SftpService($pdo);

// Get all configurations
$configs = $service->getAllConfigurations();

// Test connection
$result = $service->testConnection($configId);

// List files
$files = $service->listFiles($configId);

// Download file
$localPath = $service->downloadFile($configId, $filename);

// Delete file
$service->deleteRemoteFile($configId, $filename);
```

### SystemSettingsService

```php
use App\Domain\Settings\SystemSettingsService;

$settings = new SystemSettingsService($pdo);

// Get throttle settings
$throttle = $settings->getImportThrottleSettings();

// Get individual setting
$enabled = $settings->get('import_throttle_enabled', false);

// Set setting
$settings->set('import_throttle_enabled', true, 'boolean');
```

## Troubleshooting

### Import Timeouts (504 Errors)

**Problem**: Large imports timeout with 504 error.

**Solution**: Enable throttling:
```sql
UPDATE system_settings SET setting_value = 'true' WHERE setting_key = 'import_throttle_enabled';
```

Adjust batch size and delay as needed:
```sql
UPDATE system_settings SET setting_value = '50' WHERE setting_key = 'import_throttle_batch_size';
UPDATE system_settings SET setting_value = '200' WHERE setting_key = 'import_throttle_delay_ms';
```

### SFTP Connection Fails

**Problem**: Cannot connect to SFTP server.

**Solutions**:
1. **Check credentials**: Verify username and password
2. **Test port**: Ensure port 22 (or custom port) is accessible
3. **Firewall**: Check firewall rules allow outbound SFTP
4. **SSH keys**: If using key auth, verify path and permissions
5. **Review logs**: Check `last_error` column in `sftp_configurations`

### Sites Not Showing in Report

**Problem**: Some sites missing from consumption report.

**Solution**: This was fixed by using LEFT JOIN. If still missing:
1. Verify site is active: `SELECT * FROM sites WHERE is_active = 1`
2. Check meters exist for site: `SELECT * FROM meters WHERE site_id = X`
3. Clear any caches

### Progress Bar Not Updating

**Problem**: Progress bar shows 0% or doesn't update.

**Solutions**:
1. Ensure job has `total_rows` set in database
2. Check `processed_rows` is being updated
3. Verify auto-refresh is working (check browser console)
4. Clear browser cache

## Performance Considerations

### Throttling Impact

- **With throttling disabled**: ~500-1000 rows/second
- **With throttling (100ms/100 rows)**: ~50-100 rows/second
- **Trade-off**: Slower imports but more stable server

### SFTP Performance

- File download speed depends on network and server
- Large files may take several minutes to download
- Consider file size limits in PHP configuration:
  - `upload_max_filesize`
  - `post_max_size`
  - `memory_limit`

### Progress Calculation

- ETA becomes more accurate after ~5-10% progress
- Initial ETA may fluctuate as processing speed stabilizes
- Auto-refresh every 5 seconds adds minimal overhead

## Future Enhancements

Potential improvements for future versions:

1. **SFTP Scheduling**: Cron job integration for automatic file retrieval
2. **Email Notifications**: Alert when new files are imported
3. **Advanced Throttling**: Dynamic throttling based on server load
4. **Multi-threading**: Parallel import processing
5. **File Archiving**: Archive imported files instead of deletion
6. **Retry Logic**: Automatic retry of failed SFTP connections
7. **Progress Websockets**: Real-time progress updates without page refresh

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review import job logs in `/admin/imports/jobs`
3. Check system health in `/tools/system-health`
4. Review audit logs for import operations
