# Testing Guide - Order System Fixes

This document provides comprehensive testing instructions for the order system fixes implemented in this PR.

## Changes Summary

### Backend (api/orders.php)
- ✅ Added `table_number` field to INSERT query
- ✅ Added logging for order creation debugging
- ✅ Confirmed `pickup_time` is optional (NULL allowed)

### Frontend - Cart Page (carrinho.html)
- ✅ Made pickup time field optional with updated label and help text
- ✅ Fixed table number field visibility logic
- ✅ Added initialization to show table number for logged-in users

### Frontend - Scripts (scripts.js)
- ✅ Made `pickup_time` optional in `finalizeOrder()`
- ✅ Updated WhatsApp message to include table number
- ✅ Fixed `saveOrder()` to send `user_id` and `table_number` correctly
- ✅ Improved logging with emojis

### Frontend - Navigation (auth.js)
- ✅ Added "📦 Meus Pedidos" link for logged-in users
- ✅ Added "👤 Meu Perfil" link for logged-in users
- ✅ Links appear dynamically based on login status

### Frontend - Orders Page (pedidos.js)
- ✅ Replaced localStorage with API calls
- ✅ Added user filtering (non-admin users see only their orders)
- ✅ Added auto-refresh every 30 seconds
- ✅ Display shows table number and order type

## Testing Scenarios

### Test 1: Local Order WITH Table Number (Logged In User)
**Steps:**
1. Login to the system
2. Add items to cart via menu.html
3. Navigate to carrinho.html
4. Verify "Número da Mesa" field is visible (not checked "Para Viagem")
5. Enter table number (e.g., 5)
6. Optionally enter pickup time (should not be required)
7. Click "Finalizar via WhatsApp"

**Expected Results:**
- ✅ Table number field is visible
- ✅ Order can be completed without pickup time
- ✅ WhatsApp message includes "Mesa 5"
- ✅ Order appears in admin panel with table_number = 5
- ✅ Order appears in "Meus Pedidos" page

### Test 2: Delivery Order WITHOUT Table Number
**Steps:**
1. Login to the system
2. Add items to cart
3. Navigate to carrinho.html
4. Check "Para Viagem" checkbox
5. Fill in delivery address
6. Calculate distance
7. Select payment method
8. Optionally enter pickup time
9. Click "Finalizar via WhatsApp"

**Expected Results:**
- ✅ Table number field is hidden
- ✅ Order can be completed without pickup time
- ✅ WhatsApp message does NOT include table number
- ✅ Order appears in admin panel with table_number = NULL
- ✅ Order type shows "🚗 Para Viagem"

### Test 3: Order WITHOUT Pickup Time
**Steps:**
1. Login to the system
2. Add items to cart
3. Navigate to carrinho.html
4. Leave pickup time field empty
5. Complete order (either local or delivery)

**Expected Results:**
- ✅ No error about missing pickup time
- ✅ Order completes successfully
- ✅ WhatsApp message does not include pickup time section
- ✅ Order saved with pickup_time = NULL in database

### Test 4: Navigation Links
**Steps:**
1. Visit any page (index.html, menu.html, carrinho.html, etc.)
2. Without logging in, verify navigation shows "Login" link
3. Login to the system
4. Verify navigation now shows:
   - "📦 Meus Pedidos"
   - "👤 Meu Perfil"
   - User name with "Sair" button
   - "🛠️ Admin" (if admin user)

**Expected Results:**
- ✅ Links appear dynamically based on login status
- ✅ "Meus Pedidos" redirects to pedidos.html
- ✅ "Meu Perfil" redirects to perfil.html
- ✅ Links work on all pages

### Test 5: Orders Page Loading from API
**Steps:**
1. Login as regular user
2. Create 2-3 orders
3. Navigate to pedidos.html
4. Verify orders are displayed
5. Wait 30 seconds
6. Verify page auto-refreshes

**Expected Results:**
- ✅ Orders load from API (not localStorage)
- ✅ User sees only their own orders
- ✅ Orders show table number when available
- ✅ Orders show order type (Local/Viagem)
- ✅ Page refreshes every 30 seconds
- ✅ Console logs show "📥 Fetching orders from API..."

### Test 6: Admin Panel Verification
**Steps:**
1. Login as admin
2. Navigate to admin panel
3. Check orders tab
4. Verify all orders created in previous tests appear

**Expected Results:**
- ✅ All orders are visible in admin panel
- ✅ table_number column shows correct values
- ✅ Orders without pickup_time show NULL or empty
- ✅ All order details are complete

## Browser Console Checks

Open browser console (F12) and verify:
- ✅ No JavaScript errors
- ✅ Console logs show order creation: "📤 Sending order to API:"
- ✅ Console logs show success: "✅ Order saved to database successfully:"
- ✅ Console logs show orders fetch: "📥 Fetching orders from API..."

## Database Verification (Optional)

If you have database access, verify:
```sql
SELECT id, order_number, table_number, pickup_time, order_type, user_id 
FROM orders 
ORDER BY created_at DESC 
LIMIT 10;
```

Expected:
- ✅ table_number is populated for local orders
- ✅ table_number is NULL for delivery orders
- ✅ pickup_time can be NULL
- ✅ user_id is populated when user is logged in

## Known Limitations

- Orders created via QR code with mesa parameter will have table_number set automatically
- Orders without login (if allowed) will have user_id = NULL
- WhatsApp integration depends on user having WhatsApp installed

## Troubleshooting

### Table Number Field Not Showing
- Verify user is logged in
- Check browser console for errors
- Verify "Para Viagem" is NOT checked
- Try refreshing the page

### Orders Not Appearing in Admin Panel
- Check browser console for API errors
- Verify database connection is working
- Check that api/orders.php is accessible

### Orders Not Loading in pedidos.html
- Check browser console for "📥 Fetching orders from API..."
- Verify API response is successful
- Check network tab for failed requests

## Security Notes

✅ **CodeQL Scan Passed** - No security vulnerabilities detected
✅ **CSRF Protection** - Maintained in auth flows
✅ **Input Validation** - Table numbers validated (must be > 0)
✅ **SQL Injection** - Prevented via prepared statements
✅ **XSS Protection** - No unsafe HTML injection

## Success Criteria

All tests must pass with these results:
- ✅ Orders save table_number correctly
- ✅ Pickup time is optional
- ✅ Navigation links work for all users
- ✅ Orders load from API in pedidos.html
- ✅ No JavaScript errors in console
- ✅ No SQL errors in backend logs
- ✅ WhatsApp messages include all relevant information
