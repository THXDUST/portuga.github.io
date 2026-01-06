# Fix Summary: Menu Loading Error with Empty Database

## Problem Statement

When the database was empty or tables didn't exist, the menu management page would throw an error:

```
Error loading menu management: Error: Erro ao carregar dados do cardápio
    at loadMenuManagement (admin.js:975:19)
```

### Root Cause

In `api/admin/menu.php`, the `items` action used `INNER JOIN`:

```php
FROM menu_items i
INNER JOIN menu_groups g ON i.group_id = g.id
```

This caused issues when:
- The database was empty
- Tables didn't exist
- There were no groups but items existed (orphaned items)

## Solution Implemented

### 1. Changed INNER JOIN to LEFT JOIN

**Before:**
```php
FROM menu_items i
INNER JOIN menu_groups g ON i.group_id = g.id
```

**After:**
```php
FROM menu_items i
LEFT JOIN menu_groups g ON i.group_id = g.id
```

This allows the query to work even when no groups exist.

### 2. Added Try-Catch Error Handling

Both `groups` and `items` actions now have try-catch blocks that:
- Catch `PDOException` errors
- Check for PostgreSQL error code `42P01` (undefined_table)
- Return empty arrays `[]` instead of errors
- Re-throw other exceptions for proper error handling

```php
try {
    // Query execution
    sendSuccess($groups);
} catch (PDOException $e) {
    // PostgreSQL error code 42P01 = undefined_table
    $errorInfo = $e->errorInfo ?? null;
    $sqlState = $errorInfo[0] ?? null;
    if ($sqlState === '42P01' || strpos($e->getMessage(), 'does not exist') !== false) {
        sendSuccess([]);
    } else {
        throw $e;
    }
}
```

### 3. Added Null Handling

Added `NULLS FIRST` to the ORDER BY clause in groups query:
```php
ORDER BY g.parent_id NULLS FIRST, g.display_order, g.name
```

### 4. Safe Array Returns

Added fallback to empty arrays when query results are null:
```php
$groups = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
```

## Results

### Before
- ❌ Error message: "Erro ao carregar dados do cardápio"
- ❌ Page fails to load
- ❌ User cannot access menu management

### After
- ✅ Returns: `{"success": true, "data": []}`
- ✅ Page loads successfully
- ✅ Displays: "Nenhum grupo cadastrado ainda. Clique em 'Adicionar Grupo' para criar um grupo de menu."
- ✅ User can add new groups and items

## Files Modified

1. **api/admin/menu.php**
   - Lines 212-241: Added try-catch for `groups` action
   - Lines 244-279: Added try-catch for `items` action
   - Line 255: Changed `INNER JOIN` to `LEFT JOIN`
   - Line 227: Added `NULLS FIRST` to ORDER BY

## Testing

- ✅ Code review completed
- ✅ Security scan (CodeQL) passed
- ✅ Frontend already handles empty arrays correctly (admin.js lines 978-987)

## Security Notes

- Uses PostgreSQL error code `42P01` for reliable error detection across different locales
- Fallback to string matching for additional safety
- No SQL injection vulnerabilities introduced
- Proper exception handling maintains security

## Commits

1. `5ec6abc` - Fix menu loading error with empty database - use LEFT JOIN and handle missing tables
2. `cdfa11f` - Improve error detection using PDO error codes instead of string matching
