# Environment Configuration Guide

## Overview

The Eclectyc Energy platform now includes a comprehensive GUI-based configuration system that allows administrators to manage all environment settings without needing SSH/FTP access or manual .env file editing.

## Features

### 1. Environment Configuration Page
**URL:** `/admin/env-config`

This page provides a complete interface for managing all environment variables in your `.env` file.

#### Key Features:
- **40+ Settings Organized by Category**
- **Automatic Backups** before every save
- **Permission Checking** to ensure file is writable
- **Smart Field Types**: 
  - Text inputs
  - Password fields (masked)
  - Boolean toggles
  - Dropdown selects
  - Number inputs
  - Email validation
  - URL validation

#### Categories:
1. **Application** - ENV, DEBUG, URL, TIMEZONE
2. **Database** - Connection settings
3. **Logging** - Log level and path
4. **Email** - SMTP configuration
5. **SFTP Export** - SFTP server settings
6. **Session** - Session security settings
7. **API Keys** - General API credentials
8. **Alerts & Monitoring** - Health check settings
9. **External APIs** - Weather and Carbon Intensity APIs
10. **AI Insights** - All AI provider settings

### 2. System Settings (Database-Backed)
**URL:** `/tools/settings`

For settings that should be stored in the database (recommended for dynamic settings):
- Import throttling settings
- AI Insights configuration (NEW)

#### AI Insights Settings:
- `ai_provider` - Select provider (openai, anthropic, google, azure)
- `ai_openai_api_key` - OpenAI API key
- `ai_openai_model` - Model name (default: gpt-4o-mini)
- `ai_anthropic_api_key` - Anthropic API key
- `ai_anthropic_model` - Model name (default: claude-3-5-sonnet-20241022)
- `ai_google_api_key` - Google AI API key
- `ai_google_model` - Model name (default: gemini-pro)
- `ai_azure_api_key` - Azure OpenAI API key
- `ai_azure_endpoint` - Azure endpoint URL
- `ai_azure_model` - Azure model deployment name

## How to Use

### Configure AI Insights

**Method 1: System Settings (Recommended - No Restart)**
1. Navigate to `/tools/settings`
2. Scroll to the "AI Insights" category
3. Select your preferred provider in the `ai_provider` dropdown
4. Enter your API key for that provider
5. (Optional) Customize the model name
6. Click "Save Settings"
7. Changes are effective immediately!

**Method 2: Environment Configuration**
1. Navigate to `/admin/env-config`
2. Scroll to the "AI Insights" category
3. Enter settings for your preferred provider
4. Click "Save All Changes"
5. A backup is created automatically
6. Changes take effect on next page load

### Update Database Settings

⚠️ **Warning:** Be careful when changing database settings!

1. Navigate to `/admin/env-config`
2. Scroll to the "Database" category
3. Update `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, or `DB_PASSWORD`
4. Click "Save All Changes"
5. The application will reconnect to the database

### Configure Email (SMTP)

1. Navigate to `/admin/env-config`
2. Scroll to the "Email" category
3. Enter your SMTP server details:
   - `MAIL_HOST` - SMTP server hostname
   - `MAIL_PORT` - Usually 587 for TLS or 465 for SSL
   - `MAIL_USERNAME` - SMTP username
   - `MAIL_PASSWORD` - SMTP password
   - `MAIL_ENCRYPTION` - Select tls, ssl, or none
   - `MAIL_FROM_ADDRESS` - Default sender email
   - `MAIL_FROM_NAME` - Default sender name
4. Click "Save All Changes"

### Setup SFTP Export

1. Navigate to `/admin/env-config`
2. Scroll to the "SFTP Export" category
3. Configure:
   - `SFTP_HOST` - SFTP server hostname
   - `SFTP_PORT` - Usually 22
   - `SFTP_USERNAME` - SFTP username
   - `SFTP_PASSWORD` - SFTP password (or use key auth)
   - `SFTP_PATH` - Remote path for exports
   - `SFTP_PRIVATE_KEY` - (Optional) Path to SSH private key
   - `SFTP_PASSPHRASE` - (Optional) SSH key passphrase
   - `SFTP_TIMEOUT` - Connection timeout in seconds
4. Click "Save All Changes"

## Backup and Recovery

### Automatic Backups
Every time you save changes via `/admin/env-config`, an automatic backup is created:
- Location: Same directory as `.env`
- Format: `.env.backup.YYYY-MM-DD_HH-mm-ss`
- Example: `.env.backup.2025-11-12_19-30-45`

### Manual Backup
1. Go to `/admin/env-config`
2. Click "Download Backup" button in the header
3. Save the file to your computer

### Restore from Backup
If something goes wrong:
1. Access your server via SSH/FTP
2. Rename the backup file to `.env`
```bash
cp .env.backup.2025-11-12_19-30-45 .env
```

## Permissions

### Required Permissions
The `.env` file must be writable by the web server:
```bash
chmod 664 .env
chown www-data:www-data .env  # Adjust user/group as needed
```

### Test Permissions
1. Go to `/admin/env-config`
2. Click "Test Permissions" button
3. View the results:
   - File path
   - Permissions (should be 0664)
   - Owner
   - Group
   - Writable status

## Security

### Access Control
- All configuration pages are admin-only
- Regular users cannot view or modify settings
- Enforced by existing AuthMiddleware

### Sensitive Data
- Password fields are masked with `type="password"`
- API keys are never logged in plain text
- Database credentials are protected
- Backups preserve original permissions

### Best Practices
1. **Use Strong Passwords**: For database, email, and API keys
2. **Limit Admin Access**: Only trusted users should have admin role
3. **Keep Backups**: Download backups before major changes
4. **Monitor Logs**: Check `/logs/app.log` for any issues
5. **Production Mode**: Set `APP_ENV=production` and `APP_DEBUG=false` in production

## Troubleshooting

### .env File Not Writable
**Error:** "File is NOT writable. Please check permissions."

**Solution:**
```bash
cd /path/to/eclectyc-energy
chmod 664 .env
chown www-data:www-data .env
```

### Changes Not Taking Effect
**For .env changes:**
- Most changes require page reload
- Some changes (like database) require reconnection
- Try clearing browser cache
- Restart PHP-FPM if needed:
  ```bash
  sudo systemctl restart php-fpm
  # or
  sudo service apache2 restart
  ```

**For System Settings (database) changes:**
- Changes are immediate
- No restart required
- Just reload the page

### Import Deletion Issues
If batch `758ca034...` or other imports won't delete:
1. Try deleting via `/admin/imports/history`
2. Check `/logs/app.log` for detailed errors
3. Look for the batch_id in log messages
4. Errors now include:
   - Entry ID
   - Batch ID
   - PDO error details
   - Deletion counts

## AI Insights Priority

The AI service checks for configuration in this order:
1. **System Settings (database)** - `/tools/settings`
2. **Environment Variables (.env)** - `/admin/env-config`

This means database settings take precedence. You can:
- Use database for easy switching between providers
- Use .env for permanent configuration
- Mix both (database overrides .env)

## Support

If you encounter any issues:
1. Check file permissions
2. Review `/logs/app.log`
3. Test with "Test Permissions" button
4. Download a backup before making changes
5. Contact support with specific error messages

---

**Last Updated:** November 12, 2025  
**Version:** 1.0.0
