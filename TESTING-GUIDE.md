# Testing Guide - Menu Management Migration

## Quick Test Checklist

### Prerequisites
1. Ensure database is set up with tables from `database/setup.sql`
2. Ensure API endpoints are accessible
3. Log into admin panel with appropriate permissions

## Test Scenarios

### 1. Test Creating Main Groups ✓

**Steps:**
1. Navigate to Admin Panel → Cardápio
2. Click "Adicionar Grupo"
3. Fill in:
   - Nome: "Pizzas"
   - Descrição: "Nossas deliciosas pizzas artesanais"
   - Grupo Pai: Leave as "Grupo Principal (sem grupo pai)"
4. Click "Salvar"

**Expected Result:**
- Success message "Grupo salvo com sucesso!"
- New group appears in the menu management list
- Group shows with yellow heading

### 2. Test Creating Subgroups ✓

**Steps:**
1. Click "Adicionar Grupo" again
2. Fill in:
   - Grupo Pai: Select "Pizzas" from dropdown
   - Nome: "Pizzas Salgadas"
   - Descrição: "Pizzas salgadas tradicionais"
3. Click "Salvar"

**Expected Result:**
- Success message "Grupo salvo com sucesso!"
- Subgroup appears indented under "Pizzas" with "↳" symbol
- Left border (yellow) indicates hierarchy

### 3. Test Adding Items to Parent Group ✓

**Steps:**
1. Click "Adicionar Item"
2. Fill in:
   - Grupo: Select "Pizzas" (not indented)
   - Nome: "Pizza Margherita"
   - Descrição: "Molho de tomate, mussarela e manjericão"
   - Preço: 45.00
   - URL da Imagem: (optional)
   - Disponível para Venda: ✓ (checked)
3. Click "Salvar"

**Expected Result:**
- Success message "Item salvo com sucesso!"
- Item appears under "Pizzas" group
- Price shows as "R$ 45.00"
- Green badge shows "✅ Disponível"

### 4. Test Adding Items to Subgroup ✓

**Steps:**
1. Click "Adicionar Item"
2. Fill in:
   - Grupo: Select "   ↳ Pizzas Salgadas" (indented option)
   - Nome: "Pizza Portuguesa"
   - Descrição: "Presunto, ovo, cebola e azeitonas"
   - Preço: 52.00
   - Disponível para Venda: ✓ (checked)
3. Click "Salvar"

**Expected Result:**
- Success message "Item salvo com sucesso!"
- Item appears under "Pizzas Salgadas" subgroup
- Subgroup is visually indented

### 5. Test Editing Group ✓

**Steps:**
1. Find the "Pizzas" group
2. Click "✏️ Editar" button
3. Change description to: "Pizzas artesanais com massa fina"
4. Click "Salvar"

**Expected Result:**
- Success message "Grupo salvo com sucesso!"
- Updated description shows in the group header

### 6. Test Editing Item ✓

**Steps:**
1. Find "Pizza Margherita" item
2. Click "✏️" button on the item
3. Change price to: 48.00
4. Click "Salvar"

**Expected Result:**
- Success message "Item salvo com sucesso!"
- Updated price shows as "R$ 48.00"

### 7. Test Circular Reference Prevention ✓

**Steps:**
1. Create a parent group: "Bebidas"
2. Create a subgroup: "Bebidas → Refrigerantes"
3. Try to edit "Bebidas" and set parent to "Refrigerantes"

**Expected Result:**
- "Refrigerantes" should NOT appear in the parent dropdown
- Only other top-level groups (or none) should be available
- This prevents circular reference: Bebidas → Refrigerantes → Bebidas

### 8. Test Deleting Item ✓

**Steps:**
1. Find an item to delete
2. Click "🗑️" button on the item
3. Confirm deletion

**Expected Result:**
- Confirmation dialog appears
- After confirmation, success message "Item excluído com sucesso!"
- Item disappears from the list

### 9. Test Deleting Empty Group ✓

**Steps:**
1. Create a test group with no items
2. Click "🗑️ Excluir" button on the group
3. Confirm deletion

**Expected Result:**
- Simple confirmation dialog
- Success message "Grupo excluído com sucesso!"
- Group disappears from the list

### 10. Test Deleting Group With Items ✓

**Steps:**
1. Find a group with items
2. Click "🗑️ Excluir" button
3. Read the warning (e.g., "Este grupo possui 3 item(ns)...")
4. Confirm deletion

**Expected Result:**
- Warning dialog mentions number of items
- All items are deleted first (one by one)
- Then group is deleted
- Success message "Grupo excluído com sucesso!"

### 11. Test Deleting Group With Subgroups ✓

**Steps:**
1. Try to delete a group that has subgroups
2. Click "🗑️ Excluir" button

**Expected Result:**
- Error message: "Este grupo possui X subgrupo(s). Por favor, remova ou mova os subgrupos primeiro."
- Group is NOT deleted
- Must delete or move subgroups before deleting parent

### 12. Test Public Menu Display ✓

**Steps:**
1. Open `menu.html` in a browser
2. View the menu structure

**Expected Result:**
- Groups appear as main sections (H2 headings)
- Subgroups appear as subsections (H3 headings) under their parents
- Items show with image, name, description, price, and "Adicionar ao Carrinho" button
- Empty groups are hidden
- Hierarchical structure is clear and logical

### 13. Test Resumes Display ✓

**Steps:**
1. Navigate to Admin Panel → Currículos
2. View the list of resumes

**Expected Result:**
- All submitted resumes appear
- Shows name, email, phone, desired position
- Status badges (Em Análise, Aprovado, Rejeitado)
- Can filter by status
- Can update status with notes

### 14. Test Ouvidoria Display ✓

**Steps:**
1. Navigate to Admin Panel → Ouvidoria
2. View the list of messages

**Expected Result:**
- All submitted messages appear
- Shows protocol number, subject, sender info
- Status badges (Pendente, Em Atendimento, Resolvido)
- Can filter by status
- Can respond to messages
- Can update status

## API Testing

### Using Browser Console

```javascript
// Test GET groups
fetch('/api/admin/menu.php?action=groups')
  .then(r => r.json())
  .then(data => console.log('Groups:', data));

// Test GET items
fetch('/api/admin/menu.php?action=items')
  .then(r => r.json())
  .then(data => console.log('Items:', data));

// Test GET full menu (public)
fetch('/api/admin/menu.php?action=full-menu')
  .then(r => r.json())
  .then(data => console.log('Full Menu:', data));

// Test CREATE group
fetch('/api/admin/menu.php?action=create-group', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'Test Group',
    description: 'Test description',
    parent_id: null
  })
}).then(r => r.json()).then(data => console.log('Created:', data));

// Test UPDATE group
fetch('/api/admin/menu.php?action=update-group', {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    id: 1,
    name: 'Updated Group Name'
  })
}).then(r => r.json()).then(data => console.log('Updated:', data));

// Test DELETE group
fetch('/api/admin/menu.php?action=delete-group&id=1', {
  method: 'DELETE'
}).then(r => r.json()).then(data => console.log('Deleted:', data));
```

## Common Issues & Solutions

### Issue: "Nenhum grupo cadastrado ainda"
**Solution:** Create a main group first before adding items.

### Issue: "Por favor, crie um grupo primeiro antes de adicionar itens"
**Solution:** You need at least one group before you can add items.

### Issue: "Este grupo possui X subgrupo(s)..."
**Solution:** Delete or reassign subgroups before deleting the parent group.

### Issue: Items not showing in public menu
**Solution:** 
- Check that item's `is_available` is TRUE
- Check that group's `is_active` is TRUE
- Clear browser cache
- Check browser console for errors

### Issue: "Erro ao carregar cardápio"
**Solution:**
- Check that API endpoints are accessible
- Check database connection
- Check browser console for detailed error
- Verify database tables exist

### Issue: Parent group not showing in dropdown
**Solution:**
- If editing a group, descendants are filtered out (this is correct behavior)
- Only top-level groups can be parents of other groups
- A group cannot be its own parent or ancestor

## Success Indicators

✅ **All CRUD operations work**
- Create, Read, Update, Delete for both groups and items

✅ **Hierarchy works correctly**
- Can create parent groups
- Can create subgroups under parents
- Cannot create circular references

✅ **API returns correct data**
- Groups include parent_id, subgroup_count
- Items include group_id, group_name
- Full menu includes hierarchical structure

✅ **Public menu displays correctly**
- Shows groups and subgroups
- Shows items with details
- "Add to Cart" buttons work

✅ **Error handling works**
- Validation errors show helpful messages
- Network errors are caught and displayed
- Loading states prevent duplicate actions

✅ **Resumes and Ouvidoria work**
- Lists show correctly
- Filters work
- Status updates work
- Responses can be added

## Performance Notes

- **Fast**: Creating/editing single items or groups
- **Fast**: Loading small menus (< 50 items)
- **Acceptable**: Deleting groups with items (sequential deletion)
- **Acceptable**: Deep hierarchy checking (< 5 levels)

For large menus (100+ items), consider implementing the batch operations mentioned in MENU-MIGRATION-SUMMARY.md.

## Security Notes

✅ **CodeQL scan passed** - 0 vulnerabilities found
✅ **Session tracking** - User actions are logged with session IDs
✅ **Input validation** - API validates required fields
✅ **SQL injection protection** - Uses prepared statements
✅ **XSS protection** - HTML is escaped in menu.html

## Next Steps After Testing

1. If all tests pass, deploy to production
2. If issues found, document them and fix before deploying
3. Consider implementing batch operations for better performance
4. Monitor API performance with real data
5. Gather user feedback on the new hierarchical structure

## Rollback Plan (if needed)

If critical issues are found:
1. The old localStorage code has been removed
2. Would need to revert the entire PR
3. Or manually fix issues and re-deploy
4. Database changes would need to be preserved

**Recommendation:** Test thoroughly in staging before production deployment.
