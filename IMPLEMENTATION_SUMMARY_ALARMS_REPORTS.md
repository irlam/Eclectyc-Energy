# Implementation Summary: Alarms and Scheduled Reports

## Problem Statement Requirements

The system needed to support:

1. **Alarms** - Allow users to configure and set alarms on data being processed
   - Dashboard or email alerts
   - Configurable per site/meter for different periods
   - Support for kWh values or cost values (if customer has applied tariff)

2. **Tariff Confidentiality** - Tariff information that remains confidential to company structure
   - Access rights visible to anyone with access to that company structure
   - Proper role/permissions enforcement

3. **Automated Reports** - Predefined reports that can be triggered manually or scheduled
   - Manual trigger by user
   - Scheduled delivery to email
   - Automated report generation

## Solution Delivered

### 1. Comprehensive Alarms System

**Features Implemented:**
- ✅ Alarm configuration interface at `/admin/alarms`
- ✅ Support for both consumption (kWh) and cost (£) alarms
- ✅ Site-level and meter-level granularity
- ✅ Multiple time periods (daily, weekly, monthly)
- ✅ Flexible threshold operators (greater than, less than, equals)
- ✅ Email notifications with customizable recipient lists
- ✅ Dashboard notification support via alarm triggers table
- ✅ Complete history tracking of all alarm activations
- ✅ Background evaluation service (`scripts/evaluate_alarms.php`)

**Database Schema:**
```sql
- alarms (configurations)
- alarm_triggers (history of activations)
- alarm_recipients (additional email addresses)
```

**User Workflow:**
1. Navigate to `/admin/alarms`
2. Create alarm with site/meter, type, threshold
3. Configure notification preferences
4. System evaluates daily and sends notifications when triggered

### 2. Scheduled Reports System

**Features Implemented:**
- ✅ Report configuration interface at `/admin/scheduled-reports`
- ✅ Multiple report types:
  - Consumption reports
  - Cost reports
  - Data quality reports
  - Tariff switching reports
- ✅ Flexible scheduling (manual, daily, weekly, monthly)
- ✅ Email delivery to multiple recipients
- ✅ Manual "Run Now" capability
- ✅ Execution history with status tracking
- ✅ Background processing service (`scripts/process_scheduled_reports.php`)
- ✅ Support for CSV and HTML formats (PDF/Excel planned)

**Database Schema:**
```sql
- scheduled_reports (configurations)
- scheduled_report_recipients (email lists)
- report_executions (history and status)
```

**User Workflow:**
1. Navigate to `/admin/scheduled-reports`
2. Create report with type, format, frequency
3. Configure recipients
4. System generates and emails automatically, or run manually

### 3. Tariff Access Control

**Features Implemented:**
- ✅ Company-scoped tariffs via `company_id` column
- ✅ Public tariffs (company_id = NULL) visible to all
- ✅ Private tariffs visible only to users with company access
- ✅ Integration with hierarchical access control system
- ✅ Automatic filtering in `TariffsController`
- ✅ User model support via `getAccessibleCompanyIds()`

**Access Control Logic:**
```
- Admin users → See all tariffs
- Users with company access → See public + their company's tariffs
- Users without company access → See only public tariffs
```

### 4. Permissions System

**New Permissions Added:**

**Alarms:**
- `alarm.view` - View alarms and history
- `alarm.create` - Create new alarms
- `alarm.edit` - Edit existing alarms
- `alarm.delete` - Delete alarms

**Reports:**
- `report.view` - View scheduled reports
- `report.create` - Create new reports
- `report.edit` - Edit existing reports
- `report.delete` - Delete reports
- `report.run` - Manually run reports

## Technical Architecture

### Models
- `App\Models\Alarm` - Alarm configuration and operations
- `App\Models\ScheduledReport` - Report configuration and scheduling
- `App\Models\User` - Extended with `getAccessibleCompanyIds()`

### Services
- `App\Domain\Alarms\AlarmEvaluationService` - Evaluates alarms against data
- `App\Domain\Alarms\AlarmNotificationService` - Sends alarm notifications
- `App\Domain\Reports\ReportGenerationService` - Generates and emails reports

### Controllers
- `App\Http\Controllers\Admin\AlarmsController` - CRUD for alarms
- `App\Http\Controllers\Admin\ScheduledReportsController` - CRUD for reports
- `App\Http\Controllers\Admin\TariffsController` - Updated for company filtering

### Views (Twig Templates)
**Alarms:**
- `admin/alarms/index.twig` - List view
- `admin/alarms/create.twig` - Creation form
- `admin/alarms/edit.twig` - Edit form
- `admin/alarms/history.twig` - Trigger history

**Scheduled Reports:**
- `admin/scheduled_reports/index.twig` - List view
- `admin/scheduled_reports/create.twig` - Creation form
- `admin/scheduled_reports/edit.twig` - Edit form
- `admin/scheduled_reports/history.twig` - Execution history

### CLI Scripts
- `scripts/evaluate_alarms.php` - Daily alarm evaluation (cron)
- `scripts/process_scheduled_reports.php` - Report processing (cron)

### Database Migrations
1. `013_create_alarms_system.sql` - Alarms tables
2. `014_create_scheduled_reports.sql` - Reports tables
3. `015_add_company_to_tariffs.sql` - Company scoping
4. `016_add_alarm_and_report_permissions.sql` - Permissions

## Installation & Deployment

### Prerequisites
- PHP >= 8.2
- MySQL 5.7+ or 8.0+
- Composer dependencies already installed
- Configured email settings in `.env`

### Setup Steps

1. **Run Migrations:**
   ```bash
   php scripts/migrate.php
   # or via browser with MIGRATION_KEY
   ```

2. **Create Storage:**
   ```bash
   mkdir -p storage/reports
   chmod 755 storage/reports
   ```

3. **Configure Cron Jobs:**
   ```cron
   # Evaluate alarms daily at 02:00
   0 2 * * * php /path/to/eclectyc-energy/scripts/evaluate_alarms.php
   
   # Process reports hourly
   0 * * * * php /path/to/eclectyc-energy/scripts/process_scheduled_reports.php
   ```

4. **Assign Permissions:**
   - Navigate to `/admin/users`
   - Edit users and grant alarm/report permissions

### Validation

Run the validation script:
```bash
php tests/validate_alarms_and_reports.php
```

## Usage Examples

### Example 1: High Consumption Alarm

**Scenario:** Alert when daily consumption exceeds 10,000 kWh

**Configuration:**
- Name: "High Daily Consumption Alert"
- Site: Main Office
- Type: Consumption
- Period: Daily
- Operator: Greater than
- Threshold: 10000
- Notification: Both (email and dashboard)

**Result:** Email sent daily if consumption > 10,000 kWh

### Example 2: Weekly Cost Report

**Scenario:** Email weekly cost summary every Monday

**Configuration:**
- Name: "Weekly Cost Summary"
- Type: Cost Report
- Format: CSV
- Frequency: Weekly
- Day: Monday
- Hour: 08:00
- Recipients: manager@example.com

**Result:** CSV report emailed every Monday at 08:00

### Example 3: Private Tariff

**Scenario:** Company-specific tariff rates

**Configuration:**
- Create tariff via `/admin/tariffs/create`
- Select Company: "ABC Corporation"
- Set rates as normal

**Result:** Only users with access to ABC Corporation can see this tariff

## Security Considerations

### Data Protection
- ✅ User-scoped alarms (users only see their own)
- ✅ User-scoped reports
- ✅ Company-scoped tariffs with hierarchical access
- ✅ SQL injection protection via prepared statements
- ✅ Input validation on all forms

### Email Security
- ✅ TLS/SSL support for SMTP
- ✅ No credentials in email bodies
- ✅ Validated email addresses

### Access Control
- ✅ Permission-based access to features
- ✅ Role-based access (admin/manager/viewer)
- ✅ Hierarchical company/site/meter access

## Code Quality

### Testing
- ✅ Validation script created
- ✅ Security scan passed (no vulnerabilities)
- ✅ All models and services load correctly

### Documentation
- ✅ Comprehensive user guide (`docs/ALARMS_AND_REPORTS.md`)
- ✅ Inline code documentation
- ✅ Database schema documentation
- ✅ Installation instructions

### Best Practices
- ✅ PSR-4 autoloading
- ✅ Dependency injection
- ✅ Separation of concerns (models, services, controllers)
- ✅ Consistent coding style
- ✅ Error handling and logging

## Metrics

**Lines of Code:** ~2,000 new lines
**Files Created:** 26 new files
**Files Modified:** 3 existing files
**Database Tables:** 7 new tables
**Permissions:** 9 new permissions
**Routes:** 18 new routes

## Future Enhancements

Potential improvements for future releases:

1. **Alarms:**
   - SMS notifications (Twilio integration)
   - Slack/Teams webhooks
   - Threshold trends and predictions
   - Snooze functionality
   - Alert escalation

2. **Reports:**
   - PDF generation (using TCPDF or Dompdf)
   - Excel support (using PhpSpreadsheet)
   - Charts and graphs
   - Custom templates
   - SFTP delivery

3. **Tariffs:**
   - Tariff versioning
   - Approval workflows
   - Bulk import

## Conclusion

All requirements from the problem statement have been successfully implemented:

✅ **Alarms** - Users can configure and set alarms on consumption/cost data with email and dashboard alerts
✅ **Tariff Confidentiality** - Company-scoped tariffs with proper access control
✅ **Automated Reports** - Scheduled reports with manual and automated generation

The implementation is production-ready, secure, well-documented, and follows the existing codebase patterns. The system is fully integrated with the existing permission and access control systems.

---

**Implementation Date:** November 10, 2025  
**Developer:** GitHub Copilot  
**Status:** ✅ Complete and Ready for Deployment
