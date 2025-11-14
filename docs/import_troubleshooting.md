# CSV Import Troubleshooting Guide

This guide helps you resolve common issues when importing meter reading data into the Eclectyc Energy platform.

## Common Import Errors

### 1. "CSV must include a column containing the meter identifier"

**Problem:** The system cannot find a column that identifies which meter the readings belong to.

**Solution:**
- Ensure your CSV has at least one of these column headers:
  - `MPAN`, `MPANCore`, `MPAN_Core`
  - `MeterCode`, `Meter_Code`, `Meter`
  - `MeterID`, `Meter_ID`
  - `SerialNumber`, `Serial`
  - `SupplyNumber`
  - `MPRN` (for gas meters)

**Example CSV Header:**
```csv
MPAN,Date,Reading
1234567890123,2025-11-01,125.5
```

### 2. "Meter not found in database"

**Problem:** The MPAN/Meter Code in your CSV doesn't exist in the system yet.

**Solutions:**

**Option A: Add meters via Admin UI**
1. Go to `/admin/meters`
2. Click "Add Meter"
3. Enter the MPAN and site details
4. Save
5. Re-run your import

**Option B: Check MPAN format**
- Ensure MPANs in CSV exactly match those in the database
- Check for extra spaces or different case
- Verify 13-digit format for electricity meters

### 3. "Invalid date format"

**Problem:** Date columns are not in a recognized format.

**Supported formats:**
- `YYYY-MM-DD` (recommended)
- `DD/MM/YYYY`
- `MM/DD/YYYY`
- `YYYY-MM-DD HH:MM:SS`

**Example:**
```csv
MPAN,Date,Reading
1234567890123,2025-11-01,125.5
1234567890123,2025-11-02,130.2
```

### 4. "Missing required columns"

**Problem:** CSV is missing Date or Value columns.

**Required columns:**
- Meter identifier (MPAN, MeterCode, etc.)
- Date or DateTime
- At least one value column (Reading, Value, Consumption, kWh)

**Minimum CSV structure:**
```csv
MeterCode,Date,Reading
METER001,2025-11-01,125.5
```

## Import Types

### Half-Hourly (HH) Data

For meters that record 48 readings per day (every 30 minutes).

**CSV Format Option 1 - Wide format:**
```csv
MPAN,Date,HH01,HH02,HH03,...,HH48
1234567890123,2025-11-01,10.5,10.2,10.1,...,11.2
```

**CSV Format Option 2 - Long format:**
```csv
MPAN,DateTime,Reading
1234567890123,2025-11-01 00:30:00,10.5
1234567890123,2025-11-01 01:00:00,10.2
1234567890123,2025-11-01 01:30:00,10.1
```

### Daily Data

For meters that record one total reading per day.

**CSV Format:**
```csv
MPAN,Date,Reading
1234567890123,2025-11-01,1250.5
1234567890123,2025-11-02,1280.2
1234567890123,2025-11-03,1310.8
```

## Best Practices

### 1. Use Dry-Run Mode First

Always test your import with dry-run mode enabled:
1. Check the "Dry run" checkbox
2. Upload your file
3. Review any errors
4. Fix issues in your CSV
5. Re-upload without dry-run to save data

### 2. Verify MPANs Before Importing

Before importing large files:
1. Go to `/admin/meters`
2. Verify all MPANs from your CSV exist in the system
3. Add any missing meters

### 3. Check Date Ranges

Ensure your import dates:
- Are not in the future
- Match the expected format
- Cover the intended period
- Don't have gaps or duplicates

### 4. File Size Considerations

- **Small files (< 1 MB):** Import directly via web UI
- **Medium files (1-10 MB):** Use dry-run first, then import
- **Large files (> 10 MB):** Consider using CLI importer for better performance

## CLI Import Alternative

For advanced users or large files, use the command-line importer:

```bash
# Basic import
php scripts/import_csv.php -f /path/to/file.csv -t hh

# Dry-run validation
php scripts/import_csv.php -f /path/to/file.csv -t hh --dry-run

# Daily data import
php scripts/import_csv.php -f /path/to/file.csv -t daily
```

**Advantages of CLI:**
- Progress bar showing real-time status
- Better performance for large files
- Detailed error logging
- Can run in background

## Viewing Import Results

After import:

1. **Check Import History:** `/admin/imports/history`
   - See batch ID
   - View success/failure counts
   - Review error messages

2. **View Consumption Report:** `/reports/consumption`
   - Select date range matching your import
   - Verify data appears correctly
   - Check consumption totals

3. **Check Audit Logs:**
   - All imports are logged with timestamps
   - Includes user, file, and outcome details

## Getting Help

If you continue to have issues:

1. **Check the error message carefully** - it usually indicates exactly what's wrong
2. **Verify CSV format** - ensure headers match accepted aliases
3. **Check meter setup** - ensure meters exist before importing
4. **Use dry-run mode** - validate before committing data
5. **Review import history** - check previous successful imports for format reference

## Quick Reference: Accepted Column Names

| Purpose | Accepted Names |
|---------|---------------|
| **Meter ID** | MPAN, MPANCore, MPAN_Core, MeterCode, Meter_Code, Meter, MeterID, Meter_ID, MeterReference, Meter_Reference, MeterRef, MeterSerial, Meter_Serial, MeterSerialNumber, Serial, SerialNumber, SupplyNumber, MPRN |
| **Date** | Date, ReadDate, Read_Date, ReadingDate, PeriodDate, BillDate, InsertDate |
| **Time** | Time, ReadTime, Read_Time, ReadingTime, PeriodTime |
| **DateTime** | DateTime, Timestamp, ReadDateTime, Read_DateTime, ReadingDateTime |
| **Value** | Reading, ReadValue, Read_Value, Value, Consumption, kWh, Wh, Usage |
| **Unit** | Unit, Units, UOM |

## Example CSV Templates

### Template 1: Simple Daily Import
```csv
MPAN,Date,Consumption
1234567890123,2025-11-01,1250.5
1234567890123,2025-11-02,1280.2
1234567890123,2025-11-03,1310.8
```

### Template 2: Half-Hourly with DateTime
```csv
MeterCode,DateTime,Value
METER001,2025-11-01 00:30:00,10.5
METER001,2025-11-01 01:00:00,10.2
METER001,2025-11-01 01:30:00,10.1
```

### Template 3: Multiple Meters Daily
```csv
MPAN,Date,Reading,Site
1234567890123,2025-11-01,1250.5,Building A
9876543210987,2025-11-01,850.2,Building B
1234567890123,2025-11-02,1280.2,Building A
9876543210987,2025-11-02,870.5,Building B
```

---

## Related Documentation

- [Troubleshooting 504 Gateway Timeouts](troubleshooting_504_timeouts.md) - Solutions for import timeout errors
- [Import Progress, SFTP & Throttling](import_progress_sftp_throttling.md) - Advanced import features
- [Quick Start Import Guide](quick_start_import.md) - Getting started with imports

---

**Last Updated:** November 2025
**Version:** 1.1
