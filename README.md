# Eclectyc-Energy
Self‑hosted energy intelligence for estates, construction &amp; industry
# eclectyc-energy/.README.md  
# Energy Management Platform Documentation
# Last updated: 06/11/2024 14:45:00

# Eclectyc Energy Management Platform

A modern PHP-based energy management platform designed for deployment at https://eclectyc.energy/

## System Requirements

- PHP >= 8.2
- MySQL 5.7+ or 8.0+
- Composer 2.x
- Apache/Nginx with mod_rewrite
- Plesk hosting environment (recommended)

## Installation Instructions

### 1. Database Setup

1. Log into your Plesk control panel
2. Navigate to "Databases" → "Add Database"
3. Create a new database named `energy_platform`
4. Create a database user with full privileges
5. Note down the credentials (you'll need them for .env)

### 2. Upload Project Files

#### Method A: Via Plesk File Manager
1. Create a ZIP file of the entire project folder
2. Upload via Plesk File Manager to your domain directory
3. Extract the ZIP file
4. Ensure the folder structure is correct (public/ should be lowercase)

#### Method B: Via FTP/SFTP
1. Connect to your server using FTP credentials from Plesk
2. Upload all project files to your domain directory
3. Maintain the exact folder structure

### 3. Configure Environment

1. Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```

2. Edit `.env` file with your database credentials:
```
DB_HOST=localhost
DB_DATABASE=energy_platform
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### 4. Install Dependencies

Via Plesk SSH or Scheduled Tasks:
```bash
cd /path/to/eclectyc-energy
composer install --no-dev --optimize-autoloader
```

### 5. Set Document Root in Plesk

1. Go to "Hosting Settings" for your domain
2. Change Document Root to: `/httpdocs/eclectyc-energy/public`
3. Save changes

### 6. Set Permissions
```bash
chmod -R 755 /path/to/eclectyc-energy
chmod -R 777 /path/to/eclectyc-energy/logs
chmod 644 /path/to/eclectyc-energy/.env
```

### 7. Run Database Migrations
```bash
php scripts/migrate.php
```

### 8. Seed Database (Optional)
```bash
php scripts/seed.php
```

## Project Structure
```
eclectyc-energy/
├── app/                    # Application core
│   ├── config/            # Configuration files
│   ├── http/              # HTTP layer
│   │   ├── Controllers/   # Request controllers
│   │   └── routes.php     # Route definitions
│   ├── domain/            # Business logic
│   │   ├── ingestion/     # Data ingestion
│   │   ├── aggregation/   # Data aggregation
│   │   ├── tariffs/       # Tariff calculations
│   │   ├── analytics/     # Analytics engine
│   │   └── exports/       # Export handlers
│   ├── models/            # Data models
│   └── views/             # Twig templates
├── database/              # Database files
│   ├── migrations/        # Schema migrations
│   └── seeds/            # Data seeders
├── public/               # Web root (IMPORTANT: Set as document root)
│   ├── index.php         # Application entry point
│   ├── router.php        # Built-in server router
│   └── assets/           # CSS/JS/Images
├── scripts/              # CLI utilities
├── tools/                # Development tools
├── logs/                 # Application logs
├── vendor/               # Composer dependencies
├── .env.example          # Environment template
└── composer.json         # Dependencies definition
```

## Available Tools

### Health Check
Visit: https://eclectyc.energy/api/health
- Shows system status
- Database connectivity
- Environment configuration

### Structure Checker
```bash
php tools/check-structure.php
```
Or visit: https://eclectyc.energy/tools/check-structure.php

### Structure Viewer
Visit: https://eclectyc.energy/tools/show-structure.php
- Visual tree of project structure
- Helps verify deployment

## CLI Scripts

### Import CSV Data
```bash
php scripts/import_csv.php /path/to/data.csv
```

### Run Aggregation (for cron)
```bash
php scripts/aggregate_cron.php
```

### Export via SFTP
```bash
php scripts/export_sftp.php
```

## Cron Job Setup (Plesk)

1. Go to "Scheduled Tasks" in Plesk
2. Add new task:
   - Command: `/usr/bin/php /path/to/eclectyc-energy/scripts/aggregate_cron.php`
   - Schedule: Every hour (0 * * * *)

## Security Considerations

- **NEVER** commit `.env` file to version control
- Keep sensitive data within project boundaries (GDPR compliance)
- Regularly update dependencies: `composer update`
- Monitor logs for suspicious activity
- Use HTTPS only (configured in Plesk)

## GDPR Compliance

This platform is designed with GDPR in mind:
- All data stays within project boundaries
- No external data sharing by default
- Audit logging for data access
- User consent tracking capabilities
- Data export functionality

## Troubleshooting

### Case Sensitivity Issues
Linux servers are case-sensitive. Ensure:
- `public/` not `Public/`
- `vendor/` not `Vendor/`
Run `php tools/check-structure.php` to verify

### 500 Errors
1. Check `.env` file exists and is readable
2. Verify database credentials
3. Check error logs in `logs/` directory
4. Ensure `vendor/` directory exists (run `composer install`)

### Database Connection Issues
1. Verify MySQL is running
2. Check credentials in `.env`
3. Ensure database exists
4. Try `127.0.0.1` instead of `localhost`

## Development Roadmap

- [ ] AI-powered reporting layer (Python integration)
- [ ] Advanced tariff engine
- [ ] Real-time data streaming
- [ ] Mobile application API
- [ ] Multi-tenancy support
- [ ] Advanced user roles and permissions

## Inspiration & References

This platform draws architectural inspiration from:
- **OpenEMS**: https://github.com/OpenEMS/openems (Edge energy management)
- **MyEMS**: https://github.com/MyEMS/myems (Enterprise energy management)
- **OpenRemote**: https://github.com/openremote/openremote (IoT platform)
- **OpenEnergyDashboard**: https://github.com/OpenEnergyDashboard/OED (Energy visualisation)
- **BEMServer**: https://github.com/BEMServer (Building energy management)
- **BEMOSS**: https://github.com/bemoss/BEMOSS3.5 (Open-source BMS)

## Support

For deployment assistance or questions, consult:
- Plesk documentation: https://docs.plesk.com/
- PHP documentation: https://www.php.net/manual/
- Slim Framework: https://www.slimframework.com/docs/

## License

Proprietary - All rights reserved Eclectyc Energy 2024