# o-portuga

## Database Migrations

This project uses a simple SQL-based migration system to manage database schema changes.

### How to Run Migrations

#### Method 1: Via HTTP Endpoint (Recommended for Production)

Send a POST request to `/api/admin/run_migrations.php`:

```bash
# With admin authentication (after logging in as admin)
curl -X POST https://your-domain.com/api/admin/run_migrations.php

# Or with MIGRATIONS_TOKEN environment variable
curl -X POST https://your-domain.com/api/admin/run_migrations.php \
  -H "X-Migrations-Token: your-secret-token"
```

**Security**: The endpoint requires either:
- Admin user authentication (logged in via session), OR
- A valid `MIGRATIONS_TOKEN` environment variable matching the token in `X-Migrations-Token` header

#### Method 2: Directly via PHP CLI (Development)

```bash
php api/admin/run_migrations.php
```

### Creating New Migrations

1. Create a new SQL file in `database/migrations/` with a numbered prefix:
   - Format: `NNN_descriptive_name.sql`
   - Example: `002_add_user_preferences.sql`

2. Write your SQL statements in the file:
   ```sql
   -- Add new column
   ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
   
   -- Create index
   CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
   ```

3. Run migrations using one of the methods above

### Migration Files

Current migrations:
- `001_add_menu_item_image_columns.sql` - Adds BYTEA image storage to menu_items

### Migration Tracking

Migrations are tracked in the `schema_migrations` table:
- Each applied migration is recorded with its version and timestamp
- Already-applied migrations are automatically skipped
- Failed migrations are rolled back and reported

### Environment Variables

- `MIGRATIONS_TOKEN` (optional) - Secret token for running migrations without admin auth