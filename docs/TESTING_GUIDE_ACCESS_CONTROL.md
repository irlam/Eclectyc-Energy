# Testing Guide: Dashboard Widgets & Hierarchical Access Control

## Prerequisites

1. Database migrations have been run
2. Seed data has been loaded from `seed_hierarchical_access.sql`
3. Web server is running and accessible

## Test Scenario 1: Dashboard Widgets (Admin User)

**Objective:** Verify dashboard widgets display correctly for admin users

**User:** admin@eclectyc.energy  
**Password:** admin123

### Steps:

1. Log in to the platform
2. Navigate to Dashboard (should land here by default)

### Expected Results:

✅ **Yesterday's Consumption Widget:**
- Widget appears on dashboard
- Shows yesterday's date
- Displays total kWh consumption
- Shows meter count
- If no data: Shows "No data available for yesterday"

✅ **Health Report Widget:**
- Shows "Sites Data Status (Last 7 Days)"
- Displays:
  - Sites with data count (green)
  - Sites without data count (yellow)
  - Total sites count (blue)
- Progress bar shows percentage of sites with data
- Shows "Reading Type Distribution"
- Displays actual vs estimated percentages
- Progress bar shows actual (teal) vs estimated (orange) split

✅ **Interactive Sites List:**
- "View Sites Details" button is visible
- Click button to expand sites list
- Table shows all sites with:
  - Site name
  - Company name
  - Meter count
  - Status (Active/No recent data)
- Sites with data have green background
- Sites without data have yellow background
- Click button again to collapse list

## Test Scenario 2: Site-Level Access (User1)

**Objective:** Verify user can only see their assigned site

**User:** user1@example.com  
**Password:** admin123  
**Expected Access:** Merv's House only

### Steps:

1. Log in with user1 credentials
2. Check Dashboard
3. Navigate to Admin → Sites
4. Navigate to Admin → Meters
5. Navigate to Reports → Consumption

### Expected Results:

✅ **Dashboard:**
- Total sites: 1
- All stats show data only for Merv's House
- Yesterday's consumption: Only Merv's House data
- Health report: Shows Merv's House status only

✅ **Sites List:**
- Shows only "Merv's House"
- Cannot see any other sites
- Total count: 1 site

✅ **Meters List:**
- Shows only meters from Merv's House
- Cannot see meters from other sites

✅ **Reports:**
- Consumption report shows only Merv's House
- No other sites visible in reports

## Test Scenario 3: Region-Level Access (Regional Manager)

**Objective:** Verify regional access shows all sites in region

**User:** regional.manager@example.com  
**Password:** admin123  
**Expected Access:** All Northwest region sites

### Steps:

1. Log in with regional manager credentials
2. Check Dashboard
3. Navigate to Admin → Sites
4. Navigate to Reports → Consumption

### Expected Results:

✅ **Dashboard:**
- Total sites: 3 (Bolton, Manchester, Liverpool)
- Stats aggregate all Northwest sites
- Health report shows all 3 Northwest sites

✅ **Sites List:**
- Shows Bolton - Spinning Mule
- Shows Manchester - Moon Under Water
- Shows Liverpool - Richard John Blackler
- Does NOT show Southeast or London sites
- Total count: 3 sites

✅ **Reports:**
- Shows all 3 Northwest sites
- Total consumption aggregates all 3
- Cannot see sites from other regions

## Test Scenario 4: Company-Level Access (Energy Manager)

**Objective:** Verify company access shows all company sites

**User:** energy.manager@jdw.com  
**Password:** admin123  
**Expected Access:** All JDW sites (7 pubs)

### Steps:

1. Log in with energy manager credentials
2. Check Dashboard
3. Navigate to Admin → Sites
4. Navigate to Reports → Consumption

### Expected Results:

✅ **Dashboard:**
- Total sites: 7 (all JDW pubs)
- Stats aggregate all JDW sites across all regions
- Health report shows all 7 sites

✅ **Sites List:**
- Shows all 7 JDW sites:
  - Bolton - Spinning Mule (Northwest)
  - Manchester - Moon Under Water (Northwest)
  - Liverpool - Richard John Blackler (Northwest)
  - Brighton - Bright Helm (Southeast)
  - Canterbury - Thomas Becket (Southeast)
  - London - Hamilton Hall (London)
  - London - Knights Templar (London)
- Does NOT show Acme Properties or Green Energy sites
- Total count: 7 sites

✅ **Reports:**
- Shows all 7 JDW sites
- Total consumption aggregates all sites
- Cannot see non-JDW sites

## Test Scenario 5: Access Management (Admin)

**Objective:** Verify admin can manage user access

**User:** admin@eclectyc.energy  
**Password:** admin123

### Steps:

1. Log in as admin
2. Navigate to Admin → Users
3. Click "🔐 Access" button for user1@example.com
4. Verify current access shows "Merv's House"
5. Grant additional access:
   - Check "Bolton - Spinning Mule" under Site Access
6. Click "Save Access Configuration"
7. Log out
8. Log in as user1@example.com
9. Check Sites list

### Expected Results:

✅ **Access Management Page:**
- Shows user information (User One, user1@example.com)
- Current access summary shows:
  - Companies: 0
  - Regions: 0
  - Sites: 1
- Company Access section shows all companies with checkboxes
- Region Access section shows all regions
- Site Access section shows all sites with search
- Merv's House is checked
- Can check/uncheck other sites

✅ **After Saving:**
- Success message appears
- Access summary updates to Sites: 2

✅ **User1 After Grant:**
- Can now see 2 sites (Merv's House and Bolton - Spinning Mule)
- Dashboard stats show both sites
- Cannot see other sites

## Test Scenario 6: Multi-Level Access

**Objective:** Verify cascade permissions work correctly

**User:** Create new test user via Admin → Users → Create

### Steps:

1. Create new user with viewer role
2. Grant company access to "JD Wetherspoon"
3. Grant region access to "Midlands"
4. Grant site access to "Irlam's House"
5. Log in as the new user

### Expected Results:

✅ **Access Cascade:**
- User can see:
  - All 7 JDW sites (from company access)
  - All Midlands sites (from region access, if any)
  - Irlam's House (from site access)
- Total accessible sites = JDW sites + Midlands sites + Irlam's House
- No duplicates if a site matches multiple criteria

## Test Scenario 7: Search Functionality

**Objective:** Verify site search works in access management

**User:** admin@eclectyc.energy

### Steps:

1. Navigate to Admin → Users
2. Click Access for any user
3. Scroll to Site Access section
4. Type "Bolton" in search box
5. Type "JDW" in search box
6. Clear search box

### Expected Results:

✅ **Search Results:**
- Typing "Bolton" shows only Bolton - Spinning Mule
- Other sites are hidden
- Typing "JDW" shows all JDW company sites
- Sites from other companies are hidden
- Clearing search shows all sites again
- Search is case-insensitive

## Test Scenario 8: Data Isolation

**Objective:** Verify users cannot access unauthorized data

**Users:** user1@example.com and user45@example.com

### Steps:

1. Note the total consumption for Merv's House as user1
2. Log out
3. Log in as user45@example.com
4. Check dashboard and reports

### Expected Results:

✅ **Data Isolation:**
- user45 sees ONLY Irlam's House data
- user45 cannot see Merv's House consumption
- Total sites for user45: 1 (Irlam's House)
- Dashboard shows different stats for each user
- No data leakage between users

## Test Scenario 9: Admin Override

**Objective:** Verify admin can see everything

**User:** admin@eclectyc.energy

### Steps:

1. Log in as admin
2. Check Dashboard, Sites, Meters, Reports
3. Navigate to Admin → Users → Access for admin user

### Expected Results:

✅ **Admin Access:**
- Dashboard shows ALL sites from all companies
- Sites list shows ALL sites (10 in seed data)
- Meters list shows ALL meters
- Reports include ALL sites
- Access management page shows info box: "This user has administrative privileges"
- Cannot select access for admin (automatic full access)

## Test Scenario 10: Pagination with Access Control

**Objective:** Verify pagination works with access filtering

**User:** energy.manager@jdw.com

### Steps:

1. Log in as JDW energy manager
2. Navigate to Admin → Meters
3. Try different per_page values (10, 25, 50)
4. Navigate through pages if enough meters exist

### Expected Results:

✅ **Pagination:**
- Only meters from JDW sites are shown
- Total count reflects JDW meters only
- Pagination works correctly
- No meters from other companies appear

## Edge Cases to Test

### No Access Granted
**User:** Create new viewer user without any access grants

**Expected:** 
- Dashboard shows 0 sites
- Sites list is empty
- Meters list is empty
- Reports show no data

### Inactive Sites
**User:** Admin user

**Steps:**
1. Mark a site as inactive
2. Check if it still appears in access management
3. Check if user with access to that site can see it

**Expected:**
- Inactive sites may or may not appear (implementation dependent)
- Should be consistent across the application

### Deleted Companies/Regions
**User:** User with company/region access

**Steps:**
1. Delete a company user has access to
2. Check user's accessible sites

**Expected:**
- Sites from deleted company are no longer accessible
- Foreign key constraints handle deletion properly
- No application errors

## Performance Testing

### Large Dataset
**Setup:** Create 1000+ sites via SQL

**User:** Admin user

**Steps:**
1. Load Sites list
2. Load Meters list
3. Load Reports
4. Use search in access management

**Expected:**
- Pages load in reasonable time (<2 seconds)
- Pagination works correctly
- Search is responsive
- No timeout errors

## Reporting Issues

When reporting issues, include:
- User email used for testing
- Steps to reproduce
- Expected vs actual results
- Screenshots if applicable
- Browser console errors (if any)

## Success Criteria

All tests should pass with:
✅ Correct data filtering by user access  
✅ No unauthorized data exposure  
✅ Widgets display correctly  
✅ Access management functions properly  
✅ Search and pagination work  
✅ Performance is acceptable  
✅ No security vulnerabilities  

---

**Test Version:** 1.0  
**Last Updated:** 2025-11-10  
**Status:** Ready for Testing
