# Quick Start Guide: Importing Your First Data

This guide walks you through importing energy consumption data into the Eclectyc Energy platform for the first time.

## Prerequisites

Before you can import data, you need:
1. ✅ Access to the admin interface
2. ✅ At least one site created
3. ✅ Meter details (MPAN or Meter Code)
4. ✅ CSV file with consumption data

## Step-by-Step Process

### Step 1: Create a Site

1. Navigate to **Admin → Sites** (`/admin/sites`)
2. Click **"Add Site"**
3. Fill in site details:
   - Site name (e.g., "Head Office")
   - Address
   - Region
4. Click **"Save"**

### Step 2: Add Meters

1. Navigate to **Admin → Meters** (`/admin/meters`)
2. Click **"Add Meter"**
3. Fill in meter details:
   - **MPAN:** Your 13-digit meter reference (e.g., `1234567890123`)
   - **Site:** Select the site you just created
   - **Meter Type:** Usually "Electricity"
   - **Half-hourly:** Check if meter provides 48 daily readings
   - **Active:** Check to include in reports
4. Click **"Save Meter"**

**Repeat for each meter you want to track.**

### Step 3: Prepare Your CSV File

Create a CSV file with your consumption data. Minimum required columns:

```csv
MPAN,Date,Reading
1234567890123,2025-11-01,125.5
1234567890123,2025-11-02,130.2
1234567890123,2025-11-03,128.7
```

**Important:**
- Ensure MPAN matches exactly what you entered in Step 2
- Use date format: `YYYY-MM-DD`
- Use decimal point for readings (not comma)

### Step 4: Test Import with Dry-Run

1. Navigate to **Admin → Imports** (`/admin/imports`)
2. Click **"Choose File"** and select your CSV
3. Select import type:
   - **Half-hourly:** For 48 readings per day
   - **Daily:** For 1 reading per day
4. **Check the "Dry run" checkbox**
5. Click **"Upload & Process"**

Review the results:
- ✅ **Success:** All rows validated successfully
- ⚠️ **Warnings:** Some issues found (check error details)
- ❌ **Error:** Major issues preventing import

### Step 5: Perform Actual Import

If dry-run was successful:

1. Return to **Admin → Imports**
2. Upload the same file
3. Select the same import type
4. **Uncheck "Dry run"**
5. Click **"Upload & Process"**

Wait for the import to complete. You'll see:
- Rows processed
- Rows imported
- Rows failed
- Any error messages

### Step 6: Verify Data

1. Navigate to **Reports → Consumption** (`/reports/consumption`)
2. Select the date range matching your import
3. Click **"Update Report"**
4. Verify:
   - Your site appears in the table
   - Consumption values look correct
   - Date range covers your import period

## What's Next?

### Run Aggregations

After importing raw readings, run aggregations to generate summary data:

```bash
php scripts/aggregate_orchestrated.php --all --verbose
```

This creates:
- Daily summaries
- Weekly summaries
- Monthly summaries
- Annual summaries

### Set Up Automated Imports

For regular data updates:

1. **Scheduled Imports:** Set up a cron job to run imports nightly
2. **SFTP Integration:** Configure automated file retrieval
3. **API Integration:** Connect directly to your meter data source

### Explore Reports

Navigate to different reports:
- **Consumption Report:** Overall energy usage
- **Cost Analysis:** Estimated costs by supplier
- **Carbon Dashboard:** Environmental impact

## Common First-Time Issues

### Issue: "Meter not found in database"

**Solution:** The MPAN in your CSV doesn't match any meters in the system.

1. Go to `/admin/meters`
2. Verify MPANs are entered correctly
3. Add missing meters
4. Re-run import

### Issue: "No data showing in reports"

**Solution:** Reports use aggregated data, not raw readings.

1. Run aggregation scripts (see above)
2. Or wait for nightly cron job to run
3. Refresh the report page

### Issue: "Import taking a long time"

**Solution:** Large files can take time to process.

- ✅ You can close the browser - import continues in background
- ✅ Check `/admin/imports/history` to see status
- ✅ For files > 10MB, consider using CLI importer

### Issue: "Date format errors"

**Solution:** Ensure dates are in `YYYY-MM-DD` format.

Wrong: `01/11/2025`, `Nov 1 2025`, `11-01-2025`
Correct: `2025-11-01`

## Example: Complete First Import

Let's walk through a complete example:

### 1. Your Situation
- Company: "Green Energy Ltd"
- Building: "Head Office"
- Meter: MPAN `1234567890123`
- Data: 7 days of daily readings

### 2. Create Site
```
Name: Head Office
Address: 123 Business Park
Region: London
```

### 3. Add Meter
```
MPAN: 1234567890123
Site: Head Office
Type: Electricity
Half-hourly: No (daily readings only)
Active: Yes
```

### 4. Create CSV File

Save as `head-office-nov-2025.csv`:

```csv
MPAN,Date,Consumption
1234567890123,2025-11-01,1250.5
1234567890123,2025-11-02,1280.2
1234567890123,2025-11-03,1310.8
1234567890123,2025-11-04,1275.3
1234567890123,2025-11-05,1290.1
1234567890123,2025-11-06,1245.7
1234567890123,2025-11-07,1265.4
```

### 5. Import Steps

1. Go to `/admin/imports`
2. Upload `head-office-nov-2025.csv`
3. Select "Daily totals"
4. Check "Dry run"
5. Click "Upload & Process"
6. If successful, uncheck "Dry run" and re-upload
7. Wait for completion

### 6. Expected Result

```
Import Summary:
- Rows processed: 7
- Rows imported: 7
- Rows failed: 0
- Data points handled: 7
```

### 7. Run Aggregation

```bash
php scripts/aggregate_orchestrated.php --all --date 2025-11-07 --verbose
```

### 8. View Report

1. Go to `/reports/consumption`
2. Set dates: Start `2025-11-01`, End `2025-11-07`
3. Click "Update Report"

You should see:
```
Site: Head Office
Consumption: 8,917.0 kWh (total for 7 days)
Average: 1,273.9 kWh per day
```

## Tips for Success

1. **Start Small:** Import 1-2 days of data first to verify everything works
2. **Use Dry-Run:** Always test with dry-run before committing large imports
3. **Check Meters:** Ensure all meters exist before importing
4. **Verify Dates:** Use consistent date format throughout
5. **Run Aggregations:** Reports need aggregated data to display properly
6. **Monitor History:** Check import history regularly to catch issues early

## Need Help?

- **Import Issues:** See `docs/import_troubleshooting.md`
- **CSV Format:** Check `/admin/imports` for accepted column names
- **Meters:** Visit `/admin/meters` to manage meter database
- **Reports:** Verify data at `/reports/consumption`

## Checklist

Before you start:
- [ ] Site created
- [ ] Meters added with correct MPANs
- [ ] CSV file prepared with correct format
- [ ] Date range covers intended period
- [ ] Dry-run successful
- [ ] Aggregations scheduled or run manually

After import:
- [ ] Import completed without errors
- [ ] Data visible in import history
- [ ] Aggregations run successfully
- [ ] Reports showing correct data
- [ ] Date ranges match expectations

---

**Congratulations!** You've successfully imported your first energy data into the platform. You can now explore the various reports and analytics features.

**Last Updated:** November 2024
**Version:** 1.0
