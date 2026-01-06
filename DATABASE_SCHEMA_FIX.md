# Database Schema Fix - Menu Items Image Columns

## Problem Fixed

**Error**: `SQLSTATE[42703]: Undefined column: 7 ERROR: column "image_data" of relation "menu_items" does not exist`

This error occurred when trying to save menu items because the PHP code expected columns `image_data` and `image_mime_type` to exist, but they were missing from the base database schema.

## Root Cause

1. **Base Schema Missing Columns**: The file `database/setup.sql` did not include `image_data` and `image_mime_type` columns in the `menu_items` table definition
2. **Migration Files Not Applied**: While migration files existed to add these columns (`001_add_menu_item_image_columns.sql` and `002_fix_image_data_column_type.sql`), they had not been executed on the production database
3. **Code Assumes Columns Exist**: The PHP code in `api/admin/menu.php` unconditionally tries to INSERT/UPDATE these columns

## Solution Applied

### Changed File: `database/setup.sql`

Added two columns to the `menu_items` table definition:

```sql
image_data TEXT,
image_mime_type VARCHAR(100),
```

Also added:
- Partial index for performance: `CREATE INDEX idx_menu_items_has_image`
- Column comments documenting the storage format

### Why This Solution

This is the **minimal change** that fixes the root cause:
- ✅ New database setups will have the columns automatically
- ✅ No PHP code changes required
- ✅ Consistent with existing migration files
- ✅ Follows PostgreSQL best practices (TEXT for base64 data)

## For Existing Databases

If you have an **existing database** that was created before this fix, you have two options:

### Option 1: Run Migrations (Recommended)

Run the existing migration files that add these columns:

```bash
# Via browser
https://your-site.com/run_migrations.html

# Via API (requires admin authentication)
curl -X POST https://your-site.com/api/admin/run_migrations.php
```

The following migrations will be applied:
- `001_add_menu_item_image_columns.sql` - Adds the columns
- `002_fix_image_data_column_type.sql` - Ensures correct data type (TEXT)

### Option 2: Manual SQL (if migrations fail)

If migrations don't work, you can manually add the columns:

```sql
-- Add columns if they don't exist
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_data TEXT;
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_mime_type VARCHAR(100);

-- Add index for performance
CREATE INDEX IF NOT EXISTS idx_menu_items_has_image 
  ON menu_items(id) 
  WHERE image_data IS NOT NULL;

-- Add documentation comments
COMMENT ON COLUMN menu_items.image_data IS 'Base64-encoded image data (compressed to JPEG format during upload, max 1024px)';
COMMENT ON COLUMN menu_items.image_mime_type IS 'MIME type of the stored image (always image/jpeg after processing)';
```

## For New Databases

New databases created using the updated `database/setup.sql` will automatically have these columns. No additional action needed!

## Verification

To verify the columns exist in your database:

1. **Via Test Script**:
   ```
   https://your-site.com/test-menu-columns.php
   ```
   
   Expected response:
   ```json
   {
     "success": true,
     "table_exists": true,
     "columns": [...],
     "missing_columns": []
   }
   ```

2. **Via PostgreSQL**:
   ```sql
   SELECT column_name, data_type 
   FROM information_schema.columns
   WHERE table_name = 'menu_items' 
     AND column_name IN ('image_data', 'image_mime_type');
   ```
   
   Should return 2 rows.

## Technical Details

### Column Specifications

| Column Name | Data Type | Nullable | Purpose |
|------------|-----------|----------|---------|
| `image_data` | TEXT | YES | Base64-encoded JPEG image data (max 1024px, compressed) |
| `image_mime_type` | VARCHAR(100) | YES | MIME type (always 'image/jpeg' after server-side processing) |

### How Image Upload Works

1. **Frontend** (`admin.js`): User selects image file
2. **Frontend Compression**: Image is compressed client-side to reduce size
3. **Upload**: Sent as multipart/form-data to `/api/admin/menu.php?action=create-item`
4. **Server Processing** (`menu.php`):
   - Validates file type (JPEG, PNG, WebP)
   - Resizes to max 1024px on longest side
   - Converts to JPEG format
   - Compresses to 80% quality
   - Base64 encodes the result
   - Stores in `image_data` column
5. **Storage**: Base64 string stored in database (not file system)

### Why TEXT Instead of BYTEA?

The application stores **base64-encoded strings**, not raw binary data:
- ✅ TEXT is appropriate for base64 strings
- ❌ BYTEA would require different encoding/decoding
- ✅ Base64 is portable across different database systems
- ✅ Easier to debug and inspect

## Files Modified

- ✅ `database/setup.sql` - Added columns to base schema

## Files NOT Modified (no changes needed)

- `api/admin/menu.php` - Already handles these columns correctly
- `admin.js` - Already sends image data correctly
- Migration files - Still valid for existing databases

## Related Documentation

- `MENU_ITEM_ERROR_FIX.md` - Previous debugging guide
- `IMAGE_FIX_GUIDE.md` - Image system documentation
- `001_add_menu_item_image_columns.sql` - Migration for existing databases
- `002_fix_image_data_column_type.sql` - Migration for data type fix

## Testing Recommendations

After applying this fix:

1. **Test new item creation without image**:
   - Create a menu item with no image upload
   - Verify it saves successfully
   - Verify `image_data` and `image_mime_type` are NULL

2. **Test new item creation with image**:
   - Create a menu item with an image upload
   - Verify it saves successfully
   - Verify image is displayed correctly

3. **Test item update with new image**:
   - Edit an existing item
   - Upload a new image
   - Verify the new image replaces the old one

4. **Test item update without changing image**:
   - Edit an existing item (that has an image)
   - Don't upload a new image
   - Verify the existing image is preserved

## Rollback (if needed)

If you need to rollback this change:

```sql
-- Remove columns (will lose all stored images!)
ALTER TABLE menu_items DROP COLUMN IF EXISTS image_data;
ALTER TABLE menu_items DROP COLUMN IF EXISTS image_mime_type;
DROP INDEX IF EXISTS idx_menu_items_has_image;
```

**Warning**: This will delete all uploaded images stored in the database!

## Support

If you still experience issues:
1. Verify columns exist: `https://your-site.com/test-menu-columns.php`
2. Check PHP error logs for detailed error messages
3. Enable debug mode in `menu.php` to see detailed errors
4. Verify database connection with `check-setup.php`
