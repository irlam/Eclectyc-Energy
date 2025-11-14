# Deployment Path Issue: public/public Duplication

## Quick Diagnostic

**Are you experiencing this issue?** Check for these symptoms:

1. ✗ Apache error logs show `/public/public/index.php` in the path
2. ✗ Error: `Failed to open vendor/autoload.php`
3. ✗ 403 Forbidden or 500 Internal Server Error
4. ✗ "No matching DirectoryIndex" error

**Quick Check (via web):** Access `https://yourdomain.com/check-deployment.php`

**Quick Check (via SSH):**
```bash
cd /path/to/httpdocs  # or wherever DocumentRoot parent is
ls -la
# You should see: public/, app/, vendor/, composer.json
# You should NOT see: public/public/
```

If you see `public/public/`, follow the fix instructions below.

---

## Problem Description

If you're seeing errors like these in your Apache error logs:

```
PHP Warning: require(/var/www/.../httpdocs/public/vendor/autoload.php): Failed to open stream: No such file or directory in /var/www/.../httpdocs/public/public/index.php on line 75
```

Notice the **duplicate `public/public`** in the path. This indicates the project files were uploaded to the wrong location.

## Root Cause

The project was uploaded to the `public/` directory when it should have been uploaded to the parent directory (`httpdocs/` or equivalent).

### Incorrect Deployment Structure

```
/var/www/vhosts/yourdomain.com/
  httpdocs/
    public/              ← DocumentRoot (correct)
      .htaccess          ← ROOT .htaccess (WRONG LOCATION!)
      public/            ← Actual public folder (WRONG LOCATION!)
        index.php
        assets/
      app/
      vendor/
      composer.json
      ...
```

### Correct Deployment Structure

```
/var/www/vhosts/yourdomain.com/
  httpdocs/              ← Project root (correct)
    .htaccess            ← Root .htaccess (correct)
    public/              ← DocumentRoot should point here
      .htaccess
      index.php
      assets/
    app/
    vendor/
    composer.json
    ...
```

## How to Fix

### Option 1: Move Files (Recommended)

1. **Connect to your server via SSH or FTP**

2. **Navigate to the hosting directory**:
   ```bash
   cd /var/www/vhosts/yourdomain.com/httpdocs
   ```

3. **Create a temporary directory**:
   ```bash
   mkdir ../temp_project
   ```

4. **Move all files from public/ to temp**:
   ```bash
   mv public/* ../temp_project/
   mv public/.* ../temp_project/ 2>/dev/null || true
   ```

5. **Remove empty public directory**:
   ```bash
   rmdir public
   ```

6. **Move files back to httpdocs**:
   ```bash
   mv ../temp_project/* .
   mv ../temp_project/.* . 2>/dev/null || true
   rmdir ../temp_project
   ```

7. **Verify structure**:
   ```bash
   ls -la
   # Should show: public/, app/, vendor/, composer.json, etc.
   ```

8. **Update DocumentRoot in Plesk/cPanel**:
   - Go to "Hosting Settings"
   - Set Document Root to: `/httpdocs/public`
   - NOT: `/httpdocs/public/public`
   - Save changes

### Option 2: Re-upload Files (Alternative)

1. **Delete all files in httpdocs/public/**:
   - Via FTP or File Manager
   - Keep the `public/` folder itself empty

2. **Upload project files to httpdocs/** (not httpdocs/public/):
   - Upload `app/`, `public/`, `vendor/`, `composer.json`, etc.
   - To the `httpdocs/` directory
   
3. **Verify the structure**:
   ```
   httpdocs/
     public/         ← This should exist
       index.php
       assets/
     app/
     vendor/
   ```

4. **Set DocumentRoot**:
   - In Plesk: "Hosting Settings" → Document Root: `/httpdocs/public`

## Verification

After fixing, verify the structure:

```bash
# Connect via SSH
cd /var/www/vhosts/yourdomain.com/httpdocs

# Check structure
ls -la
# Should show: public/, app/, vendor/, composer.json, .env, etc.

# Check public folder
ls -la public/
# Should show: index.php, assets/, .htaccess, router.php

# Check vendor exists
ls -la vendor/
# Should show: autoload.php, composer/, etc.
```

## Test the Fix

1. **Visit your website**:
   ```
   https://yourdomain.com/
   ```
   Should load without errors

2. **Check for vendor/autoload.php**:
   ```bash
   php -r "require __DIR__ . '/vendor/autoload.php'; echo 'Autoload OK\n';"
   ```

3. **Check error logs**:
   - Should NOT contain `public/public` in paths
   - Should NOT contain "Failed to open stream" for vendor/autoload.php

## Prevention

To prevent this issue in future deployments:

1. **Always verify the upload location**:
   - Upload to `httpdocs/` or `public_html/` (root)
   - NOT to `httpdocs/public/`

2. **Set DocumentRoot correctly**:
   - Point to the `public/` subdirectory
   - Example: `/httpdocs/public`

3. **Use deployment checklist**:
   - Follow [DEPLOYMENT_CHECKLIST.md](../DEPLOYMENT_CHECKLIST.md)
   - Verify structure before testing

## Related Issues

This issue causes:
- ✗ 403 Forbidden errors
- ✗ 500 Internal Server errors  
- ✗ "No matching DirectoryIndex" errors
- ✗ "Failed to open vendor/autoload.php" errors
- ✗ Application not loading at all

## See Also

- [DEPLOYMENT_CHECKLIST.md](../DEPLOYMENT_CHECKLIST.md) - Complete deployment guide
- [APACHE_CONFIGURATION_FIX.md](APACHE_CONFIGURATION_FIX.md) - Apache configuration issues
- [README.md](../README.md) - Installation instructions

---

**Last Updated**: November 14, 2025  
**Status**: Common deployment issue - follow this guide to resolve
