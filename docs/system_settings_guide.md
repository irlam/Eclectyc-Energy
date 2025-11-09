# System Settings Guide

## Overview

The System Settings page (`/admin/settings`) provides a user-friendly interface for configuring system-wide settings, particularly import throttling to prevent 504 timeout errors during large CSV imports.

## Accessing Settings

1. Log in as an **admin** user
2. Click **Admin** in the navigation menu
3. Click **⚙️ Settings**
4. Or navigate directly to `/admin/settings`

## Settings Categories

### Import Throttling

Controls how the system handles large CSV imports to prevent server overload.

#### Import Throttle Enabled
- **Type**: Toggle switch (ON/OFF)
- **Default**: OFF (disabled)
- **Purpose**: Enable throttling to prevent 504 timeouts
- **When to enable**: 
  - Large imports (>10,000 rows)
  - Experiencing 504 Gateway Timeout errors
  - Server resource constraints

**Visual Indicators**:
- When **OFF**: Shows warning "Throttling is disabled. Large imports may cause 504 timeouts."
- When **ON**: Shows note "Throttling is currently enabled. Import speeds will be reduced but 504 timeouts should be prevented."

#### Import Throttle Batch Size
- **Type**: Number input
- **Default**: 100
- **Unit**: rows per batch
- **Range**: 1-1000 (recommended: 50-200)
- **Purpose**: Number of rows to process before pausing
- **Effect**: 
  - Lower values = more frequent pauses = slower but safer
  - Higher values = fewer pauses = faster but riskier

#### Import Throttle Delay Ms
- **Type**: Number input
- **Default**: 100
- **Unit**: milliseconds
- **Range**: 10-1000 (recommended: 50-200)
- **Purpose**: How long to pause between batches
- **Effect**:
  - Lower values = shorter pauses = faster imports
  - Higher values = longer pauses = more server breathing room

### Import Limits

Maximum resource allocations for import processes.

#### Import Max Execution Time
- **Type**: Number input
- **Default**: 300
- **Unit**: seconds (5 minutes)
- **Purpose**: Maximum time an import can run
- **Note**: Should be less than web server timeout

#### Import Max Memory Mb
- **Type**: Number input
- **Default**: 256
- **Unit**: megabytes
- **Purpose**: Maximum memory an import process can use
- **Note**: Should be less than PHP memory_limit

## Using the Settings Page

### Changing a Setting

1. Navigate to `/admin/settings`
2. Locate the setting you want to change
3. For toggle switches: Click to toggle ON/OFF
4. For number inputs: Type or use +/- buttons
5. Click **💾 Save Settings** at the bottom
6. Confirmation message appears at top

### Resetting to Default

Each setting has a reset button (↺):

1. Click the **↺** button next to the setting
2. Confirm the reset action
3. Setting reverts to its default value
4. Changes are applied immediately

### Best Practices

#### For Small Imports (<5,000 rows)
```
Import Throttle Enabled: OFF
(Maximum speed, no throttling needed)
```

#### For Medium Imports (5,000-20,000 rows)
```
Import Throttle Enabled: ON
Batch Size: 100
Delay: 100ms
```

#### For Large Imports (20,000-100,000 rows)
```
Import Throttle Enabled: ON
Batch Size: 50
Delay: 200ms
Max Execution Time: 600 (10 minutes)
```

#### For Very Large Imports (>100,000 rows)
```
Import Throttle Enabled: ON
Batch Size: 25
Delay: 300ms
Max Execution Time: 900 (15 minutes)
Use Async Import!
```

## Troubleshooting

### Settings Won't Save

**Problem**: Click "Save Settings" but nothing happens

**Solutions**:
- Check you're logged in as admin
- Refresh the page and try again
- Check browser console for JavaScript errors
- Verify database connection in System Health

### Throttling Not Working

**Problem**: Enabled throttling but still getting 504 timeouts

**Solutions**:
1. **Verify it's enabled**: Check toggle shows "Enabled"
2. **Increase delay**: Try 200-300ms instead of 100ms
3. **Reduce batch size**: Try 25-50 instead of 100
4. **Use async import**: For very large files
5. **Check server timeouts**: Apache/Nginx may have lower limits

### Don't See Settings Page

**Problem**: No "Settings" link in admin menu

**Solutions**:
- Verify you're logged in as **admin** (not manager/viewer)
- Check `/admin/settings` URL directly
- Clear browser cache
- Verify routes are configured correctly

## Understanding Performance Impact

### Without Throttling
- **Speed**: ~500-1000 rows/second
- **Server Load**: HIGH
- **Timeout Risk**: HIGH
- **Best For**: Small imports, development

### With Default Throttling (100/100)
- **Speed**: ~50-100 rows/second
- **Server Load**: MEDIUM
- **Timeout Risk**: LOW
- **Best For**: Medium imports, production

### With Conservative Throttling (25/300)
- **Speed**: ~15-30 rows/second
- **Server Load**: LOW
- **Timeout Risk**: VERY LOW
- **Best For**: Large imports, constrained servers

## Examples

### Example 1: Enabling Throttling for First Time

**Scenario**: You've been getting 504 timeouts on 50,000 row imports.

**Steps**:
1. Go to `/admin/settings`
2. Toggle **Import Throttle Enabled** to **ON**
3. Leave other settings at defaults initially
4. Click **💾 Save Settings**
5. Try your import again
6. If still timing out, increase delay to 200ms

### Example 2: Tuning for Speed vs Stability

**Scenario**: Import works but takes too long.

**Steps**:
1. Go to `/admin/settings`
2. Increase **Batch Size** to 200 (from 100)
3. Decrease **Delay** to 50ms (from 100ms)
4. Click **💾 Save Settings**
5. Monitor imports for stability
6. Adjust as needed based on results

### Example 3: Resetting After Testing

**Scenario**: Made many changes during testing, want to start fresh.

**Steps**:
1. Go to `/admin/settings`
2. Click **↺** next to each changed setting
3. Confirm each reset
4. Settings return to defaults (throttling OFF)

## Visual Reference

### Setting States

**Toggle Switch - OFF**:
```
[O     ] Disabled
⚠️ Warning: Throttling is disabled. Large imports may cause 504 timeouts.
```

**Toggle Switch - ON**:
```
[     O] Enabled
✅ Note: Throttling is currently enabled. Import speeds will be reduced...
```

**Number Input**:
```
[100] rows per batch [↺]
```

## Related Documentation

- [Troubleshooting 504 Timeouts](troubleshooting_504_timeouts.md) - Complete guide to timeout issues
- [Import Progress & Throttling](import_progress_sftp_throttling.md) - Technical details
- [Import Troubleshooting](import_troubleshooting.md) - General import issues

## Support

If you have issues with settings:

1. **Check System Health**: `/tools/system-health`
2. **Review Logs**: `/tools/logs`
3. **Test with Small File**: Verify throttling works with small import first
4. **Check Documentation**: Links above
5. **Contact Support**: With specific error messages

---

**Last Updated**: November 2025  
**Version**: 1.0
