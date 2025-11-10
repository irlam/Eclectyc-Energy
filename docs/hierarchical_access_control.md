# Dashboard Widgets and Hierarchical Access Control - User Guide

## Overview

This implementation adds two major features to the Eclectyc Energy platform:

1. **Enhanced Dashboard Widgets** - Real-time health monitoring and consumption tracking
2. **Hierarchical Access Control** - Granular control over what users can see and manage

## Feature 1: Dashboard Widgets

### Yesterday's Energy Consumption Widget

A new widget on the dashboard displays the total energy consumption from yesterday:

**Features:**
- Total kWh consumption for yesterday
- Number of meters reporting
- Easy-to-read large format display
- Automatically calculated from aggregated data

**Location:** Dashboard homepage (accessible after login)

### Interactive Health Report Widget

A comprehensive health monitoring widget showing:

**Site Data Coverage:**
- Number of sites with recent data (last 7 days)
- Number of sites without recent data
- Total sites count
- Visual progress bar showing coverage percentage

**Reading Type Distribution:**
- Percentage of actual readings
- Percentage of estimated readings
- Visual breakdown of reading quality

**Expandable Sites List:**
- Click "View Sites Details" to see full site breakdown
- Shows each site's name, company, meter count, and status
- Color-coded rows (green for active, yellow for no recent data)
- Searchable and filterable

**Location:** Dashboard homepage, below the energy cards

## Feature 2: Hierarchical Access Control

### Access Hierarchy Structure

The system implements a 4-level hierarchy:

```
Company
  └─ Region
       └─ Site
            └─ Meter
```

**Access Rules:**
- Access granted at any level cascades down
- Company access → see all regions and sites under that company
- Region access → see all sites in that region
- Site access → see only that specific site and its meters
- Admin users automatically have full access

### Managing User Access

**For Administrators:**

1. Navigate to **Admin → Users**
2. Find the user you want to manage
3. Click the **🔐 Access** button
4. Select access levels:
   - **Company Access**: Grant access to entire companies
   - **Region Access**: Grant access to regional sites
   - **Site Access**: Grant access to specific sites
5. Use the search box to filter sites by name or company
6. Click **Save Access Configuration**

**Access Summary:**
- The page shows current access counts (companies, regions, sites)
- Changes take effect immediately
- Users see only the data they have access to

### User Access Examples

The seed data includes these example users (password: `admin123`):

| User | Email | Access Level | Can See |
|------|-------|--------------|---------|
| User One | user1@example.com | Site | Merv's House only |
| User Forty Five | user45@example.com | Site | Irlam's House only |
| Bolton Pub Manager | bolton.manager@jdw.com | Site | Bolton - Spinning Mule only |
| Regional Manager NW | regional.manager@example.com | Region | All Northwest sites |
| JDW Energy Manager | energy.manager@jdw.com | Company | All JDW sites (7 pubs) |
| Portfolio Manager | manager1@acme.com | Company | All Acme Properties sites |
| Southern Area Manager | south.manager@jdw.com | Multi-region | Southeast + London sites |

### What Gets Filtered

Access control is applied to:

✅ **Dashboard**
- All statistics (sites, meters, readings)
- Yesterday's consumption
- Health report
- Trend data
- Data quality metrics

✅ **Reports**
- Consumption reports
- Cost reports
- All site-based analytics

✅ **Admin Sections**
- Sites list
- Meters list
- Any site-specific data

### Security Features

- **Database-level filtering**: All queries filter at the database level for security
- **Cascade permissions**: Higher-level access automatically grants lower-level access
- **Admin override**: Admin users always have full access
- **Audit trail**: Access grants are tracked with granted_by user ID
- **No data leakage**: Users cannot access data outside their permissions

## Installation and Setup

### 1. Run Migration

```bash
php scripts/migrate.php
```

This creates the new tables:
- `user_company_access`
- `user_region_access`
- `user_site_access`

### 2. Load Sample Data (Optional)

To test the hierarchical access system:

```bash
mysql -u username -p database_name < database/seeds/seed_hierarchical_access.sql
```

This creates:
- 3 companies (JD Wetherspoon, Acme Properties, Green Energy Solutions)
- 4 regions (Northwest, Southeast, Midlands, London)
- 10 sites (including JDW pubs and residential properties)
- 7 test users with different access levels

### 3. Configure Initial Access

For existing users:

1. Log in as admin
2. Go to **Admin → Users**
3. Click **🔐 Access** for each user
4. Grant appropriate company/region/site access
5. Save

## Use Cases

### Use Case 1: Portfolio Management

**Scenario:** A property portfolio manager needs to see all sites they manage.

**Solution:**
- Grant company-level access to the portfolio company
- Manager sees all sites, meters, and consumption data
- Dashboard shows aggregated stats for their portfolio only

### Use Case 2: Regional Operations

**Scenario:** A regional manager oversees all sites in Northwest England.

**Solution:**
- Grant region-level access to "Northwest"
- Manager sees all sites in their region
- Can compare performance across regional sites
- Cannot see sites in other regions

### Use Case 3: Individual Site Access

**Scenario:** A homeowner wants to see only their own property's energy data.

**Solution:**
- Grant site-level access to their specific property
- Homeowner sees only their site and meters
- Complete privacy from other users' data

### Use Case 4: Multi-Company Energy Manager

**Scenario:** JD Wetherspoon has 7k sites across the UK with different regional managers.

**Solution:**
- Energy Manager: Company access to JDW → sees all 7k sites
- Northwest Regional Manager: Region access → sees only Northwest pubs
- Bolton Pub Manager: Site access → sees only Bolton - Spinning Mule
- Each user sees appropriate dashboard widgets and reports

## Technical Implementation

### Database Schema

**user_company_access:**
- Links users to companies they can access
- Includes granted_by tracking

**user_region_access:**
- Links users to regions they can access
- Allows regional-level permissions

**user_site_access:**
- Links users to individual sites
- Granular site-level control

### Service Layer

**AccessControlService** provides:
- `canAccessSite($userId, $siteId)` - Check site access
- `getAccessibleSiteIds($userId)` - Get all accessible sites
- `grantCompanyAccess()` / `grantRegionAccess()` / `grantSiteAccess()`
- `revokeCompanyAccess()` / `revokeRegionAccess()` / `revokeSiteAccess()`
- `getUserAccessSummary($userId)` - Get complete access overview

### User Model Extensions

```php
$user = User::find($userId);

// Get all accessible site IDs
$siteIds = $user->getAccessibleSiteIds();

// Check if user can access a specific site
if ($user->canAccessSite($siteId)) {
    // Show site data
}
```

## Best Practices

1. **Principle of Least Privilege**: Grant access at the lowest level needed
2. **Regular Audits**: Review user access periodically
3. **Use Regions for Scalability**: For large deployments, use region access rather than individual sites
4. **Company-Level for Executives**: Energy managers should have company access
5. **Site-Level for Operations**: Site managers and homeowners need only site access

## Troubleshooting

**Problem:** User can't see any data after login

**Solution:** 
- Check if user has any access grants (company/region/site)
- Admin users should see everything automatically
- Check that sites/companies are marked as active

**Problem:** Access changes not taking effect

**Solution:**
- Log out and log back in
- Clear browser cache
- Check database for correct access records

**Problem:** Need to bulk assign access

**Solution:**
- Use SQL to bulk insert access records
- Or use the admin UI to select multiple items at once

## Future Enhancements

Potential improvements for future versions:

- Bulk access assignment interface
- Access templates/roles
- Temporary access grants with expiration
- Access request workflow
- Detailed access audit logs in UI
- API endpoints for programmatic access management

## Support

For questions or issues:
1. Check this documentation
2. Review the seed data examples
3. Test with sample users provided
4. Contact system administrator

---

**Version:** 1.0  
**Last Updated:** 2025-11-10  
**Author:** Eclectyc Energy Development Team
