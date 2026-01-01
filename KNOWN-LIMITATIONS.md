# Known Limitations and TODO Items

This document tracks known limitations and planned improvements for the restaurant management system.

## 🔴 High Priority (Security)

### Authentication & Authorization
**Status**: Authentication checks added, but role/permission verification not yet implemented

**Issue**: All admin endpoints currently check for authentication (user is logged in) but do not verify if the user has proper admin/manager roles or specific permissions.

**Affected Files**:
- `api/reviews.php` - Review management endpoints
- `api/admin/schedule.php` - Schedule management endpoints
- `api/admin/notes.php` - Notes management endpoints

**TODO**: Add permission checks like:
```php
// Example implementation needed
function hasPermission($userId, $permission) {
    // Query user_roles and role_permissions tables
    // Return true if user has the required permission
}

// In each admin endpoint:
if (!hasPermission($userId, 'reviews_manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}
```

**Files to create**:
- `api/includes/auth.php` - Centralized authentication/authorization functions
- `api/includes/permissions.php` - Permission checking logic

---

## 🟡 Medium Priority (Functionality)

### OAuth Integration
**Status**: Placeholder implementation

**Issue**: OAuth login functions (Instagram/Facebook) are referenced but not fully implemented in `avaliar.html`.

**TODO**:
1. Complete OAuth callback handler in `api/auth/oauth-callback.php`
2. Implement client-side OAuth flow
3. Store OAuth tokens securely
4. Test with real OAuth credentials

**Files affected**:
- `avaliar.html` - Lines 480, 485
- `api/auth/oauth-callback.php` - Needs completion

---

### Maintenance Mode API
**Status**: Frontend implemented, backend not connected

**Issue**: Admin settings page saves maintenance mode configuration but doesn't persist to API.

**TODO**:
1. Create maintenance mode save endpoint in `api/admin/maintenance.php`
2. Implement JSON field updates for restricted pages
3. Update `admin.js` saveSettings() to call API
4. Load existing settings when admin panel opens

**Files to modify**:
- `api/admin/maintenance.php` - Add update endpoint
- `admin.js` - Complete saveSettings() function (line 1159)

---

### Menu Items Loading
**Status**: Not implemented

**Issue**: Profile page references loading menu items for favorite dish selector, but this is not implemented.

**TODO**:
1. Add endpoint to fetch menu items: `GET /api/admin/menu.php?action=list`
2. Populate favorite dish dropdown in `perfil.html`
3. Load menu items when employee stats section is displayed

**Files to modify**:
- `perfil.html` - Lines 604, 617
- May need to update `api/admin/menu.php`

---

### Admin Pending Counts
**Status**: Partial implementation

**Issue**: Admin quick panel shows pending counts but only reviews are implemented.

**TODO**:
1. Add resume count: `GET /api/admin/resumes.php?action=count&status=em_analise`
2. Add ouvidoria count: `GET /api/ouvidoria.php?action=count&status=pendente`
3. Update `perfil.html` to load all counts

**Files to modify**:
- `perfil.html` - Line 617 (loadAdminPendingCounts function)

---

### Schedule Add/Edit Modals
**Status**: Placeholder functions

**Issue**: Admin schedule management has delete functionality but add/edit use alerts instead of proper modals.

**TODO**:
1. Create modal HTML for schedule form
2. Implement `showAddScheduleModal()` function
3. Implement `editSchedule()` function
4. Add form validation

**Files to modify**:
- `admin.html` - Add modal HTML
- `admin.js` - Complete functions at lines ~1756, 1761

---

## 🟢 Low Priority (Enhancements)

### Rating Constraint Clarification
**Status**: Documentation inconsistency

**Issue**: Database allows 0-5 stars, but UI suggests 1-5. Clarify intended behavior.

**Options**:
1. Change constraint to `rating >= 1 AND rating <= 5`
2. Update UI to support 0 stars as "no rating"
3. Document that 0 means "no rating given"

**Files to review**:
- `database/migrations/add_reviews_schedule_profile.sql` - Line 16
- `avaliar.html` - Star rating UI
- `FEATURES-DOCUMENTATION.md` - Documentation

---

### File Upload Limits
**Status**: Magic number

**Issue**: Profile photo upload has hardcoded 5MB limit.

**TODO**: Move to configuration constant.

**Files to modify**:
- `api/profile.php` - Line 306
- Consider adding to `config/constants.php` or `.env`

---

### Error Handling
**Status**: Basic implementation

**Issue**: Maintenance mode API call lacks comprehensive error handling.

**TODO**:
1. Add try-catch in `checkMaintenanceMode()`
2. Add fallback behavior if API is unavailable
3. Log errors for debugging

**Files to modify**:
- `scripts.js` - Line 795

---

### Session Validation
**Status**: Client-side only

**Issue**: Authentication check in review page uses localStorage/sessionStorage which can be manipulated.

**TODO**:
1. Add server-side session validation endpoint
2. Call validation before displaying review form
3. Implement proper session refresh

**Files to modify**:
- `avaliar.html` - Line 340 (checkAuthStatus function)
- Create endpoint: `GET /api/auth/validate-session.php`

---

## 📋 Implementation Priority Order

1. **[SECURITY] Add role/permission verification** - Prevents unauthorized access
2. **[CORE] Complete OAuth integration** - Required for review functionality
3. **[CORE] Implement maintenance mode API** - Complete the feature
4. **[UX] Add schedule add/edit modals** - Improve admin experience
5. **[DATA] Implement menu items loading** - Complete profile page
6. **[INFO] Add remaining admin counts** - Complete admin dashboard
7. **[CLEANUP] Move magic numbers to config** - Code maintainability
8. **[POLISH] Improve error handling** - Better user experience

---

## 🔧 Development Notes

### Setting Up Permission System

Example implementation for permission checking:

```php
// api/includes/permissions.php
function getUserPermissions($userId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.name
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function hasPermission($userId, $permission, $pdo) {
    $permissions = getUserPermissions($userId, $pdo);
    return in_array($permission, $permissions);
}
```

### OAuth Flow

1. User clicks Instagram/Facebook button
2. Redirect to OAuth provider
3. Provider redirects back with authorization code
4. Exchange code for access token
5. Fetch user profile from provider
6. Create or update user record
7. Create session
8. Return to review page

---

## 📝 Testing Checklist

Before marking any TODO as complete, verify:

- [ ] Feature works as expected
- [ ] Error cases are handled
- [ ] Security checks are in place
- [ ] Code is documented
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] Input validation is implemented
- [ ] Edge cases are considered

---

## 🤝 Contributing

When implementing these TODOs:

1. Create a new branch for each feature
2. Write tests if applicable
3. Update this document
4. Update FEATURES-DOCUMENTATION.md if user-facing
5. Submit PR with detailed description

---

**Last Updated**: December 30, 2024
**Version**: 2.0.0-beta
