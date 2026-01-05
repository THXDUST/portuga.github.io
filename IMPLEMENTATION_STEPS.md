# Implementation Steps for Image Upload Feature

## Database Migration

Run the following SQL migration to add image BLOB columns to the `menu_items` table:

```sql
-- Add BLOB column for storing dish images directly in database
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_data BYTEA;
ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_mime_type VARCHAR(100);

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_menu_items_has_image ON menu_items((image_data IS NOT NULL));
```

Or run the migration file:
```bash
psql -U your_username -d portuga_db -f database/migrations/add_image_blob_to_menu_items.sql
```

## Create Default Image

A placeholder file `images/default.png` is needed as a fallback for dishes without images.

You can:
1. Create your own default.png (recommended size: 400x200px)
2. Use the included default.png placeholder

## Testing the Feature

### Test Image Upload
1. Log in to the admin panel at `/admin.html`
2. Navigate to the "Cardápio" (Menu) tab
3. Click "Adicionar Item" to create a new dish
4. Fill in the required fields:
   - Group (Grupo)
   - Name (Nome do Prato)
   - Price (Preço)
5. Click "Fazer Upload de Imagem" and select an image file
   - Supported formats: JPEG, PNG, WebP
   - Maximum size: 5MB
   - Image will be automatically resized and compressed
6. Click "Salvar"

### Test Image Display
1. Navigate to `/menu.html` to view the public menu
2. Dishes with uploaded images should display them
3. Dishes without images should show the default.png fallback

### Test Default Fallback
1. Create a dish without uploading an image
2. The system will use `images/default.png` as the fallback
3. Verify the dish displays correctly in the menu

## API Endpoints

### Dish Image Endpoint
- **URL**: `/api/dish-image.php?id={dish_id}`
- **Method**: GET
- **Description**: Serves dish images from database BLOB or returns default.png fallback
- **Response**: Image binary data with appropriate Content-Type header

### Menu Item Creation/Update
- **URL**: `/api/admin/menu.php?action=create-item` or `/api/admin/menu.php?action=update-item`
- **Method**: POST (for file uploads)
- **Content-Type**: multipart/form-data (when uploading image)
- **Fields**:
  - `group_id`: Integer
  - `name`: String
  - `description`: String (optional)
  - `price`: Decimal
  - `is_available`: "1" or "0"
  - `delivery_enabled`: "1" or "0"
  - `image`: File (optional)
  - `id`: Integer (required for update)

## Security Features

1. **File Type Validation**: Only JPEG, PNG, and WebP formats are accepted
2. **File Size Limit**: Maximum 5MB upload size
3. **Server-side Processing**: 
   - Images are resized to max 1024px on the longest side
   - Images are converted to JPEG format with 80% quality
   - Preserves transparency for PNG images during processing
4. **Fallback**: If GD extension is not available, original file is stored without processing

## Troubleshooting

### Image not displaying
1. Check browser console for errors
2. Verify the dish ID exists in the database
3. Check if `image_data` column has data
4. Ensure `images/default.png` exists for fallback

### Upload fails
1. Verify file size is under 5MB
2. Check file format (must be JPEG, PNG, or WebP)
3. Ensure PHP `upload_max_filesize` and `post_max_size` are adequate
4. Check server error logs

### Image quality issues
1. Images are automatically compressed to JPEG at 80% quality
2. Images are resized to max 1024px to reduce database size
3. Original image is not preserved after processing
