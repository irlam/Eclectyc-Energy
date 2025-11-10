# Daily Usage Comparison Report

## Overview

The Daily Usage Comparison report provides a visual analysis of energy consumption patterns across the last 7 days, breaking down usage by half-hourly (HH) periods. This allows users to identify consumption trends, compare usage across different days of the week, and understand peak usage times.

## Features

### 1. Last 7 Days HH Analysis
- Displays consumption data for the previous 7 days (yesterday and 6 days prior)
- Aggregates all half-hourly readings across selected sites
- Shows each day as a separate line on the graph
- X-axis displays HH periods from 00:00 to 23:30 (48 periods)
- Y-axis shows total consumption in kWh (or per-metric units)

### 2. Per-Metric Analytics
- Optional toggle to view consumption normalized by metric variables
- Displays consumption per square meter, per bed, or other custom metrics
- Requires meters to have metric variables configured
- Useful for league table analysis and benchmarking across different-sized properties

### 3. Site Filtering
- Automatically filters data based on user's accessible sites
- Respects hierarchical access control
- Aggregates consumption across all accessible meters

### 4. Visual Breakdown
- Color-coded lines for each day of the week
- Interactive chart with hover tooltips showing exact values
- Legend showing day names and dates
- Summary statistics (total consumption, average per day, etc.)

## Accessing the Report

Navigate to: **Reports → Daily Comparison**

URL: `/reports/daily-usage-comparison`

## Using the Report

### Basic View
1. The report loads showing the last 7 days of data automatically
2. Each line represents a different day (Monday, Tuesday, etc.)
3. Hover over the chart to see exact consumption values for each HH period
4. Review the summary statistics at the top for overall trends

### Per-Metric Analysis
1. Check the "Show per-metric analysis" checkbox
2. The report will reload showing consumption normalized by metric variables
3. Only meters with configured metric variables will be included
4. Useful for comparing properties of different sizes

## Configuring Metric Variables

To enable per-metric analysis, configure metric variables on your meters:

1. Navigate to **Admin → Meters**
2. Click "Edit" on a meter
3. Scroll to the "📊 Per-Metric Analytics (Optional)" section
4. Enter a **Metric Variable Name** (e.g., "Square Meters", "Beds", "Occupancy")
5. Enter a **Metric Variable Value** (e.g., 50)
6. Save the meter

### Example Calculation

If a meter has:
- Metric Variable Name: "Square Meters"
- Metric Variable Value: 50
- HH consumption: 1.3 kWh at 03:00

The per-metric value will be:
```
1.3 kWh ÷ 50 = 0.026 kWh per Square Meter
```

## Understanding the Chart

### X-Axis: Half-Hourly Periods
- Period 1: 00:00
- Period 2: 00:30
- Period 3: 01:00
- ...
- Period 48: 23:30

### Y-Axis: Consumption
- **Standard View**: Total kWh across all selected sites
- **Per-Metric View**: Normalized consumption (e.g., kWh per square meter)

### Lines: Days of the Week
Each line represents consumption for a specific day:
- 🔴 Red: Day 1 (oldest)
- 🟠 Orange: Day 2
- 🟡 Yellow: Day 3
- 🟢 Green: Day 4
- 🔵 Blue: Day 5
- 🟣 Purple: Day 6
- 🩷 Pink: Day 7 (most recent)

## Use Cases

### 1. Identifying Peak Usage Times
Identify which HH periods have the highest consumption across your sites to optimize tariffs or usage patterns.

### 2. Comparing Day-of-Week Patterns
Compare weekday vs. weekend consumption patterns to understand operational differences.

### 3. Benchmarking Sites
Use per-metric analysis to create league tables of energy efficiency across properties of different sizes.

### 4. NHS Example
For NHS sites:
- Metric Variable Name: "Beds"
- Metric Variable Value: 200 (number of beds)
- Report shows: kWh per bed across all sites
- Enables fair comparison across hospitals of different sizes

## Technical Details

### Data Source
- Table: `meter_readings`
- Filters: `reading_date BETWEEN (yesterday - 6 days) AND yesterday`
- Grouping: By `reading_date` and `period_number`

### Aggregation Logic
```sql
SELECT 
    mr.reading_date,
    mr.period_number,
    SUM(mr.reading_value) as total_kwh,
    SUM(CASE 
        WHEN m.metric_variable_value > 0 
        THEN mr.reading_value / m.metric_variable_value 
        ELSE 0 
    END) as total_per_metric
FROM meter_readings mr
INNER JOIN meters m ON m.id = mr.meter_id
WHERE mr.reading_date BETWEEN :start AND :end
    AND m.is_active = 1
    AND mr.period_number BETWEEN 1 AND 48
GROUP BY mr.reading_date, mr.period_number
```

### Visualization
- Library: Chart.js 4.4.0
- Chart Type: Line chart with multiple datasets
- Interaction Mode: Index (shows all days at the same HH period)

## Permissions

Required role: `admin` or `manager`

## Troubleshooting

### No Data Displayed
**Possible Causes:**
1. No half-hourly data has been imported for the last 7 days
2. All meters are inactive
3. User has no accessible sites configured

**Solutions:**
1. Import HH data via Admin → Imports
2. Activate meters via Admin → Meters
3. Configure user access via Admin → Users → Manage Access

### "No metric variables configured" Warning
**Cause:** Attempting to view per-metric analysis without configured metric variables

**Solution:** Configure metric variables on meters as described above

### Missing Days in Chart
**Cause:** No readings for certain dates

**Solution:** Import missing data or check aggregation process

## Related Features

- **Consumption Report** (`/reports/consumption`): Site-by-site consumption summary
- **HH Visualization** (`/reports/hh-consumption`): Single-day HH breakdown with actual/estimated flags
- **Metric Variables**: Configured in meter edit form (`/admin/meters/{id}/edit`)

## Future Enhancements

Potential additions to this report:
- Custom date range selection (currently fixed at last 7 days)
- Export to CSV/Excel
- Site-specific filtering
- Comparison with previous week
- Peak/off-peak highlighting
- Download chart as image
