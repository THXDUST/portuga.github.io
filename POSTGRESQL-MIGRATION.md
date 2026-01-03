# PostgreSQL Migration Guide

This document describes the migration from MySQL to PostgreSQL and how to set up the application.

## Changes Made

### Database Configuration
- **Driver**: Changed from `mysql:` to `pgsql:`
- **Port**: Changed from 3306 to 5432
- **Environment Variables**: Added support for `DATABASE_URL` (Render.com format)

### SQL Schema Changes
All MySQL-specific syntax has been converted to PostgreSQL:

1. **Auto-increment**: `AUTO_INCREMENT` → `SERIAL`
2. **Enums**: `ENUM('value1', 'value2')` → `CHECK (column IN ('value1', 'value2'))`
3. **Timestamps**: Added triggers for `ON UPDATE CURRENT_TIMESTAMP` functionality
4. **JSON**: `JSON` → `JSONB` for better performance
5. **Insert Ignore**: `INSERT IGNORE` → `INSERT ... ON CONFLICT DO NOTHING`

### SQL Function Changes in PHP Code
- `NOW()` → `CURRENT_TIMESTAMP`
- `GROUP_CONCAT()` → `STRING_AGG()`
- `TIMESTAMPDIFF()` → `EXTRACT(EPOCH FROM ...)`
- `DATE_FORMAT()` → `TO_CHAR()`
- `DATE()` → `::date` casting
- `HOUR()`, `DAYNAME()`, `DAYOFWEEK()` → PostgreSQL equivalents
- `DATE_ADD()` → `+ INTERVAL` syntax
- `ON DUPLICATE KEY UPDATE` → `ON CONFLICT DO UPDATE`

## Setup Instructions

### Option 1: Using Docker Compose (Recommended for Local Development)

1. Make sure Docker and Docker Compose are installed
2. Clone the repository
3. Create a `.env` file (copy from `.env.example`)
4. Start the services:
   ```bash
   docker-compose up -d
   ```
5. Access the application at `http://localhost:8080`
6. Run database setup at `http://localhost:8080/dbsetup.php`

### Option 2: Using Render.com (Production)

1. Create a PostgreSQL database on Render.com
2. Copy the `DATABASE_URL` from Render.com dashboard
3. Set environment variables in Render.com:
   - `DATABASE_URL`: Your PostgreSQL connection string
   - Or use individual variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
4. Deploy the application
5. Run `dbsetup.php` once to initialize the database

### Option 3: Manual PostgreSQL Setup

1. Install PostgreSQL 12 or higher
2. Create a database:
   ```sql
   CREATE DATABASE portuga_db;
   ```
3. Update your `.env` file with database credentials:
   ```
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=portuga_db
   DB_USER=postgres
   DB_PASS=your_password
   ```
4. Run the application
5. Visit `dbsetup.php` to create tables and initial data

## Environment Variables

### Format 1: DATABASE_URL (Render.com)
```
DATABASE_URL=postgresql://username:password@hostname:5432/dbname
```

### Format 2: Individual Variables
```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=portuga_db
DB_USER=postgres
DB_PASS=your_password
```

## Database Schema

The application creates the following tables:
- `users` - User accounts
- `login_attempts` - Rate limiting
- `sessions` - Active sessions
- `password_resets` - Password reset tokens
- `roles` - User roles (Admin, Gerente, etc.)
- `permissions` - Granular permissions
- `role_permissions` - Role-permission mapping
- `user_roles` - User-role assignments
- `menu_groups` - Menu categories
- `menu_items` - Menu items
- `orders` - Customer orders
- `order_items` - Order line items
- `order_notes` - Order comments
- `restaurant_settings` - Configuration
- `reports` - Saved reports
- `resumes` - Job applications
- `ouvidoria` - Customer support
- `maintenance_mode` - System maintenance
- `employee_schedule` - Work schedules
- `service_points` - Service locations
- `admin_logs` - Audit trail

## Compatibility Notes

### What Works
✅ All CRUD operations
✅ Role-based permissions
✅ Order management
✅ Reporting with date grouping
✅ Session management
✅ OAuth authentication
✅ File uploads

### Breaking Changes from MySQL
- Event scheduler replaced with manual cleanup (can use pg_cron extension if needed)
- Different date/time formatting functions
- Case-sensitive collation by default
- Different regex syntax (if any regex is used)

## Troubleshooting

### Connection Errors
- Verify PostgreSQL is running: `pg_isready`
- Check credentials in `.env` file
- Ensure port 5432 is accessible

### Schema Errors
- Drop and recreate database if schema changes are made
- Run `dbsetup.php` to reinitialize

### Performance
- PostgreSQL uses JSONB which is faster than MySQL's JSON
- Indexes are automatically created for foreign keys
- Consider `VACUUM` and `ANALYZE` for optimization

## Migration from Existing MySQL Data

If you have existing MySQL data to migrate:

1. Export MySQL data:
   ```bash
   mysqldump -u user -p portuga_db > mysql_dump.sql
   ```

2. Use migration tools like `pgloader`:
   ```bash
   pgloader mysql://user:pass@localhost/portuga_db \
             postgresql://user:pass@localhost/portuga_db
   ```

3. Or manually:
   - Export data as CSV from MySQL
   - Import into PostgreSQL using `COPY` command

## Backup and Restore

### Backup
```bash
pg_dump -U postgres portuga_db > backup.sql
```

### Restore
```bash
psql -U postgres portuga_db < backup.sql
```

## Resources
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Render PostgreSQL Guide](https://render.com/docs/databases)
- [MySQL to PostgreSQL Migration Guide](https://wiki.postgresql.org/wiki/Converting_from_other_Databases_to_PostgreSQL)
