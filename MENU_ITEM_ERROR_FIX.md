# Menu Item Database Error Fix - Complete Guide

## Problem

When trying to save a menu item (create or update), the following error occurs:
```
Error saving item: Error: Erro no banco de dados. Tente novamente.
```

This error is thrown from `admin.js:1510` and originates from the API endpoint `/api/admin/menu.php` when performing `create-item` or `update-item` operations.

## Root Cause

The original error handling in `api/admin/menu.php` was catching PDO exceptions but returning only a generic error message without exposing the actual database error. This made it impossible to diagnose the real issue.

Potential causes include:
1. Missing database columns (`image_data`, `image_mime_type`, `delivery_enabled`)
2. Database migrations not yet applied
3. Column type mismatches
4. Foreign key constraint violations
5. Invalid SQL syntax

## Solution Applied

### 1. Enhanced Error Handling

Updated `api/admin/menu.php` to:
- **Log detailed error information** using `error_log()` for server-side debugging
- **Expose actual PDO errors** when `MENU_DEBUG_MODE` is enabled
- **Add try-catch blocks** around INSERT/UPDATE operations
- **Use PDO errorInfo()** to get specific database error details

### 2. Debug Mode

Debug mode (`MENU_DEBUG_MODE`) is now **temporarily enabled** to help diagnose the actual error. Once the issue is identified and fixed, it should be disabled for production.

**Location**: `api/admin/menu.php` line 20
```php
define('MENU_DEBUG_MODE', true);  // Set to false in production
```

When debug mode is enabled:
- Actual database error messages are exposed to the client
- Detailed logging is written to `api/admin/debug_upload.log`
- Server error logs contain full PDO error details

### 3. Error Information Now Includes

- **Error message**: Actual PDO exception message
- **Error code**: PDO error code
- **File and line**: Where the error occurred
- **SQL query**: The query that failed (in debug log)
- **Error info**: PDO errorInfo() array with SQLSTATE and driver-specific details

## Diagnosis Steps

### Step 1: Verify Database Columns

Run the test script to check if all required columns exist:
```bash
curl https://your-site.com/test-menu-columns.php
```

Or open in browser:
```
https://your-site.com/test-menu-columns.php
```

**Expected output** (if everything is OK):
```json
{
  "success": true,
  "table_exists": true,
  "columns": [...],
  "missing_columns": [],
  "errors": []
}
```

**If columns are missing**:
```json
{
  "success": false,
  "table_exists": true,
  "missing_columns": ["image_data", "image_mime_type", "delivery_enabled"],
  "errors": ["Missing required columns: image_data, image_mime_type, delivery_enabled"],
  "recommendation": "Run migrations using /api/admin/run_migrations.php"
}
```

### Step 2: Run Database Migrations

If columns are missing, run the migrations:

**Via Browser:**
1. Navigate to `https://your-site.com/run_migrations.html`
2. Log in as admin
3. Click "Run Migrations" button

**Via API** (with admin token):
```bash
curl -X POST https://your-site.com/api/admin/run_migrations.php \
  -H "X-Migrations-Token: your-token-here"
```

**Expected response**:
```json
{
  "success": true,
  "summary": {
    "total": 6,
    "applied": 3,
    "skipped": 3,
    "failed": 0
  },
  "migrations": [...]
}
```

### Step 3: Try Saving Menu Item Again

With debug mode enabled, attempt to save a menu item:

1. Open admin panel: `https://your-site.com/admin.html`
2. Navigate to Menu Management
3. Try to add or edit a menu item
4. If error occurs, note the **actual error message** shown

**Common errors and solutions:**

#### Error: `column "image_data" does not exist`
**Solution**: Run migrations (Step 2)

#### Error: `column "delivery_enabled" does not exist`
**Solution**: Run migrations (Step 2)

#### Error: `null value in column "group_id" violates not-null constraint`
**Solution**: Ensure a menu group is selected when creating/editing an item

#### Error: `invalid input syntax for type numeric`
**Solution**: Ensure price is a valid number

#### Error: `foreign key constraint violation`
**Solution**: The selected group_id doesn't exist. Create the group first.

### Step 4: Check Server Logs

Server error logs now contain detailed information. Check:
- PHP error log (location depends on your server configuration)
- `api/admin/debug_upload.log` (when debug mode is enabled)

**Example log entry**:
```
[2026-01-06 12:34:56] INSERT exception: SQLSTATE[42703]: Undefined column: 7 ERROR:  column "delivery_enabled" does not exist
LINE 2:                                        ingredients, is_avail...
```

## After Fixing the Issue

### Disable Debug Mode

Once the issue is identified and resolved:

1. Open `api/admin/menu.php`
2. Find line 20
3. Change:
   ```php
   define('MENU_DEBUG_MODE', false);  // Disabled for production
   ```
4. Commit the change

### Clean Up Debug Files

Remove debug log file:
```bash
rm api/admin/debug_upload.log
```

## Testing

After applying fixes, test the menu item operations:

### Test 1: Create Menu Item (without image)
```javascript
fetch('/api/admin/menu.php?action=create-item', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    group_id: 1,
    name: 'Test Item',
    description: 'Test description',
    price: 10.50,
    is_available: true,
    delivery_enabled: true
  })
})
.then(r => r.json())
.then(console.log);
```

### Test 2: Create Menu Item (with image)
Use the admin panel UI to upload an image and create a menu item.

### Test 3: Update Menu Item
```javascript
fetch('/api/admin/menu.php?action=update-item', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    id: 1,
    group_id: 1,
    name: 'Updated Item',
    price: 12.00,
    is_available: true,
    delivery_enabled: true
  })
})
.then(r => r.json())
.then(console.log);
```

## Files Modified

- `/api/admin/menu.php` - Enhanced error handling and debug mode
- `/test-menu-columns.php` - New diagnostic script (can be deleted after fix)
- `/MENU_ITEM_ERROR_FIX.md` - This documentation

## Required Database Columns

The `menu_items` table requires these columns:
- `id` (SERIAL PRIMARY KEY)
- `group_id` (INTEGER NOT NULL)
- `name` (VARCHAR(255) NOT NULL)
- `description` (TEXT)
- `price` (DECIMAL(10,2) NOT NULL)
- `image_url` (VARCHAR(512)) - legacy field
- `ingredients` (TEXT)
- `is_available` (BOOLEAN)
- `delivery_enabled` (BOOLEAN) - **Added via migration**
- `display_order` (INTEGER)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)
- `image_data` (BYTEA) - **Added via migration**
- `image_mime_type` (VARCHAR(100)) - **Added via migration**

## Migrations

Required migrations:
1. `001_add_menu_item_image_columns.sql` - Adds `image_data` and `image_mime_type`
2. `add_delivery_enabled_to_menu_items.sql` - Adds `delivery_enabled` column

## Security Notes

- Debug mode should **only be enabled temporarily** for troubleshooting
- Debug mode exposes internal error messages to clients
- Debug logs may contain sensitive data (form fields, file information)
- Always disable debug mode before deploying to production
- Delete `debug_upload.log` file after troubleshooting
- Delete `test-menu-columns.php` after diagnosis is complete

## Support

If the issue persists:
1. Share the actual error message from debug mode
2. Share the output of `test-menu-columns.php`
3. Share relevant server error logs
4. Verify database connection with `check-setup.php`
