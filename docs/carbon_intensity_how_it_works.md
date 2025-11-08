# Carbon Intensity Calculation - How It Works

## Overview

Carbon intensity measures how much CO₂ is emitted per kilowatt-hour (kWh) of electricity generated. This guide explains how the Eclectyc Energy platform calculates and uses carbon intensity data.

## What is Carbon Intensity?

**Carbon Intensity** = Amount of CO₂ emitted ÷ Amount of electricity generated

Measured in: **gCO₂/kWh** (grams of CO₂ per kilowatt-hour)

### Example:
- Grid carbon intensity: 250 gCO₂/kWh
- You consume: 10 kWh
- Your carbon emissions: 10 kWh × 250 gCO₂/kWh = 2,500 gCO₂ = **2.5 kg CO₂**

## How Carbon Intensity Varies

Carbon intensity changes throughout the day based on the **energy generation mix**:

### Low Carbon Intensity (Clean Energy)
**50-150 gCO₂/kWh** - Typically during sunny/windy periods
- ✅ High renewable generation (wind, solar)
- ✅ Nuclear power
- ✅ Hydroelectric
- ⚠️ Low/no fossil fuels

**Example:** Midday on a sunny, windy day
```
40% Wind + 30% Nuclear + 20% Solar + 10% Gas = ~100 gCO₂/kWh
```

### Medium Carbon Intensity
**150-300 gCO₂/kWh** - Mixed generation
- 🔄 Balanced mix of renewables and fossil fuels
- 🔄 Moderate wind/solar
- 🔄 Some gas generation

**Example:** Evening with moderate wind
```
30% Wind + 25% Nuclear + 35% Gas + 10% Imports = ~220 gCO₂/kWh
```

### High Carbon Intensity (Fossil Fuels)
**300-500+ gCO₂/kWh** - Heavy fossil fuel use
- ❌ Low renewable generation (calm, dark nights)
- ❌ High gas/coal usage
- ❌ Peak demand periods

**Example:** Winter evening, no wind
```
10% Wind + 20% Nuclear + 60% Gas + 10% Coal = ~400 gCO₂/kWh
```

## Data Sources

### National Grid ESO API
The platform uses the **National Grid Electricity System Operator (ESO)** API:

```
Base URL: https://api.carbonintensity.org.uk
```

**Endpoints used:**
1. `/intensity` - Current carbon intensity
2. `/intensity/date/{date}` - Historical data
3. `/intensity/date/{start}/{end}` - Date range

**Data provided:**
- **Forecast**: Predicted carbon intensity (updated every 30 minutes)
- **Actual**: Real measured carbon intensity (available after the fact)
- **Index**: Rating (very low, low, moderate, high, very high)

### Data Storage
Carbon intensity data is stored in the `external_carbon_intensity` table:

```sql
CREATE TABLE external_carbon_intensity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) NOT NULL,         -- 'GB' for Great Britain
    datetime DATETIME NOT NULL,            -- Timestamp
    intensity DECIMAL(10,2) NOT NULL,      -- gCO2/kWh
    forecast DECIMAL(10,2),                -- Forecasted value
    actual DECIMAL(10,2),                  -- Actual measured value
    source VARCHAR(100),                   -- 'National Grid ESO'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_region_datetime (region, datetime)
);
```

## Calculation Process

### Step 1: Fetch Carbon Intensity Data
The system periodically fetches carbon intensity from the National Grid ESO API:

```php
// CarbonIntensityService.php
public function getCurrentIntensity(): ?array
{
    $url = $this->apiUrl . '/intensity';
    $data = $this->makeApiRequest($url);
    
    return [
        'from' => $intensity['from'],
        'to' => $intensity['to'],
        'forecast' => $intensity['intensity']['forecast'],
        'actual' => $intensity['intensity']['actual'],
        'index' => $intensity['intensity']['index'],
    ];
}
```

**Update frequency:** Every 30 minutes (recommended)

### Step 2: Store Carbon Intensity
Data is stored with timestamps for historical analysis:

```php
INSERT INTO external_carbon_intensity (
    region, datetime, intensity, forecast, actual, source
) VALUES (
    'GB', 
    '2025-11-08 14:00:00', 
    245.50,  -- Average intensity
    245.00,  -- Forecasted
    246.20,  -- Actual (if available)
    'National Grid ESO'
);
```

### Step 3: Match with Consumption Data
When calculating emissions, the system joins consumption data with carbon intensity:

```sql
SELECT 
    da.date,
    da.total_consumption,                    -- kWh consumed
    AVG(eci.intensity) as avg_carbon_intensity,  -- gCO2/kWh
    AVG(eci.forecast) as avg_forecast,
    AVG(eci.actual) as avg_actual
FROM daily_aggregations da
LEFT JOIN external_carbon_intensity eci 
    ON DATE(eci.datetime) = da.date
    AND eci.region = 'GB'
WHERE da.meter_id = 123
    AND da.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY da.date
ORDER BY da.date DESC;
```

### Step 4: Calculate Emissions
For each day, calculate total CO₂ emissions:

```php
// For each row of data
$carbonIntensity = $row['avg_actual'] ?? $row['avg_carbon_intensity'] ?? 0;

// Emissions in grams CO2
$emissionsGrams = $consumption_kWh * $carbonIntensity;

// Convert to kilograms
$emissionsKg = $emissionsGrams / 1000;
```

**Example Calculation:**
```
Consumption: 100 kWh
Carbon Intensity: 250 gCO₂/kWh

Emissions = 100 × 250 = 25,000 gCO₂ = 25 kg CO₂
```

## Real-World Example

### Scenario: Office Building - One Week

| Date | Consumption (kWh) | Carbon Intensity (gCO₂/kWh) | Emissions (kg CO₂) |
|------|-------------------|------------------------------|---------------------|
| Mon  | 120.5 | 245 | 29.52 |
| Tue  | 115.3 | 198 | 22.83 |
| Wed  | 118.7 | 210 | 24.93 |
| Thu  | 122.1 | 185 | 22.59 |
| Fri  | 110.8 | 165 | 18.28 |
| Sat  | 45.2  | 120 | 5.42 |
| Sun  | 42.1  | 95  | 4.00 |

**Totals:**
- Total Consumption: 674.7 kWh
- Average Carbon Intensity: 174 gCO₂/kWh
- **Total Emissions: 127.57 kg CO₂**

**Insights:**
- ⚡ Monday had highest emissions (29.52 kg) despite not being the highest consumption day
- 🌍 Sunday had lowest emissions (4.00 kg) due to both low consumption AND low carbon intensity
- 💡 Shifting Monday's consumption to Sunday's carbon intensity would save: (245-95) × 120.5 / 1000 = **18.08 kg CO₂**

## Half-Hourly Granularity

For half-hourly meters, calculations are more precise:

### Half-Hourly Data Example

| Time | Consumption (kWh) | Carbon Intensity (gCO₂/kWh) | Emissions (kg CO₂) |
|------|-------------------|------------------------------|---------------------|
| 00:00-00:30 | 2.3 | 180 | 0.414 |
| 00:30-01:00 | 2.1 | 175 | 0.368 |
| ... | ... | ... | ... |
| 12:00-12:30 | 5.8 | 150 | 0.870 |
| 12:30-13:00 | 6.2 | 145 | 0.899 |
| ... | ... | ... | ... |
| 23:30-00:00 | 2.5 | 220 | 0.550 |

**Daily Total:** Sum of all 48 half-hourly periods

```sql
SELECT 
    mr.reading_date,
    SUM(mr.reading_value) as total_consumption,
    SUM(mr.reading_value * eci.intensity / 1000) as total_emissions_kg
FROM meter_readings mr
LEFT JOIN external_carbon_intensity eci 
    ON DATE(eci.datetime) = mr.reading_date
    AND HOUR(eci.datetime) = HOUR(mr.reading_time)
WHERE mr.meter_id = 123
    AND mr.reading_date = '2025-11-08'
GROUP BY mr.reading_date;
```

## Accuracy & Limitations

### ✅ Accurate When:
- Carbon intensity data is available for the consumption period
- Using actual (not forecast) carbon intensity values
- Half-hourly readings matched with half-hourly carbon intensity
- Recent data (last 12 months)

### ⚠️ Less Accurate When:
- Carbon intensity data missing for some periods
- Using forecasted instead of actual values
- Daily aggregations matched with averaged daily carbon intensity
- Historical data before carbon intensity tracking started

### 📊 Priority Order for Carbon Intensity Values:
1. **Actual** - Real measured value (most accurate)
2. **Forecast** - Predicted value (good accuracy)
3. **Intensity** - Generic value (fallback)

```php
$carbonIntensity = $row['avg_actual']        // First choice
                ?? $row['avg_forecast']      // Second choice
                ?? $row['avg_carbon_intensity']  // Fallback
                ?? 0;                         // No data available
```

## Use Cases

### 1. Carbon Footprint Reporting
Track your organization's carbon emissions:
```
Monthly Report:
- Total Consumption: 15,000 kWh
- Total Emissions: 3,250 kg CO₂
- Average Intensity: 217 gCO₂/kWh
```

### 2. Cost vs. Carbon Optimization
Compare cost and carbon:
```
Option A: Run at peak time
- Cost: £450
- Carbon: 85 kg CO₂
- Intensity: 340 gCO₂/kWh

Option B: Run at off-peak
- Cost: £180
- Carbon: 35 kg CO₂
- Intensity: 140 gCO₂/kWh

Savings: £270 + 50 kg CO₂
```

### 3. Renewable Energy Credits
Calculate renewable energy percentage:
```
If average carbon intensity is 200 gCO₂/kWh
And renewable-only generation is ~50 gCO₂/kWh

Renewable percentage ≈ (350 - 200) / (350 - 50) = 50%
```

### 4. Demand Shifting Opportunities
Identify when to shift flexible loads:
```
Current pattern:
- 7am: 50 kWh @ 320 gCO₂/kWh = 16.0 kg CO₂

Better pattern:
- 2pm: 50 kWh @ 120 gCO₂/kWh = 6.0 kg CO₂

Saving: 10 kg CO₂ (62.5% reduction)
```

## Accessing Carbon Intensity in the Platform

### 1. Via API
```bash
curl https://your-domain/api/carbon-intensity
```

Response:
```json
{
  "current": {
    "intensity": 245.5,
    "forecast": 245.0,
    "actual": 246.2,
    "index": "moderate",
    "from": "2025-11-08T14:00:00Z",
    "to": "2025-11-08T14:30:00Z"
  }
}
```

### 2. Dashboard Widget
The main dashboard shows current carbon intensity with color coding:
- 🟢 Green: < 150 gCO₂/kWh (Low)
- 🟡 Yellow: 150-300 gCO₂/kWh (Moderate)
- 🔴 Red: > 300 gCO₂/kWh (High)

### 3. Meter-Specific View
Navigate to: `/admin/meters/{id}/carbon`

Shows:
- Current grid carbon intensity
- Meter consumption with carbon intensity
- Total emissions calculation
- Daily breakdown with emissions per day

### 4. Database Queries
Direct SQL access:
```sql
-- Get latest carbon intensity
SELECT * 
FROM external_carbon_intensity 
WHERE region = 'GB' 
ORDER BY datetime DESC 
LIMIT 1;

-- Calculate emissions for a meter
SELECT 
    meter_id,
    SUM(total_consumption) as total_kwh,
    AVG(carbon_intensity) as avg_intensity,
    SUM(total_consumption * carbon_intensity / 1000) as total_emissions_kg
FROM (
    SELECT 
        da.meter_id,
        da.total_consumption,
        AVG(eci.intensity) as carbon_intensity
    FROM daily_aggregations da
    LEFT JOIN external_carbon_intensity eci 
        ON DATE(eci.datetime) = da.date
    WHERE da.meter_id = 123
        AND da.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY da.meter_id, da.date, da.total_consumption
) as emissions_data;
```

## Best Practices

### 1. Regular Data Updates
```php
// Run every 30 minutes via cron
0,30 * * * * php /path/to/scripts/fetch_carbon_intensity.php
```

### 2. Use Actual Values When Available
Always prefer `actual` over `forecast`:
```php
$intensity = $data['actual'] ?? $data['forecast'] ?? $data['intensity'];
```

### 3. Handle Missing Data
Implement fallbacks for missing carbon intensity:
```php
if (!$carbonIntensity) {
    // Use UK average: ~250 gCO₂/kWh
    $carbonIntensity = 250;
    $dataSource = 'estimated (UK average)';
}
```

### 4. Time Zone Awareness
Carbon intensity timestamps are in UTC, ensure proper conversion:
```php
$datetime = new DateTime($timestamp, new DateTimeZone('UTC'));
$datetime->setTimezone(new DateTimeZone('Europe/London'));
```

## Summary

**Carbon Intensity Calculation Formula:**
```
Emissions (kg CO₂) = Consumption (kWh) × Carbon Intensity (gCO₂/kWh) ÷ 1000
```

**Key Points:**
- ✅ Carbon intensity varies throughout the day (50-500 gCO₂/kWh)
- ✅ Lower intensity = more renewable energy in the grid
- ✅ Higher intensity = more fossil fuel generation
- ✅ Actual data is more accurate than forecasts
- ✅ Half-hourly matching is more precise than daily averages
- ✅ National Grid ESO provides free, reliable data for GB

**Related Documentation:**
- `docs/carbon_intensity_prediction_analysis.md` - Can you predict carbon intensity from meter readings?
- `app/Services/CarbonIntensityService.php` - Service implementation
- `app/Http/Controllers/Api/CarbonIntensityController.php` - API endpoints
- `database/migrations/003_add_analytics_tables.sql` - Database schema

---

**Document created:** November 8, 2025  
**Last updated:** November 8, 2025  
**Related to:** Carbon intensity calculation explanation
