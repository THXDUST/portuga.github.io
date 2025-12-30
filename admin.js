// Admin configuration
const ADMIN_CREDENTIALS = {
    username: 'admin',
    password: 'portuga123'
};

// Current active tab
let currentTab = 'dashboard';

// Check if user is logged in
function checkAuth() {
    const isLoggedIn = sessionStorage.getItem('adminLoggedIn');
    return isLoggedIn === 'true';
}

// Tab Navigation
function initTabNavigation() {
    const tabLinks = document.querySelectorAll('.nav-tab');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
}

function switchTab(tabName) {
    // Check if user has permission to access this tab
    if (window.canAccessTab && !canAccessTab(tabName)) {
        showAccessDenied(tabName);
        // Still switch to the tab to show the access denied message
    }
    
    // Hide all tabs
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.style.display = 'none';
        tab.classList.remove('active');
    });
    
    // Remove active class from all nav links
    document.querySelectorAll('.nav-tab').forEach(link => {
        link.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(`tab-${tabName}`);
    if (selectedTab) {
        selectedTab.style.display = 'block';
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked nav link
    const activeLink = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    // Load tab content only if user has permission
    currentTab = tabName;
    if (!window.canAccessTab || canAccessTab(tabName)) {
        loadTabContent(tabName);
    }
}

function loadTabContent(tabName) {
    switch(tabName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'orders':
            loadKanbanBoard();
            break;
        case 'menu':
            loadMenuManagement();
            break;
        case 'notes':
            loadNotes();
            break;
        case 'reviews':
            loadReviews();
            break;
        case 'schedule':
            loadSchedules();
            break;
        case 'reports':
            initReportFilters();
            break;
        case 'resumes':
            loadResumes();
            break;
        case 'ouvidoria':
            loadOuvidoriaMessages();
            break;
        case 'permissions':
            loadPermissions();
            break;
        case 'roles':
            loadRoles();
            break;
        case 'users':
            loadUsers();
            break;
        case 'settings':
            loadSettings();
            break;
    }
}

// Login function
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const loginSection = document.getElementById('login-section');
    const adminPanel = document.getElementById('admin-panel');
    
    // Check if already logged in
    if (checkAuth()) {
        showAdminPanel();
    }
    
    // Handle login
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            if (username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password) {
                sessionStorage.setItem('adminLoggedIn', 'true');
                showAdminPanel();
            } else {
                alert('Usuário ou senha incorretos!');
            }
        });
    }
    
    function showAdminPanel() {
        if (loginSection) loginSection.style.display = 'none';
        if (adminPanel) adminPanel.style.display = 'block';
        initTabNavigation();
        filterAdminMenuByPermissions();
        loadDashboard();
    }
});

// Logout function
function logout() {
    sessionStorage.removeItem('adminLoggedIn');
    location.reload();
}

// Load dashboard data
function loadDashboard() {
    updateStatistics();
    renderOrders();
    calculatePopularItems();
    
    // Setup status filter
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', renderOrders);
    }
}

// Update statistics
function updateStatistics() {
    const orders = getOrders();
    
    const totalOrders = orders.length;
    const pendingOrders = orders.filter(o => o.status === 'pendente').length;
    const preparingOrders = orders.filter(o => o.status === 'preparo').length;
    const completedOrders = orders.filter(o => o.status === 'concluido').length;
    
    const totalRevenue = orders.reduce((sum, order) => sum + order.total, 0);
    const avgOrderValue = totalOrders > 0 ? totalRevenue / totalOrders : 0;
    
    // Update DOM
    document.getElementById('stat-total').textContent = totalOrders;
    document.getElementById('stat-pending').textContent = pendingOrders;
    document.getElementById('stat-preparing').textContent = preparingOrders;
    document.getElementById('stat-completed').textContent = completedOrders;
    document.getElementById('stat-revenue').textContent = `R$ ${totalRevenue.toFixed(2)}`;
    document.getElementById('stat-avg-order').textContent = `R$ ${avgOrderValue.toFixed(2)}`;
}

// Calculate most popular items
function calculatePopularItems() {
    const orders = getOrders();
    const itemCounts = {};
    
    orders.forEach(order => {
        order.items.forEach(item => {
            if (itemCounts[item.name]) {
                itemCounts[item.name] += item.quantity;
            } else {
                itemCounts[item.name] = item.quantity;
            }
        });
    });
    
    // Convert to array and sort
    const sortedItems = Object.entries(itemCounts)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5);
    
    const container = document.getElementById('popular-items');
    
    if (sortedItems.length === 0) {
        container.innerHTML = '<p style="color: #666;">Nenhum pedido registrado ainda.</p>';
        return;
    }
    
    let html = '<div style="display: grid; gap: 15px;">';
    sortedItems.forEach(([name, count], index) => {
        const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
        html += `
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 1.5rem;">${medals[index]}</span>
                <span style="flex: 1; margin-left: 15px; color: #333; font-weight: 600;">${name}</span>
                <span style="color: #e8c13f; font-weight: bold; font-size: 1.2rem;">${count}x</span>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Render orders
function renderOrders() {
    const orders = getOrders();
    const container = document.getElementById('orders-container');
    const noOrders = document.getElementById('no-orders');
    const statusFilter = document.getElementById('status-filter');
    
    if (!container) return;
    
    // Filter orders
    let filteredOrders = orders;
    if (statusFilter && statusFilter.value !== 'all') {
        filteredOrders = orders.filter(o => o.status === statusFilter.value);
    }
    
    // Sort by date (newest first)
    filteredOrders.sort((a, b) => new Date(b.date) - new Date(a.date));
    
    if (filteredOrders.length === 0) {
        container.innerHTML = '';
        if (noOrders) noOrders.style.display = 'block';
        return;
    }
    
    if (noOrders) noOrders.style.display = 'none';
    
    let html = '';
    
    filteredOrders.forEach(order => {
        const date = new Date(order.date);
        const formattedDate = date.toLocaleString('pt-BR');
        
        const statusLabels = {
            'pendente': 'Pendente',
            'preparo': 'Em Preparo',
            'concluido': 'Concluído'
        };
        
        html += `
            <div class="order-item">
                <div class="order-header">
                    <span class="order-id">Pedido #${order.id}</span>
                    <span class="order-status status-${order.status}">${statusLabels[order.status]}</span>
                </div>
                
                <div class="order-details">
                    <p><strong>Data:</strong> ${formattedDate}</p>
                    <p><strong>Total:</strong> R$ ${order.total.toFixed(2)}</p>
                    <p><strong>Itens:</strong></p>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        ${order.items.map(item => `
                            <li>${item.quantity}x ${item.name} - R$ ${(item.price * item.quantity).toFixed(2)}</li>
                        `).join('')}
                    </ul>
                </div>
                
                <div class="order-actions">
                    ${order.status === 'pendente' ? `
                        <button class="btn" onclick="changeOrderStatus(${order.id}, 'preparo')">
                            👨‍🍳 Iniciar Preparo
                        </button>
                    ` : ''}
                    ${order.status === 'preparo' ? `
                        <button class="btn btn-success" onclick="changeOrderStatus(${order.id}, 'concluido')">
                            ✅ Marcar como Concluído
                        </button>
                    ` : ''}
                    ${order.status === 'concluido' ? `
                        <button class="btn btn-secondary" onclick="changeOrderStatus(${order.id}, 'pendente')">
                            🔄 Reabrir Pedido
                        </button>
                    ` : ''}
                    <button class="btn btn-danger" onclick="deleteOrder(${order.id})">
                        🗑️ Remover
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Change order status
function changeOrderStatus(orderId, newStatus) {
    updateOrderStatus(orderId, newStatus);
    loadDashboard();
}

// Delete order
function deleteOrder(orderId) {
    if (!confirm('Tem certeza que deseja remover este pedido?')) {
        return;
    }
    
    let orders = getOrders();
    orders = orders.filter(o => o.id !== orderId);
    localStorage.setItem('orders', JSON.stringify(orders));
    
    loadDashboard();
}

// Clear all orders
function clearAllOrders() {
    if (!confirm('ATENÇÃO: Isto irá remover todos os pedidos do sistema. Tem certeza?')) {
        return;
    }
    
    if (!confirm('Esta ação não pode ser desfeita. Confirma a remoção de todos os pedidos?')) {
        return;
    }
    
    localStorage.removeItem('orders');
    loadDashboard();
    alert('Todos os pedidos foram removidos com sucesso!');
}

// Auto-refresh dashboard every 30 seconds
setInterval(function() {
    if (checkAuth()) {
        loadDashboard();
    }
}, 30000);

// ==========================================
// KANBAN BOARD FUNCTIONS
// ==========================================

function loadKanbanBoard() {
    let orders = getOrders();
    
    // Apply filters
    const typeFilter = document.getElementById('kanban-type-filter')?.value;
    const tableFilter = document.getElementById('kanban-table-filter')?.value;
    
    if (typeFilter) {
        orders = orders.filter(order => {
            if (typeFilter === 'table') {
                return order.delivery && order.delivery.tableNumber;
            } else if (typeFilter === 'delivery') {
                return order.delivery && order.delivery.forDelivery;
            } else if (typeFilter === 'pickup') {
                return !order.delivery || (!order.delivery.forDelivery && !order.delivery.tableNumber);
            }
            return true;
        });
    }
    
    if (tableFilter && parseInt(tableFilter) > 0) {
        orders = orders.filter(order => {
            return order.delivery && order.delivery.tableNumber === parseInt(tableFilter);
        });
    }
    
    // Clear all columns
    ['recebido', 'em_andamento', 'finalizado'].forEach(status => {
        const column = document.getElementById(`kanban-${status}`);
        if (column) column.innerHTML = '';
    });
    
    // Group orders by status
    const kanbanData = {
        recebido: [],
        em_andamento: [],
        finalizado: []
    };
    
    orders.forEach(order => {
        // Map old status to new status
        let status = order.status;
        if (status === 'pendente') status = 'recebido';
        if (status === 'preparo') status = 'em_andamento';
        if (status === 'concluido') status = 'finalizado';
        
        if (kanbanData[status]) {
            kanbanData[status].push(order);
        }
    });
    
    // Render cards in each column
    Object.keys(kanbanData).forEach(status => {
        renderKanbanColumn(status, kanbanData[status]);
    });
    
    // Initialize drag and drop
    initDragAndDrop();
}

function clearKanbanFilters() {
    const typeFilter = document.getElementById('kanban-type-filter');
    const tableFilter = document.getElementById('kanban-table-filter');
    
    if (typeFilter) typeFilter.value = '';
    if (tableFilter) tableFilter.value = '';
    
    loadKanbanBoard();
}

function renderKanbanColumn(status, orders) {
    const column = document.getElementById(`kanban-${status}`);
    const countEl = document.getElementById(`count-${status}`);
    
    if (!column) return;
    
    // Update count
    if (countEl) countEl.textContent = orders.length;
    
    if (orders.length === 0) {
        column.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">Nenhum pedido</p>';
        return;
    }
    
    column.innerHTML = '';
    
    orders.forEach(order => {
        const card = createKanbanCard(order);
        column.appendChild(card);
    });
}

function createKanbanCard(order) {
    const card = document.createElement('div');
    card.className = 'kanban-card';
    card.draggable = true;
    card.dataset.orderId = order.id;
    
    const date = new Date(order.date);
    const timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    
    const itemsList = order.items.map(item => 
        `${item.quantity}x ${item.name}`
    ).join(', ');
    
    // Determine order type and details
    let orderTypeInfo = '';
    let orderTypeClass = '';
    
    if (order.delivery && order.delivery.tableNumber) {
        orderTypeInfo = `<span class="kanban-badge kanban-badge-table">🪑 Mesa ${order.delivery.tableNumber}</span>`;
        orderTypeClass = ' kanban-card-table';
    } else if (order.delivery && order.delivery.forDelivery) {
        orderTypeInfo = '<span class="kanban-badge kanban-badge-delivery">🚚 Entrega</span>';
        orderTypeClass = ' kanban-card-delivery';
    } else {
        orderTypeInfo = '<span class="kanban-badge kanban-badge-pickup">📦 Retirada</span>';
        orderTypeClass = ' kanban-card-pickup';
    }
    
    // Add user info if available
    let userInfo = '';
    if (order.delivery && order.delivery.userId) {
        userInfo = `<div style="font-size: 0.85rem; color: #666; margin-top: 5px;">👤 Usuário ID: ${order.delivery.userId}</div>`;
    }
    
    card.innerHTML = `
        <div class="kanban-card-header">
            <span class="kanban-card-id">Pedido #${order.id}</span>
            <span class="kanban-card-time">${timeStr}</span>
        </div>
        <div class="kanban-card-type">
            ${orderTypeInfo}
        </div>
        <div class="kanban-card-content">
            <div class="kanban-card-items">${itemsList}</div>
            <div class="kanban-card-total">R$ ${order.total.toFixed(2)}</div>
            ${userInfo}
        </div>
    `;
    
    card.classList.add(orderTypeClass);
    
    return card;
}

function initDragAndDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-cards');
    
    cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
    });
}

let draggedElement = null;

function handleDragStart(e) {
    draggedElement = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragEnter(e) {
    this.classList.add('drag-over');
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    this.classList.remove('drag-over');
    
    if (draggedElement !== this) {
        const orderId = parseInt(draggedElement.dataset.orderId);
        const newStatus = this.closest('.kanban-column').dataset.status;
        
        // Update order status
        updateOrderStatus(orderId, mapKanbanStatusToOld(newStatus));
        
        // Reload kanban board
        loadKanbanBoard();
    }
    
    return false;
}

function mapKanbanStatusToOld(newStatus) {
    const mapping = {
        'recebido': 'pendente',
        'em_andamento': 'preparo',
        'finalizado': 'concluido'
    };
    return mapping[newStatus] || newStatus;
}

// ==========================================
// MENU MANAGEMENT FUNCTIONS
// ==========================================

function getMenuData() {
    const menu = localStorage.getItem('menu_data');
    return menu ? JSON.parse(menu) : { groups: [], items: [] };
}

function saveMenuData(menuData) {
    localStorage.setItem('menu_data', JSON.stringify(menuData));
}

function loadMenuManagement() {
    const container = document.getElementById('menu-management');
    if (!container) return;
    
    const menuData = getMenuData();
    
    if (menuData.groups.length === 0) {
        container.innerHTML = '<p style="color: #666;">Nenhum grupo cadastrado ainda. Clique em "Adicionar Grupo" para criar um grupo de menu.</p>';
        return;
    }
    
    let html = '';
    
    menuData.groups.forEach(group => {
        const groupItems = menuData.items.filter(item => item.groupId === group.id);
        
        html += `
            <div class="menu-group">
                <div class="menu-group-header">
                    <div>
                        <h3 style="color: #e8c13f; margin-bottom: 5px;">${group.name}</h3>
                        ${group.description ? `<p style="color: #666; font-size: 0.9rem;">${group.description}</p>` : ''}
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn" onclick="editGroup(${group.id})" style="padding: 8px 16px;">✏️ Editar</button>
                        <button class="btn btn-danger" onclick="deleteGroup(${group.id})" style="padding: 8px 16px;">🗑️ Excluir</button>
                    </div>
                </div>
                
                ${groupItems.length > 0 ? `
                    <div style="margin-top: 15px;">
                        ${groupItems.map(item => `
                            <div class="menu-item">
                                <div class="menu-item-info">
                                    <h4 style="color: #333; margin-bottom: 5px;">${item.name}</h4>
                                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">${item.description || ''}</p>
                                    <p style="color: #e8c13f; font-weight: bold; font-size: 1.1rem;">R$ ${parseFloat(item.price).toFixed(2)}</p>
                                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                                        ${item.deliveryEnabled ? '<span style="color: #28a745; font-size: 0.85rem;">🚚 Entrega</span>' : '<span style="color: #dc3545; font-size: 0.85rem;">🚚 Sem Entrega</span>'}
                                        ${item.available ? '<span style="color: #28a745; font-size: 0.85rem;">✅ Disponível</span>' : '<span style="color: #dc3545; font-size: 0.85rem;">❌ Indisponível</span>'}
                                    </div>
                                </div>
                                <div class="menu-item-actions">
                                    <button class="btn" onclick="editItem(${item.id})" style="padding: 8px 16px;">✏️</button>
                                    <button class="btn btn-danger" onclick="deleteItem(${item.id})" style="padding: 8px 16px;">🗑️</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p style="color: #999; font-style: italic; margin-top: 10px;">Nenhum item neste grupo</p>'}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function showAddGroupModal() {
    const modal = document.getElementById('group-modal');
    const modalTitle = document.getElementById('group-modal-title');
    const form = document.getElementById('group-form');
    
    if (modal && modalTitle && form) {
        modalTitle.textContent = 'Adicionar Grupo';
        form.reset();
        document.getElementById('group-id').value = '';
        modal.style.display = 'block';
    }
}

function closeGroupModal() {
    const modal = document.getElementById('group-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function saveGroup(event) {
    event.preventDefault();
    
    const groupId = document.getElementById('group-id')?.value;
    const name = document.getElementById('group-name')?.value;
    const description = document.getElementById('group-description')?.value;
    
    if (!name) {
        alert('Por favor, informe o nome do grupo.');
        return;
    }
    
    const menuData = getMenuData();
    
    if (groupId) {
        // Edit existing group
        const index = menuData.groups.findIndex(g => g.id === parseInt(groupId));
        if (index !== -1) {
            menuData.groups[index] = {
                ...menuData.groups[index],
                name,
                description
            };
        }
    } else {
        // Add new group
        const newGroup = {
            id: Date.now(),
            name,
            description
        };
        menuData.groups.push(newGroup);
    }
    
    saveMenuData(menuData);
    closeGroupModal();
    loadMenuManagement();
    alert('Grupo salvo com sucesso!');
}

function editGroup(groupId) {
    const menuData = getMenuData();
    const group = menuData.groups.find(g => g.id === groupId);
    
    if (!group) {
        alert('Grupo não encontrado!');
        return;
    }
    
    const modal = document.getElementById('group-modal');
    const modalTitle = document.getElementById('group-modal-title');
    
    if (modal && modalTitle) {
        modalTitle.textContent = 'Editar Grupo';
        document.getElementById('group-id').value = group.id;
        document.getElementById('group-name').value = group.name;
        document.getElementById('group-description').value = group.description || '';
        modal.style.display = 'block';
    }
}

function deleteGroup(groupId) {
    const menuData = getMenuData();
    const groupItems = menuData.items.filter(item => item.groupId === groupId);
    
    if (groupItems.length > 0) {
        if (!confirm(`Este grupo possui ${groupItems.length} item(ns). Todos os itens serão excluídos. Tem certeza?`)) {
            return;
        }
    } else {
        if (!confirm('Tem certeza que deseja excluir este grupo?')) {
            return;
        }
    }
    
    menuData.groups = menuData.groups.filter(g => g.id !== groupId);
    menuData.items = menuData.items.filter(item => item.groupId !== groupId);
    
    saveMenuData(menuData);
    loadMenuManagement();
    alert('Grupo excluído com sucesso!');
}

function showAddItemModal() {
    const menuData = getMenuData();
    
    if (menuData.groups.length === 0) {
        alert('Por favor, crie um grupo primeiro antes de adicionar itens.');
        return;
    }
    
    const modal = document.getElementById('item-modal');
    const modalTitle = document.getElementById('item-modal-title');
    const form = document.getElementById('item-form');
    const groupSelect = document.getElementById('item-group');
    
    if (modal && modalTitle && form && groupSelect) {
        modalTitle.textContent = 'Adicionar Item';
        form.reset();
        document.getElementById('item-id').value = '';
        document.getElementById('item-delivery').checked = true;
        document.getElementById('item-available').checked = true;
        
        // Populate group select
        groupSelect.innerHTML = '<option value="">Selecione um grupo</option>';
        menuData.groups.forEach(group => {
            groupSelect.innerHTML += `<option value="${group.id}">${group.name}</option>`;
        });
        
        modal.style.display = 'block';
    }
}

function closeItemModal() {
    const modal = document.getElementById('item-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function saveItem(event) {
    event.preventDefault();
    
    const itemId = document.getElementById('item-id')?.value;
    const groupId = parseInt(document.getElementById('item-group')?.value);
    const name = document.getElementById('item-name')?.value;
    const description = document.getElementById('item-description')?.value;
    const price = parseFloat(document.getElementById('item-price')?.value);
    const image = document.getElementById('item-image')?.value;
    const deliveryEnabled = document.getElementById('item-delivery')?.checked || false;
    const available = document.getElementById('item-available')?.checked || false;
    
    if (!groupId || !name || isNaN(price)) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }
    
    const menuData = getMenuData();
    
    if (itemId) {
        // Edit existing item
        const index = menuData.items.findIndex(i => i.id === parseInt(itemId));
        if (index !== -1) {
            menuData.items[index] = {
                ...menuData.items[index],
                groupId,
                name,
                description,
                price,
                image,
                deliveryEnabled,
                available
            };
        }
    } else {
        // Add new item
        const newItem = {
            id: Date.now(),
            groupId,
            name,
            description,
            price,
            image,
            deliveryEnabled,
            available
        };
        menuData.items.push(newItem);
    }
    
    saveMenuData(menuData);
    closeItemModal();
    loadMenuManagement();
    alert('Item salvo com sucesso!');
}

function editItem(itemId) {
    const menuData = getMenuData();
    const item = menuData.items.find(i => i.id === itemId);
    
    if (!item) {
        alert('Item não encontrado!');
        return;
    }
    
    const modal = document.getElementById('item-modal');
    const modalTitle = document.getElementById('item-modal-title');
    const groupSelect = document.getElementById('item-group');
    
    if (modal && modalTitle && groupSelect) {
        modalTitle.textContent = 'Editar Item';
        
        // Populate group select
        groupSelect.innerHTML = '<option value="">Selecione um grupo</option>';
        menuData.groups.forEach(group => {
            groupSelect.innerHTML += `<option value="${group.id}">${group.name}</option>`;
        });
        
        // Fill form
        document.getElementById('item-id').value = item.id;
        document.getElementById('item-group').value = item.groupId;
        document.getElementById('item-name').value = item.name;
        document.getElementById('item-description').value = item.description || '';
        document.getElementById('item-price').value = item.price;
        document.getElementById('item-image').value = item.image || '';
        document.getElementById('item-delivery').checked = item.deliveryEnabled;
        document.getElementById('item-available').checked = item.available;
        
        modal.style.display = 'block';
    }
}

function deleteItem(itemId) {
    if (!confirm('Tem certeza que deseja excluir este item?')) {
        return;
    }
    
    const menuData = getMenuData();
    menuData.items = menuData.items.filter(i => i.id !== itemId);
    
    saveMenuData(menuData);
    loadMenuManagement();
    alert('Item excluído com sucesso!');
}

// ==========================================
// REPORTS FUNCTIONS
// ==========================================

function initReportFilters() {
    // Set default dates
    const today = new Date();
    const lastMonth = new Date(today);
    lastMonth.setMonth(lastMonth.getMonth() - 1);
    
    const dateFromInput = document.getElementById('report-date-from');
    const dateToInput = document.getElementById('report-date-to');
    
    if (dateFromInput) dateFromInput.value = lastMonth.toISOString().split('T')[0];
    if (dateToInput) dateToInput.value = today.toISOString().split('T')[0];
}

function generateReport() {
    const reportType = document.getElementById('report-type')?.value;
    const dateFrom = document.getElementById('report-date-from')?.value;
    const dateTo = document.getElementById('report-date-to')?.value;
    const resultsDiv = document.getElementById('report-results');
    
    if (!resultsDiv) return;
    
    resultsDiv.innerHTML = '<p style="color: #666; text-align: center; padding: 40px;">Gerando relatório...</p>';
    
    // Simulate report generation based on local storage data
    setTimeout(() => {
        const orders = getOrders();
        
        if (reportType === 'revenue') {
            generateRevenueReport(orders, dateFrom, dateTo, resultsDiv);
        } else if (reportType === 'popular-items') {
            generatePopularItemsReport(orders, resultsDiv);
        } else if (reportType === 'customer-flow') {
            generateCustomerFlowReport(orders, resultsDiv);
        }
    }, 500);
}

function generateRevenueReport(orders, dateFrom, dateTo, container) {
    const filteredOrders = orders.filter(order => {
        const orderDate = new Date(order.date).toISOString().split('T')[0];
        return orderDate >= dateFrom && orderDate <= dateTo;
    });
    
    const totalRevenue = filteredOrders.reduce((sum, order) => sum + order.total, 0);
    const completedOrders = filteredOrders.filter(o => o.status === 'concluido' || o.status === 'finalizado');
    const completedRevenue = completedOrders.reduce((sum, order) => sum + order.total, 0);
    
    container.innerHTML = `
        <div class="report-card">
            <h3>Relatório de Faturamento</h3>
            <p><strong>Período:</strong> ${dateFrom} a ${dateTo}</p>
            <div class="stats-grid" style="margin-top: 20px;">
                <div class="stat-card">
                    <div class="stat-value">${filteredOrders.length}</div>
                    <div class="stat-label">Total de Pedidos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ ${totalRevenue.toFixed(2)}</div>
                    <div class="stat-label">Receita Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ ${completedRevenue.toFixed(2)}</div>
                    <div class="stat-label">Receita Concluída</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ ${(completedRevenue / completedOrders.length || 0).toFixed(2)}</div>
                    <div class="stat-label">Ticket Médio</div>
                </div>
            </div>
        </div>
    `;
}

function generatePopularItemsReport(orders, container) {
    const itemCounts = {};
    
    orders.forEach(order => {
        order.items.forEach(item => {
            if (!itemCounts[item.name]) {
                itemCounts[item.name] = { quantity: 0, revenue: 0 };
            }
            itemCounts[item.name].quantity += item.quantity;
            itemCounts[item.name].revenue += item.price * item.quantity;
        });
    });
    
    const sortedItems = Object.entries(itemCounts)
        .sort((a, b) => b[1].quantity - a[1].quantity)
        .slice(0, 10);
    
    let html = '<div class="report-card"><h3>Top 10 Produtos Mais Pedidos</h3><div style="margin-top: 20px;">';
    
    sortedItems.forEach(([name, data], index) => {
        html += `
            <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; display: flex; justify-content: space-between;">
                <div>
                    <span style="font-weight: bold; margin-right: 10px;">#${index + 1}</span>
                    <span>${name}</span>
                </div>
                <div>
                    <span style="color: #e8c13f; font-weight: bold; margin-right: 20px;">${data.quantity}x</span>
                    <span style="color: #28a745; font-weight: bold;">R$ ${data.revenue.toFixed(2)}</span>
                </div>
            </div>
        `;
    });
    
    html += '</div></div>';
    container.innerHTML = html;
}

function generateCustomerFlowReport(orders, container) {
    const hourCounts = {};
    
    orders.forEach(order => {
        const hour = new Date(order.date).getHours();
        hourCounts[hour] = (hourCounts[hour] || 0) + 1;
    });
    
    let html = '<div class="report-card"><h3>Fluxo de Clientes por Horário</h3><div style="margin-top: 20px;">';
    
    for (let hour = 0; hour < 24; hour++) {
        const count = hourCounts[hour] || 0;
        const barWidth = count > 0 ? (count / Math.max(...Object.values(hourCounts))) * 100 : 0;
        
        html += `
            <div style="margin: 10px 0;">
                <div style="display: flex; align-items: center;">
                    <span style="min-width: 60px;">${hour}:00</span>
                    <div style="flex: 1; background: #e9ecef; height: 30px; border-radius: 4px; margin: 0 10px; overflow: hidden;">
                        <div style="width: ${barWidth}%; height: 100%; background: #e8c13f; transition: width 0.3s;"></div>
                    </div>
                    <span style="min-width: 40px; text-align: right; font-weight: bold;">${count}</span>
                </div>
            </div>
        `;
    }
    
    html += '</div></div>';
    container.innerHTML = html;
}

// ==========================================
// RESUMES FUNCTIONS
// ==========================================

function loadResumes() {
    const container = document.getElementById('resumes-list');
    if (!container) return;
    
    container.innerHTML = '<p style="color: #666;">Nenhum currículo recebido ainda. Os currículos enviados pelo formulário aparecerão aqui.</p>';
    // In a real implementation, this would fetch from the API
}

// ==========================================
// OUVIDORIA FUNCTIONS
// ==========================================

function loadOuvidoriaMessages() {
    const container = document.getElementById('ouvidoria-list');
    if (!container) return;
    
    container.innerHTML = '<p style="color: #666;">Nenhuma mensagem recebida ainda. As mensagens da ouvidoria aparecerão aqui.</p>';
    // In a real implementation, this would fetch from the API
}

// ==========================================
// SETTINGS FUNCTIONS
// ==========================================

function loadSettings() {
    // Load current settings
    // In a real implementation, this would fetch from the API
    const restaurantStatus = document.getElementById('restaurant-status');
    const statusLabel = document.getElementById('restaurant-status-label');
    
    if (restaurantStatus && statusLabel) {
        // Default to closed for demo
        restaurantStatus.checked = false;
        statusLabel.textContent = 'Fechado';
        statusLabel.style.color = '#dc3545';
    }
}

function toggleRestaurantStatus() {
    const checkbox = document.getElementById('restaurant-status');
    const label = document.getElementById('restaurant-status-label');
    
    if (checkbox && label) {
        if (checkbox.checked) {
            label.textContent = 'Aberto';
            label.style.color = '#28a745';
        } else {
            label.textContent = 'Fechado';
            label.style.color = '#dc3545';
        }
    }
}

function toggleMaintenanceMode() {
    const checkbox = document.getElementById('maintenance-mode');
    const optionsDiv = document.getElementById('maintenance-options');
    
    if (checkbox && checkbox.checked) {
        if (!confirm('Ativar modo manutenção? Isso pode restringir o acesso ao site.')) {
            checkbox.checked = false;
            return;
        }
        if (optionsDiv) optionsDiv.style.display = 'block';
    } else {
        if (optionsDiv) optionsDiv.style.display = 'none';
    }
}

function toggleRestrictAll() {
    const restrictAll = document.getElementById('restrict-all');
    const pageCheckboxes = document.querySelectorAll('.maintenance-page');
    
    if (restrictAll && restrictAll.checked) {
        pageCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.disabled = true;
        });
    } else {
        pageCheckboxes.forEach(cb => {
            cb.disabled = false;
        });
    }
}

function saveSettings() {
    const settings = {
        restaurantStatus: document.getElementById('restaurant-status')?.checked,
        kitchenStart: document.getElementById('kitchen-start')?.value,
        kitchenEnd: document.getElementById('kitchen-end')?.value,
        pizzaStart: document.getElementById('pizza-start')?.value,
        pizzaEnd: document.getElementById('pizza-end')?.value,
        deliveryStart: document.getElementById('delivery-start')?.value,
        deliveryEnd: document.getElementById('delivery-end')?.value,
        maxDeliveryDistance: document.getElementById('max-delivery-distance')?.value,
        deliveryFeePerKm: document.getElementById('delivery-fee-per-km')?.value,
        maintenanceMode: document.getElementById('maintenance-mode')?.checked
    };
    
    // Get maintenance mode configuration
    if (settings.maintenanceMode) {
        settings.restrictAll = document.getElementById('restrict-all')?.checked;
        
        const restrictedPages = [];
        document.querySelectorAll('.maintenance-page:checked').forEach(cb => {
            restrictedPages.push(cb.value);
        });
        settings.restrictedPages = restrictedPages;
        settings.maintenanceMessage = document.getElementById('maintenance-message')?.value;
        settings.maintenanceETA = document.getElementById('maintenance-eta')?.value;
    }
    
    // In a real implementation, this would save to the API
    console.log('Saving settings:', settings);
    
    // TODO: Call API to save maintenance mode settings
    // fetch('/api/admin/maintenance.php?action=update', {
    //     method: 'PUT',
    //     headers: { 'Content-Type': 'application/json' },
    //     body: JSON.stringify(settings)
    // });
    
    alert('Configurações salvas com sucesso!');
}

// ==========================================
// NOTES MANAGEMENT FUNCTIONS
// ==========================================

function getNotes() {
    const notes = localStorage.getItem('admin_notes');
    return notes ? JSON.parse(notes) : [];
}

function saveNotesToStorage(notes) {
    localStorage.setItem('admin_notes', JSON.stringify(notes));
}

function loadNotes() {
    const container = document.getElementById('notes-list');
    if (!container) return;
    
    const notes = getNotes();
    
    if (notes.length === 0) {
        container.innerHTML = '<p style="color: #666;">Nenhuma nota cadastrada ainda. Clique em "Adicionar Nova Nota" para criar uma.</p>';
        return;
    }
    
    let html = '';
    
    notes.forEach(note => {
        const date = new Date(note.createdAt);
        const formattedDate = date.toLocaleString('pt-BR');
        
        html += `
            <div class="note-card" style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <h3 style="color: #e8c13f; margin-bottom: 5px;">${note.title}</h3>
                        <small style="color: #666;">Criado em: ${formattedDate}</small>
                    </div>
                    <div>
                        <span class="note-status ${note.active ? 'status-ativo' : 'status-inativo'}" 
                              style="padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; 
                                     ${note.active ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;'}">
                            ${note.active ? '✓ Ativa' : '✗ Inativa'}
                        </span>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; color: #333;">
                    ${note.content.replace(/\n/g, '<br>')}
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn" onclick="editNote(${note.id})" style="flex: 1;">✏️ Editar</button>
                    <button class="btn ${note.active ? 'btn-secondary' : ''}" 
                            onclick="toggleNoteStatus(${note.id})" style="flex: 1;">
                        ${note.active ? '🔕 Desativar' : '🔔 Ativar'}
                    </button>
                    <button class="btn btn-danger" onclick="deleteNote(${note.id})" style="flex: 1;">🗑️ Excluir</button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function showAddNoteModal() {
    const modal = document.getElementById('note-modal');
    const modalTitle = document.getElementById('note-modal-title');
    const form = document.getElementById('note-form');
    
    if (modal && modalTitle && form) {
        modalTitle.textContent = 'Adicionar Nova Nota';
        form.reset();
        document.getElementById('note-id').value = '';
        document.getElementById('note-active').checked = true;
        modal.style.display = 'block';
    }
}

function closeNoteModal() {
    const modal = document.getElementById('note-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function saveNote(event) {
    event.preventDefault();
    
    const noteId = document.getElementById('note-id')?.value;
    const title = document.getElementById('note-title')?.value;
    const content = document.getElementById('note-content')?.value;
    const active = document.getElementById('note-active')?.checked || false;
    
    if (!title || !content) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }
    
    const notes = getNotes();
    
    if (noteId) {
        // Edit existing note
        const index = notes.findIndex(n => n.id === parseInt(noteId));
        if (index !== -1) {
            notes[index] = {
                ...notes[index],
                title,
                content,
                active,
                updatedAt: new Date().toISOString()
            };
        }
    } else {
        // Add new note
        const newNote = {
            id: Date.now(),
            title,
            content,
            active,
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
        notes.push(newNote);
    }
    
    saveNotesToStorage(notes);
    closeNoteModal();
    loadNotes();
    alert('Nota salva com sucesso!');
}

function editNote(noteId) {
    const notes = getNotes();
    const note = notes.find(n => n.id === noteId);
    
    if (!note) {
        alert('Nota não encontrada!');
        return;
    }
    
    const modal = document.getElementById('note-modal');
    const modalTitle = document.getElementById('note-modal-title');
    
    if (modal && modalTitle) {
        modalTitle.textContent = 'Editar Nota';
        document.getElementById('note-id').value = note.id;
        document.getElementById('note-title').value = note.title;
        document.getElementById('note-content').value = note.content;
        document.getElementById('note-active').checked = note.active;
        modal.style.display = 'block';
    }
}

function toggleNoteStatus(noteId) {
    const notes = getNotes();
    const note = notes.find(n => n.id === noteId);
    
    if (!note) {
        alert('Nota não encontrada!');
        return;
    }
    
    note.active = !note.active;
    note.updatedAt = new Date().toISOString();
    
    saveNotesToStorage(notes);
    loadNotes();
}

function deleteNote(noteId) {
    if (!confirm('Tem certeza que deseja excluir esta nota?')) {
        return;
    }
    
    let notes = getNotes();
    notes = notes.filter(n => n.id !== noteId);
    
    saveNotesToStorage(notes);
    loadNotes();
    alert('Nota excluída com sucesso!');
}

// ============================================
// PERMISSION-BASED MENU FILTERING
// ============================================

/**
 * Filter admin menu tabs based on user permissions
 */
async function filterAdminMenuByPermissions() {
    // Check if user has permissions loaded
    if (!window.fetchUserInfo) {
        console.log('Permission functions not available');
        return;
    }
    
    try {
        // Fetch fresh user info with permissions
        const userInfo = await fetchUserInfo();
        
        if (!userInfo || !userInfo.permissionMap) {
            console.log('No permission info available');
            return;
        }
        
        // Define tab-to-permission mapping
        const tabPermissions = {
            'dashboard': null, // Always visible
            'orders': 'order_view',
            'menu': 'menu_view',
            'notes': null, // Always visible for admins
            'reports': 'reports_access',
            'resumes': 'resumes_access',
            'ouvidoria': 'ouvidoria_access',
            'permissions': 'permissions_management',
            'roles': 'roles_management',
            'users': 'users_management',
            'settings': 'settings_access'
        };
        
        // Filter menu items
        const navLinks = document.querySelectorAll('.nav-tab');
        
        navLinks.forEach(link => {
            const tabName = link.getAttribute('data-tab');
            const requiredPermission = tabPermissions[tabName];
            
            // If no permission required or user has admin access, show tab
            if (!requiredPermission || userInfo.hasAdminAccess || userInfo.permissionMap[requiredPermission]) {
                link.parentElement.style.display = '';
            } else {
                // Hide tab if user doesn't have permission
                link.parentElement.style.display = 'none';
            }
        });
        
    } catch (error) {
        console.error('Error filtering admin menu:', error);
        // On error, don't hide any tabs to avoid locking out users
    }
}

/**
 * Check if user has permission to access a specific tab
 */
function canAccessTab(tabName) {
    const userInfoStr = localStorage.getItem('userInfo');
    if (!userInfoStr) return false;
    
    try {
        const userInfo = JSON.parse(userInfoStr);
        
        // Admin has access to everything
        if (userInfo.hasAdminAccess) return true;
        
        // Define permission requirements
        const tabPermissions = {
            'dashboard': true, // Always accessible
            'orders': userInfo.permissionMap['order_view'],
            'menu': userInfo.permissionMap['menu_view'],
            'notes': true, // Always accessible
            'reports': userInfo.permissionMap['reports_access'],
            'resumes': userInfo.permissionMap['resumes_access'],
            'ouvidoria': userInfo.permissionMap['ouvidoria_access'],
            'permissions': userInfo.permissionMap['permissions_management'],
            'roles': userInfo.permissionMap['roles_management'],
            'users': userInfo.permissionMap['users_management'],
            'settings': userInfo.permissionMap['settings_access']
        };
        
        return tabPermissions[tabName] || false;
        
    } catch (error) {
        console.error('Error checking tab access:', error);
        return false;
    }
}

/**
 * Show access denied message
 */
function showAccessDenied(tabName) {
    const tabElement = document.getElementById(`tab-${tabName}`);
    if (tabElement) {
        tabElement.innerHTML = `
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 4rem; margin-bottom: 20px;">🔒</div>
                <h2 style="color: #dc3545; margin-bottom: 15px;">Acesso Negado</h2>
                <p style="color: #666; font-size: 1.1rem;">
                    Você não tem permissão para acessar esta seção do painel administrativo.
                </p>
                <p style="color: #999; margin-top: 10px;">
                    Entre em contato com o administrador se você acredita que deveria ter acesso.
                </p>
            </div>
        `;
    }
}

// ============================================
// REVIEWS MANAGEMENT
// ============================================

/**
 * Load reviews management
 */
async function loadReviews() {
    try {
        // Load statistics
        const statsResponse = await fetch('/api/reviews.php?action=statistics');
        const statsData = await statsResponse.json();
        
        if (statsData.success) {
            const stats = statsData.statistics;
            document.getElementById('review-average').textContent = stats.average_rating.toFixed(1);
            document.getElementById('review-total').textContent = stats.total_reviews;
            document.getElementById('review-approved').textContent = stats.approved_reviews;
            
            // Calculate pending (total - approved)
            const pending = stats.total_reviews - stats.approved_reviews;
            document.getElementById('review-pending').textContent = pending;
            
            // Display rating distribution
            displayRatingDistribution(stats.rating_distribution);
        }
        
        // Load reviews list
        await loadReviewsList();
        
    } catch (error) {
        console.error('Error loading reviews:', error);
        document.getElementById('reviews-list').innerHTML = 
            '<p style="color: #dc3545;">Erro ao carregar avaliações.</p>';
    }
}

/**
 * Display rating distribution chart
 */
function displayRatingDistribution(distribution) {
    const container = document.getElementById('rating-distribution');
    container.innerHTML = '';
    
    const total = distribution.reduce((sum, count) => sum + count, 0);
    
    for (let i = 5; i >= 1; i--) {
        const count = distribution[i] || 0;
        const percentage = total > 0 ? (count / total * 100).toFixed(1) : 0;
        
        const row = document.createElement('div');
        row.style.cssText = 'display: flex; align-items: center; gap: 10px;';
        row.innerHTML = `
            <span style="min-width: 60px; color: #666;">${i} ⭐</span>
            <div style="flex: 1; height: 30px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                <div style="height: 100%; background: #ffd700; width: ${percentage}%; transition: width 0.5s;"></div>
            </div>
            <span style="min-width: 80px; color: #666; text-align: right;">${count} (${percentage}%)</span>
        `;
        container.appendChild(row);
    }
}

/**
 * Load reviews list
 */
async function loadReviewsList() {
    const statusFilter = document.getElementById('review-status-filter').value;
    const container = document.getElementById('reviews-list');
    
    container.innerHTML = '<p style="color: #666;">Carregando avaliações...</p>';
    
    try {
        let url = '/api/reviews.php?action=list&per_page=50';
        if (statusFilter) {
            url += `&status=${statusFilter}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success || data.reviews.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhuma avaliação encontrada.</p>';
            return;
        }
        
        container.innerHTML = '';
        
        data.reviews.forEach(review => {
            const reviewCard = document.createElement('div');
            reviewCard.className = 'order-card';
            reviewCard.style.marginBottom = '15px';
            
            const stars = '⭐'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
            const statusBadge = getReviewStatusBadge(review.status);
            
            reviewCard.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                    <div>
                        <div style="font-size: 1.5rem; margin-bottom: 5px;">${stars}</div>
                        <div style="color: #666; font-size: 0.9rem;">
                            <strong>${review.user_name || 'Anônimo'}</strong> • 
                            ${new Date(review.created_at).toLocaleDateString('pt-BR')}
                        </div>
                    </div>
                    <div>${statusBadge}</div>
                </div>
                ${review.comment ? `<p style="color: #666; margin: 15px 0;">${review.comment}</p>` : ''}
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    ${review.status === 'pendente' ? `
                        <button class="btn btn-success" onclick="updateReviewStatus(${review.id}, 'aprovado')">
                            ✅ Aprovar
                        </button>
                        <button class="btn btn-danger" onclick="updateReviewStatus(${review.id}, 'rejeitado')">
                            ❌ Rejeitar
                        </button>
                    ` : ''}
                    ${review.status !== 'arquivado' ? `
                        <button class="btn btn-secondary" onclick="updateReviewStatus(${review.id}, 'arquivado')">
                            📁 Arquivar
                        </button>
                    ` : ''}
                    <button class="btn btn-danger" onclick="deleteReview(${review.id})">
                        🗑️ Deletar
                    </button>
                </div>
            `;
            
            container.appendChild(reviewCard);
        });
        
    } catch (error) {
        console.error('Error loading reviews list:', error);
        container.innerHTML = '<p style="color: #dc3545;">Erro ao carregar avaliações.</p>';
    }
}

/**
 * Get review status badge
 */
function getReviewStatusBadge(status) {
    const badges = {
        'pendente': '<span style="background: #ffc107; color: #856404; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">⏳ Pendente</span>',
        'aprovado': '<span style="background: #28a745; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">✅ Aprovado</span>',
        'rejeitado': '<span style="background: #dc3545; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">❌ Rejeitado</span>',
        'arquivado': '<span style="background: #6c757d; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">📁 Arquivado</span>'
    };
    return badges[status] || '';
}

/**
 * Update review status
 */
async function updateReviewStatus(reviewId, status) {
    try {
        const response = await fetch('/api/reviews.php?action=update-status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ review_id: reviewId, status: status })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Status atualizado com sucesso!');
            loadReviews(); // Reload the list
        } else {
            alert('❌ Erro ao atualizar status: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating review status:', error);
        alert('❌ Erro ao atualizar status');
    }
}

/**
 * Delete review
 */
async function deleteReview(reviewId) {
    if (!confirm('Tem certeza que deseja deletar esta avaliação? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/reviews.php?action=delete&review_id=${reviewId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Avaliação deletada com sucesso!');
            loadReviews();
        } else {
            alert('❌ Erro ao deletar avaliação: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting review:', error);
        alert('❌ Erro ao deletar avaliação');
    }
}

// ============================================
// SCHEDULE MANAGEMENT
// ============================================

/**
 * Load schedules management
 */
async function loadSchedules() {
    const container = document.getElementById('schedule-list');
    container.innerHTML = '<p style="color: #666;">Carregando horários...</p>';
    
    try {
        const response = await fetch('/api/admin/schedule.php?action=list');
        const data = await response.json();
        
        if (!data.success || data.schedules.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhum horário configurado.</p>';
            return;
        }
        
        // Group schedules by user
        const schedulesByUser = {};
        data.schedules.forEach(schedule => {
            if (!schedulesByUser[schedule.user_id]) {
                schedulesByUser[schedule.user_id] = {
                    user_name: schedule.user_name,
                    schedules: []
                };
            }
            schedulesByUser[schedule.user_id].schedules.push(schedule);
        });
        
        container.innerHTML = '';
        
        Object.keys(schedulesByUser).forEach(userId => {
            const userData = schedulesByUser[userId];
            
            const userSection = document.createElement('div');
            userSection.className = 'menu-section';
            userSection.style.marginBottom = '30px';
            
            userSection.innerHTML = `
                <h3 style="color: #333; margin-bottom: 15px;">👤 ${userData.user_name}</h3>
                <table class="schedule-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 10px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">Dia</th>
                            <th style="padding: 10px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">Entrada</th>
                            <th style="padding: 10px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">Almoço</th>
                            <th style="padding: 10px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">Retorno</th>
                            <th style="padding: 10px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">Saída</th>
                            <th style="padding: 10px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${userData.schedules.map(schedule => `
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">${capitalize(schedule.day_of_week)}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">${schedule.shift_start}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">${schedule.lunch_start || '-'}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">${schedule.lunch_end || '-'}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">${schedule.shift_end}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e9ecef;">
                                    <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.85rem;" onclick="editSchedule(${schedule.id})">✏️</button>
                                    <button class="btn btn-danger" style="padding: 5px 10px; font-size: 0.85rem;" onclick="deleteSchedule(${schedule.id})">🗑️</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            
            container.appendChild(userSection);
        });
        
    } catch (error) {
        console.error('Error loading schedules:', error);
        container.innerHTML = '<p style="color: #dc3545;">Erro ao carregar horários.</p>';
    }
}

/**
 * Show add schedule modal
 */
function showAddScheduleModal() {
    // TODO: Implement modal for adding schedule
    alert('Funcionalidade de adicionar horário será implementada em breve.');
}

/**
 * Edit schedule
 */
function editSchedule(scheduleId) {
    // TODO: Implement schedule editing
    alert('Funcionalidade de editar horário será implementada em breve.');
}

/**
 * Delete schedule
 */
async function deleteSchedule(scheduleId) {
    if (!confirm('Tem certeza que deseja deletar este horário?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/schedule.php?action=delete&id=${scheduleId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Horário deletado com sucesso!');
            loadSchedules();
        } else {
            alert('❌ Erro ao deletar horário: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting schedule:', error);
        alert('❌ Erro ao deletar horário');
    }
}

/**
 * Capitalize first letter
 */
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

