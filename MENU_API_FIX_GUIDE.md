# Menu API Fix - Testing Guide

## Summary of Changes

Fixed a critical bug in `/api/admin/menu.php` where a duplicate execution block was attempting to execute API handlers before required variables were initialized. This caused the admin menu management to fail with:

```
Error loading menu management: Error: Erro ao carregar dados do cardápio
```

## What Was Fixed

**Problem**: Lines 208-227 contained a try-catch block that used `$method`, `$conn`, and `$action` before they were defined:

```php
try {
    switch ($method) {  // ❌ $method not defined yet
        case 'GET':
            handleGet($conn, $action);  // ❌ $conn and $action not defined yet
            // ...
    }
}
```

**Solution**: Removed the duplicate execution block. The proper MAIN EXECUTION section (starting at line 671) now handles all requests:

```php
// MAIN EXECUTION
try {
    $conn = getDBConnection();           // ✓ Define $conn
    $method = $_SERVER['REQUEST_METHOD']; // ✓ Define $method
    $action = $_GET['action'] ?? '';      // ✓ Define $action
    
    switch ($method) {
        case 'GET':
            handleGet($conn, $action);    // ✓ Variables properly initialized
            // ...
    }
}
```

## Expected API Responses

### Success Response (GET /api/admin/menu.php?action=groups)

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Bebidas",
      "description": "Bebidas quentes e frias",
      "parent_id": null,
      "display_order": 0,
      "is_active": true,
      "created_at": "2024-01-01 10:00:00",
      "parent_name": null,
      "item_count": 5,
      "subgroup_count": 2
    }
  ]
}
```

### Success Response (GET /api/admin/menu.php?action=items)

```json
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "group_id": 1,
      "name": "Café Expresso",
      "description": "Café forte e encorpado",
      "price": 5.50,
      "image_url": null,
      "ingredients": "Café 100% arábica",
      "is_available": true,
      "display_order": 0,
      "created_at": "2024-01-01 10:00:00",
      "group_name": "Bebidas"
    }
  ]
}
```

### Error Response (Database Connection Failed)

```json
{
  "error": "Erro no banco de dados. Tente novamente."
}
```

## How to Test

### 1. Manual Browser Test

1. Open the admin panel at `https://your-site.com/admin.html`
2. Navigate to the "Cardápio" (Menu) section
3. The page should load without errors showing:
   - List of menu groups
   - List of menu items
   - Add/Edit/Delete buttons

**Expected**: Menu management loads successfully without throwing "Erro ao carregar dados do cardápio"

### 2. API Test with curl

Test the groups endpoint:
```bash
curl -X GET "https://your-site.com/api/admin/menu.php?action=groups"
```

Expected response:
- Status code: 200
- Content-Type: application/json
- Body: JSON with `success: true` and `data` array

Test the items endpoint:
```bash
curl -X GET "https://your-site.com/api/admin/menu.php?action=items"
```

Expected response:
- Status code: 200
- Content-Type: application/json
- Body: JSON with `success: true` and `data` array

### 3. Browser Console Test

Open browser DevTools (F12) and run:

```javascript
// Test groups endpoint
fetch('/api/admin/menu.php?action=groups')
  .then(r => r.json())
  .then(data => {
    console.log('Groups response:', data);
    console.log('Has success:', data.success);
    console.log('Has data:', Array.isArray(data.data));
  });

// Test items endpoint
fetch('/api/admin/menu.php?action=items')
  .then(r => r.json())
  .then(data => {
    console.log('Items response:', data);
    console.log('Has success:', data.success);
    console.log('Has data:', Array.isArray(data.data));
  });
```

Expected console output:
```
Groups response: {success: true, message: "Success", data: Array(X)}
Has success: true
Has data: true

Items response: {success: true, message: "Success", data: Array(Y)}
Has success: true
Has data: true
```

## Validation Checklist

Use this checklist to verify the fix:

- [ ] PHP syntax is valid (`php -l api/admin/menu.php` shows no errors)
- [ ] Admin panel loads without JavaScript errors
- [ ] Menu management section displays groups and items
- [ ] API returns JSON with `success: true` for groups endpoint
- [ ] API returns JSON with `success: true` for items endpoint
- [ ] API returns JSON with `success: true` for full-menu endpoint
- [ ] Add Group button works
- [ ] Add Item button works
- [ ] Edit/Delete buttons display correctly
- [ ] No "Erro ao carregar dados do cardápio" error appears

## Troubleshooting

### If menu still doesn't load:

1. **Check Database Connection**
   - Verify `.env` file has correct database credentials
   - Test connection with: `php check-setup.php`

2. **Check Browser Console**
   - Open DevTools (F12) → Console tab
   - Look for JavaScript errors
   - Check Network tab for failed API requests

3. **Check PHP Error Logs**
   - Location depends on server configuration
   - Look for any PHP warnings or errors

4. **Verify Database Tables Exist**
   ```sql
   SELECT table_name FROM information_schema.tables 
   WHERE table_schema = 'portuga_db' 
   AND table_name IN ('menu_groups', 'menu_items');
   ```

5. **Check Permissions**
   - Ensure web server has read access to PHP files
   - Verify database user has SELECT permissions

## Files Modified

- `/api/admin/menu.php` - Removed duplicate execution block (21 lines)

## Impact

This fix ensures:
- Menu management loads correctly in admin panel
- All API endpoints return proper JSON responses
- Error handling works correctly
- No PHP errors break JSON responses

## Security Notes

- No security vulnerabilities introduced
- Error messages properly sanitized
- Database errors don't expose sensitive information
- All responses return valid JSON format
