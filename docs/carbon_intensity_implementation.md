# Carbon Intensity API Implementation Guide

## Overview
This implementation integrates the National Grid ESO Carbon Intensity API to provide real-time carbon emissions data for the UK electricity grid. The data helps users understand the environmental impact of their energy consumption.

## Components Created

### 1. Service Layer (`app/Services/CarbonIntensityService.php`)
- Fetches current and forecast carbon intensity data
- Classifies intensity levels (Very Low, Low, Moderate, High, Very High)
- Provides dashboard summaries with trend analysis
- Handles API authentication (future-ready for API keys)

### 2. API Controller (`app/Http/Controllers/Api/CarbonIntensityController.php`)
- `GET /api/carbon-intensity` - Get current dashboard summary
- `POST /api/carbon-intensity/refresh` - Manually refresh data from API
- `GET /api/carbon-intensity/history` - Get historical data

### 3. CLI Script (`scripts/fetch_carbon_intensity.php`)
- `php scripts/fetch_carbon_intensity.php current` - Fetch current data
- `php scripts/fetch_carbon_intensity.php forecast` - Fetch today's forecast
- `php scripts/fetch_carbon_intensity.php summary` - Display summary

### 4. Dashboard Integration
- Added carbon intensity card to the main dashboard
- Shows current intensity, classification, and trend
- Color-coded display based on intensity level

## Setup Instructions

### 1. Environment Configuration
Add to your `.env` file:
```bash
# Carbon Intensity API (National Grid ESO)
CARBON_API_KEY=
CARBON_API_URL=https://api.carbonintensity.org.uk
```

**Note:** The National Grid ESO API is currently free and doesn't require an API key. The `CARBON_API_KEY` is optional and reserved for future use.

### 2. Database Migration
The `external_carbon_intensity` table is already created in migration `003_add_analytics_tables.sql`:
```sql
CREATE TABLE IF NOT EXISTS external_carbon_intensity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) NOT NULL,
    datetime DATETIME NOT NULL,
    intensity DECIMAL(10, 2) NOT NULL COMMENT 'gCO2/kWh',
    forecast DECIMAL(10, 2) NULL COMMENT 'Forecasted intensity',
    actual DECIMAL(10, 2) NULL COMMENT 'Actual intensity',
    source VARCHAR(100) DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_region_datetime (region, datetime),
    INDEX idx_region (region),
    INDEX idx_datetime (datetime)
);
```

### 3. Automated Data Fetching
Set up a cron job to fetch carbon intensity data every 30 minutes:

```bash
# Add to your crontab (crontab -e)
*/30 * * * * cd /path/to/eclectyc-energy && php scripts/fetch_carbon_intensity.php current >> logs/carbon-fetch.log 2>&1

# Fetch daily forecast at 6 AM
0 6 * * * cd /path/to/eclectyc-energy && php scripts/fetch_carbon_intensity.php forecast >> logs/carbon-fetch.log 2>&1
```

### 4. Manual Testing
Test the implementation:
```bash
# Test API connectivity
curl -s "https://api.carbonintensity.org.uk/intensity"

# Test script manually
php scripts/fetch_carbon_intensity.php summary

# Test API endpoints (after setting up web server)
curl http://localhost:8000/api/carbon-intensity
curl -X POST http://localhost:8000/api/carbon-intensity/refresh
```

## API Response Format

### Current Intensity
```json
{
  "current_intensity": 220,
  "classification": {
    "level": "high",
    "label": "High",
    "color": "#FFA500"
  },
  "trend": "rising",
  "last_updated": "2025-11-07T08:00Z",
  "recent_data": [...]
}
```

### Classification Levels
- **Very Low**: ≤150 gCO2/kWh (Green #00FF00)
- **Low**: 151-200 gCO2/kWh (Light Green #90EE90)
- **Moderate**: 201-250 gCO2/kWh (Yellow #FFD700)
- **High**: 251-300 gCO2/kWh (Orange #FFA500)
- **Very High**: >300 gCO2/kWh (Red #FF0000)

## Dashboard Features

The carbon intensity card displays:
- Current intensity value in gCO2/kWh
- Classification level with color coding
- Trend indicator (Rising/Falling/Stable)
- Last update timestamp

## Future Enhancements

### 1. Regional Support
The API supports regional data. Future implementation could include:
- Postcode-based region detection
- Multiple region monitoring for multi-site portfolios

### 2. Carbon Footprint Calculator
Integration with consumption data to calculate:
- Total carbon emissions per site/meter
- Carbon savings from efficiency improvements
- Optimal consumption timing based on carbon intensity

### 3. Smart Alerts
- Notifications when carbon intensity drops to optimal levels
- Alerts for high carbon periods
- Integration with demand response systems

### 4. Carbon Reporting
- Monthly/annual carbon footprint reports
- Carbon intensity trend analysis
- Comparison with national averages

## Error Handling

The system gracefully handles:
- API timeouts and failures
- Missing data periods
- Database connection issues
- Invalid responses

Carbon intensity data is optional - dashboard functionality continues even if carbon data is unavailable.

## Maintenance

### Log Monitoring
Monitor logs for carbon fetch errors:
```bash
tail -f logs/app.log | grep CARBON_FETCH
```

### Data Cleanup
Old carbon intensity data can be cleaned up periodically:
```sql
DELETE FROM external_carbon_intensity 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Technical Notes

- Uses National Grid ESO's real-time carbon intensity API
- Data updated every 30 minutes by the grid operator
- Supports both forecast and actual intensity values
- Historical data available for up to 14 days via API
- No rate limiting on the free API tier