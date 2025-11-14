# Quick Deployment Fix Guide

If you're seeing errors like these on your production server:

```
Error: Failed to open vendor/autoload.php
Error: public/public/index.php in path
Error: No matching DirectoryIndex
```

## Quick Fix (2 minutes)

### Option 1: Via SSH

```bash
# 1. SSH into your server
ssh user@yourserver.com

# 2. Navigate to project directory
cd /var/www/vhosts/yourdomain.com/httpdocs

# 3. Run the installer
bash deployment/install-dependencies.sh

# 4. Verify everything is OK
php deployment/fix-deployment-structure.php

# 5. Reload your website - it should now work!
```

### Option 2: Via Web Browser (if SSH not available)

1. Upload all files to your server (if not already done)
2. Access: `https://yourdomain.com/deployment/fix-deployment-structure.php?allow`
3. Follow the instructions shown
4. Access: `https://yourdomain.com/check-deployment.php?allow`

## Common Issues

### "public/public" Error
Files were uploaded to wrong directory. 

**Fix:** See [docs/DEPLOYMENT_PATH_ISSUE.md](docs/DEPLOYMENT_PATH_ISSUE.md)

### "vendor/autoload.php not found"
Dependencies not installed.

**Fix:** Run `bash deployment/install-dependencies.sh`

### "No matching DirectoryIndex"
DocumentRoot not pointing to `public/` directory.

**Fix:** In Plesk/cPanel, set DocumentRoot to `/path/to/project/public`

## Complete Guides

- **[DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)** - Full deployment guide
- **[deployment/README.md](deployment/README.md)** - All deployment scripts explained
- **[docs/DEPLOYMENT_PATH_ISSUE.md](docs/DEPLOYMENT_PATH_ISSUE.md)** - Path issue detailed fix

## Need Help?

All diagnostic tools are in the `deployment/` directory:
- `deployment/install-dependencies.sh` - Install dependencies
- `deployment/fix-deployment-structure.php` - Diagnose issues
- `deployment/README.md` - Complete documentation

---

**TL;DR:** Run `bash deployment/install-dependencies.sh` then `php deployment/fix-deployment-structure.php`
