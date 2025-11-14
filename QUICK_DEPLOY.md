# Quick Deployment Reference

## ⚠️ CRITICAL STEP - DO NOT SKIP

### 1. Check Deployment Structure FIRST

```bash
# Run this BEFORE doing anything else (first-time deployments)
php scripts/check-deployment.php
```

This prevents the common `public/public/` path duplication error!

### 2. Install Dependencies

After pulling the latest code, you MUST run:

```bash
composer install --no-dev --optimize-autoloader
```

Without this step, the website will NOT work!

## Quick Deployment Commands

```bash
# 0. Check deployment structure (first-time only)
php scripts/check-deployment.php

# 1. Navigate to project directory
cd /path/to/eclectyc-energy

# 2. Pull latest code
git pull origin main

# 3. Install dependencies (CRITICAL!)
composer install --no-dev --optimize-autoloader

# 4. Set permissions
chmod -R 755 .
chmod -R 777 logs
chmod 644 .env

# 5. Test database connection
php -r "new PDO('mysql:host=10.35.233.124;port=3306;dbname=k87747_eclectyc', 'k87747_eclectyc', 'Subaru5554346'); echo 'DB OK\n';"
```

## Verify Deployment

```bash
# Check website loads
curl -I https://eclectyc.energy/

# Check logs for errors
tail -f logs/app.log
tail -f logs/php-error.log
```

## What Was Fixed

✅ Added composer.lock to ensure consistent dependencies
✅ Fixed missing controller registrations (AI Insights, Alarms, Scheduled Reports)
✅ Improved database connection timeout
✅ Added deployment documentation
✅ Added deployment path checker to prevent public/public errors

## Common Issues

### "public/public/index.php" or "Failed to open vendor/autoload.php"

This means files were uploaded to wrong location!

**Quick Fix:**
```bash
# Check for the issue
php scripts/check-deployment.php

# If confirmed, see detailed instructions:
cat docs/DEPLOYMENT_PATH_ISSUE.md
```

**Root Cause:** Project files were uploaded to `httpdocs/public/` instead of `httpdocs/`

## Need More Help?

See full documentation:
- 🔍 [DEPLOYMENT_PATH_ISSUE.md](docs/DEPLOYMENT_PATH_ISSUE.md) - Fix public/public errors
- 📋 [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) - Complete deployment guide
- 📄 [WEBSITE_FIX_SUMMARY.md](WEBSITE_FIX_SUMMARY.md) - Recent fixes
- 📖 [README.md](README.md) - Installation instructions

## Support

If issues persist after running `composer install`:
1. Check that vendor/ directory exists
2. Verify .env has correct database credentials
3. Check error logs in logs/ directory
4. Ensure database host 10.35.233.124:3306 is accessible
