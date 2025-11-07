# Tariff Switching Analysis Feature

## Overview

The Tariff Switching Analysis feature enables users to compare current tariff costs against alternative tariffs and identify potential savings opportunities. This feature analyzes historical consumption data to provide accurate cost comparisons and switching recommendations.

## Features

### 1. Switching Analysis
- Compare current tariff costs against all available alternative tariffs
- Calculate potential savings based on actual consumption history
- Analyze consumption data over customizable date ranges (recommended: 90 days)
- Support for time-of-use tariffs with peak/off-peak rates
- Automatic ranking of alternatives by potential savings

### 2. Historical Tracking
- Save all switching analyses for future reference
- Track analysis history per meter
- Compare recommendations over time
- Audit trail for switching decisions

### 3. Quick Analysis
- One-click analysis using the last 90 days of consumption data
- Automatic current tariff detection based on meter's supplier
- Instant results with detailed breakdowns

## Usage

### Accessing the Feature

Navigate to **Admin → Tariff Switching** in the main navigation menu (admin users only).

### Performing an Analysis

1. **Select a Meter**: Choose the meter you want to analyze from the dropdown
2. **Choose Analysis Period**: 
   - Use the "Use Last 90 Days" button for quick setup
   - Or manually select start and end dates
3. **Click "Analyze Tariffs"**: The system will:
   - Retrieve consumption data for the selected period
   - Calculate costs for the current tariff
   - Calculate costs for all alternative tariffs
   - Rank alternatives by potential savings
   - Display detailed results

### Understanding Results

The analysis results include:

#### Analysis Summary
- **Period Analyzed**: Date range used for consumption data
- **Total Consumption**: Total kWh consumed during the period
- **Days Analyzed**: Number of days included in the analysis
- **Current Tariff**: Name of the current tariff being used

#### Current Tariff Cost
- **Total Cost**: Complete cost including unit rates and standing charges
- **Unit Cost**: Cost based on consumption × unit rate
- **Standing Charge**: Daily standing charges × days in period

#### Recommended Tariff (if available)
- **Best Alternative**: The tariff offering the highest savings
- **Total Cost**: Projected cost with the recommended tariff
- **Potential Savings**: Amount that could be saved (£ and %)

#### Alternative Tariffs Table
A comprehensive comparison of all available tariffs showing:
- Tariff name and supplier
- Tariff type (fixed, variable, time of use, dynamic)
- Unit rates and standing charges
- Calculated total cost
- Potential savings compared to current tariff

### Historical Analyses

View past switching analyses for any meter:
1. Navigate to **Admin → Tariff Switching**
2. Select a meter and perform analysis
3. Access history from the analysis results or directly via the meter details

## Technical Details

### Database Schema

The feature uses a new table `tariff_switching_analyses`:

```sql
CREATE TABLE tariff_switching_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    current_tariff_id INT UNSIGNED NULL,
    recommended_tariff_id INT UNSIGNED NULL,
    analysis_start_date DATE NOT NULL,
    analysis_end_date DATE NOT NULL,
    current_cost DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    recommended_cost DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    potential_savings DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    savings_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    analysis_data JSON NULL,
    analyzed_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ...
);
```

### Key Components

1. **TariffSwitchingAnalyzer** (`app/Domain/Tariffs/TariffSwitchingAnalyzer.php`)
   - Core service for switching analysis logic
   - Uses `TariffCalculator` for cost calculations
   - Handles comparison logic and recommendations

2. **TariffSwitchingController** (`app/Http/Controllers/Admin/TariffSwitchingController.php`)
   - HTTP controller for the switching analysis UI
   - Handles form submissions and analysis requests
   - Manages historical analysis retrieval

3. **Views**
   - `tariff_switching.twig`: Main analysis interface
   - `tariff_switching_history.twig`: Historical analyses view

### API Endpoints

- `GET /admin/tariff-switching` - Display switching analysis form
- `POST /admin/tariff-switching/analyze` - Perform custom analysis
- `GET /admin/tariff-switching/{id}/quick` - Quick 90-day analysis
- `GET /admin/tariff-switching/{id}/history` - View historical analyses

## Best Practices

### Analysis Period Selection

**Recommended: 90 days**
- Provides sufficient data for accurate cost projections
- Captures seasonal variations
- Balances accuracy with recent consumption patterns

**Minimum: 30 days**
- Use for new meters or when recent data is more relevant
- May not capture seasonal patterns

**Maximum: 365 days**
- Useful for annual contract comparisons
- Captures full seasonal cycle
- Best for established meters with consistent usage

### Interpreting Results

- **High Savings (>10%)**: Strong switching opportunity, consider acting quickly
- **Moderate Savings (5-10%)**: Review terms and conditions, exit fees, and contract lengths
- **Low Savings (<5%)**: May not be worth switching due to switching costs/effort
- **No Savings**: Current tariff is competitive, continue monitoring

### Considerations

When making switching decisions, consider:
1. **Contract Terms**: Fixed vs. variable rates, contract length
2. **Exit Fees**: Cost to exit current contract
3. **Standing Charges**: Daily charges can significantly impact overall costs
4. **Time-of-Use Rates**: Ensure consumption patterns match tariff structure
5. **Supplier Reliability**: Service quality and customer support
6. **Future Consumption**: Anticipated changes in usage patterns

## Migration

To enable this feature, run the database migration:

```bash
php scripts/migrate.php
```

This will create the `tariff_switching_analyses` table.

## Limitations

Current limitations:
1. **Current Tariff Detection**: Simplified logic based on meter's supplier
   - Production deployment should implement a meter-tariff assignment system
2. **Time-of-Use Analysis**: Uses simplified time band detection
   - May need enhancement for complex tariff structures
3. **Export Meters**: Analysis assumes consumption (import) meters
   - Generation/export scenarios not yet supported

## Future Enhancements

Planned improvements:
1. **Automated Monitoring**: Regular analysis with email alerts for saving opportunities
2. **Switching Workflow**: Built-in process for requesting and tracking supplier switches
3. **Contract Management**: Track contract end dates and renewal opportunities
4. **Multi-Meter Analysis**: Analyze switching opportunities across multiple meters
5. **Carbon Impact**: Include carbon footprint comparison in switching analysis
6. **API Integration**: Direct tariff data feeds from suppliers
7. **What-If Scenarios**: Project costs under different consumption scenarios

## Support

For questions or issues with the Tariff Switching Analysis feature:
1. Check this documentation
2. Review the `tariff_switching_analyses` table for saved analyses
3. Consult application logs at `logs/app.log`
4. Contact the development team

## Version History

- **v1.0** (Nov 2025): Initial implementation
  - Basic switching analysis
  - Alternative tariff comparison
  - Historical tracking
  - Admin UI interface
