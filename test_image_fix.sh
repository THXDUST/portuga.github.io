#!/bin/bash
# Test script to verify image upload/display fixes
# Run this after applying database migrations

echo "=========================================="
echo "🧪 Image Upload/Display Verification Test"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we can connect to the database
echo "1️⃣ Checking database connection..."
if psql -h localhost -U postgres -d portuga_db -c "SELECT 1" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Database connection successful"
else
    echo -e "${RED}✗${NC} Cannot connect to database"
    echo "Please check your database configuration"
    exit 1
fi

# Check if image_data column exists and is correct type
echo ""
echo "2️⃣ Checking image_data column..."
COLUMN_TYPE=$(psql -h localhost -U postgres -d portuga_db -t -c "
    SELECT data_type 
    FROM information_schema.columns 
    WHERE table_name = 'menu_items' 
    AND column_name = 'image_data'
" | tr -d '[:space:]')

if [ "$COLUMN_TYPE" = "text" ]; then
    echo -e "${GREEN}✓${NC} image_data column exists and is type TEXT (correct)"
elif [ "$COLUMN_TYPE" = "bytea" ]; then
    echo -e "${RED}✗${NC} image_data column is type BYTEA (incorrect)"
    echo "  Please run migration 002_fix_image_data_column_type.sql"
    exit 1
elif [ -z "$COLUMN_TYPE" ]; then
    echo -e "${RED}✗${NC} image_data column does not exist"
    echo "  Please run migration 001_add_menu_item_image_columns.sql"
    exit 1
else
    echo -e "${YELLOW}⚠${NC} image_data column has unexpected type: $COLUMN_TYPE"
fi

# Check if image_mime_type column exists
echo ""
echo "3️⃣ Checking image_mime_type column..."
MIME_COLUMN=$(psql -h localhost -U postgres -d portuga_db -t -c "
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'menu_items' 
    AND column_name = 'image_mime_type'
" | tr -d '[:space:]')

if [ "$MIME_COLUMN" = "image_mime_type" ]; then
    echo -e "${GREEN}✓${NC} image_mime_type column exists"
else
    echo -e "${RED}✗${NC} image_mime_type column does not exist"
    echo "  Please run migration 001_add_menu_item_image_columns.sql"
    exit 1
fi

# Check if migrations tracking table exists
echo ""
echo "4️⃣ Checking migrations tracking..."
MIGRATIONS_COUNT=$(psql -h localhost -U postgres -d portuga_db -t -c "
    SELECT COUNT(*) FROM schema_migrations
" 2>/dev/null | tr -d '[:space:]')

if [ -n "$MIGRATIONS_COUNT" ]; then
    echo -e "${GREEN}✓${NC} Found $MIGRATIONS_COUNT applied migration(s)"
    echo ""
    echo "Applied migrations:"
    psql -h localhost -U postgres -d portuga_db -c "
        SELECT version, applied_at 
        FROM schema_migrations 
        ORDER BY applied_at DESC
    "
else
    echo -e "${YELLOW}⚠${NC} No migrations tracking found (schema_migrations table doesn't exist)"
    echo "  Migrations may not have been run via run_migrations.php"
fi

# Test if dish-image.php exists
echo ""
echo "5️⃣ Checking dish-image.php..."
if [ -f "api/dish-image.php" ]; then
    echo -e "${GREEN}✓${NC} api/dish-image.php exists"
else
    echo -e "${RED}✗${NC} api/dish-image.php not found"
    exit 1
fi

# Check if menu.php has image columns in queries
echo ""
echo "6️⃣ Checking menu.php queries..."
if grep -q "i.image_data" api/admin/menu.php && grep -q "i.image_mime_type" api/admin/menu.php; then
    echo -e "${GREEN}✓${NC} menu.php includes image columns in queries"
else
    echo -e "${RED}✗${NC} menu.php queries missing image columns"
    echo "  Please update api/admin/menu.php"
    exit 1
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✅ All checks passed!${NC}"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Go to http://localhost/admin.html"
echo "2. Navigate to 'Cardápio' tab"
echo "3. Add a new item with an image"
echo "4. Verify the image displays correctly"
echo ""
