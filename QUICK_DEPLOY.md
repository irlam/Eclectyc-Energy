# Quick Deployment Reference

## ⚠️ CRITICAL STEP - DO NOT SKIP

After pulling the latest code, you MUST run:

```bash
composer install --no-dev --optimize-autoloader
```

Without this step, the website will NOT work!

## Quick Deployment Commands

```bash
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

## Need More Help?

See full documentation:
- 📋 [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)
- 📄 [WEBSITE_FIX_SUMMARY.md](WEBSITE_FIX_SUMMARY.md)
- 📖 [README.md](README.md)

## Support

If issues persist after running `composer install`:
1. Check that vendor/ directory exists
2. Verify .env has correct database credentials
3. Check error logs in logs/ directory
4. Ensure database host 10.35.233.124:3306 is accessible
