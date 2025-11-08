# Dashboard "System Degraded" Status - Troubleshooting Guide

## Why Does the Dashboard Show "System Degraded"?

The health status indicator in the header polls `/api/health` every 30 seconds and displays the system status.

### Status Levels
1. **System Healthy** (Green) - All checks pass
2. **System Degraded** (Yellow/Warning) - Some non-critical checks fail
3. **System Offline** (Red) - Critical failures or API unreachable

## What Triggers "System Degraded"?

The health check performs multiple checks. If **any** return a status other than "healthy", the overall status becomes "degraded":

### Check 1: Environment Variables
**What it checks:** Required environment variables are set
**Required variables:**
- `APP_KEY`
- `APP_URL`
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

**Common causes of degraded:**
- Missing `.env` file
- Empty environment variable values
- `APP_KEY` or `APP_URL` not configured

**How to fix:**
```bash
# Check if .env exists
ls -la .env

# Verify required variables are set
grep -E "APP_KEY|APP_URL|DB_" .env
```

### Check 2: Database
**What it checks:** 
- Database connection works
- Required tables exist: `users`, `sites`, `meters`, `meter_readings`
- Migrations have run

**Common causes of degraded:**
- Database not accessible
- Migrations not run (missing tables)
- Wrong database credentials

**How to fix:**
```bash
# Run migrations
php scripts/migrate.php

# Or via browser with MIGRATION_KEY
https://your-domain/scripts/migrate.php?key=YOUR_MIGRATION_KEY
```

### Check 3: File System
**What it checks:**
- `/logs` directory exists and is writable
- `/exports` directory exists and is writable
- `.env` file exists and is readable

**Common causes of degraded:**
- Wrong directory permissions
- Missing directories

**How to fix:**
```bash
# Create directories if missing
mkdir -p logs exports storage/imports

# Set permissions
chmod -R 755 logs exports storage
chmod 777 logs  # Or 755 if web server user owns it
```

### Check 4: PHP Version
**What it checks:** PHP >= 8.2.0

**Common causes of degraded:**
- PHP version too old

**How to fix:**
- Upgrade PHP to 8.2 or higher in Plesk

### Check 5: SFTP Configuration
**What it checks:** SFTP credentials are configured
**Required variables:**
- `SFTP_HOST`
- `SFTP_USERNAME`
- `SFTP_PASSWORD` or `SFTP_PRIVATE_KEY`

**Important:** This is **optional** for the platform to work. If you don't use SFTP exports, this warning is expected.

**Common causes of degraded:**
- SFTP not configured (expected if not using exports)

**How to fix (if you want to use SFTP):**
Add to `.env`:
```
SFTP_HOST=your.sftp.server.com
SFTP_PORT=22
SFTP_USERNAME=your_username
SFTP_PASSWORD=your_password
# OR
SFTP_PRIVATE_KEY=/path/to/private_key
SFTP_PATH=/remote/path
```

### Check 6: Recent Activity
**What it checks:**
- Recent import activity (within 24 hours by default)
- Recent export activity (within 48 hours by default)

**Important:** This check can cause "degraded" status if:
- No imports have run recently
- No exports have been configured/run

**Common causes of degraded:**
- Fresh installation with no data imported yet
- Exports not configured (expected if not using SFTP)
- No recent data imports

**How to fix:**
- Import some data via `/admin/imports`
- Or adjust thresholds in `.env`:
```
HEALTH_MAX_IMPORT_HOURS=168  # 7 days
HEALTH_MAX_EXPORT_HOURS=168  # 7 days
```

### Check 7: Disk Space
**What it checks:** Disk usage < 90%

**Common causes of degraded:**
- Low disk space

**How to fix:**
- Free up disk space
- Delete old log files
- Clear tmp directories

## Most Likely Causes

Based on common scenarios:

### Fresh Installation
**Symptoms:** "System Degraded" immediately after setup

**Likely causes:**
1. SFTP not configured (expected - can be ignored if not using exports)
2. No recent activity (expected - import some data)
3. Missing environment variables

**Quick fix:**
```bash
# 1. Check .env has all required variables
cat .env | grep -E "APP_KEY|APP_URL|DB_"

# 2. Run migrations
php scripts/migrate.php

# 3. Import test data
# Via web: /admin/imports
# Or use the Test_HH_Data.csv file
```

### After Deployment
**Symptoms:** Was working, now shows degraded

**Likely causes:**
1. File permissions changed
2. Database connection issue
3. Disk space filled up

**Quick fix:**
```bash
# Check logs for errors
tail -f logs/app.log

# Verify database connection
php -r "new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass'); echo 'OK';"

# Check disk space
df -h
```

## How to Diagnose

### Method 1: Check API Response (Recommended)
Visit: `https://your-domain/api/health`

This returns JSON showing exactly which checks failed:

```json
{
  "status": "degraded",
  "checks": {
    "environment": {
      "status": "healthy"
    },
    "database": {
      "status": "healthy"
    },
    "filesystem": {
      "status": "degraded",
      "message": "File system issues detected",
      "checks": {
        "logs_writable": false  // ← This is the problem!
      }
    },
    "sftp": {
      "status": "degraded",
      "message": "Incomplete SFTP configuration"  // ← Expected if not using SFTP
    }
  }
}
```

### Method 2: Check Browser Console
1. Open browser DevTools (F12)
2. Go to Console tab
3. Look for health check responses
4. Will show which check is failing

### Method 3: Check Server Logs
```bash
# Application logs
tail -f logs/app.log

# PHP error logs (location varies)
tail -f /var/log/php-fpm/error.log
# or
tail -f /var/log/apache2/error.log
```

## Can I Ignore "System Degraded"?

**It depends on which checks are failing:**

### ✅ Safe to ignore:
- **SFTP configuration** - If you're not using SFTP exports
- **Recent export activity** - If you're not using exports
- **Recent import activity** - For fresh installations before first import

### ⚠️ Should investigate:
- **Environment variables** - May cause other issues
- **File system** - Could prevent logging or exports

### ❌ Must fix:
- **Database** - Core functionality won't work
- **PHP version** - Platform may not work correctly
- **Disk space** - System could fail

## Quick Fix Checklist

```bash
# 1. Verify environment
[ -f .env ] && echo "✓ .env exists" || echo "✗ .env missing"

# 2. Check database
php -r "try { new PDO('mysql:host=DB_HOST;dbname=DB_NAME', 'DB_USER', 'DB_PASS'); echo '✓ Database OK\n'; } catch(Exception \$e) { echo '✗ Database failed: ' . \$e->getMessage() . '\n'; }"

# 3. Check permissions
[ -w logs ] && echo "✓ logs writable" || echo "✗ logs not writable"
[ -w storage/imports ] && echo "✓ storage writable" || echo "✗ storage not writable"

# 4. Run migrations
php scripts/migrate.php

# 5. Check health API
curl -s https://your-domain/api/health | jq .status
```

## Customize Health Checks

You can adjust thresholds in `.env`:

```bash
# Extend time windows for activity checks
HEALTH_MAX_IMPORT_HOURS=168  # 7 days instead of 24 hours
HEALTH_MAX_EXPORT_HOURS=168  # 7 days instead of 48 hours

# Enable debug mode to see error details
APP_DEBUG=true
```

## Related Files

- Health check controller: `app/Http/Controllers/Api/HealthController.php`
- Dashboard JavaScript: `public/assets/js/app.js` (line 21-62)
- Base template: `app/views/base.twig` (line 33)

## Summary

The "System Degraded" status is **informational** and shows that some non-critical checks are not passing. The most common causes in fresh installations are:

1. **SFTP not configured** → Expected if not using exports
2. **No recent activity** → Expected before first data import
3. **Missing environment variables** → Should be fixed

**Action:** Visit `/api/health` to see exactly which checks are failing, then follow the specific fix for that check.

---

**Document created:** November 8, 2025  
**Last updated:** November 8, 2025
