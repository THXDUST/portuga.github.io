# Portuga - Admin Panel Implementation Guide

## 🎯 Overview

This document describes the complete implementation of the admin panel features for the Portuga Restaurant & Pizzeria website.

## ✅ Completed Features

### 1. Database Schema (database/setup.sql)

Complete database structure with the following tables:

#### Core Tables
- `users` - User accounts
- `roles` - User roles (Admin, Gerente, Atendente, Cozinha, Entregador)
- `permissions` - Granular permissions (create, read, update, delete)
- `role_permissions` - Role-permission mapping
- `user_roles` - User-role assignments

#### Menu Management
- `menu_groups` - Menu categories and subcategories
- `menu_items` - Food items with prices, descriptions, images
  
#### Order Management
- `orders` - Enhanced order tracking with:
  - Order type (viagem/local)
  - Payment method (dinheiro/cartão/PIX)
  - Delivery details (address, distance, fee)
  - Pickup/production times
  - Notes/observations
- `order_items` - Items in each order
- `order_notes` - Additional order notes/updates

#### System Configuration
- `restaurant_settings` - Restaurant configuration (hours, status, delivery settings)
- `maintenance_mode` - System maintenance control
- `employee_schedule` - Employee work schedules
- `service_points` - Service areas/tables/counters

#### Customer Support
- `resumes` - Job applications
- `ouvidoria` - Customer support messages with protocol system

#### Reporting & Logs
- `reports` - Saved reports
- `admin_logs` - Administrative action logging

### 2. Backend API (api/)

Complete REST API implementation:

#### Admin APIs (api/admin/)
- `base.php` - Common helper functions and authentication
- `permissions.php` - Permission management
- `roles.php` - Role CRUD operations
- `users.php` - User management with role assignments
- `menu.php` - Menu groups and items management
- `settings.php` - Restaurant configuration
- `reports.php` - Report generation (revenue, popular items, customer flow)
- `resumes.php` - Job application management
- `maintenance.php` - Maintenance mode control

#### Public APIs
- `orders.php` - Enhanced order management with Kanban status
- `ouvidoria.php` - Customer support messaging

### 3. Admin Panel (admin.html + admin.js)

Enhanced admin interface with tabs:

#### Dashboard Tab
- Real-time statistics (total orders, pending, in progress, completed)
- Revenue metrics (total, average order value)
- Most ordered items (top 5)
- Order list with status filtering

#### Orders Kanban Tab
- Visual Kanban board with 3 columns:
  - 📥 Recebido (Received)
  - 👨‍🍳 Em Andamento (In Progress)
  - ✅ Finalizado (Completed)
- Drag & drop functionality to change order status
- Order cards showing:
  - Order number and time
  - Items list
  - Total amount

#### Menu Management Tab
- Add/edit/delete menu groups
- Add/edit/delete menu items
- Organize items within categories
- Set availability status

#### Reports Tab
- Revenue reports (daily/weekly/monthly)
- Most ordered items
- Customer flow by hour/day
- Date range filtering
- Visual charts and graphs

#### Resumes Tab
- View submitted job applications
- Filter by status (em_analise, aprovado, rejeitado)
- Review and update application status
- Add notes to applications

#### Ouvidoria Tab
- View customer support messages
- Filter by status (pendente, em_atendimento, resolvido)
- Respond to messages
- Track by protocol number

#### Settings Tab
- Restaurant open/closed toggle
- Operating hours configuration (kitchen, pizzaria, delivery)
- Delivery settings (max distance, fees)
- Maintenance mode toggle

### 4. Customer Pages

#### Order Tracking (pedidos.html + pedidos.js)
- Visual order status tracking with stages
- Real-time order status updates
- Order history
- Auto-refresh every 30 seconds

#### Customer Support (ouvidoria.html + ouvidoria.js)
- Contact form with multiple subjects
- Image attachment support
- Protocol number generation
- Status tracking by protocol

#### Job Applications (enviar-curriculo.html)
- Resume submission form
- Multiple position options
- File upload (PDF/DOC)
- Cover letter
- Available positions display

### 5. Styling (style.css)

Comprehensive CSS including:
- Tabbed navigation for admin panel
- Kanban board styles with drag & drop visual feedback
- Order tracking stages with active states
- Toggle switches for settings
- Modal dialogs
- Responsive design (mobile-first)
- Card-based layouts
- Status badges and indicators

## 🚀 How to Use

### Admin Panel Access

1. Navigate to `admin.html`
2. Login with default credentials:
   - Username: `admin`
   - Password: `portuga123`

### Admin Panel Features

#### Managing Orders
1. Go to "Pedidos (Kanban)" tab
2. Drag orders between columns to update status
3. Orders automatically update in real-time

#### Generating Reports
1. Go to "Relatórios" tab
2. Select report type
3. Choose date range
4. Click "Gerar Relatório"

#### Configuring Restaurant
1. Go to "Configurações" tab
2. Toggle restaurant status
3. Set operating hours
4. Configure delivery settings
5. Click "Salvar Configurações"

### Customer Features

#### Tracking Orders
1. Navigate to `pedidos.html`
2. View current order status
3. See order history

#### Contacting Support
1. Navigate to `ouvidoria.html`
2. Fill out contact form
3. Receive protocol number
4. Check status using protocol

#### Applying for Jobs
1. Navigate to `enviar-curriculo.html`
2. Fill out application form
3. Upload resume
4. Submit application

## 📊 Database Seeding

The database includes default data:

### Default Roles
- Admin (full access)
- Gerente (reports and settings)
- Atendente (orders and menu)
- Cozinha (order viewing only)
- Entregador (delivery orders only)

### Default Permissions
- Orders: view, create, update, delete
- Menu: view, create, update, delete
- Users: view, create, update, delete
- Reports: view, create
- Settings: view, update
- Resumes: view, update
- Ouvidoria: view, update

### Default Settings
- Restaurant name: "Portuga - Restaurante & Pizzaria"
- Phone: 5513997597759
- Open status: true
- Kitchen hours: 11:00-23:00
- Pizza hours: 18:00-23:00
- Delivery hours: 11:00-22:00
- Max delivery distance: 18km
- Delivery fee: R$2.50/km

## 🔒 Security Features

- Session-based authentication
- Password hashing (bcrypt with SHA256)
- CSRF protection ready
- Input validation on all forms
- SQL injection prevention (prepared statements)
- Admin action logging
- Role-based access control (RBAC)

## 📱 Responsive Design

All pages are fully responsive:
- Mobile-first approach
- Adaptive layouts
- Touch-friendly controls
- Optimized for tablets and phones

## 🔄 Real-time Features

- Auto-refresh on order tracking (30s interval)
- Dashboard auto-refresh (30s interval)
- Drag & drop Kanban updates
- Instant status badge updates

## 🎨 UI/UX Features

- Color-coded status indicators
- Visual feedback on interactions
- Smooth transitions and animations
- Intuitive drag & drop
- Clear visual hierarchy
- Consistent design language

## 📝 Next Steps (Optional Enhancements)

1. **Connect to Database**: Integrate API calls to replace localStorage
2. **Add Chart.js**: Visual charts for reports
3. **Add SortableJS**: Enhanced drag & drop library
4. **Real-time Notifications**: WebSocket or polling for instant updates
5. **Print Receipts**: Order printing functionality
6. **Email Notifications**: Automated emails for orders and support
7. **SMS Notifications**: Order status updates via SMS
8. **Advanced Filters**: More filtering options for orders and reports
9. **Export Data**: CSV/PDF export for reports
10. **Multi-language**: i18n support

## 🐛 Known Limitations

- Currently uses localStorage (mock data)
- No actual database connection (PHP APIs ready but not connected)
- No file upload backend (form validation only)
- Drag & drop uses basic HTML5 API (can be enhanced with SortableJS)
- Charts are text-based (can be enhanced with Chart.js)

## 📚 Files Structure

```
portuga/
├── api/
│   ├── admin/
│   │   ├── base.php
│   │   ├── permissions.php
│   │   ├── roles.php
│   │   ├── users.php
│   │   ├── menu.php
│   │   ├── settings.php
│   │   ├── reports.php
│   │   ├── resumes.php
│   │   └── maintenance.php
│   ├── orders.php
│   └── ouvidoria.php
├── database/
│   └── setup.sql
├── admin.html
├── admin.js
├── pedidos.html
├── pedidos.js
├── ouvidoria.html
├── ouvidoria.js
├── enviar-curriculo.html
├── style.css
└── scripts.js
```

## 💡 Tips

- Use Chrome DevTools to inspect Kanban drag & drop
- Check browser console for debugging information
- LocalStorage persists data between sessions
- Clear localStorage to reset demo data
- Test responsive design with browser DevTools

## 📞 Support

For questions or issues, contact the development team or refer to the inline code documentation.
