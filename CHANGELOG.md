# Eclectyc Energy Platform - Changelog

This file tracks significant changes, implementations, and fixes to the Eclectyc Energy Platform.

## November 2025

### Major Features Implemented

#### User Permissions System (Nov 9, 2025)
- Implemented granular permission system with 30+ distinct permissions
- Permission categories: Imports, Exports, Users, Meters, Sites, Tariffs, Reports, Settings, Tools, Dashboard
- User permission management UI at `/admin/users`
- Database migrations: `009_create_user_permissions.sql`
- Backward compatible with existing role system

#### Async Import System Operationalization (Nov 7, 2025)
- Web-triggered background CSV imports
- Import job queue with priority support
- Real-time progress tracking with auto-refresh
- Background worker: `scripts/process_import_jobs.php`
- Automatic retry with exponential backoff (up to 3 retries)
- Failure alerting via email and Slack
- Enhanced monitoring and stuck job handling
- Cleanup jobs for old import records

#### Tariff Switching Analysis (Nov 7, 2025)
- Compare consumption against all available tariffs
- Calculate potential savings by switching suppliers
- Historical tracking of analyses
- Support for time-of-use rate structures
- Pre-loaded UK supplier tariffs (British Gas, EDF, Octopus, OVO)
- Selectable current tariff for accurate comparison

#### Data Aggregation & Analytics (Nov 7, 2025)
- Orchestrated aggregation with telemetry tracking
- Scheduler execution monitoring in `scheduler_executions` table
- Automated failure alerts and warnings
- Data quality checks with outlier detection
- Comparison snapshots (day/week/month/year-over-year)
- Baseload analytics for optimization opportunities
- External data integration (temperature, calorific values, carbon intensity)

#### Carbon Intensity Integration (Nov 2025)
- Real-time UK grid carbon intensity from National Grid ESO API
- Live dashboard display with color-coded classifications
- Automated data fetching every 30 minutes
- Carbon intensity trending and forecasting
- API endpoints: `/api/carbon-intensity`, `/api/carbon-intensity/refresh`

#### Alarms and Scheduled Reports (Nov 2025)
- Configurable alarm thresholds for meters
- Email and Slack notifications
- Scheduled report generation (daily, weekly, monthly)
- Report delivery via email and SFTP
- Alert acknowledgment system

#### Hierarchical Access Control (Nov 2025)
- Organization-based access control
- User assignment to organizations
- Hierarchical filtering for sites, meters, tariffs
- Dashboard widgets with access-controlled data
- Migration: `011_create_hierarchical_access_control.sql`

### Critical Bug Fixes

#### Import Job Deletion Error (Nov 2025)
- **Issue:** SQLSTATE[HY093] parameter binding error in `ImportController::deleteJob()`
- **Fix:** Used separate parameter names (`:batch_id` and `:batch_id2`) for nested query
- **Impact:** Import job deletion now works correctly

#### Database Connection Cleanup (Nov 12, 2025)
- Fixed connection leaks in long-running processes
- Improved error handling and reconnection logic
- Added connection pooling optimization

#### HH Consumption Page Infinite Scroll (Nov 2025)
- Fixed pagination issues causing duplicate data
- Improved scroll performance for large datasets

#### SFTP Controller Error (Nov 8, 2025)
- Fixed Slim Application error on SFTP tools page
- Improved SFTP connection handling and error messages

#### Dashboard Layout and Health Check (Nov 2025)
- Resolved "degraded service" warnings
- Fixed health check endpoint to properly assess system status
- Improved dashboard widget layout and responsiveness

### Enhancements

#### Import System
- Flexible column mapping recognizing common variations
- Dry run mode for validation without database changes
- Optional default site and tariff assignment
- Batch summaries with detailed error reporting
- CSV file storage in `storage/imports/` directory
- Pagination for meter lists (10/25/50/100 per page)
- Progress bars for CLI imports
- Enhanced error messages with solutions

#### SFTP Integration
- Live uploads via phpseclib
- Automatic remote directory creation
- Export tracking in `exports` table
- Enhanced credential validation
- Support for private key authentication

#### Security & Compliance
- GDPR compliance features
- Audit logging for all critical operations
- Session security improvements
- Input validation and sanitization
- HTTPS enforcement

#### API Endpoints
- `/api/health` - Multi-tier system health diagnostics
- `/api/carbon-intensity` - Carbon intensity dashboard summary
- `/api/carbon-intensity/refresh` - Manual data refresh
- `/api/carbon-intensity/history` - Historical data with date range
- `/api/meters` - List all meters
- `/api/meters/{mpan}/readings` - Meter-specific readings
- `/api/import/status` - Import job status and progress

### Database Schema Updates

#### New Tables
- `scheduler_executions` - Aggregation job telemetry
- `scheduler_alerts` - System alerts and warnings
- `external_temperature_data` - Temperature data for analytics
- `external_calorific_values` - Gas calorific values
- `external_carbon_intensity` - Carbon intensity data
- `data_quality_issues` - Data quality tracking
- `comparison_snapshots` - Cached comparison data
- `ai_insights` - AI-generated insights (ready for implementation)
- `permissions` - Available system permissions
- `user_permissions` - User-permission relationships
- `tariff_switching_analyses` - Tariff comparison history
- `sftp_configurations` - SFTP export configurations
- `scheduled_reports` - Report scheduling
- `alarm_configurations` - Alarm thresholds
- `hierarchical_access_control` - Organization-based access

### Documentation
- Comprehensive README with installation guide
- Complete feature showcase: `docs/COMPLETE_GUIDE.md`
- Quick start guide: `docs/quick_start_import.md`
- Troubleshooting guides for imports, timeouts, system issues
- API documentation and examples
- Deployment guide for production environments
- Testing guides for new features

## Deployment Notes

### Production Checklist
1. Run database migrations: `php scripts/migrate.php`
2. Configure `.env` with production credentials
3. Set up cron jobs for:
   - Import worker (every minute)
   - Aggregation (daily at 01:30)
   - Data quality checks (daily at 02:00)
   - System monitoring (every 15 minutes)
   - Job cleanup (weekly)
4. Configure SFTP credentials if using exports
5. Configure email settings for alerts
6. Update default user passwords
7. Enable HTTPS in Plesk
8. Set proper file permissions (755/777 for logs)

### Performance Recommendations
- Use PHP 8.2+ with OPcache enabled
- Configure MySQL query cache
- Enable Composer autoloader optimization
- Use production environment variables
- Monitor system health at `/api/health`

### Security Recommendations
- Never commit `.env` to version control
- Rotate `MIGRATION_KEY` after migrations
- Use strong passwords for all user accounts
- Enable HTTPS only
- Regular dependency updates: `composer update`
- Monitor audit logs for suspicious activity
- Implement rate limiting for public APIs

## Future Roadmap
- AI-powered insights and recommendations (in progress)
- Advanced visualization with interactive charts
- Mobile application API
- Multi-tenancy support
- Real-time data streaming with WebSocket
- Advanced forecasting and predictive analytics
- Integration with IoT devices and smart meters
- Expanded reporting capabilities
- Enhanced user authentication (2FA, SSO)

---

For detailed documentation on specific features, see the `docs/` directory.
For the complete feature list, see `README.md`.
