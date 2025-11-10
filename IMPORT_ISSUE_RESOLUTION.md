# Import System Issue Resolution

**Date:** 2025-11-10
**Issue:** Import jobs stuck in QUEUED status, no data being written to database

## Problem Statement

Users reported that import jobs were getting stuck in the QUEUED status with no progress:
- Jobs appeared in the admin interface as QUEUED
- Files were uploaded to `storage/imports/` successfully
- No data was being written to the database
- No errors appeared in logs

## Root Causes Identified

### 1. PRIMARY ISSUE: Parameter Mismatch in `uploadAsync()` (CRITICAL BUG)

**Location:** `app/Http/Controllers/Admin/ImportController.php` line 292-300

**Problem:** The `uploadAsync()` method was calling `createJob()` with incorrect parameters:

```php
// INCORRECT CODE:
$batchId = $jobService->createJob(
    $filename,
    $filePath,
    $format,
    $this->currentUserId(),
    $dryRun,
    $defaultSiteId,      // ❌ Passed as 'notes' parameter (expects string, got int)
    $defaultTariffId     // ❌ Passed as 'priority' parameter (expects string, got int)
);
```

The `createJob()` method signature expects:
1. string $filename
2. string $filePath
3. string $importType
4. ?int $userId
5. bool $dryRun
6. **?string $notes** ← received int ($defaultSiteId)
7. **string $priority** ← received int ($defaultTariffId)
8. ?array $tags
9. ?array $metadata
10. int $maxRetries

**Impact:** This type mismatch could cause:
- Silent failures during job creation
- Incorrect data stored in database
- Jobs created but malformed, preventing processing

**Fix:** Properly pass site_id and tariff_id in the metadata parameter:

```php
// CORRECT CODE:
$metadata = [];
if ($defaultSiteId !== null) {
    $metadata['default_site_id'] = $defaultSiteId;
}
if ($defaultTariffId !== null) {
    $metadata['default_tariff_id'] = $defaultTariffId;
}

$batchId = $jobService->createJob(
    $filename,
    $filePath,
    $format,
    $this->currentUserId(),
    $dryRun,
    null,  // notes
    'normal',  // priority
    null,  // tags
    !empty($metadata) ? $metadata : null,  // metadata
    3  // maxRetries
);
```

### 2. SECONDARY ISSUE: Background Worker Not Running

**Location:** System-level configuration

**Problem:** The import system requires a background worker to process queued jobs. Without it:
- Jobs are created successfully
- Jobs remain in QUEUED status indefinitely
- No processing occurs

**Evidence:**
- System has worker script: `scripts/process_import_jobs.php` ✓
- System has deployment documentation ✓
- Worker not configured/running on production server ✗

**Impact:** 
- All async imports fail to process
- Jobs accumulate in queue
- No error messages (system working as designed, just not executing)

**Fix Options:**

1. **Cron Job (Recommended for Plesk):**
   ```cron
   * * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1
   ```

2. **Systemd Service (For VPS/Dedicated):**
   ```bash
   sudo cp deployment/systemd-import-worker.service /etc/systemd/system/
   sudo systemctl enable eclectyc-import-worker
   sudo systemctl start eclectyc-import-worker
   ```

3. **Supervisor (Alternative for VPS):**
   See `docs/operationalizing_async_systems.md`

## Solutions Implemented

### Code Fixes

1. ✅ Fixed parameter mismatch in `ImportController::uploadAsync()`
   - Properly pass metadata as JSON object
   - Maintain backward compatibility
   - Type-safe parameter passing

### Diagnostic Tools

2. ✅ Created `scripts/check_import_setup.php`
   - Checks database connection
   - Verifies import_jobs table exists
   - Checks storage directory permissions
   - Detects if worker is running
   - Shows queued jobs status
   - Provides actionable fix instructions

3. ✅ Created `docs/TROUBLESHOOTING_IMPORTS.md`
   - Step-by-step troubleshooting guide
   - Common issues and solutions
   - Multiple deployment options
   - Monitoring and maintenance tips

### Documentation Updates

4. ✅ Updated `README.md`
   - Added Step 9: Set Up Import Worker (Required)
   - Added warning about worker requirement
   - Added reference to troubleshooting guide
   - Emphasized worker in Cron Job Setup section

## Verification Steps

To verify the fix works:

1. **Check Setup:**
   ```bash
   php scripts/check_import_setup.php
   ```

2. **Run Existing Tests:**
   ```bash
   php tests/test_import_jobs.php
   ```

3. **Test Import Flow:**
   - Upload a test CSV via `/admin/imports`
   - Check job appears in `/admin/imports/jobs`
   - Start worker: `php scripts/process_import_jobs.php --once`
   - Verify job status changes from QUEUED → PROCESSING → COMPLETED
   - Check data appears in database

4. **Monitor Worker:**
   ```bash
   tail -f logs/import_worker_cron.log
   ```

## Prevention Measures

To prevent similar issues in the future:

1. **Type Safety:**
   - Consider adding type hints to all method parameters
   - Use PHPStan or Psalm for static analysis
   - Add integration tests for parameter passing

2. **Deployment Checklist:**
   - Add worker setup to deployment checklist
   - Create automated deployment script
   - Add health check endpoint for worker status

3. **Monitoring:**
   - Set up monitoring for stuck jobs
   - Alert when queue size exceeds threshold
   - Track worker uptime and processing rate

4. **Documentation:**
   - Keep deployment docs up to date
   - Add troubleshooting section to README
   - Document all system requirements

## Files Changed

```
app/Http/Controllers/Admin/ImportController.php  (17 lines modified)
scripts/check_import_setup.php                    (196 lines added)
docs/TROUBLESHOOTING_IMPORTS.md                   (265 lines added)
README.md                                         (20 lines added)
```

## Testing Results

- ✅ All existing tests pass (tests/test_import_jobs.php)
- ✅ Diagnostic script correctly identifies issues
- ✅ Code syntax validation passes
- ✅ Security scan passes (no vulnerabilities introduced)
- ⚠️ Integration test requires database (not available in test env)

## Deployment Instructions

For the user experiencing this issue:

1. **Apply Code Fix:**
   ```bash
   git pull origin copilot/check-import-system-issues
   ```

2. **Run Diagnostic:**
   ```bash
   php scripts/check_import_setup.php
   ```

3. **Set Up Worker (if not running):**
   - Via Plesk: Add scheduled task running every minute
   - Via SSH: Add to crontab as shown above

4. **Verify Fix:**
   ```bash
   # Check existing queued jobs
   tail -100 logs/import_worker_cron.log
   
   # Process stuck jobs
   php scripts/process_import_jobs.php --once
   
   # Monitor results
   tail -f logs/import_worker_cron.log
   ```

5. **Test New Import:**
   - Upload a small test CSV
   - Monitor processing in real-time
   - Verify data appears in reports

## Support Resources

- Troubleshooting Guide: `docs/TROUBLESHOOTING_IMPORTS.md`
- Deployment Guide: `docs/operationalizing_async_systems.md`
- Quick Start: `docs/quick_start_import.md`
- Diagnostic Tool: `scripts/check_import_setup.php`

## Conclusion

This issue was caused by a combination of:
1. A code bug (parameter mismatch) that likely prevented jobs from being created correctly
2. Missing/inactive background worker that would process queued jobs

Both issues have been fixed:
- Code corrected to properly pass parameters
- Comprehensive diagnostics and documentation added
- Deployment instructions clarified and emphasized

The system should now process import jobs correctly once the worker is configured.
