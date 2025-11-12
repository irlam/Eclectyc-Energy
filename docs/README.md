# Eclectyc Energy Platform - Documentation Index

Welcome to the Eclectyc Energy Platform documentation. This comprehensive guide covers all aspects of the platform, from installation to advanced features.

## Getting Started

### Installation & Setup
- **[README](../README.md)** - Main README with installation instructions
- **[Quick Start Import Guide](quick_start_import.md)** - Step-by-step guide for your first data import
- **[Operationalizing Async Systems](operationalizing_async_systems.md)** - Production deployment guide for background workers

### Troubleshooting
- **[Import Troubleshooting](import_troubleshooting.md)** - Common import issues and solutions
- **[TROUBLESHOOTING_IMPORTS](TROUBLESHOOTING_IMPORTS.md)** - Detailed import system diagnostics
- **[504 Timeout Troubleshooting](troubleshooting_504_timeouts.md)** - Handling timeout errors
- **[System Degraded Issues](troubleshooting_system_degraded.md)** - Resolving health check warnings

## Core Features

### Data Management
- **[Complete Feature Guide](COMPLETE_GUIDE.md)** - Comprehensive overview of all platform features
- **[Import Progress Tracking](import_progress_tracking.md)** - Understanding import job status and progress
- **[Import Progress, SFTP & Throttling](import_progress_sftp_throttling.md)** - Advanced import features

### Analytics & Reporting
- **[Analytics Features](analytics_features.md)** - Data aggregation, quality checks, and external data integration
- **[Carbon Intensity Implementation](carbon_intensity_implementation.md)** - Real-time carbon tracking
- **[Carbon Intensity - How It Works](carbon_intensity_how_it_works.md)** - Technical details of carbon intensity features
- **[Carbon Intensity Prediction](carbon_intensity_prediction_analysis.md)** - Forecasting and analysis

### AI-Powered Insights 🤖 **NEW!**
- **[AI Insights Guide](ai_insights.md)** - Complete guide to AI-powered energy optimization
  - Consumption pattern analysis
  - Cost optimization recommendations
  - Anomaly detection
  - Carbon reduction strategies
  - Predictive maintenance
  - Multi-provider support (OpenAI, Anthropic, Google AI, Azure OpenAI)

### Cost Management
- **[Tariff Switching Analysis](tariff_switching_analysis.md)** - Compare tariffs and calculate savings
- **Product Requirements** - Feature roadmap and capability analysis

## Advanced Features

### Access Control & Security
- **[Hierarchical Access Control](hierarchical_access_control.md)** - Organization-based permissions
- **[Testing Guide - Access Control](TESTING_GUIDE_ACCESS_CONTROL.md)** - Testing access control features

### Monitoring & Alerting
- **[Alarms and Reports](ALARMS_AND_REPORTS.md)** - Configurable alerts and scheduled reports
- **[Application Logging Guide](application_logging_guide.md)** - Understanding system logs
- **[Cron Job Logging](CRON_LOGGING.md)** - Background job monitoring

### System Administration
- **[System Settings Guide](system_settings_guide.md)** - Configuring platform settings
- **[Email System Usage](email_system_usage.md)** - Email notification configuration
- **[DB Connection Fix](DB_CONNECTION_FIX.md)** - Database connection troubleshooting

## API Documentation

### Available Endpoints

#### Health & Status
- `GET /api/health` - System health check with detailed diagnostics

#### Carbon Intensity
- `GET /api/carbon-intensity` - Current carbon intensity dashboard summary
- `POST /api/carbon-intensity/refresh` - Manually refresh data
- `GET /api/carbon-intensity/history?days=7` - Historical data

#### Meters & Readings
- `GET /api/meters` - List all meters
- `GET /api/meters/{mpan}/readings` - Get readings for specific meter

#### Import Management
- `GET /api/import/status` - Import job status and progress

## Directory Structure

```
docs/
├── COMPLETE_GUIDE.md              # Complete feature showcase
├── ai_insights.md                 # AI-powered insights (NEW!)
├── analytics_features.md          # Data analytics and aggregation
├── carbon_intensity_*.md          # Carbon intensity features (3 files)
├── hierarchical_access_control.md # Access control system
├── import_*.md                    # Import system docs (4 files)
├── operationalizing_async_systems.md # Production deployment
├── quick_start_import.md          # Getting started guide
├── tariff_switching_analysis.md   # Tariff comparison
├── troubleshooting_*.md           # Troubleshooting guides (3 files)
├── system_settings_guide.md       # Settings configuration
├── email_system_usage.md          # Email notifications
├── application_logging_guide.md   # Logging system
└── examples/                      # Example files and templates
```

## Quick Reference

### Common Tasks

#### Generate AI Insights
```bash
# For a specific meter
php scripts/generate_ai_insights.php --meter-id 123

# For all meters
php scripts/generate_ai_insights.php --all --type cost_optimization
```

#### Import CSV Data
```bash
# Via CLI
php scripts/import_csv.php -f /path/to/readings.csv -t hh

# Via web interface
Navigate to /admin/imports
```

#### Run Aggregations
```bash
# All ranges (recommended)
php scripts/aggregate_orchestrated.php --all --verbose

# Specific range
php scripts/aggregate_daily.php --date 2025-11-06
```

#### Check System Health
```bash
# Via CLI
php scripts/check_import_setup.php

# Via web
Visit: /api/health
```

#### Code Quality Check
```bash
php scripts/check_code_quality.php
```

### Cron Job Schedule

Recommended production cron jobs:

```cron
# Import job processor (every minute)
* * * * * php /path/to/scripts/process_import_jobs.php --once

# Orchestrated aggregation (daily at 1:30 AM)
30 1 * * * php /path/to/scripts/aggregate_orchestrated.php --all --verbose

# Data quality checks (daily at 2:00 AM)
0 2 * * * php /path/to/scripts/run_data_quality_checks.php --verbose

# Import system monitoring (every 15 minutes)
*/15 * * * * php /path/to/scripts/monitor_import_system.php --handle-stuck --send-alerts

# Import job cleanup (weekly on Sundays at 2:00 AM)
0 2 * * 0 php /path/to/scripts/cleanup_import_jobs.php --days 30 --verbose
```

## Support & Contributions

### Getting Help
1. Check the relevant documentation file above
2. Review the [CHANGELOG](../CHANGELOG.md) for recent changes
3. Check error logs in `logs/` directory
4. Use `/api/health` endpoint for system diagnostics

### Documentation Standards
- All features should have corresponding documentation
- Include code examples where appropriate
- Keep documentation up to date with code changes
- Use clear, concise language

### Related Resources
- **Main README:** [../README.md](../README.md)
- **Changelog:** [../CHANGELOG.md](../CHANGELOG.md)
- **Example Files:** [examples/](examples/)

---

**Last Updated:** November 2025  
**Platform Version:** 1.0 (Production Ready)  
**Documentation Status:** Complete
