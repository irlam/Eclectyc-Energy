# Eclectyc Energy Management Platform

**Self‑hosted energy intelligence for estates, construction & industry**

> A modern, production-ready PHP-based energy management platform with enterprise-grade features for consumption tracking, tariff analysis, carbon reporting, and intelligent automation.

**Last updated:** November 9, 2025  
**Status:** ✅ Production Ready  
**Deployment:** https://eclectyc.energy/

---

## 🌟 Platform Highlights

The Eclectyc Energy platform is a comprehensive solution for energy management that includes:

✨ **Real-time Monitoring** - Live dashboard with carbon intensity tracking  
⚡ **Smart Import System** - Web-triggered async CSV imports with progress tracking  
💰 **Tariff Intelligence** - Automated switching analysis with savings recommendations  
📊 **Advanced Analytics** - Consumption trends, baseload analysis, and year-over-year comparisons  
🤖 **AI-Powered Insights** - Intelligent recommendations using OpenAI, Anthropic, Google AI, or Azure OpenAI  
🔄 **Background Processing** - Queue-based import jobs with retry logic and monitoring  
📧 **Alerting & Notifications** - Email and Slack alerts for system events  
🌍 **Carbon Reporting** - Real-time UK grid carbon intensity integration  
🔒 **Enterprise Security** - Granular permission system, role-based access control, audit logging, and GDPR compliance  
📈 **Data Quality** - Automated quality checks, outlier detection, and gap analysis  
🚀 **Production Ready** - Complete with deployment configs, monitoring, and maintenance scripts

[📘 Complete Feature Showcase](docs/COMPLETE_GUIDE.md) | [🚀 Quick Start](docs/quick_start_import.md) | [⚙️ Deployment Guide](docs/operationalizing_async_systems.md) | [🤖 AI Insights](docs/ai_insights.md)


## System Requirements

- PHP >= 8.2
- MySQL 5.7+ or 8.0+
- Composer 2.x
- Apache/Nginx with mod_rewrite
- Plesk hosting environment (recommended)

## Installation Instructions

### 1. Database Setup

1. Log into your Plesk control panel
2. Navigate to "Databases" → "Add Database"
3. Create a new database for the platform (e.g. `k87747_eclectyc` on production)
4. Create a database user with full privileges
5. Note down the credentials (you'll need them for .env)

### 2. Upload Project Files

#### Method A: Via Plesk File Manager
1. Create a ZIP file of the entire project folder
2. Upload via Plesk File Manager to your domain directory
3. Extract the ZIP file
4. Ensure the folder structure is correct (public/ should be lowercase)

#### Method B: Via FTP/SFTP
1. Connect to your server using FTP credentials from Plesk
2. Upload all project files to your domain directory
3. Maintain the exact folder structure

### 3. Configure Environment

1. Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```

2. Edit `.env` file with your database credentials:
```
DB_HOST=localhost
DB_DATABASE=k87747_eclectyc
DB_USERNAME=your_db_user
DB_PASSWORD=your_actual_password
MIGRATION_KEY=replace_with_long_random_string

# Optional: Carbon Intensity API (National Grid ESO)
CARBON_API_URL=https://api.carbonintensity.org.uk
```

> `MIGRATION_KEY` secures the browser-triggered migration endpoint. Use a unique, high-entropy value for each environment and rotate it after use.

> The Carbon Intensity API is free and requires no API key. Set up automated data fetching using `scripts/setup_carbon_cron.sh` for real-time dashboard display.

### 4. Install Dependencies

Via Plesk SSH or Scheduled Tasks:
```bash
cd /path/to/eclectyc-energy
composer install --no-dev --optimize-autoloader
```

### 5. Set Document Root in Plesk

1. Go to "Hosting Settings" for your domain
2. Change Document Root to: `/httpdocs/eclectyc-energy/public`
3. Save changes

### 6. Set Permissions
```bash
chmod -R 755 /path/to/eclectyc-energy
chmod -R 777 /path/to/eclectyc-energy/logs
chmod 644 /path/to/eclectyc-energy/.env
```

### 7. Run Database Migrations
Run migrations via either method:

```bash
php scripts/migrate.php
```

Or through the browser once your `MIGRATION_KEY` is configured:

```
https://your-domain/scripts/migrate.php?key=YOUR_MIGRATION_KEY
```

Append `&seed=true` to load sample data when running from the browser.

### 8. Seed Database (Optional)
```bash
php scripts/seed.php
```

This loads sample readings for 30 Oct–06 Nov 2025 alongside precomputed daily, weekly, monthly, and annual aggregation rows so dashboards have data immediately.

### 9. Set Up Import Worker (Required for Async Imports)

**⚠️ IMPORTANT:** The import system requires a background worker to process queued jobs. Without this, import jobs will remain stuck in "QUEUED" status.

Add this cron job via Plesk Scheduled Tasks or crontab:

```cron
* * * * * cd /path/to/eclectyc-energy && /usr/bin/php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1
```

To verify the worker is set up correctly:

```bash
php scripts/check_import_setup.php
```

See the [Cron Job Setup section](#cron-job-setup-plesk) below for detailed instructions, or [Troubleshooting Guide](docs/TROUBLESHOOTING_IMPORTS.md) if you experience issues.

### Default Accounts & Roles

Running the seeder creates three demo operators, all with the temporary password `admin123`:

| Email | Role | Access |
| --- | --- | --- |
| `admin@eclectyc.energy` | admin | Full platform access, user management, tooling, all permissions |
| `manager@eclectyc.energy` | manager | Dashboard + reporting modules, most permissions except user management |
| `viewer@eclectyc.energy` | viewer | Dashboard only, read-only permissions |

Update these passwords immediately after seeding. Remove demo accounts in production environments.

**Granular Permissions (November 2025):**
The platform now includes a comprehensive permissions system that allows fine-grained control over user access to specific features. Permissions can be managed independently of roles, enabling custom access patterns for each user. See the User Management section below for details.

## Project Structure
```
eclectyc-energy/
├── app/                    # Application core
│   ├── Config/           # Configuration files
│   ├── Http/             # HTTP layer
│   │   ├── Controllers/  # Request controllers
│   │   └── routes.php    # Route definitions
│   ├── Domain/            # Business logic
│   │   ├── Ingestion/     # Data ingestion
│   │   ├── Aggregation/   # Data aggregation
│   │   ├── Tariffs/       # Tariff calculations
│   │   ├── Analytics/     # Analytics engine
│   │   └── Exports/       # Export handlers
│   ├── Models/           # Data models
│   └── views/             # Twig templates
├── database/              # Database files
│   ├── migrations/        # Schema migrations
│   └── seeds/            # Data seeders
├── public/               # Web root (IMPORTANT: Set as document root)
│   ├── index.php         # Application entry point
│   ├── router.php        # Built-in server router
│   └── assets/           # CSS/JS/Images
├── scripts/              # CLI utilities
├── tools/                # Development tools
├── logs/                 # Application logs
├── vendor/               # Composer dependencies
├── .env.example          # Environment template
└── composer.json         # Dependencies definition
```

## Available Tools

### Health Check
Visit: https://eclectyc.energy/api/health
- Multi-tier status (healthy/degraded/critical) with per-check metadata
- Database, filesystem, PHP, disk, and memory diagnostics
- Environment/SFTP configuration validation plus recent import/export activity timestamps

## API Endpoints

The platform provides RESTful API endpoints for integration and automation:

### Carbon Intensity API
- `GET /api/carbon-intensity` - Get current carbon intensity dashboard summary
- `POST /api/carbon-intensity/refresh` - Manually refresh data from National Grid ESO API
- `GET /api/carbon-intensity/history?days=7` - Get historical carbon intensity data

### System Health API  
- `GET /api/health` - System health check with detailed diagnostics

### Meters API
- `GET /api/meters` - List all meters
- `GET /api/meters/{mpan}/readings` - Get readings for specific meter

### Import Status API
- `GET /api/import/status` - Get import job status and progress

### Structure Checker
```bash
php tools/check-structure.php
```
Or visit: https://eclectyc.energy/tools/check

### Structure Viewer
Visit: https://eclectyc.energy/tools/show
- Visual tree of project structure
- Helps verify deployment

### Management Dashboards
- `/reports/consumption` (manager+) summarises site demand for the selected window.
- `/reports/costs` (manager+) estimates spend per supplier using tariff unit rates.
- `/admin/sites` (admin only) shows estates with meter counts and status.
- `/admin/tariffs` (admin only) lists configured supply tariffs including UK energy suppliers (British Gas, EDF, Octopus Energy, OVO Energy).
- `/admin/tariff-switching` (admin only) analyzes switching opportunities and recommends alternative tariffs based on consumption history with selectable current tariff.
- `/admin/ai-insights` (admin only) generates AI-powered insights and recommendations for energy optimization using OpenAI, Anthropic, Google AI, or Azure OpenAI.
- `/admin/users` (admin only) lists seeded accounts for quick role testing, with granular permission management for each user.
- `/admin/imports` (admin only) provides CSV uploads with optional dry-run previews, batch summaries, and optional default site/tariff assignment for imported meters.
- `/admin/imports/jobs` (admin only) shows all import jobs with real-time progress tracking and filtering.
- `/admin/imports/history` (admin only) lists recent ingestion runs with filters, decoded metadata, and surfaced errors.
- `/admin/exports` (admin only) tracks SFTP export jobs, delivery status, and failure messages.
- `/admin/meters` (admin only) allows you to add, view, and manage meters with pagination (10 meters per page by default).

## AI-Powered Energy Insights

The platform includes groundbreaking AI-powered insights that analyze your energy data and provide intelligent recommendations:

- **Consumption Pattern Analysis**: Identify daily, weekly, and seasonal trends with AI-driven analysis
- **Cost Optimization**: Get personalized recommendations for reducing energy costs
- **Anomaly Detection**: Automatically detect unusual consumption patterns and potential equipment issues
- **Carbon Reduction Strategies**: AI-suggested approaches to reduce your carbon footprint
- **Predictive Maintenance**: Anticipate equipment problems before they occur
- **Multi-Provider Support**: Choose from OpenAI (GPT-4), Anthropic (Claude), Google AI (Gemini), or Azure OpenAI
- **Privacy-Focused**: Only aggregated statistics are sent to AI providers, never raw meter readings
- **Cost-Effective**: Starting from ~$0.001 per insight with Google Gemini

**Setup**: Simply add your preferred AI provider's API key to the `.env` file and restart your web server. See [docs/ai_insights.md](docs/ai_insights.md) for detailed setup instructions.

**Access**: Navigate to `/admin/ai-insights` to start generating intelligent insights for your meters.

## Tariff Switching Analysis

The platform includes comprehensive tariff switching analysis capabilities:

- **Compare Tariffs**: Analyze consumption history against all available tariffs
- **Calculate Savings**: Identify potential cost savings by switching suppliers/tariffs
- **Historical Tracking**: Save and review past switching analyses
- **Detailed Breakdowns**: View unit costs, standing charges, and total costs side-by-side
- **Time-of-Use Support**: Handles peak/off-peak/weekend rate structures
- **Selectable Current Tariff**: Choose the tariff currently applied to each meter for accurate comparison
- **UK Supplier Tariffs**: Pre-loaded with Q4 2024 tariffs from British Gas, EDF Energy, Octopus Energy, and OVO Energy based on Ofgem price cap

See `docs/tariff_switching_analysis.md` for detailed documentation on using the tariff switching feature.

## Import Features

### CSV Import Capabilities
- **Flexible Column Mapping**: Recognizes common column name variations (MPAN, MeterCode, ReadDateTime, etc.)
- **Dry Run Mode**: Validate CSV files without saving data to the database
- **Background Processing**: Queue large imports to run asynchronously
- **Real-time Progress Tracking**: Monitor import jobs at `/admin/imports/jobs`
- **Optional Defaults**: Assign imported meters to a default site and/or tariff
- **Pagination**: Meters page shows 10 meters per page (configurable to 10/25/50/100)

### File Storage Location
Uploaded CSV files are stored in `storage/imports/` directory when using async imports.

**For Plesk hosting users:** Files are accessible via File Manager at:
- Path: `/httpdocs/eclectyc-energy/storage/imports/`
- Navigate: File Manager → your domain → eclectyc-energy → storage → imports

See `storage/README.md` for detailed information about file storage and troubleshooting.

## Getting Started with Data Import

New to the platform? Start here:

1. **Quick Start Guide:** `docs/quick_start_import.md`
   - Step-by-step first import walkthrough
   - Site and meter setup
   - Example CSV templates
   - Verification steps

2. **Troubleshooting Guide:** `docs/import_troubleshooting.md`
   - Common error solutions
   - CSV format requirements
   - Accepted column names reference
   - Best practices

## CLI Scripts

### Import CSV Data (CLI + Admin UI)
```bash
php scripts/import_csv.php -f /path/to/readings.csv -t hh
```
Run from the project root so the autoloader resolves; switch `-t` to `daily` for single-value totals. The CLI importer supports `--dry-run`/`-n` validation, assigns a UUID batch ID, and upserts rows in `meter_readings`.

- **Flexible headers**: `CsvIngestionService` recognises aliases such as `MeterCode`, `ReadDateTime`, `ReadValue`, and common unit labels, so third-party CSVs import without manual renaming.
- **Streaming progress bar**: the CLI counts rows up front (when possible) and renders a live ASCII progress bar showing processed/imported rows and warnings. For very large files you can skip the preview count by precomputing totals (e.g. `wc -l file.csv`).
- **Admin uploader parity**: the same alias-aware ingestion and progress metadata power `/admin/imports`, which now receives richer batch summaries.

The same ingestion service powers the admin console at `/admin/imports`, giving administrators a browser-based uploader with dry-run support and flash summaries covering processed/imported/failed rows plus sample errors. The web interface provides:
- Interactive help with accepted column names
- Real-time file size preview
- Detailed error messages with solutions
- Links to meter management for missing MPANs
- Progress indicators and status updates

#### Web-Triggered Background Imports (NEW)
The platform now supports asynchronous, web-triggered CSV imports that can run in the background:

- Upload CSV files through `/admin/imports` with the "Process in background" option
- Close the browser and track progress later at `/admin/imports/jobs`
- Real-time progress updates with auto-refresh
- Multiple imports can be queued and processed sequentially
- Background worker processes jobs: `php scripts/process_import_jobs.php`

See `docs/web_triggered_import.md` for detailed documentation on setup and usage.

### Process Import Jobs (Background Worker)
```bash
# Run continuously to process queued imports
php scripts/process_import_jobs.php

# Process jobs once and exit (suitable for cron)
php scripts/process_import_jobs.php --once

# Limit number of jobs per iteration
php scripts/process_import_jobs.php --limit=5
```
This background worker processes CSV import jobs that are queued via the web interface. Required for async imports to work. Can be run as a cron job (recommended) or as a long-running process with supervisord.

**Enhanced Features:**
- Automatic retry with exponential backoff (up to 3 retries by default)
- Failure alerting via email and Slack
- Priority-based job processing
- Rich batch attribution (notes, tags, metadata)

### Monitor Import System
```bash
# Check system health
php scripts/monitor_import_system.php --verbose

# Automatically handle stuck jobs and send alerts
php scripts/monitor_import_system.php --handle-stuck --send-alerts
```
Monitors the import system for:
- Stuck jobs (processing for >60 minutes)
- High failure rates
- Queue backlogs
- Performance metrics and retry statistics

### Cleanup Import Jobs
```bash
# Clean up jobs older than 30 days
php scripts/cleanup_import_jobs.php --days 30 --verbose

# Dry run to preview what would be deleted
php scripts/cleanup_import_jobs.php --days 30 --dry-run
```
Removes old completed, failed, and cancelled import jobs based on retention policy. Also cleans up orphaned CSV files.

### Run Aggregations
```bash
# Run all ranges (daily, weekly, monthly, annual) relative to a date
php scripts/aggregate_cron.php --all --date 2025-11-06 --verbose

# Convenience wrappers for single ranges (default date = yesterday)
php scripts/aggregate_daily.php --date 2025-11-06
php scripts/aggregate_weekly.php --date 2025-11-06
php scripts/aggregate_monthly.php --date 2025-11-01
php scripts/aggregate_annual.php --date 2025-01-01
```
Each script logs to `audit_logs` and reuses the shared aggregation helper, so you can schedule individual frequencies or run everything in one hit.

### Enhanced Orchestrated Aggregation (Recommended)
```bash
# Run all ranges with orchestration, telemetry, and failure alerts
php scripts/aggregate_orchestrated.php --all --verbose

# Run specific range with monitoring
php scripts/aggregate_orchestrated.php --range daily --date 2025-11-06
```
The orchestrated aggregation provides:
- Automatic telemetry tracking in `scheduler_executions` table
- Email alerts for failures and warnings (configured via `ADMIN_EMAIL`)
- Detailed execution metrics (duration, meters processed, errors)
- Audit trail for compliance and debugging

### Data Quality Checks
```bash
# Run data quality checks for all active meters
php scripts/run_data_quality_checks.php --verbose

# Check specific date or meter
php scripts/run_data_quality_checks.php --date 2025-11-06 --meter 123
```
Detects missing data, anomalies, outliers, and negative/zero readings. Results are stored in `data_quality_issues` table.

### External Data Import
```bash
# Import temperature data
php scripts/import_external_data.php -t temperature -f data/temp.csv -l London

# Import calorific values (for gas)
php scripts/import_external_data.php -t calorific -f data/cv.csv -r UK_SE

# Import carbon intensity data
php scripts/import_external_data.php -t carbon -f data/carbon.csv -r GB
```
Integrates external datasets for enhanced analytics and carbon reporting. See `docs/analytics_features.md` for CSV formats.

### Export via SFTP
```bash
php scripts/export_sftp.php -t daily -d 2025-11-05 -f csv
```
Configure `SFTP_HOST`, `SFTP_PORT`, `SFTP_USERNAME`, and either `SFTP_PASSWORD` or `SFTP_PRIVATE_KEY` (plus optional `SFTP_PASSPHRASE`) and `SFTP_PATH` in `.env`. The exporter now performs live uploads via phpseclib, creates remote directories when missing, and records outcomes in the `exports` table. If credentials are incomplete it retains the file locally and reports the warning.

## Cron Job Setup (Plesk)

1. Go to "Scheduled Tasks" in Plesk
2. Add a nightly orchestrated aggregation task (e.g. 01:30) - **Recommended**:
   - Command: `/usr/bin/php /path/to/eclectyc-energy/scripts/aggregate_orchestrated.php --all --verbose`
   - Schedule: `30 1 * * *`
3. Add a daily data quality check task (e.g. 02:00):
   - Command: `/usr/bin/php /path/to/eclectyc-energy/scripts/run_data_quality_checks.php --verbose`
   - Schedule: `0 2 * * *`
4. Add import job processor (e.g. every minute) - **Required for async imports**:
   - Command: `/usr/bin/php /path/to/eclectyc-energy/scripts/process_import_jobs.php --once`
   - Schedule: `* * * * *`
5. Add import system monitoring (e.g. every 15 minutes) - **Recommended**:
   - Command: `/usr/bin/php /path/to/eclectyc-energy/scripts/monitor_import_system.php --handle-stuck --send-alerts`
   - Schedule: `*/15 * * * *`
6. Add import job cleanup (e.g. weekly on Sundays at 02:00) - **Recommended**:
   - Command: `/usr/bin/php /path/to/eclectyc-energy/scripts/cleanup_import_jobs.php --days 30 --verbose`
   - Schedule: `0 2 * * 0`
7. Optional: add additional tasks for dedicated ranges or exports, for example:
   - Legacy aggregation: `/usr/bin/php /path/to/eclectyc-energy/scripts/aggregate_cron.php --all --verbose`
   - Weekly roll-up every Monday at 02:15: `/usr/bin/php /path/to/eclectyc-energy/scripts/aggregate_weekly.php`
   - Daily SFTP export once credentials are wired: `/usr/bin/php /path/to/eclectyc-energy/scripts/export_sftp.php -t daily`
8. Ensure each task uses the same PHP version as the site and inherits the correct `.env` file (set `Working directory` to the project root).

**Note:** The orchestrated aggregation script (`aggregate_orchestrated.php`) provides enhanced monitoring, telemetry, and failure alerts. Use it instead of `aggregate_cron.php` for production deployments.

**Alternative Deployment:** For VPS/dedicated servers, consider using Supervisor or Systemd for the import worker instead of cron. See `docs/operationalizing_async_systems.md` for detailed instructions.

**Troubleshooting:** If imports are stuck in QUEUED status or not processing, see the [Import Troubleshooting Guide](docs/TROUBLESHOOTING_IMPORTS.md) for diagnosis and solutions.

## Security Considerations

- **NEVER** commit `.env` file to version control
- Keep sensitive data within project boundaries (GDPR compliance)
- Regularly update dependencies: `composer update`
- Monitor logs for suspicious activity
- Use HTTPS only (configured in Plesk)
- Rotate `MIGRATION_KEY` after each migration and keep it secret

## GDPR Compliance

This platform is designed with GDPR in mind:
- All data stays within project boundaries
- No external data sharing by default
- Audit logging for data access
- User consent tracking capabilities
- Data export functionality

## Data Aggregation & Analytics

The platform now includes comprehensive data aggregation and analytics features:

- **Automated Orchestration**: Scheduler with telemetry tracking and failure alerts
- **Comparison Snapshots**: Day/week/month/year-over-year analysis
- **Baseload Analytics**: Identify minimum constant load and optimization opportunities
- **Missing Data Detection**: Automated quality checks with outlier detection
- **External Datasets**: Temperature, calorific values, and carbon intensity integration
- **Carbon Reporting**: Calculate emissions based on consumption and grid intensity
- **Real-time Carbon Intensity**: Live UK grid carbon intensity data from National Grid ESO API
  - Real-time dashboard display with color-coded classifications
  - Automated data fetching every 30 minutes
  - Carbon intensity trending and forecasting
  - API endpoints for integration and manual refresh

See `docs/analytics_features.md` and `docs/carbon_intensity_implementation.md` for detailed documentation.

## User Management & Permissions (November 2025)

The platform includes a comprehensive granular permissions system that provides fine-grained access control beyond basic roles:

### Permission Categories
- **Imports**: View, upload, manage jobs, retry failed imports
- **Exports**: View and create exports
- **Users**: View, create, edit, delete users, manage permissions
- **Meters**: View, create, edit, delete meters, view carbon intensity
- **Sites**: View, create, edit, delete sites
- **Tariffs**: View, create, edit, delete tariffs
- **Tariff Switching**: View analysis, perform analysis, view history
- **Reports**: View reports, consumption reports, cost reports
- **Settings**: View and edit system settings
- **Tools**: View tools, system health, SFTP management, logs
- **Dashboard**: General dashboard access

### Managing User Permissions
1. Navigate to `/admin/users`
2. Create or edit a user
3. Select permissions by category
4. Roles still apply but can be customized with specific permissions
5. Admin users have all permissions by default

### Permission Inheritance
- **Admin role**: Automatically has all permissions
- **Manager role**: Has most permissions except user deletion and sensitive settings
- **Viewer role**: Has read-only permissions (view-only access)

### Database Schema
New tables added in migration `009_create_user_permissions.sql`:
- `permissions` table: Defines all available permissions
- `user_permissions` table: Junction table for user-permission relationships

## Bug Fixes (November 2025)

### Fixed Issues
1. **Import Job Deletion Error (SQLSTATE[HY093])**: Fixed SQL parameter binding issue in `ImportController::deleteJob()` method where the `:batch_id` parameter was used twice in a nested query but only bound once. The fix uses separate parameter names (`:batch_id` and `:batch_id2`) for each occurrence.

2. **Tariff Switching Confirmation**: Verified that the tariff switching analysis feature correctly recommends better tariffs based on consumption history. The system:
   - Analyzes all alternative tariffs for the meter's energy type
   - Calculates costs using actual consumption data
   - Ranks alternatives by potential savings (highest first)
   - Only recommends tariffs that provide actual cost savings

## Troubleshooting

### Case Sensitivity Issues
Linux servers are case-sensitive. Ensure:
- `public/` not `Public/`
- `vendor/` not `Vendor/`
Run `php tools/check-structure.php` to verify

### Updating Autoloading After Structural Changes
When adding new namespaced classes or moving directories, follow these steps to keep Composer’s PSR-4 autoloader and the production host in sync:

1. **Match directory casing**: Namespace segments must mirror directory names (e.g. `App\Models` → `app/Models`). Rename any lowercase folders (`app/models`, `app/http`, `app/config`, etc.) so they match the namespace exactly.
2. **Update internal references**: Adjust includes and tooling that reference the old paths, such as `public/index.php` (`require BASE_PATH . '/app/Http/routes.php';`) and `tools/check-structure.php`.
3. **Regenerate the autoloader**:
   ```bash
   composer dump-autoload --optimize
   # or, in a chrooted Plesk shell:
   php /composer.phar dump-autoload --optimize
   ```
4. **Restart PHP/OPcache**: In Plesk, go to **Domains → eclectyc.energy → PHP Settings → Reload/Restart PHP** so the runtime picks up the new class map.
5. **Verify endpoints**: Hit `https://eclectyc.energy/api/health` (or run `php tools/health.php`) to confirm controllers and middleware resolve correctly.

Keeping this checklist handy avoids “Class not found” errors after refactors or deployments.

### 500 Errors
1. Check `.env` file exists and is readable
2. Verify database credentials
3. Check error logs in `logs/` directory
4. Ensure `vendor/` directory exists (run `composer install`)

### Database Connection Issues
1. Verify MySQL is running
2. Check credentials in `.env`
3. Ensure database exists
4. Try `127.0.0.1` instead of `localhost`

## Development Roadmap

- [ ] Harden authentication flow (session regeneration, throttling, password reset)
- [ ] Extend role matrix to APIs and non-admin surfaces
- [ ] Implement Sites/Meters/Tariffs CRUD in UI and API
- [ ] Operationalise ingestion pipeline (add background queueing, monitoring, and alerting for long-running jobs)
- [ ] Complete reporting dashboards with charts and drill-downs
- [ ] Add API validation and authentication (tokens/JWT)
- [ ] Expand automated tests, fixtures, and seed data coverage
- [ ] Polish front-end interactivity (AJAX filters, visualisations)
- [ ] Harden deployment scripts, SFTP integration, and monitoring

- [ ] AI-powered reporting layer (Python integration)
- [ ] Advanced tariff engine
- [ ] Real-time data streaming
- [ ] Mobile application API
- [ ] Multi-tenancy support
- [ ] Advanced user roles and permissions

See also `docs/product_requirements.md` for a capability-by-capability gap analysis sourced from early product discovery.

## Inspiration & References

This platform draws architectural inspiration from:
- **OpenEMS**: https://github.com/OpenEMS/openems (Edge energy management)
- **MyEMS**: https://github.com/MyEMS/myems (Enterprise energy management)
- **OpenRemote**: https://github.com/openremote/openremote (IoT platform)
- **OpenEnergyDashboard**: https://github.com/OpenEnergyDashboard/OED (Energy visualisation)
- **BEMServer**: https://github.com/BEMServer (Building energy management)
- **BEMOSS**: https://github.com/bemoss/BEMOSS3.5 (Open-source BMS)

## Support

For deployment assistance or questions, consult:
- Plesk documentation: https://docs.plesk.com/
- PHP documentation: https://www.php.net/manual/
- Slim Framework: https://www.slimframework.com/docs/

## License

Proprietary - All rights reserved Eclectyc Energy 2024