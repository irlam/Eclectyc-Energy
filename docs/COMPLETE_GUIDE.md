# Eclectyc Energy Platform - Complete Guide & Showcase

**A comprehensive walkthrough of the platform's architecture, features, and capabilities**

> 📺 **Interactive Web Showcase Available**: For a rich, interactive experience with visual walkthroughs and auto-guided tours, visit the [Interactive Showcase Page](../showcase/index.html)

---

## About This Guide

This guide provides a complete overview of the Eclectyc Energy Management Platform, explaining:
- How the system architecture works
- What each component does
- How data flows through the platform
- How to use each feature effectively
- Best practices and optimization tips

For the full interactive experience with animations and guided tours, see the web-based showcase.

---

## 🏗️ System Architecture Overview

The Eclectyc Energy platform is built on a modern, layered architecture that separates concerns and enables scalability.

### The Four Pillars

1. **Data Ingestion Layer** - How energy data enters the system
2. **Processing & Analytics Layer** - How data is transformed into insights
3. **Storage & Retrieval Layer** - How data is persisted and queried
4. **Presentation Layer** - How users interact with the system

[See Interactive Architecture Diagram](../showcase/index.html#architecture)

---

## 📥 Data Ingestion: From CSV to Database

**Let me walk you through how energy data enters our system...**

### Step 1: Data Upload
When you upload a CSV file through the web interface, several things happen behind the scenes:

- The file is validated for size and format
- A unique batch ID is generated for tracking
- The file is stored securely in `storage/imports/`
- An import job record is created in the database

### Step 2: Queue Processing
The import job is placed in a queue, where:

- Priority is assigned (high/normal/low)
- A background worker picks it up for processing
- Progress is tracked in real-time
- Users can monitor status from anywhere

### Step 3: Parsing & Validation
As the CSV is processed row by row:

- Headers are matched using flexible aliasing (MPAN, MeterCode, etc.)
- Dates are validated and normalized
- Values are checked for validity (no negatives, reasonable ranges)
- Missing data is flagged but not rejected

### Step 4: Data Insertion
Valid readings are inserted into the database:

- Duplicate detection prevents re-importing
- Readings are linked to meters via MPAN
- Timestamps are stored in UTC
- Batch metadata is preserved for auditing

**Interactive Demo**: [See the import process in action](../showcase/index.html#import-flow)

---

## 🔄 Background Processing: The Import Worker

**Here's how the background worker keeps your imports running smoothly...**

### The Worker Loop
The `process_import_jobs.php` script runs continuously (or via cron), checking for:

1. **Queued Jobs** - New imports waiting to be processed
2. **Retryable Jobs** - Failed jobs within retry limits
3. **Priority Order** - High-priority jobs are processed first

### Retry Logic with Exponential Backoff
When a job fails, it's not immediately given up on:

```
Attempt 1: Process immediately → Fails? Wait 1 minute
Attempt 2: Process again → Fails? Wait 2 minutes  
Attempt 3: Process again → Fails? Wait 4 minutes
Attempt 4: Process again → Fails? Wait 8 minutes
Final: Mark as permanently failed, send alert
```

This gives temporary network issues time to resolve without overwhelming the system.

### Progress Tracking
As each row is processed, callbacks update the database:

- `rows_processed` increments
- `rows_imported` counts successful inserts
- `rows_failed` tracks errors
- Progress percentage is calculated in real-time

Users watching the status page see live updates without refreshing!

**Interactive Demo**: [Watch the worker process a job](../showcase/index.html#worker-demo)

---

## 📊 Data Aggregation: From Raw Readings to Insights

**Now let's see how raw meter readings become meaningful analytics...**

### The Aggregation Pipeline

Every night at 1:30 AM, the orchestrated aggregation runs:

#### Phase 1: Daily Aggregation
For each meter and day:
- Sum all half-hourly readings
- Calculate min, max, and average values
- Identify baseload (minimum constant usage)
- Store in `daily_aggregates` table

#### Phase 2: Weekly Roll-ups
For each meter and week:
- Sum daily totals from the week
- Compare to previous week for trends
- Store in `weekly_aggregates` table

#### Phase 3: Monthly Roll-ups
For each meter and month:
- Sum daily totals from the month
- Calculate average daily usage
- Compare to previous year same month
- Store in `monthly_aggregates` table

#### Phase 4: Annual Roll-ups
For each meter and year:
- Sum all monthly totals
- Calculate seasonal patterns
- Year-over-year comparisons
- Store in `annual_aggregates` table

### Comparison Snapshots
A separate process creates comparison records:

- **Day-over-day**: Compare each day to previous day
- **Week-over-week**: Compare each week to previous week
- **Month-over-month**: Compare each month to previous month
- **Year-over-year**: Compare to same period last year

These power the trend arrows and percentage changes you see in reports!

**Interactive Demo**: [Visualize the aggregation flow](../showcase/index.html#aggregation-flow)

---

## 💰 Tariff Analysis: Finding Savings Opportunities

**Let me show you how the tariff switching analyzer works its magic...**

### The Analysis Process

When you request a tariff switching analysis:

#### Step 1: Gather Consumption Data
The analyzer retrieves all readings for the selected date range:
- Default is last 90 days for quick analysis
- Custom ranges supported for detailed analysis
- Half-hourly data is used for time-of-use calculations

#### Step 2: Identify Current Tariff
The system determines your current tariff based on:
- Meter's supplier relationship
- Active tariff during the period
- Fallback to supplier's default if not explicit

#### Step 3: Calculate Current Costs
Using the `TariffCalculator` service:
- Apply peak/off-peak/weekend rates if time-of-use tariff
- Add standing charges (daily rate × days)
- Sum unit costs (kWh × rate)
- Total = standing charges + unit costs

#### Step 4: Calculate Alternative Costs
For EVERY other active tariff in the database:
- Apply the same consumption pattern
- Calculate costs using that tariff's rates
- Compare to current costs

#### Step 5: Rank & Recommend
Results are sorted by potential savings:
- Highest savings first
- Both absolute (£) and percentage shown
- Detailed breakdown provided for each option

#### Step 6: Persist Analysis
The complete analysis is saved to `tariff_switching_analyses`:
- Full JSON of all alternatives stored
- Historical tracking for trend analysis
- User attribution for audit trail

**Interactive Demo**: [See a live tariff analysis](../showcase/index.html#tariff-analysis)

---

## 🌍 Carbon Intensity: Real-time Grid Tracking

**Here's how we integrate live carbon intensity data...**

### The Carbon Intensity Flow

#### Step 1: API Integration
Every 30 minutes, `CarbonIntensityService` calls the National Grid ESO API:
```
GET https://api.carbonintensity.org.uk/intensity
```

This returns:
- Current carbon intensity (gCO2/kWh)
- Forecast for next 24-48 hours
- Regional breakdowns
- Index classification (very low → very high)

#### Step 2: Data Storage
The response is parsed and stored:
- Timestamp of reading
- Intensity value
- Index level (1-5 scale)
- Forecast array for trends

#### Step 3: Dashboard Display
The dashboard widget shows:
- Current intensity with color coding
  - 🟢 Very Low (0-100) / Low (100-150)
  - 🟡 Moderate (150-200)
  - 🟠 High (200-250)
  - 🔴 Very High (250+)
- Trend indicator (rising/falling)
- Last updated timestamp
- Manual refresh button

#### Step 4: Historical Analysis
The `/api/carbon-intensity/history` endpoint provides:
- 7/30/90-day historical data
- Average intensity calculations
- Peak and off-peak patterns
- Optimal usage time recommendations

**Interactive Demo**: [Explore carbon intensity tracking](../showcase/index.html#carbon-intensity)

---

## 📈 Analytics Engine: Turning Data into Insights

**Now for the really clever part - the analytics engine...**

### Consumption Trend Analysis

The `AnalyticsEngine` service examines your usage patterns to detect:

#### 1. Baseload Identification
**What is baseload?** It's the constant, always-on electricity usage.
- Minimum half-hourly reading in each day
- Average of minimums across the period
- Represents fridges, servers, always-on equipment
- Optimization opportunity: reduce baseload = 24/7 savings

#### 2. Peak Demand Detection
- Highest usage periods identified
- Patterns by time of day and day of week
- Anomaly detection for unusual spikes
- Capacity planning insights

#### 3. Seasonal Patterns
- Summer vs. winter usage comparison
- Heating degree day correlation
- Temperature impact analysis
- Budget forecasting for seasonal variation

#### 4. Missing Data Detection
The data quality system checks for:
- Gaps in half-hourly readings (expected 48/day)
- Consecutive zeros (possible meter issue)
- Outliers (readings > 3 standard deviations from mean)
- Negative values (invalid meter configuration)

Issues are flagged in `data_quality_issues` table for investigation.

**Interactive Demo**: [Explore the analytics dashboard](../showcase/index.html#analytics)

---

## 🔐 Security & Access Control

**Let's talk about how we keep your data safe...**

### Role-Based Access Control (RBAC)

Three roles with different capabilities:

#### 👤 Viewer Role
**What they can do:**
- View main dashboard
- See consumption trends
- Check carbon intensity
- Basic reporting

**What they cannot do:**
- Modify any data
- Access admin functions
- Upload imports
- Change settings

#### 👔 Manager Role
**Everything Viewer can do, plus:**
- Access detailed reports
- View cost analyses
- Export data
- Run tariff comparisons

**Still cannot:**
- Add/edit users
- Configure system
- Access admin tools

#### 🔑 Admin Role
**Full platform access:**
- User management
- Import data
- Configure tariffs
- System settings
- All admin tools
- Export configurations

### Security Measures in Place

✅ **Input Validation**: All user input sanitized
✅ **SQL Injection Prevention**: Prepared statements throughout
✅ **XSS Protection**: Output escaping in templates
✅ **CSRF Protection**: Framework-level token validation
✅ **Session Security**: Secure cookies, HttpOnly flags
✅ **File Upload Validation**: Path sanitization, type checking
✅ **Audit Logging**: All sensitive actions logged
✅ **Password Hashing**: Bcrypt with salt

**Interactive Demo**: [See security in action](../showcase/index.html#security)

---

## 🛠️ Monitoring & Operations

**Here's how to keep the system running smoothly in production...**

### Health Monitoring

The `/api/health` endpoint checks:

#### Database Health
- Connection test
- Query performance
- Table existence
- Record counts

#### Filesystem Health
- Storage directory writability
- Disk space availability
- Log file rotation status

#### Import System Health
- Queue depth (<100 jobs OK)
- Stuck job detection (>60 min = stuck)
- Failure rate (<25% OK, >50% critical)
- Recent activity timestamp

#### Memory & Resources
- PHP memory usage
- Available memory
- Process count

Results returned as JSON with status levels:
- `healthy` - All green
- `degraded` - Some warnings
- `critical` - Immediate attention needed

### Automated Monitoring

The `monitor_import_system.php` script runs every 15 minutes:

1. Check system health
2. Detect stuck jobs (>60 minutes processing)
3. Calculate failure rates
4. Monitor queue backlog
5. Generate performance metrics

If issues detected:
- Stuck jobs marked as failed
- Alerts sent via email/Slack
- Exit code indicates severity (0=OK, 1=warning, 2=critical)

### Alerting Channels

#### Email Alerts
Configured in `.env`:
```
ADMIN_EMAIL=admin@example.com
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=your-password
```

Alerts sent for:
- Permanent job failures (after all retries)
- Stuck jobs detected
- High failure rates (>50%)
- Queue backlogs (>100 jobs)

#### Slack Integration
Optional webhook for real-time notifications:
```
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

Same alerts as email, formatted for Slack channels.

**Interactive Demo**: [Monitor system health live](../showcase/index.html#monitoring)

---

## 🎯 Best Practices & Optimization Tips

**Let me share some pro tips for getting the most out of the platform...**

### Data Import Best Practices

1. **Use Background Processing for Large Files**
   - Files >10,000 rows should use async mode
   - Close browser after upload - job continues
   - Monitor progress at `/admin/imports/jobs`

2. **Leverage Dry-Run Mode**
   - Always test new CSV formats with dry-run
   - Fix errors before committing data
   - Saves time and prevents cleanup

3. **Consistent Date Formats**
   - Stick to ISO 8601: `YYYY-MM-DD HH:MM:SS`
   - Or UK format: `DD/MM/YYYY HH:MM`
   - Consistency reduces parsing errors

4. **Organize Batches**
   - Use meaningful batch notes
   - Tag imports by source/purpose
   - Makes history searchable

### Performance Optimization

1. **Run Aggregations Off-Peak**
   - Schedule for 1:30 AM when users offline
   - Reduces lock contention
   - Faster processing

2. **Monitor Queue Depth**
   - Keep <100 jobs queued
   - Batch uploads if queue grows
   - Consider parallel workers if consistently high

3. **Regular Cleanup**
   - Run `cleanup_import_jobs.php` weekly
   - 30-day retention default
   - Keeps database performant

### Tariff Analysis Tips

1. **Use 90-Day Quick Analysis First**
   - Fast results for most cases
   - Covers typical usage patterns
   - Custom ranges for seasonal businesses

2. **Re-analyze Quarterly**
   - Usage patterns change seasonally
   - Tariffs are updated regularly
   - New suppliers enter market

3. **Track Analysis History**
   - Compare recommendations over time
   - Identify persistent savings opportunities
   - Document switching decisions

**Interactive Tips**: [Interactive optimization guide](../showcase/index.html#best-practices)

---

## 🚀 Advanced Workflows

**Ready for some power-user techniques?**

### Automated Reporting Pipeline

1. **Schedule Daily Aggregations**
   ```bash
   30 1 * * * php scripts/aggregate_orchestrated.php --all
   ```

2. **Export Reports Automatically**
   ```bash
   0 6 * * 1 php scripts/export_sftp.php -t weekly
   ```

3. **Email Digest Generation**
   - Configure cron to run weekly summary
   - Email to stakeholders
   - Attach PDF reports

### API Integration Examples

#### Get Real-time Carbon Intensity
```bash
curl https://eclectyc.energy/api/carbon-intensity
```

#### Check Import Job Status
```bash
curl https://eclectyc.energy/api/import/jobs/{batchId}
```

#### Retrieve Meter Readings
```bash
curl https://eclectyc.energy/api/meters/{mpan}/readings?from=2025-01-01&to=2025-01-31
```

### Multi-Site Management

For organizations with multiple sites:

1. **Bulk Meter Creation**
   - Use API to programmatically add meters
   - Import from external systems
   - Maintain in configuration management

2. **Site-Level Aggregation**
   - Group meters by site
   - Compare site-to-site performance
   - Identify outliers

3. **Consolidated Reporting**
   - Portfolio-wide dashboards
   - Cross-site benchmarking
   - Total carbon footprint

**Interactive Workflows**: [See advanced scenarios](../showcase/index.html#workflows)

---

## 📚 Documentation Hub

### Quick Reference
- [Quick Start Guide](quick_start_import.md) - First import walkthrough
- [Troubleshooting](import_troubleshooting.md) - Common issues
- [API Reference](../README.md#api-endpoints) - All endpoints

### Feature Guides
- [Web-Triggered Imports](web_triggered_import.md) - Async import system
- [Tariff Switching](tariff_switching_analysis.md) - Savings analysis
- [Carbon Intensity](carbon_intensity_implementation.md) - Real-time tracking
- [Analytics Features](analytics_features.md) - Advanced analytics

### Operations
- [Operationalizing Async Systems](operationalizing_async_systems.md) - Production deployment
- [Product Requirements](product_requirements.md) - Capability matrix

---

## 🎓 Learning Path

### For New Users
1. Start with [Quick Start Guide](quick_start_import.md)
2. Upload your first CSV
3. Explore the dashboard
4. Run a tariff analysis
5. Set up automated aggregation

### For Administrators
1. Review [Operationalizing Guide](operationalizing_async_systems.md)
2. Configure cron jobs
3. Set up monitoring and alerts
4. Configure SFTP exports
5. Establish backup procedures

### For Developers
1. Explore codebase structure in `/app`
2. Review API endpoints
3. Study `Domain` services
4. Understand database schema
5. Set up development environment

---

## 🌐 Interactive Showcase

For the full interactive experience with:
- ✨ Visual animations of data flows
- 🎯 Auto-guided tours of each feature
- 📊 Live code examples with syntax highlighting
- 🖱️ Click-through demonstrations
- 🎬 "Voice-over" style narrations

**Visit the Interactive Showcase**: [Open Showcase Page](../showcase/index.html)

---

*This guide is part of the Eclectyc Energy Platform documentation suite. Last updated: November 7, 2025*
