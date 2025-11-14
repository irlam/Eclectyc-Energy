# Alarms and Scheduled Reports - Implementation Guide

This document describes the newly implemented alarms and scheduled reports functionality in the Eclectyc Energy platform.

## Features Implemented

### 1. Alarms System

The alarms system allows users to configure automated monitoring of consumption or cost thresholds with notifications when thresholds are breached.

#### Features
- **Alarm Types**: Monitor consumption (kWh) or costs (£)
- **Granularity**: Site-level or meter-level alarms
- **Periods**: Daily, weekly, or monthly evaluation
- **Operators**: Greater than, less than, or equals
- **Notifications**: Email, dashboard, or both
- **Recipients**: Multiple email addresses can be configured
- **History**: Complete audit trail of all triggered alarms

#### Database Tables
- `alarms` - Alarm configurations
- `alarm_triggers` - History of alarm activations
- `alarm_recipients` - Additional email recipients for alarms

#### User Interface
- **List Alarms**: `/admin/alarms`
- **Create Alarm**: `/admin/alarms/create`
- **Edit Alarm**: `/admin/alarms/{id}/edit`
- **View History**: `/admin/alarms/{id}/history`

#### Automation
Run daily via cron (recommended time: 02:00):
```bash
php /path/to/eclectyc-energy/scripts/evaluate_alarms.php -v
```

### 2. Scheduled Reports

Automated report generation and email delivery system with support for multiple report types and frequencies.

#### Features
- **Report Types**:
  - Consumption Report
  - Cost Report
  - Data Quality Report
  - Tariff Switching Report
  
- **Formats**: CSV, HTML (PDF and Excel planned for future)
  
- **Frequencies**:
  - Manual (run on demand)
  - Daily
  - Weekly (choose day of week)
  - Monthly (choose day of month)
  
- **Recipients**: Multiple email addresses
- **History**: Execution tracking with status and error logging
- **Manual Trigger**: Run any report on demand via UI

#### Database Tables
- `scheduled_reports` - Report configurations
- `scheduled_report_recipients` - Email recipients
- `report_executions` - Execution history and status

#### User Interface
- **List Reports**: `/admin/scheduled-reports`
- **Create Report**: `/admin/scheduled-reports/create`
- **Edit Report**: `/admin/scheduled-reports/{id}/edit`
- **View History**: `/admin/scheduled-reports/{id}/history`
- **Run Now**: Button on index or history page

#### Automation
Run hourly via cron:
```bash
php /path/to/eclectyc-energy/scripts/process_scheduled_reports.php -v
```

Force run a specific report:
```bash
php /path/to/eclectyc-energy/scripts/process_scheduled_reports.php --force=5
```

#### Storage
Generated reports are stored in: `storage/reports/`

Format: `report_{id}_{type}_{timestamp}.{ext}`

Example: `report_1_consumption_2025-11-10_143025.csv`

### 3. Tariff Access Control

Tariffs now support company-level confidentiality, ensuring tariff information remains private to specific companies.

#### Features
- **Public Tariffs**: Available to all users (company_id = NULL)
- **Private Tariffs**: Scoped to specific companies
- **Hierarchical Access**: Respects company access control system
- **Automatic Filtering**: Users only see tariffs they have access to

#### Database Changes
- Added `company_id` column to `tariffs` table
- Foreign key relationship to `companies` table

#### Access Control Logic
- **Admin users**: See all tariffs (public and private)
- **Users with company access**: See public tariffs + their company's private tariffs
- **Users without company access**: See only public tariffs

## Installation & Setup

### 1. Run Database Migrations

Run all new migrations to create the required tables:

```bash
# Via browser (recommended for Plesk)
https://your-domain/scripts/migrate.php?key=YOUR_MIGRATION_KEY

# Via CLI
php scripts/migrate.php
```

Required migrations:
- `013_create_alarms_system.sql`
- `014_create_scheduled_reports.sql`
- `015_add_company_to_tariffs.sql`
- `016_add_alarm_and_report_permissions.sql`

### 2. Create Storage Directory

Ensure the reports storage directory exists and is writable:

```bash
mkdir -p storage/reports
chmod 755 storage/reports
```

### 3. Configure Cron Jobs

Add these cron jobs via Plesk Scheduled Tasks or crontab:

```cron
# Evaluate alarms daily at 02:00
0 2 * * * /usr/bin/php /path/to/eclectyc-energy/scripts/evaluate_alarms.php >> /path/to/logs/alarms.log 2>&1

# Process scheduled reports hourly
0 * * * * /usr/bin/php /path/to/eclectyc-energy/scripts/process_scheduled_reports.php >> /path/to/logs/reports.log 2>&1
```

### 4. Set Up Email (if not already configured)

Configure email settings in `.env`:

```env
MAIL_SMTP_ENABLED=true
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@eclectyc.energy
MAIL_FROM_NAME="Eclectyc Energy"
```

### 5. Configure Permissions

Grant appropriate permissions to users via `/admin/users/{id}/edit`:

**For Alarms:**
- `alarm.view` - View alarms
- `alarm.create` - Create alarms
- `alarm.edit` - Edit alarms
- `alarm.delete` - Delete alarms

**For Reports:**
- `report.view` - View reports
- `report.create` - Create reports
- `report.edit` - Edit reports
- `report.delete` - Delete reports
- `report.run` - Manually run reports

## Usage Examples

### Creating an Alarm

1. Navigate to `/admin/alarms`
2. Click "Create Alarm"
3. Configure:
   - Name: "High Daily Consumption - Main Office"
   - Site: Select the site
   - Meter: Leave blank for site-wide, or select specific meter
   - Type: Consumption (kWh)
   - Period: Daily
   - Condition: Greater than
   - Threshold: 5000
   - Notification: Both (email and dashboard)
   - Recipients: additional.email@example.com
4. Save

The alarm will be evaluated daily, and if consumption exceeds 5000 kWh, notifications will be sent.

### Creating a Scheduled Report

1. Navigate to `/admin/scheduled-reports`
2. Click "Create Report"
3. Configure:
   - Name: "Weekly Consumption Summary"
   - Type: Consumption Report
   - Format: CSV
   - Frequency: Weekly
   - Day of Week: Monday
   - Hour: 8 (08:00)
   - Recipients: manager@example.com, finance@example.com
4. Save

The report will be generated and emailed every Monday at 08:00.

### Assigning a Company to a Tariff

When creating or editing a tariff at `/admin/tariffs`:

1. Select the company from the dropdown (or leave blank for public tariff)
2. Only users with access to that company will see the tariff
3. Public tariffs (no company selected) are visible to all users

## API & Services

### AlarmEvaluationService

```php
use App\Domain\Alarms\AlarmEvaluationService;

$service = new AlarmEvaluationService($pdo);
$triggeredAlarms = $service->evaluateAlarms('2025-11-10');

foreach ($triggeredAlarms as $item) {
    $alarm = $item['alarm'];
    $result = $item['result'];
    // Process triggered alarm
}
```

### ReportGenerationService

```php
use App\Domain\Reports\ReportGenerationService;

$service = new ReportGenerationService($pdo);
$result = $service->generateAndSend($scheduledReport);

if ($result['success']) {
    echo "Report sent to {$result['emails_sent']} recipients";
}
```

## Troubleshooting

### Alarms Not Being Evaluated

1. Check if cron job is running:
   ```bash
   php scripts/evaluate_alarms.php -v
   ```

2. Check audit_logs table for `alarm_evaluation` events

3. Verify alarms are active in the database

### Reports Not Being Generated

1. Check if cron job is running:
   ```bash
   php scripts/process_scheduled_reports.php -v
   ```

2. Check `report_executions` table for errors

3. Verify `storage/reports/` directory is writable

4. Check email configuration if reports are generated but not sent

### Tariff Access Issues

1. Verify user has company access via `/admin/users/{id}/access`

2. Check if tariff has correct `company_id` set

3. Admin users should see all tariffs regardless

## Security Considerations

### Data Protection
- Alarms are user-scoped (users only see their own alarms)
- Reports are user-scoped
- Tariffs respect hierarchical access control
- Company-specific tariffs remain confidential

### Email Security
- Email content includes only necessary information
- No sensitive credentials in email bodies
- Use TLS/SSL for SMTP connections

### Input Validation
- All form inputs are validated
- Threshold values are numeric
- Email addresses are validated before storage
- SQL injection protected via prepared statements

## Future Enhancements

Potential improvements for future releases:

1. **Alarms**:
   - SMS notifications
   - Slack/Teams integration
   - Custom alarm frequency (every X days)
   - Threshold trends and predictions
   - Snooze functionality

2. **Reports**:
   - PDF generation
   - Excel format support
   - Chart/graph inclusion
   - Custom report templates
   - FTP/SFTP delivery options
   - Report scheduling wizard

3. **Tariff Access**:
   - Tariff versioning
   - Approval workflows
   - Tariff templates
   - Bulk tariff import

## Support

For issues or questions:
- Check logs in `logs/` directory
- Review audit_logs table for system events
- Run validation script: `php tests/validate_alarms_and_reports.php`
- Contact platform administrator

---

**Last Updated**: November 10, 2025  
**Version**: 1.0  
**Author**: GitHub Copilot
