# Email System Documentation

## Overview

The Eclectyc Energy platform includes email functionality for alerting administrators about system issues, import failures, and other critical events. The email system is **optional** but recommended for production deployments.

## Current Email Usage

### 1. Import Job Alerts
**Service:** `App\Domain\Ingestion\ImportAlertService`

Sends emails when:
- ✉️ **Import job fails** after all retries exhausted
- ✉️ **Multiple import jobs fail** in a batch
- ✉️ **Import jobs get stuck** in processing state (>60 minutes)
- ✉️ **High failure rate detected** (>50% failures)
- ✉️ **Queue backlog alert** (>100 jobs waiting)

**Example scenarios:**
- CSV file has invalid data format → Alert sent after 3 retry attempts
- Worker process crashes → Alert sent for stuck jobs
- Database connection issues → Alert sent for high failure rate

### 2. Aggregation Alerts
**Service:** `App\Domain\Orchestration\AlertService`

Sends emails when:
- ✉️ **Daily aggregation fails** (data not rolled up)
- ✉️ **Weekly/monthly aggregation fails**
- ✉️ **Aggregation completes with warnings** (partial success)
- ✉️ **Summary of multiple aggregation issues**

**Example scenarios:**
- Database timeout during aggregation → Failure alert sent
- Missing meter data causes warnings → Warning alert sent

## How It Works

### Configuration (.env)

```bash
# Required for email functionality
MAIL_HOST=smtp.example.com          # SMTP server hostname
MAIL_PORT=587                        # SMTP port (587 for TLS, 465 for SSL)
MAIL_USERNAME=your-username          # SMTP authentication username
MAIL_PASSWORD=your-password          # SMTP authentication password
MAIL_ENCRYPTION=tls                  # Encryption: tls or ssl
MAIL_FROM_ADDRESS=noreply@eclectyc.energy
MAIL_FROM_NAME="Eclectyc Energy"

# Recipient for alerts
ADMIN_EMAIL=admin@eclectyc.energy    # Where alerts are sent

# Optional: Enable/disable alerts
ALERT_ENABLED=true
```

### Email Methods

The platform supports two email sending methods:

#### 1. PHPMailer (Recommended)
- Full SMTP support with authentication
- Better error handling and debugging
- Used by `AlertService` (aggregation alerts)

#### 2. PHP mail() Function
- Simpler, uses system's sendmail
- Used by `ImportAlertService` (import alerts)
- Requires server mail configuration

### Alert Triggers

```php
// Import monitoring script triggers alerts
// scripts/monitor_import_system.php --send-alerts

// Aggregation failures trigger alerts automatically
// scripts/aggregate_orchestrated.php --all
```

## Testing Email Functionality

### Quick Test
```bash
# Test with default recipient (ADMIN_EMAIL from .env)
php scripts/test_email.php

# Test with specific recipient
php scripts/test_email.php your.email@example.com

# Test using PHP mail() function instead of PHPMailer
php scripts/test_email.php your.email@example.com --method=mail
```

### Check Configuration
The test script will:
1. ✅ Verify all MAIL_* environment variables are set
2. ✅ Test SMTP connection
3. ✅ Send a test email
4. ✅ Report success or detailed error messages

### Example Output
```
╔══════════════════════════════════════════════════════════════╗
║          Eclectyc Energy - Email Test Utility               ║
╚══════════════════════════════════════════════════════════════╝

📧 Email Configuration Check
════════════════════════════════════════════════════════════════

  ✅ MAIL_HOST: smtp.gmail.com
  ✅ MAIL_PORT: 587
  ✅ MAIL_USERNAME: (set)
  ✅ MAIL_PASSWORD: (set)
  ✅ MAIL_ENCRYPTION: tls
  ✅ MAIL_FROM_ADDRESS: noreply@eclectyc.energy
  ✅ ADMIN_EMAIL: admin@eclectyc.energy

════════════════════════════════════════════════════════════════

📤 Sending test email...
   To: admin@eclectyc.energy
   Method: phpmailer

════════════════════════════════════════════════════════════════
✅ Email sent successfully!

   Check your inbox at: admin@eclectyc.energy
   Subject: [Eclectyc Energy] Email Test - 2025-11-08 10:00:00

Note: It may take a few minutes to arrive. Check spam folder if not received.
════════════════════════════════════════════════════════════════
```

## Common Email Providers

### Gmail (Personal/Workspace)
```bash
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password  # Use App Password, not regular password
MAIL_ENCRYPTION=tls
```

**Note:** For Gmail, you need to:
1. Enable 2-factor authentication
2. Generate an "App Password" from your Google Account settings
3. Use the app password instead of your regular password

### Office 365 / Outlook
```bash
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### SendGrid (Recommended for Production)
```bash
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

### Mailgun
```bash
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@your-domain.mailgun.org
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
```

### Amazon SES
```bash
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your-ses-smtp-username
MAIL_PASSWORD=your-ses-smtp-password
MAIL_ENCRYPTION=tls
```

## Implementing Custom Email Notifications

You can use the email functionality for custom notifications:

### Example 1: Send Alert When Meter Goes Offline
```php
use PHPMailer\PHPMailer\PHPMailer;

function sendMeterOfflineAlert($meterMpan, $adminEmail) {
    $mail = new PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'];
    $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['MAIL_USERNAME'];
    $mail->Password = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
    
    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($adminEmail);
    
    $mail->Subject = '[Alert] Meter Offline: ' . $meterMpan;
    $mail->Body = "Meter $meterMpan has not reported data in 24 hours.\n\n";
    $mail->Body .= "Please investigate:\n";
    $mail->Body .= "- Check meter connectivity\n";
    $mail->Body .= "- Verify data collection is running\n";
    $mail->Body .= "- Review recent import logs\n";
    
    $mail->send();
}
```

### Example 2: Daily Summary Email
```php
function sendDailySummaryEmail($pdo, $adminEmail) {
    $mail = new PHPMailer(true);
    
    // Configure mail...
    
    // Get today's stats
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT meter_id) as meter_count,
            SUM(total_consumption) as total_kwh,
            COUNT(*) as reading_count
        FROM daily_aggregations
        WHERE date = CURDATE()
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $mail->Subject = '[Daily Summary] Eclectyc Energy - ' . date('Y-m-d');
    $mail->Body = "Daily Energy Summary\n\n";
    $mail->Body .= "Meters reporting: {$stats['meter_count']}\n";
    $mail->Body .= "Total consumption: " . number_format($stats['total_kwh'], 2) . " kWh\n";
    $mail->Body .= "Data points processed: {$stats['reading_count']}\n";
    
    $mail->send();
}
```

### Example 3: High Consumption Alert
```php
function sendHighConsumptionAlert($siteName, $consumption, $threshold, $adminEmail) {
    $mail = new PHPMailer(true);
    
    // Configure mail...
    
    $mail->Subject = '[Alert] High Consumption: ' . $siteName;
    $mail->Body = "Unusually high consumption detected at $siteName\n\n";
    $mail->Body .= "Current consumption: " . number_format($consumption, 2) . " kWh\n";
    $mail->Body .= "Normal threshold: " . number_format($threshold, 2) . " kWh\n";
    $mail->Body .= "Exceeds threshold by: " . number_format(($consumption - $threshold) / $threshold * 100, 1) . "%\n\n";
    $mail->Body .= "Possible causes:\n";
    $mail->Body .= "- Equipment left running\n";
    $mail->Body .= "- HVAC system malfunction\n";
    $mail->Body .= "- Data quality issue\n";
    
    $mail->send();
}
```

## Monitoring Import System with Email Alerts

The import monitoring script can automatically send alerts:

```bash
# Run monitoring with email alerts enabled
php scripts/monitor_import_system.php --handle-stuck --send-alerts

# Schedule in cron for automatic monitoring
*/15 * * * * php /path/to/scripts/monitor_import_system.php --handle-stuck --send-alerts
```

This will:
- Check for stuck jobs and send alerts
- Monitor failure rates and send warnings
- Detect queue backlogs and notify admins
- Track system health metrics

## Slack Integration (Alternative/Additional)

In addition to email, both alert services support Slack webhooks:

```bash
# Add to .env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

Alerts will be sent to both email and Slack if both are configured.

## Email Best Practices

### For Development
- Use a test email service (like Mailtrap.io)
- Or use your personal email
- Set `ALERT_ENABLED=false` to disable automated alerts

### For Production
- Use a transactional email service (SendGrid, Mailgun, Amazon SES)
- Monitor email deliverability
- Set up SPF, DKIM, and DMARC records
- Use a dedicated domain for sending (e.g., noreply@eclectyc.energy)
- Monitor bounce rates and spam complaints

### Alert Frequency
Current alert thresholds:
- Import failures: After 3 retries (configurable)
- Stuck jobs: After 60 minutes
- High failure rate: >50% failures over 24 hours
- Queue backlog: >100 jobs waiting

Adjust these in the respective service configuration as needed.

## Troubleshooting

### Email Not Sending

1. **Check configuration:**
   ```bash
   php scripts/test_email.php your.email@example.com
   ```

2. **Check error logs:**
   ```bash
   tail -f logs/app.log
   ```

3. **Test SMTP connection:**
   ```bash
   telnet smtp.example.com 587
   ```

4. **Common issues:**
   - Wrong SMTP credentials
   - Firewall blocking port 587/465
   - 2FA enabled without app password (Gmail)
   - Email provider blocking outbound SMTP

### Emails Going to Spam

1. Configure SPF record for your domain
2. Configure DKIM signing
3. Use a reputable email service (SendGrid, etc.)
4. Avoid spam trigger words in subject/body
5. Keep email volume reasonable

### Email Delivery Delayed

1. Check email provider rate limits
2. Verify recipient mail server is accepting mail
3. Check for greylisting
4. Monitor email queue on your server

## Summary

✅ **Email is working if:**
- Test script sends successfully
- Alerts configured in `.env`
- PHPMailer installed (via composer)

✉️ **Current usage:**
- Import job failure alerts
- Stuck job notifications
- Aggregation failure alerts
- High failure rate warnings
- Queue backlog notifications

🔧 **Useful commands:**
```bash
# Test email configuration
php scripts/test_email.php admin@example.com

# Monitor import system with alerts
php scripts/monitor_import_system.php --send-alerts

# Check which emails would be sent
grep -r "sendEmail\|send(" app/Domain/
```

## Related Files

- `app/Domain/Ingestion/ImportAlertService.php` - Import alerts
- `app/Domain/Orchestration/AlertService.php` - Aggregation alerts
- `scripts/test_email.php` - Email testing utility
- `scripts/monitor_import_system.php` - Import monitoring
- `.env.example` - Configuration template

---

**Document created:** November 8, 2025  
**Related to:** Email functionality inquiry
