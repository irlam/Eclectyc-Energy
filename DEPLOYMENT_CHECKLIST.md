# Deployment Checklist

This document provides a step-by-step checklist to redeploy the Eclectyc Energy website after the recent fixes.

## Issues That Were Fixed

1. ✅ Missing composer dependencies (vendor directory)
2. ✅ Missing controller registrations in DI container
3. ✅ composer.lock not tracked in git
4. ✅ Database connection timeout too long

## Pre-Deployment Checklist

- [ ] Ensure you have SSH or terminal access to your hosting server
- [ ] Verify PHP 8.2+ is available on the server
- [ ] Verify Composer is installed on the server
- [ ] Confirm database host (10.35.233.124:3306) is accessible from the server
- [ ] Backup current website files (optional but recommended)

## ⚠️ CRITICAL: Upload Location Warning

**IMPORTANT:** Make sure to upload files to the **correct location**!

### ✅ Correct Structure
```
/var/www/vhosts/yourdomain.com/
  httpdocs/              ← Upload project files HERE
    .htaccess
    public/              ← DocumentRoot points to this
      index.php
      assets/
    app/
    vendor/
    composer.json
```

### ❌ Incorrect Structure (causes public/public error)
```
/var/www/vhosts/yourdomain.com/
  httpdocs/
    public/              ← DocumentRoot
      .htaccess          ← WRONG! Files uploaded to public/ instead of httpdocs/
      public/            ← This creates public/public/
        index.php
```

If you see errors like `public/public/index.php` or `Failed to open vendor/autoload.php`, see [docs/DEPLOYMENT_PATH_ISSUE.md](docs/DEPLOYMENT_PATH_ISSUE.md) for detailed fix instructions.

## Deployment Steps

### 0. Verify Project Structure (First-Time Deployment Only)

**Before doing anything else**, run the deployment checker:

```bash
php scripts/check-deployment.php
```

This will verify:
- Files are in the correct location (no public/public duplication)
- All required directories exist
- Permissions are set correctly
- Dependencies are installed

If errors are found, fix them before proceeding!

### 1. Pull Latest Code

```bash
cd /path/to/eclectyc-energy
git pull origin main
```

### 2. Install Dependencies

**This is the critical step that was missing before!**

```bash
composer install --no-dev --optimize-autoloader
```

This will:
- Install all required PHP dependencies
- Use the locked versions from composer.lock
- Optimize the autoloader for production

### 3. Verify .env Configuration

Check that your .env file has the correct database credentials:

```bash
cat .env | grep -E "^DB_"
```

Should show:
```
DB_HOST=10.35.233.124
DB_PORT=3306
DB_DATABASE=k87747_eclectyc
DB_USERNAME=k87747_eclectyc
DB_PASSWORD=Subaru5554346
```

### 4. Set Correct Permissions

```bash
chmod -R 755 /path/to/eclectyc-energy
chmod -R 777 /path/to/eclectyc-energy/logs
chmod 644 /path/to/eclectyc-energy/.env
```

### 5. Test Database Connection

```bash
php -r "
\$pdo = new PDO(
    'mysql:host=10.35.233.124;port=3306;dbname=k87747_eclectyc',
    'k87747_eclectyc',
    'Subaru5554346'
);
echo 'Database connection successful!\n';
"
```

### 6. Test the Website

Access your website in a browser:
- Main page: https://eclectyc.energy/
- Login page: https://eclectyc.energy/login

### 7. Check Application Logs

```bash
tail -f logs/app.log
tail -f logs/php-error.log
```

Look for any errors or warnings.

### 8. Verify Background Jobs (If Applicable)

If you're using the import worker:

```bash
# Check if the worker is running
ps aux | grep process_import_jobs.php

# If not running, start it via cron or manually
php scripts/process_import_jobs.php &
```

## Post-Deployment Verification

- [ ] Website homepage loads without errors
- [ ] Login page displays correctly
- [ ] Can log in with valid credentials
- [ ] Dashboard displays correctly
- [ ] No PHP errors in logs
- [ ] Database connection is working
- [ ] Import jobs are processing (if applicable)

## Troubleshooting

### "Vendor directory not found" or "Class not found" errors

**Solution:** Run `composer install` again

```bash
cd /path/to/eclectyc-energy
composer install --no-dev --optimize-autoloader
```

### Database connection timeout or "max_user_connections" errors

**Solution:** See [docs/DB_CONNECTION_FIX.md](docs/DB_CONNECTION_FIX.md) for detailed troubleshooting

Quick fix:
```bash
# Check current connections
php scripts/cleanup_db_connections.php

# Kill idle connections if needed
php scripts/cleanup_db_connections.php --kill-idle
```

### "Application Error" or blank page

**Solution:** Check error logs

```bash
tail -100 logs/php-error.log
tail -100 logs/app.log
```

### Routes not working (404 errors) or 403 "No matching DirectoryIndex" errors

**Solution:** Check for deployment path issues first

```bash
# Run the deployment structure checker
php scripts/check-deployment.php
```

If you see `public/public/` duplication:
- See [docs/DEPLOYMENT_PATH_ISSUE.md](docs/DEPLOYMENT_PATH_ISSUE.md) for detailed fix

Otherwise, verify .htaccess and document root:
1. Check that `.htaccess` exists in the `public/` directory **and** root directory
2. Verify Apache mod_rewrite is enabled
3. Confirm document root points to `public/` directory (NOT `public/public/`)
4. See [docs/APACHE_CONFIGURATION_FIX.md](docs/APACHE_CONFIGURATION_FIX.md) for detailed troubleshooting

## Files Changed in This Deployment

- `public/index.php` - Added missing controller registrations
- `.gitignore` - Removed composer.lock exclusion
- `composer.lock` - Added to repository (NEW FILE)

## Support

If you encounter issues not covered here:

1. **Path duplication errors** (`public/public/`): [docs/DEPLOYMENT_PATH_ISSUE.md](docs/DEPLOYMENT_PATH_ISSUE.md)
2. Check the main [README.md](README.md) for installation instructions
3. Review [docs/APACHE_CONFIGURATION_FIX.md](docs/APACHE_CONFIGURATION_FIX.md) for 403/404 errors
4. Review [docs/DB_CONNECTION_FIX.md](docs/DB_CONNECTION_FIX.md) for database issues
5. Check [docs/troubleshooting_504_timeouts.md](docs/troubleshooting_504_timeouts.md) for timeout issues

## Summary

The main issue was that **composer dependencies were not installed** on the server. This is because:

1. The `vendor/` directory is excluded from git (correct)
2. The `composer.lock` file was also excluded from git (incorrect for applications)
3. Without `composer.lock`, deployments would install unpredictable dependency versions

Now with `composer.lock` in the repository, running `composer install` will install the exact same dependency versions every time, ensuring consistency across deployments.

**The critical command to run:** `composer install --no-dev --optimize-autoloader`
