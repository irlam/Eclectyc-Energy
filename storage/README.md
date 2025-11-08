# Storage Directory

This directory contains uploaded files and temporary data for the Eclectyc Energy platform.

## Subdirectories

### `/imports`
Contains CSV files uploaded through the admin import interface. Files are stored here when using async/background import processing.

**File naming convention:** `{timestamp}_{sanitized_original_filename}.csv`

**Important for Plesk hosting:**
- This directory must have write permissions (755 or 777) for the web server user
- Files in this directory can be browsed via FTP/SFTP or Plesk File Manager
- The path on Plesk hosting typically resolves to: `/httpdocs/eclectyc-energy/storage/imports/`
- For finding files: Look in `File Manager` → Navigate to your domain → `eclectyc-energy` → `storage` → `imports`

**Cleanup:**
- Import job files are retained for the configured retention period (default: 30 days)
- Use the cleanup script to remove old files: `php scripts/cleanup_import_jobs.php --days 30`

**Troubleshooting File Visibility:**
If you cannot see uploaded files in Plesk File Manager:
1. Check directory permissions: `ls -la storage/imports/`
2. Verify the upload completed successfully in `/admin/imports/jobs`
3. Check that the directory path in ImportController matches your deployment structure
4. Files may be in a different location if the application root differs from expected

**Directory Structure on Plesk:**
```
/httpdocs/
  └── eclectyc-energy/          # Application root
      ├── app/
      ├── public/               # Web root (should be your document root)
      ├── storage/
      │   └── imports/          # ← Uploaded CSV files are here
      └── ...
```
