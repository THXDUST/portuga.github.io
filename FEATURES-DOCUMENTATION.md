# New Features Implementation Guide

This document describes the newly implemented features for the Portuga Restaurant system.

## 🎯 Implemented Features

### 1. ⭐ Customer Satisfaction Review System

#### Overview
Complete review/rating system with OAuth authentication, admin approval workflow, and statistics dashboard.

#### Key Features
- **Star Rating System** (0-5 stars) with interactive UI
- **OAuth Login Required** (Instagram/Facebook) for authenticity
- **Rate Limiting**: 1 review per hour per user/IP
- **Admin Approval Workflow**: Pending → Approved/Rejected/Archived
- **Statistics Dashboard**: Average rating, total reviews, rating distribution chart

#### Files Created/Modified
- `avaliar.html` - Review submission page
- `api/reviews.php` - Reviews API endpoint
- `admin.html` - Added "Avaliações" tab
- `admin.js` - Added review management functions
- `database/migrations/add_reviews_schedule_profile.sql` - Database tables

#### API Endpoints
```
POST   /api/reviews.php?action=submit           - Submit new review
GET    /api/reviews.php?action=list             - List reviews (admin)
PUT    /api/reviews.php?action=update-status    - Update review status
GET    /api/reviews.php?action=statistics       - Get review statistics
DELETE /api/reviews.php?action=delete           - Delete review
GET    /api/reviews.php?action=can-review       - Check if user can review
```

#### Admin Panel Access
Navigate to Admin → Avaliações to:
- View all reviews with status badges
- See statistics (average rating, total, distribution)
- Approve/reject/archive reviews
- Delete inappropriate reviews

---

### 2. 🗺️ Maps API Integration for Distance Calculation

#### Overview
Enhanced geocoding with optional Google Maps API support and automatic OpenStreetMap fallback.

#### Key Features
- **Google Maps Geocoding API** (optional, requires API key)
- **OpenStreetMap Nominatim** (free fallback, no key required)
- **Fixed Delivery Fee Calculation**:
  - ≤5km: R$ 5,00
  - ≤7km: R$ 7,00
  - ≤9km: R$ 9,00
  - ≤11km: R$ 11,00
  - ≤18km: R$ 18,00
  - >18km: No delivery
- **Manual Distance Input** (backup option)

#### Configuration
To enable Google Maps API (optional):

1. Add to `.env`:
   ```
   GOOGLE_MAPS_API_KEY=your_api_key_here
   ```

2. Add before `scripts.js` in HTML:
   ```html
   <script>window.GOOGLE_MAPS_API_KEY = 'your_api_key_here';</script>
   ```

If no API key is provided, the system automatically uses OpenStreetMap Nominatim (free).

---

### 3. 📝 Notes/Announcements Display System

#### Overview
Display important announcements and promotions on the homepage.

#### Key Features
- **Homepage Display**: Animated banners below header
- **Multiple Note Types**: info, warning, success, promo
- **Active/Inactive Control**
- **Expiration Dates**
- **Display Order Management**

#### API Endpoints
```
GET  /api/admin/notes.php?action=list        - Get active notes (public)
GET  /api/admin/notes.php?action=admin-list  - Get all notes (admin)
POST /api/admin/notes.php?action=create      - Create note
PUT  /api/admin/notes.php?action=update      - Update note
DELETE /api/admin/notes.php?action=delete    - Delete note
```

#### Usage
1. Admin Panel → Notas → Add new note
2. Configure title, content, type, and expiration
3. Set as active to display on homepage
4. Notes appear automatically on index.html

---

### 4. 👥 Employee Schedule System (Planner)

#### Overview
Complete employee scheduling system with weekly schedules.

#### Key Features
- **Weekly Schedules**: Configure for each day of week
- **Lunch Breaks**: Optional lunch start/end times
- **Multiple Users**: Manage schedules for all employees
- **Employee View**: Employees can view their own schedule

#### API Endpoints
```
POST   /api/admin/schedule.php?action=create      - Create schedule
GET    /api/admin/schedule.php?action=list        - List all schedules
GET    /api/admin/schedule.php?action=my-schedule - Get own schedule
PUT    /api/admin/schedule.php?action=update      - Update schedule
DELETE /api/admin/schedule.php?action=delete      - Delete schedule
```

#### Admin Panel Access
Navigate to Admin → Horários to:
- View all employee schedules grouped by user
- Add new schedule entries
- Edit existing schedules
- Delete schedules

#### Employee Access
Employees can view their schedule on their profile page (perfil.html).

---

### 5. 👤 User Profile Page

#### Overview
Comprehensive user profile with role-based sections.

#### Key Features

**For All Users:**
- Profile photo upload (with preview)
- Basic information (name, censored email, member since)
- Personal statistics:
  - Total spent at restaurant
  - Most ordered dish
  - Total orders
  - Last review given
- Privacy controls (show/hide sections)

**For Employees:**
- Personal schedule view
- Top 5 dishes sold (restaurant-wide)
- Favorite dish selector

**For Admins:**
- Quick access links to admin panel
- Pending counts (reviews, resumes, ouvidoria)

#### Files Created
- `perfil.html` - User profile page
- `api/profile.php` - Profile API
- Database tables: `user_profile_photos`, `user_favorite_dishes`, `user_privacy_settings`

#### API Endpoints
```
GET  /api/profile.php?action=info                 - Get profile info
GET  /api/profile.php?action=statistics           - Get user statistics
POST /api/profile.php?action=upload-photo         - Upload profile photo
PUT  /api/profile.php?action=update-favorite-dish - Update favorite dish
PUT  /api/profile.php?action=toggle-section       - Toggle section visibility
```

#### Access
Navigate to `/perfil.html` (requires login)

---

### 6. 🛠️ Enhanced Maintenance Mode

#### Overview
Granular maintenance mode control with per-page restrictions.

#### Key Features
- **Global Mode**: Restrict all pages at once
- **Selective Mode**: Choose specific pages to restrict
- **Custom Messages**: Per-page or global messages
- **ETA Support**: Display estimated return time
- **Admin Bypass**: Admin pages never blocked

#### Pages That Can Be Restricted
- index.html - Página Inicial
- menu.html - Cardápio
- carrinho.html - Carrinho de Compras
- pedidos.html - Acompanhar Pedidos
- avaliar.html - Avaliar Restaurante
- ouvidoria.html - Ouvidoria
- enviar-curriculo.html - Trabalhe Conosco
- perfil.html - Perfil do Usuário

#### Configuration
1. Admin Panel → Configurações → Modo Manutenção
2. Toggle "Ativar Modo Manutenção"
3. Choose "Restringir TODAS" or select specific pages
4. Add custom message (optional)
5. Set estimated return time (optional)
6. Save settings

#### How It Works
- `scripts.js` automatically checks maintenance mode on every page load
- If page is restricted, displays overlay with message
- Scrolling disabled when overlay is active
- Admin and login pages never blocked

---

## 📊 Database Schema

### New Tables

#### `reviews`
```sql
- id, user_id, order_id, rating (0-5)
- comment, status, ip_address
- created_at, approved_by, approved_at
```

#### `employee_schedule`
```sql
- id, user_id, day_of_week
- shift_start, lunch_start, lunch_end, shift_end
- notes, created_by, created_at
```

#### `user_profile_photos`
```sql
- id, user_id, photo_path
- uploaded_at, updated_at
```

#### `user_favorite_dishes`
```sql
- id, user_id, menu_item_id, set_at
```

#### `user_privacy_settings`
```sql
- id, user_id
- show_statistics, show_total_spent
- show_favorite_dish, show_order_count, show_last_review
```

#### `notes`
```sql
- id, title, content, note_type
- is_active, display_order, expires_at
- created_by, created_at
```

#### `maintenance_mode` (updated)
```sql
- Added: restricted_pages (JSON)
- Added: page_messages (JSON)
```

### Permissions Added
```sql
- reviews_view, reviews_manage, reviews_access, reviews_delete
- schedule_view, schedule_manage, schedule_access, schedule_view_own, schedule_view_all
- profile_view_all, profile_edit_own, profile_edit_all
- notes_view, notes_create, notes_update, notes_delete, notes_access
```

---

## 🚀 Getting Started

### 1. Run Database Migration
```bash
mysql -u username -p database_name < database/migrations/add_reviews_schedule_profile.sql
```

### 2. Configure Environment
Copy `.env.example` to `.env` and configure:
```bash
cp .env.example .env
```

Edit `.env`:
```env
# Optional: Google Maps API
GOOGLE_MAPS_API_KEY=your_key_here

# Database configuration
DB_HOST=localhost
DB_NAME=portuga_db
DB_USER=your_user
DB_PASS=your_password
```

### 3. Set Up OAuth (Optional)
For review authentication, configure OAuth providers in `.env`:
```env
INSTAGRAM_CLIENT_ID=your_instagram_client_id
INSTAGRAM_CLIENT_SECRET=your_instagram_client_secret

FACEBOOK_APP_ID=your_facebook_app_id
FACEBOOK_APP_SECRET=your_facebook_app_secret
```

### 4. Configure Permissions
In Admin Panel → Permissões, assign new permissions to appropriate roles.

---

## 🔧 Configuration

### Delivery Fee Calculation
The fixed delivery fee table is now:
```javascript
≤5km  → R$ 5,00
≤7km  → R$ 7,00
≤9km  → R$ 9,00
≤11km → R$ 11,00
≤18km → R$ 18,00
>18km → No delivery
```

This is configured in `scripts.js` functions:
- `calculateDeliveryFeeFromDistance()`
- `getDeliveryFee()`

---

## 📱 User Flows

### Customer Review Flow
1. Navigate to `/avaliar.html`
2. Login required (Instagram/Facebook OAuth)
3. Select 0-5 stars
4. Add optional comment (max 1000 chars)
5. Submit → Review goes to "Pendente" status
6. Admin reviews and approves/rejects
7. If approved, can be shared on Instagram

### Employee Schedule View
1. Login as employee
2. Navigate to `/perfil.html`
3. View personal weekly schedule
4. See next upcoming shift
5. View colleague schedules (if permitted)

### Admin Review Management
1. Login to admin panel
2. Navigate to "Avaliações" tab
3. View statistics and distribution
4. Filter by status (pending, approved, etc.)
5. Approve/reject/archive reviews
6. Delete inappropriate reviews

---

## 🔐 Security Features

### Rate Limiting
- Reviews: 1 per hour per user/IP
- Prevents spam and abuse

### Authentication
- OAuth required for reviews (Instagram/Facebook)
- Session-based admin access
- Role-based permissions

### Data Protection
- Email censoring in profile display
- Password hashing (bcrypt)
- SQL injection prevention (PDO prepared statements)
- XSS protection (input sanitization)

---

## 🐛 Troubleshooting

### Reviews Not Submitting
1. Check OAuth configuration in `.env`
2. Verify database table `reviews` exists
3. Check browser console for errors
4. Verify user is logged in

### Geocoding Not Working
1. If using Google Maps: Check API key is valid
2. System automatically falls back to OpenStreetMap
3. Manual distance input always available as backup

### Schedule Not Loading
1. Verify table `employee_schedule` exists
2. Check user has proper permissions
3. Verify API endpoint is accessible

### Maintenance Mode Not Working
1. Check `scripts.js` is loaded on all pages
2. Verify maintenance mode API endpoint
3. Check browser console for errors
4. Admin pages are always accessible

---

## 📚 Additional Resources

### API Documentation
All endpoints follow RESTful conventions:
- GET: Retrieve data
- POST: Create new records
- PUT: Update existing records
- DELETE: Remove records

### Response Format
All APIs return JSON:
```json
{
  "success": true,
  "data": {},
  "message": "Operation successful"
}
```

Error response:
```json
{
  "success": false,
  "message": "Error description"
}
```

---

## 🎨 Styling

All new features use the existing design system:
- Primary color: #e8c13f (gold)
- Background: #1a1a1a (dark)
- Border radius: 8-12px
- Box shadows: 0 2px 10px rgba(0,0,0,0.1)

---

## 📞 Support

For issues or questions:
1. Check console for error messages
2. Verify database schema is up to date
3. Review API responses in Network tab
4. Check file permissions on uploads folder

---

**Last Updated**: December 30, 2026
**Version**: 2.0.0
