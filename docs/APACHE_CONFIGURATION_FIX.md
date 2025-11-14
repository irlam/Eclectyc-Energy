# Apache Configuration Fix for 403 and 404 Errors

## Problem Description

The platform was experiencing two types of errors:

1. **403 Forbidden Error**: "Cannot serve directory /var/www/vhosts/.../public/: No matching DirectoryIndex"
2. **404 Not Found Error**: GET /ws/ws returning 404

## Root Cause Analysis

### 403 Error - Scenario 1: Misconfigured DocumentRoot
The 403 error can occur because the Apache DocumentRoot was incorrectly configured to point to a parent directory instead of the application's `public/` folder. When Apache tried to serve the root URL, it attempted to list the directory contents instead of serving `index.php`.

**Expected DocumentRoot**: `/httpdocs/eclectyc-energy/public/`  
**Actual DocumentRoot** (misconfigured): `/httpdocs/public/`

### 403 Error - Scenario 2: .htaccess Not Processed
Even with correct DocumentRoot, some hosting environments don't process `.htaccess` files properly, or the `AllowOverride` directive is set to `None`. In these cases, the `DirectoryIndex index.php` directive in `.htaccess` is ignored, and Apache falls back to its default DirectoryIndex list which may prioritize `index.html` over `index.php`.

**Solution**: An `index.html` file that redirects to `index.php` ensures Apache finds an index file regardless of `.htaccess` processing.

### 404 Error
The `/ws/ws` endpoint was being requested (likely by browser extensions or cached JavaScript) but was not defined in the application routes, causing a 404 error.

## Solution Implemented

### 1. Root-Level `.htaccess`

A new `.htaccess` file was added to the project root to handle requests when the DocumentRoot is misconfigured:

**File**: `/.htaccess`

**Features**:
- Automatically redirects requests to the `public/` subdirectory
- Disables directory browsing (`Options -Indexes`)
- Protects sensitive files (`.env`, `.json`, `.sql`, `.md`, etc.)
- Prevents access to hidden files (starting with `.`)

### 2. Enhanced `public/.htaccess`

The existing `public/.htaccess` was enhanced with:

**New Features**:
- Directory listing prevention (`Options -Indexes`)
- WebSocket upgrade request handling (routes to `index.php`)
- Custom error documents (403 and 404 routed through `index.php`)
- Better handling of undefined routes through the Slim application

### 3. Index.html Workaround (NEW)

An `index.html` file has been added to `public/` directory that immediately redirects to `index.php`. This ensures Apache can always find an index file, even if:
- `.htaccess` files are not being processed
- `DirectoryIndex` directives are ignored
- The server prioritizes `index.html` over `index.php`

**File**: `/public/index.html`

**How it works**:
- Uses meta refresh tag for immediate redirect
- Uses JavaScript for instant client-side redirect
- Provides a manual link as final fallback
- Zero delay - users won't notice the redirect

## Deployment Instructions

### For Existing Deployments

If you're experiencing these errors on a live server:

1. **Pull the latest code**:
   ```bash
   cd /path/to/eclectyc-energy
   git pull origin main
   ```

2. **Verify the new files exist**:
   ```bash
   ls -la .htaccess public/.htaccess public/index.html
   ```
   
   You should see:
   - `.htaccess` (root level)
   - `public/.htaccess` (with DirectoryIndex)
   - `public/index.html` (redirect workaround)

3. **Clear Apache cache** (if applicable):
   ```bash
   # For cPanel/Plesk
   # Usually not needed, but if issues persist, restart Apache:
   sudo systemctl restart apache2
   # or
   sudo service httpd restart
   ```

4. **Test the application**:
   - Visit: `https://yourdomain.com/`
   - Visit: `https://yourdomain.com/login`
   - Both should load without 403 errors

### For New Deployments

When deploying to a new server:

1. **Ensure proper DocumentRoot**:
   
   The Apache/Nginx DocumentRoot **must** point to the `public/` subdirectory:
   
   **Correct**: `/var/www/html/eclectyc-energy/public/`  
   **Incorrect**: `/var/www/html/eclectyc-energy/`
   
2. **In Plesk/cPanel**:
   - Go to "Hosting Settings" for your domain
   - Set Document Root to: `/httpdocs/eclectyc-energy/public`
   - Save changes

3. **Verify mod_rewrite is enabled**:
   ```bash
   # For Apache
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

4. **Check AllowOverride setting**:
   
   Ensure your Apache configuration allows `.htaccess` files:
   
   ```apache
   <Directory "/path/to/eclectyc-energy">
       AllowOverride All
       Require all granted
   </Directory>
   ```

## Testing the Fix

### 1. Test Root Access
```bash
curl -I https://yourdomain.com/
```

**Expected**: HTTP 200 or 302 (redirect to login)  
**Not**: HTTP 403

### 2. Test Login Page
```bash
curl -I https://yourdomain.com/login
```

**Expected**: HTTP 200  
**Not**: HTTP 403

### 3. Test Non-existent Routes
```bash
curl -I https://yourdomain.com/nonexistent
curl -I https://yourdomain.com/ws/ws
```

**Expected**: HTTP 404 (handled by NotFoundController)  
**Not**: Apache's default 404 page

### 4. Test Protected Files
```bash
curl -I https://yourdomain.com/.env
curl -I https://yourdomain.com/composer.json
```

**Expected**: HTTP 403 (denied by .htaccess)

## How It Works

### Request Flow

1. **Request arrives at Apache**
2. **Root `.htaccess` intercepts** (if DocumentRoot is parent directory)
   - Rewrites `/something` to `/public/something`
3. **Public `.htaccess` processes** (if request reaches public/)
   - Checks if file/directory exists → serve directly
   - Otherwise → route through `index.php`
4. **Slim Application handles** (in `public/index.php`)
   - Matches route → controller handles request
   - No match → NotFoundController renders 404 page

### WebSocket Handling

The `.htaccess` detects WebSocket upgrade requests and routes them through `index.php`:

```apache
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^(.*)$ index.php [L,QSA]
```

Currently, the application doesn't use WebSockets, so these requests will be handled by the NotFoundController. This provides a foundation for future WebSocket implementation if needed.

## Troubleshooting

### Still Getting 403 Errors?

1. **Check file permissions**:
   ```bash
   chmod 644 .htaccess
   chmod 644 public/.htaccess
   chmod 644 public/index.php
   ```

2. **Verify Apache can read .htaccess**:
   ```bash
   sudo apachectl configtest
   ```

3. **Check Apache error logs**:
   ```bash
   tail -f /var/log/apache2/error.log
   # or on Plesk
   tail -f /var/www/vhosts/system/yourdomain.com/logs/error_log
   ```

### Still Getting 404 for /ws/ws?

This is expected behavior now. The `/ws/ws` endpoint will return a proper 404 page handled by the application instead of Apache's default error page.

If you need WebSocket support:
1. Implement a WebSocket server (e.g., using Ratchet or Swoole)
2. Configure Apache/Nginx to proxy WebSocket requests
3. Add the route in `app/Http/routes.php`

### Directory Listing Still Showing?

If directory contents are still visible:

1. **Check for conflicting directives**:
   ```bash
   grep -r "Options.*Indexes" /etc/apache2/
   ```

2. **Ensure .htaccess is being read**:
   Add a syntax error temporarily to `.htaccess` and see if Apache throws an error when restarting.

## Security Improvements

The new `.htaccess` files provide several security enhancements:

1. **Directory Browsing Disabled**: Attackers cannot list directory contents
2. **Sensitive Files Protected**: `.env`, `.sql`, `.json` files are inaccessible
3. **Hidden Files Protected**: Files starting with `.` are denied
4. **Better Error Handling**: Custom 404 pages prevent information disclosure

## Related Documentation

- [DEPLOYMENT_CHECKLIST.md](../DEPLOYMENT_CHECKLIST.md) - Complete deployment guide
- [README.md](../README.md) - Installation instructions
- [docs/quick_start_import.md](quick_start_import.md) - Quick start guide

## Support

If you continue to experience issues:

1. Check the Apache error logs as shown above
2. Verify DocumentRoot configuration in Plesk/cPanel
3. Ensure mod_rewrite is enabled
4. Contact your hosting provider if AllowOverride is disabled

---

**Last Updated**: November 14, 2025  
**Status**: ✅ Production Ready
