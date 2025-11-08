# Database Seed Data - Quick Start Guide

## 📋 Overview

This guide explains how to populate your Eclectyc Energy database with sample data including UK energy suppliers, realistic tariffs, meters, and readings.

## 🚀 Quick Installation

### Option 1: Copy-Paste SQL (Recommended)

1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or command line)
2. Select your database:
   ```sql
   USE energy_platform;
   ```
3. Open the `SEED_DATABASE.sql` file
4. Copy the entire contents
5. Paste into your MySQL client and execute

### Option 2: Command Line

```bash
# From the repository root directory
mysql -u your_username -p energy_platform < SEED_DATABASE.sql
```

### Option 3: Using the PHP Seeder Script

```bash
# From the repository root directory
php scripts/seed.php
```

## 📊 What Gets Installed

### Users (3)
- **admin@eclectyc.energy** - System Admin (Password: `admin123`)
- **manager@eclectyc.energy** - Operations Manager (Password: `admin123`)
- **viewer@eclectyc.energy** - Read Only Analyst (Password: `admin123`)

⚠️ **IMPORTANT:** Change the admin password immediately after seeding!

### Energy Suppliers (10)
1. British Gas
2. EDF Energy
3. E.ON Next
4. Scottish Power
5. Octopus Energy
6. OVO Energy
7. Utility Warehouse
8. SSE Energy
9. Utilita Energy
10. Shell Energy

### UK Regions (12)
All standard UK electricity distribution regions

### Electricity Tariffs (41)

#### Variable Tariffs
- Average unit rate: **26.35p/kWh** (aligned with Ofgem price cap Oct-Dec 2025)
- Standing charges: **45p - 61p per day**

#### Fixed Tariffs
- 1-3 year fixed deals
- Unit rates: **24.0p - 25.8p/kWh**
- Standing charges: **44p - 60p per day**

#### EV Time-of-Use Tariffs
Special tariffs for electric vehicle charging:
- **E.ON Next Drive v9**: Off-peak **6.7p/kWh**
- **Octopus Intelligent Go**: Off-peak **7.5p/kWh**
- **Scottish Power EV Optimise**: Off-peak **8.0p/kWh**
- **OVO Charge Anytime**: Off-peak **9.0p/kWh**

#### Special Tariffs
- **Agile Octopus**: Dynamic 30-minute pricing
- **Octopus Tracker**: Daily wholesale price tracking
- **Utilita No Standing Charge**: 0p/day (but higher unit rate at 52.55p/kWh)
- **Green/Zero Carbon**: Renewable electricity options

### Sample Data
- 1 Company
- 3 Sites (Office, Warehouse, Branch)
- 4 Meters (3 electricity, 1 gas)
- 16 Meter readings (30 Oct - 06 Nov 2025)
- Daily, Weekly, Monthly, and Annual aggregations
- System settings

## 🔍 Tariff Details by Supplier

### British Gas
- Standard Variable: 26.35p/kWh, 53.68p/day
- Fixed 12M v81: 25.00p/kWh, 50.00p/day
- Electric Driver (EV): Peak 28.00p, Off-peak 12.50p
- Economy 7: Peak 35.00p, Off-peak 15.00p

### EDF Energy
- Standard Variable: 26.50p/kWh, 49.00p/day
- Fixed 12M v5: 25.00p/kWh, 47.00p/day
- GoElectric 35 (EV): Peak 32.00p, Off-peak 13.50p
- Green Electricity: 26.80p/kWh, 50.00p/day

### E.ON Next
- Next Pledge Tracker: 26.40p/kWh, 56.00p/day
- Next Drive v9 (EV): Peak 28.50p, **Off-peak 6.70p** ⚡
- Fixed 1 Year v12: 25.20p/kWh, 54.00p/day

### Octopus Energy
- Flexible Octopus: 24.50p/kWh, 45.00p/day
- Octopus 12M Fixed v6: 24.00p/kWh, 44.00p/day
- Intelligent Go (EV): Peak 30.00p, Off-peak 7.50p
- Agile (Dynamic): Variable pricing, 46.00p/day
- Tracker: 23.70p/kWh, 45.50p/day

### OVO Energy
- Standard Variable: 26.40p/kWh, 58.00p/day
- 1 Year Fixed 24: 25.10p/kWh, 56.00p/day
- Charge Anytime (EV): Peak 30.50p, Off-peak 9.00p
- Zero Carbon: 26.80p/kWh, 59.00p/day

### Scottish Power
- Standard Price Cap: 26.60p/kWh, 60.00p/day
- Fixed 1/2/3 Year options
- EV Optimise: Peak 29.00p, Off-peak 8.00p

### Other Suppliers
- **Utility Warehouse**: Club Tariff 26.00p/kWh, 47.00p/day
- **SSE Energy**: Standard 26.70p/kWh, 61.00p/day
- **Utilita**: No Standing Charge option (52.55p/kWh, 0p/day)
- **Shell Energy**: Fixed 12M 25.30p/kWh, 51.00p/day

## 📈 Market Context (2025)

All tariff rates are based on comprehensive web research of the UK energy market as of 2025:

- **Price Cap Period**: October - December 2025
- **Average Unit Rate**: 26.35p per kWh
- **Average Standing Charge**: 53.68p per day for electricity
- **Regional Variation**: Rates vary by region (London lowest, North Wales highest)
- **Payment Methods**: Direct Debit rates shown (prepayment slightly lower, standard credit higher)

## 🔧 Post-Installation Steps

1. **Change Default Passwords**
   ```sql
   -- Login as admin@eclectyc.energy and change password via UI
   -- Or update directly:
   UPDATE users SET password_hash = PASSWORD('your_new_password') WHERE email = 'admin@eclectyc.energy';
   ```

2. **Verify Data**
   ```sql
   SELECT COUNT(*) FROM suppliers;  -- Should return 10
   SELECT COUNT(*) FROM tariffs;    -- Should return 41
   SELECT COUNT(*) FROM meters;     -- Should return 4
   ```

3. **Access the Application**
   - Navigate to `/admin/tariffs` to view all seeded tariffs
   - Navigate to `/admin/meters` to view sample meters
   - Navigate to `/admin/sites` to view sample sites

## 🗑️ Resetting Data

To remove all seeded data and start fresh:

```sql
-- Warning: This will delete ALL data
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE annual_aggregations;
TRUNCATE TABLE monthly_aggregations;
TRUNCATE TABLE weekly_aggregations;
TRUNCATE TABLE daily_aggregations;
TRUNCATE TABLE meter_readings;
TRUNCATE TABLE tariffs;
TRUNCATE TABLE meters;
TRUNCATE TABLE sites;
TRUNCATE TABLE companies;
TRUNCATE TABLE regions;
TRUNCATE TABLE suppliers;
TRUNCATE TABLE settings;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- Then re-run SEED_DATABASE.sql
```

## 📝 Customizing Data

### Adding Your Own Tariffs

```sql
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from) 
VALUES (1, 'Custom Tariff', 'CUSTOM-01', 'electricity', 'fixed', 25.00, 45.00, '2025-01-01');
```

### Adding Your Own Meters

```sql
INSERT INTO meters (site_id, supplier_id, mpan, serial_number, meter_type, is_smart_meter, is_half_hourly) 
VALUES (1, 1, 'YOUR-MPAN-HERE', 'SERIAL123', 'electricity', TRUE, TRUE);
```

## 🔗 Related Files

- `SEED_DATABASE.sql` - Main standalone seed file (copy-paste ready)
- `database/seeds/seed_data.sql` - Seed file for PHP script
- `scripts/seed.php` - PHP seeder script runner

## 📞 Support

For issues or questions:
1. Check the main README.md
2. Review the database schema in `database/migrations/`
3. Check application logs in `logs/`

## 📚 References

Tariff data sourced from:
- Ofgem Price Cap (October-December 2025)
- Energy Suppliers' public tariff tables
- UK energy comparison websites
- Market research conducted November 2025

All rates are approximate and for demonstration purposes. Always verify current rates with actual suppliers.
