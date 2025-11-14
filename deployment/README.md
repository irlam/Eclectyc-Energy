# Deployment Scripts and Configuration

This directory contains scripts and configuration files to help with deployment and server setup.

## Quick Start - Production Deployment

If you're deploying to production for the first time:

```bash
# 1. Upload all project files to the server (e.g., to /var/www/vhosts/yourdomain.com/httpdocs/)
#    NOTE: vendor/ directory is included, so no composer install needed!

# 2. Verify the deployment structure
php deployment/fix-deployment-structure.php

# 3. Configure .env file with your database credentials
cp .env.example .env
nano .env  # Edit with your settings

# 4. Set correct DocumentRoot in Apache/Plesk
#    Set to: /var/www/vhosts/yourdomain.com/httpdocs/public

# 5. Test the application
curl https://yourdomain.com/
```

**Key Points:**
- ✅ Vendor directory included - no `composer install` required
- ✅ Index.html workaround included for DirectoryIndex issues
- ✅ All dependencies are production-ready (no dev packages)


## Files in This Directory

### Installation & Setup Scripts

#### `install-dependencies.sh` (OPTIONAL - Dependencies Now Included)
Automated script to install all PHP dependencies via Composer.

**⚠️ NOTE:** As of November 14, 2025, the `vendor/` directory is included in the repository. You typically don't need to run this script unless you're updating dependencies.

**Usage:**
```bash
bash deployment/install-dependencies.sh
```

**What it does:**
- Checks if Composer is installed
- Installs all PHP dependencies from composer.lock
- Creates required directories (logs, storage, exports)
- Sets correct file permissions
- Verifies installation

**When to use:** 
- When updating dependencies to newer versions
- When you've modified composer.json
- Optional for first-time deployment (vendor/ already included)

---

#### `fix-deployment-structure.php` 🔍 **Diagnostics**
Diagnostic and fix script for common deployment issues.

**Usage:**
```bash
# Via CLI:
php deployment/fix-deployment-structure.php

# Via Web Browser:
https://yourdomain.com/deployment/fix-deployment-structure.php?allow
```

**What it checks:**
- ✓ Path duplication (public/public/ issue)
- ✓ Required directories exist
- ✓ Vendor directory and autoload.php
- ✓ index.php location
- ✓ .htaccess files
- ✓ File permissions

**When to use:** 
- After deployment to verify everything is correct
- When troubleshooting 403/500 errors
- When seeing "vendor/autoload.php not found" errors

---

### Background Job Configuration

#### `crontab.example`
Example crontab configuration for scheduled tasks.

**Setup:**
```bash
# Edit and install the crontab
crontab -e
# Copy the contents from crontab.example and adjust paths
```

**Scheduled jobs:**
- Import worker for processing energy data imports
- (Add more as needed)

---

#### `supervisor-import-worker.conf`
Supervisor configuration for running the import worker as a daemon.

**Setup:**
```bash
sudo cp deployment/supervisor-import-worker.conf /etc/supervisor/conf.d/eclectyc-import-worker.conf
sudo nano /etc/supervisor/conf.d/eclectyc-import-worker.conf  # Adjust paths
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start eclectyc-import-worker
```

---

#### `systemd-import-worker.service`
Systemd service configuration for the import worker (alternative to Supervisor).

**Setup:**
```bash
sudo cp deployment/systemd-import-worker.service /etc/systemd/system/
sudo nano /etc/systemd/system/systemd-import-worker.service  # Adjust paths
sudo systemctl daemon-reload
sudo systemctl enable systemd-import-worker
sudo systemctl start systemd-import-worker
```

---

## Common Deployment Scenarios

### Scenario 1: First-Time Deployment
```bash
# 1. Upload files via FTP/SCP to server
scp -r /local/path/* user@server:/var/www/vhosts/yourdomain.com/httpdocs/

# 2. SSH into server
ssh user@server

# 3. Navigate to project
cd /var/www/vhosts/yourdomain.com/httpdocs

# 4. Install dependencies
bash deployment/install-dependencies.sh

# 5. Configure environment
cp .env.example .env
nano .env  # Set DB credentials

# 6. Verify setup
php deployment/fix-deployment-structure.php

# 7. Set DocumentRoot (in Plesk/cPanel)
# Set to: /var/www/vhosts/yourdomain.com/httpdocs/public

# 8. Test in browser
curl https://yourdomain.com/
```

### Scenario 2: Update Existing Deployment
```bash
# 1. SSH into server
ssh user@server

# 2. Navigate to project
cd /var/www/vhosts/yourdomain.com/httpdocs

# 3. Pull latest changes
git pull origin main

# 4. Update dependencies (if composer.lock changed)
composer install --no-dev --optimize-autoloader

# 5. Clear any caches (if applicable)
rm -rf storage/cache/*

# 6. Restart services (if using background workers)
sudo supervisorctl restart eclectyc-import-worker
```

### Scenario 3: Fixing "public/public" Error
```bash
# 1. Run diagnostic
php deployment/fix-deployment-structure.php

# 2. If it detects public/public duplication, see:
cat docs/DEPLOYMENT_PATH_ISSUE.md

# 3. Quick fix (if confirmed safe):
cd /var/www/vhosts/yourdomain.com/httpdocs/public
mv * ../temp/
mv .* ../temp/ 2>/dev/null || true
cd ..
rmdir public
mv temp/* .
mv temp/.* . 2>/dev/null || true
rmdir temp

# 4. Verify fix
php deployment/fix-deployment-structure.php
```

---

## Troubleshooting

### Error: "vendor/autoload.php not found"
**Solution:** Run `bash deployment/install-dependencies.sh`

### Error: "public/public/index.php in path"
**Solution:** Files uploaded to wrong location. See `docs/DEPLOYMENT_PATH_ISSUE.md`

### Error: "No matching DirectoryIndex"
**Solution:** 
1. Ensure DocumentRoot points to `/path/to/project/public`
2. Verify `public/.htaccess` exists
3. Check that PHP is enabled in Apache

### Error: "Failed to connect to database"
**Solution:**
1. Verify `.env` file has correct credentials
2. Test connection: `php -r "new PDO('mysql:host=...', 'user', 'pass');"`
3. Check firewall allows connection to database server

---

## File Permissions

Recommended permissions:
```bash
# Project root
chmod -R 755 /path/to/project

# Writable directories
chmod -R 777 /path/to/project/logs
chmod -R 777 /path/to/project/storage
chmod -R 777 /path/to/project/exports

# Environment file (should not be readable by others)
chmod 640 /path/to/project/.env
```

---

## Related Documentation

- **[DEPLOYMENT_CHECKLIST.md](../DEPLOYMENT_CHECKLIST.md)** - Complete deployment guide with verification steps
- **[docs/DEPLOYMENT_PATH_ISSUE.md](../docs/DEPLOYMENT_PATH_ISSUE.md)** - Fix path duplication issues in detail
- **[README.md](../README.md)** - Application installation and setup
- **[docs/APACHE_CONFIGURATION_FIX.md](../docs/APACHE_CONFIGURATION_FIX.md)** - Apache configuration troubleshooting

---

## Security Notes

⚠️ **After deployment:**

1. Delete or restrict access to these diagnostic files:
   - `check-deployment.php` (in root)
   - `deployment/fix-deployment-structure.php`
   - `public/setup-required.html`

2. Ensure `.env` file is not publicly accessible:
   ```bash
   chmod 640 .env
   ```

3. The root `.htaccess` already blocks access to `.env` files

---

**Last Updated:** November 14, 2025  
**Maintainer:** Eclectyc Energy Development Team
