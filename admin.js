// Admin configuration
const ADMIN_CREDENTIALS = {
    username: 'admin',
    password: 'portuga123'
};

// Debug mode for development
const DEBUG_MODE = true;  // Set to false in production

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

// ==========================================
// ADMIN PANEL DISPLAY FUNCTION (GLOBAL)
// ==========================================

/**
 * Show admin panel and hide login screen
 * Must be in global scope to be callable from everywhere
 */
function showAdminPanel() {
    console.log('🔵 [ADMIN] ===== showAdminPanel() CALLED =====');
    console.log('🔵 [ADMIN] Timestamp:', new Date().toISOString());
    console.log('🔵 [ADMIN] Call stack:', new Error().stack);
    
    const loginSection = document.getElementById('login-section');
    const adminPanel = document.getElementById('admin-panel');
    
    console.log('[ADMIN] Panel elements check:', {
        loginSection: loginSection ? 'Found' : 'NULL',
        adminPanel: adminPanel ? 'Found' : 'NULL',
        loginSection_currentDisplay: loginSection?.style.display || '(empty)',
        adminPanel_currentDisplay: adminPanel?.style.display || '(empty)'
    });
    
    if (!loginSection || !adminPanel) {
        console.error('[ADMIN] CRITICAL: Panel elements not found!');
        alert('ERRO: Elementos do painel não encontrados. Recarregue a página.');
        return;
    }
    
    // Hide login section
    console.log('🔵 [ADMIN] Hiding login section...');
    loginSection.style.display = 'none';
    console.log('[ADMIN] Login section display set to:', loginSection.style.display);
    
    // Show admin panel
    console.log('🔵 [ADMIN] Showing admin panel...');
    adminPanel.style.display = 'block';
    console.log('[ADMIN] Admin panel display set to:', adminPanel.style.display);
    
    // Initialize admin panel components
    console.log('🔵 [ADMIN] Initializing tab navigation...');
    initTabNavigation();
    
    console.log('🔵 [ADMIN] Filtering menu by permissions...');
    if (typeof filterAdminMenuByPermissions === 'function') {
        try {
            filterAdminMenuByPermissions();
            console.log('[ADMIN] Permissions filtered');
        } catch (error) {
            console.error('[ADMIN] Error filtering permissions:', error);
        }
    } else {
        console.warn('[ADMIN] filterAdminMenuByPermissions not available');
    }
    
    console.log('🔵 [ADMIN] Loading dashboard...');
    try {
        loadDashboard();
        console.log('[ADMIN] Dashboard loaded');
    } catch (error) {
        console.error('[ADMIN] Error loading dashboard:', error);
    }
    
    // Final verification
    console.log('[ADMIN] ===== FINAL STATE =====');
    console.log({
        loginSection_display: document.getElementById('login-section')?.style.display,
        adminPanel_display: document.getElementById('admin-panel')?.style.display,
        sessionStorage_adminLoggedIn: sessionStorage.getItem('adminLoggedIn'),
        timestamp: new Date().toISOString()
    });
    console.log('[ADMIN] ===== showAdminPanel() COMPLETED =====');
}

// Expose function globally for debugging
window.showAdminPanel = showAdminPanel;

// Login function - FIXED VERSION
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔵 [ADMIN] DOMContentLoaded fired at:', new Date().toISOString());
    console.log('🔵 [ADMIN] Current URL:', window.location.href);
    console.log('🔵 [ADMIN] Document readyState:', document.readyState);
    
    const loginForm = document.getElementById('login-form');
    const loginSection = document.getElementById('login-section');
    const adminPanel = document.getElementById('admin-panel');
    
    console.log('[ADMIN] Elements check:', {
        loginForm: loginForm ? 'Found' : 'NULL',
        loginFormId: loginForm?.id,
        loginSection: loginSection ? 'Found' : 'NULL',
        adminPanel: adminPanel ? 'Found' : 'NULL',
        allForms: document.querySelectorAll('form').length,
        allInputs: document.querySelectorAll('input').length
    });
    
    // Check if already logged in
    if (checkAuth()) {
        console.log('[ADMIN] User already logged in (checkAuth returned true)');
        console.log('🔵 [ADMIN] Will show panel after short delay...');
        
        // Use setTimeout to ensure DOM is fully loaded
        setTimeout(() => {
            console.log('🔵 [ADMIN] Executing showAdminPanel from checkAuth...');
            showAdminPanel();
        }, 100);
        return;
    }
    
    // Handle login - USE CAPTURE PHASE TO OVERRIDE OTHER LISTENERS
    if (loginForm) {
        console.log('[ADMIN] Login form found, attaching listener');
        
        // Clone form to remove ALL existing event listeners
        const newForm = loginForm.cloneNode(true);
        loginForm.parentNode.replaceChild(newForm, loginForm);
        
        // Attach listener to cloned form using CAPTURE phase (priority execution)
        newForm.addEventListener('submit', function(e) {
            console.log('🔵 [ADMIN] ===== SUBMIT EVENT FIRED =====');
            console.log('🔵 [ADMIN] Event type:', e.type);
            console.log('🔵 [ADMIN] Event target:', e.target);
            console.log('🔵 [ADMIN] Default prevented:', e.defaultPrevented);
            
            // STOP EVERYTHING - prevent page reload
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('[ADMIN] Event blocked successfully');
            
            // Log all form inputs at submit time
            console.log('📋 [ADMIN] All form inputs:', {
                allInputs: document.querySelectorAll('input').length,
                inputsWithId: Array.from(document.querySelectorAll('input[id]')).map(el => ({
                    id: el.id,
                    type: el.type,
                    value: el.value ? '***' : 'empty'
                }))
            });
            
            const usernameEl = document.getElementById('username');
            const passwordEl = document.getElementById('password');
            
            console.log('[ADMIN] Login field elements:', {
                usernameEl: usernameEl ? `Found (type: ${usernameEl.type})` : 'NULL',
                usernameValue: usernameEl?.value || 'EMPTY',
                passwordEl: passwordEl ? `Found (type: ${passwordEl.type})` : 'NULL',
                passwordLength: passwordEl?.value?.length || 0
            });
            
            if (!usernameEl || !passwordEl) {
                console.error('[ADMIN] Login form elements not found!');
                alert('ERRO: Campos de login não encontrados. Verifique o console (F12).');
                return false;
            }
            
            const username = usernameEl.value;
            const password = passwordEl.value;
            
            console.log('[ADMIN] Attempting login:', {
                username: username,
                passwordLength: password.length,
                expectedUsername: ADMIN_CREDENTIALS.username,
                expectedPasswordLength: ADMIN_CREDENTIALS.password.length,
                match: username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password
            });
            
            if (username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password) {
                console.log('[ADMIN] Credentials match! Login successful!');
                console.log('🔵 [ADMIN] Setting sessionStorage adminLoggedIn = true');
                
                sessionStorage.setItem('adminLoggedIn', 'true');
                
                console.log('[ADMIN] sessionStorage set. Verifying:', {
                    value: sessionStorage.getItem('adminLoggedIn'),
                    type: typeof sessionStorage.getItem('adminLoggedIn')
                });
                
                console.log('[ADMIN] Preparing to call showAdminPanel...');
                console.log('[ADMIN] Function check:', {
                    showAdminPanel_type: typeof showAdminPanel,
                    showAdminPanel_exists: typeof showAdminPanel !== 'undefined',
                    window_showAdminPanel: typeof window.showAdminPanel
                });
                
                // Small delay to ensure DOM is ready
                console.log('🔵 [ADMIN] Calling showAdminPanel in 100ms...');
                setTimeout(() => {
                    console.log('🔵 [ADMIN] ===== EXECUTING showAdminPanel NOW =====');
                    showAdminPanel();
                }, 100);
            } else {
                console.error('[ADMIN] Login failed - incorrect credentials');
                console.error('[ADMIN] Debug info:', {
                    providedUsername: username,
                    providedPasswordLength: password.length,
                    expectedUsername: ADMIN_CREDENTIALS.username,
                    expectedPasswordLength: ADMIN_CREDENTIALS.password.length
                });
                alert('Usuário ou senha incorretos!');
            }
            
            return false; // Extra safety
        }, true); // USE CAPTURE PHASE (true) - executes before bubble phase
        
        console.log('[ADMIN] Login form listener attached successfully (capture phase)');
    } else {
        console.error('[ADMIN] Login form NOT FOUND - cannot attach listener');
    }
});

// Logout function
function logout() {
    sessionStorage.removeItem('adminLoggedIn');
    location.reload();
}

// Load dashboard data
async function loadDashboard() {
    await updateStatistics();
    await renderOrders();
    await calculatePopularItems();
    
    // Setup status filter
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', () => renderOrders());
    }
}

// Update statistics
async function updateStatistics() {
    const orders = await getOrders();
    
    const totalOrders = orders.length;
    // Use new status values from API: recebido, em_andamento, finalizado
    const pendingOrders = orders.filter(o => o.status === 'recebido' || o.status === 'pendente').length;
    const preparingOrders = orders.filter(o => o.status === 'em_andamento' || o.status === 'preparo').length;
    const completedOrders = orders.filter(o => o.status === 'finalizado' || o.status === 'concluido').length;
    
    const totalRevenue = orders.reduce((sum, order) => sum + (Number(order.total) || 0), 0);
    const avgOrderValue = totalOrders > 0 ? totalRevenue / totalOrders : 0;
    
    // Update DOM
    document.getElementById('stat-total').textContent = totalOrders;
    document.getElementById('stat-pending').textContent = pendingOrders;
    document.getElementById('stat-preparing').textContent = preparingOrders;
    document.getElementById('stat-completed').textContent = completedOrders;
    document.getElementById('stat-revenue').textContent = `R$ ${Number(totalRevenue).toFixed(2)}`;
    document.getElementById('stat-avg-order').textContent = `R$ ${Number(avgOrderValue).toFixed(2)}`;
}

// Calculate most popular items
async function calculatePopularItems() {
    const orders = await getOrders();
    const itemCounts = {};
    
    orders.forEach(order => {
        // Handle both old format (items array) and new format (order_items via API)
        const items = order.items || order.order_items || [];
        items.forEach(item => {
            const itemName = item.name || item.item_name;
            if (itemCounts[itemName]) {
                itemCounts[itemName] += item.quantity;
            } else {
                itemCounts[itemName] = item.quantity;
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
async function renderOrders() {
    const orders = await getOrders();
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
                    <p><strong>Total:</strong> R$ ${Number(order.total).toFixed(2)}</p>
                    <p><strong>Itens:</strong></p>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        ${order.items.map(item => `
                            <li>${item.quantity}x ${item.name} - R$ ${(Number(item.price) * Number(item.quantity)).toFixed(2)}</li>
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
                            Marcar como Concluído
                        </button>
                    ` : ''}
                    ${order.status === 'concluido' ? `
                        <button class="btn btn-secondary" onclick="changeOrderStatus(${order.id}, 'pendente')">
                            Reabrir Pedido
                        </button>
                    ` : ''}
                    <button class="btn btn-danger" onclick="deleteOrder(${order.id})">
                        Remover
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Change order status
async function changeOrderStatus(orderId, newStatus) {
    await updateOrderStatus(orderId, newStatus);
    loadDashboard();
}

// Delete order
async function deleteOrder(orderId) {
    if (!confirm('Tem certeza que deseja remover este pedido?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/orders.php?action=delete&id=${orderId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            alert('Erro ao remover pedido: ' + data.error);
            return;
        }
        
        await loadDashboard();
    } catch (error) {
        console.error('Error deleting order:', error);
        alert('Erro ao remover pedido.');
    }
}

// Clear all orders
async function clearAllOrders() {
    if (!confirm('ATENÇÃO: Isto irá remover todos os pedidos do sistema. Tem certeza?')) {
        return;
    }
    
    if (!confirm('Esta ação não pode ser desfeita. Confirma a remoção de todos os pedidos?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/orders.php?action=delete-all', {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            alert('Erro ao remover pedidos: ' + data.error);
            return;
        }
        
        await loadDashboard();
        alert('Todos os pedidos foram removidos com sucesso!');
    } catch (error) {
        console.error('Error clearing orders:', error);
        alert('Erro ao remover pedidos.');
    }
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

async function loadKanbanBoard() {
    console.log('Loading Kanban board...');
    
    try {
        // Fetch orders from API instead of localStorage
        const response = await fetch('/api/orders.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar pedidos');
        }
        
        let orders = data.data || [];
        console.log('Orders from API:', orders);
        
        // If no orders found, show empty state
        if (!orders || orders.length === 0) {
            console.warn('No orders found');
            
            // Show empty state
            ['recebido', 'em_andamento', 'finalizado'].forEach(status => {
                const column = document.getElementById(`kanban-${status}`);
                if (column) {
                    column.innerHTML = `
                        <div style="padding: 20px; text-align: center; color: #999;">
                            <p style="margin-bottom: 10px;">Nenhum pedido encontrado</p>
                            <small style="display: block; color: #666;">
                                Os pedidos aparecerão aqui quando criados.<br>
                                Status esperado: ${status}
                            </small>
                        </div>
                    `;
                }
            });
            
            // Update counts
            ['recebido', 'em_andamento', 'finalizado'].forEach(status => {
                const countEl = document.getElementById(`count-${status}`);
                if (countEl) countEl.textContent = '0';
            });
            
            return;
        }
    
    // Apply filters
    const typeFilter = document.getElementById('kanban-type-filter')?.value;
    const tableFilter = document.getElementById('kanban-table-filter')?.value;
    
    if (typeFilter) {
        orders = orders.filter(order => {
            // Handle both old format (order.delivery.*) and new format (order.table_number, order.order_type)
            if (typeFilter === 'table') {
                return order.table_number || (order.delivery && order.delivery.tableNumber);
            } else if (typeFilter === 'delivery') {
                return order.order_type === 'viagem' || (order.delivery && order.delivery.forDelivery);
            } else if (typeFilter === 'pickup') {
                return (order.order_type === 'local' && !order.table_number) || 
                       (!order.delivery || (!order.delivery.forDelivery && !order.delivery.tableNumber));
            }
            return true;
        });
    }
    
    if (tableFilter && parseInt(tableFilter) > 0) {
        orders = orders.filter(order => {
            // Handle both old and new format
            const tableNum = order.table_number || (order.delivery && order.delivery.tableNumber);
            return tableNum === parseInt(tableFilter);
        });
    }
    
    console.log('📋 Orders after filters:', orders);
    
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
        
        console.log(`📌 Order ${order.id} has status: ${order.status} -> mapped to: ${status}`);
        
        if (kanbanData[status]) {
            kanbanData[status].push(order);
        } else {
            console.warn(`Unknown status "${status}" for order ${order.id}`);
        }
    });
    
    console.log('Kanban data:', kanbanData);
    
    // Render cards in each column
    Object.keys(kanbanData).forEach(status => {
        renderKanbanColumn(status, kanbanData[status]);
    });
    
    // Initialize drag and drop
    initDragAndDrop();
    
    } catch (error) {
        console.error('Error loading kanban board:', error);
        
        // Show error in all columns
        ['recebido', 'em_andamento', 'finalizado'].forEach(status => {
            const column = document.getElementById(`kanban-${status}`);
            if (column) {
                column.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #dc3545;">
                        <p>Erro ao carregar pedidos</p>
                        <small>${error.message}</small>
                    </div>
                `;
            }
        });
    }
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
        column.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #999;">
                <p style="margin-bottom: 5px;">Nenhum pedido</p>
                <small style="color: #666;">Status: ${status}</small>
            </div>
        `;
        return;
    }
    
    column.innerHTML = '';
    
    orders.forEach(order => {
        const card = createKanbanCard(order);
        column.appendChild(card);
    });
}

// Debug function to create test order
async function createTestOrder() {
    try {
        const testOrder = {
            order_number: 'TEST' + Date.now(),
            status: 'recebido',
            order_type: 'local',
            payment_method: 'dinheiro',
            table_number: 10,
            subtotal: 45.00,
            total: 45.00,
            items: [
                { 
                    menu_item_id: null,
                    item_name: 'Bacalhau à Portuguesa', 
                    item_price: 45.00, 
                    quantity: 1,
                    subtotal: 45.00
                }
            ],
            notes: 'Pedido de teste criado pelo admin'
        };
        
        const response = await fetch('/api/orders.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(testOrder)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            alert('Erro ao criar pedido de teste: ' + data.error);
            return;
        }
        
        console.log('Test order created:', data);
        alert('Pedido de teste criado! Recarregando Kanban...');
        await loadKanbanBoard();
    } catch (error) {
        console.error('Error creating test order:', error);
        alert('Erro ao criar pedido de teste.');
    }
}

function createKanbanCard(order) {
    const card = document.createElement('div');
    card.className = 'kanban-card';
    card.draggable = true;
    card.dataset.orderId = order.id;
    
    // Handle both old format (date) and new format (created_at)
    const dateStr = order.date || order.created_at;
    const date = new Date(dateStr);
    const timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    
    // Handle both old format (items array) and new format (order_items from API)
    const items = order.items || order.order_items || [];
    const itemsList = items.map(item => {
        const quantity = item.quantity;
        const name = item.name || item.item_name;
        return `${quantity}x ${name}`;
    }).join(', ');
    
    // Determine order type and details - handle both old and new format
    let orderTypeInfo = '';
    let orderTypeClass = '';
    
    // New format uses order_type and table_number directly
    if (order.table_number) {
        orderTypeInfo = `<span class="kanban-badge kanban-badge-table">🪑 Mesa ${order.table_number}</span>`;
        orderTypeClass = ' kanban-card-table';
    } else if (order.order_type === 'viagem' || (order.delivery && order.delivery.forDelivery)) {
        orderTypeInfo = '<span class="kanban-badge kanban-badge-delivery">🚚 Entrega</span>';
        orderTypeClass = ' kanban-card-delivery';
    } else if (order.order_type === 'local' || order.delivery) {
        // Old format check
        if (order.delivery && order.delivery.tableNumber) {
            orderTypeInfo = `<span class="kanban-badge kanban-badge-table">🪑 Mesa ${order.delivery.tableNumber}</span>`;
            orderTypeClass = ' kanban-card-table';
        } else {
            orderTypeInfo = '<span class="kanban-badge kanban-badge-pickup">Retirada</span>';
            orderTypeClass = ' kanban-card-pickup';
        }
    } else {
        orderTypeInfo = '<span class="kanban-badge kanban-badge-pickup">Retirada</span>';
        orderTypeClass = ' kanban-card-pickup';
    }
    
    // Add user info if available
    let userInfo = '';
    if (order.customer_name) {
        userInfo = `<div style="font-size: 0.85rem; color: #666; margin-top: 5px;">${order.customer_name}</div>`;
    } else if (order.delivery && order.delivery.userId) {
        userInfo = `<div style="font-size: 0.85rem; color: #666; margin-top: 5px;">Usuário ID: ${order.delivery.userId}</div>`;
    }
    
    // Handle both old format (id) and new format (order_number)
    const orderDisplay = order.order_number || `#${order.id}`;
    
    card.innerHTML = `
        <div class="kanban-card-header">
            <span class="kanban-card-id">Pedido ${orderDisplay}</span>
            <span class="kanban-card-time">${timeStr}</span>
        </div>
        <div class="kanban-card-type">
            ${orderTypeInfo}
        </div>
        <div class="kanban-card-content">
            <div class="kanban-card-items">${itemsList}</div>
            <div class="kanban-card-total">R$ ${Number(order.total).toFixed(2)}</div>
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

async function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    this.classList.remove('drag-over');
    
    if (draggedElement !== this) {
        const orderId = parseInt(draggedElement.dataset.orderId);
        const newStatus = this.closest('.kanban-column').dataset.status;
        
        // Update order status via API (status values match database: recebido, em_andamento, finalizado)
        try {
            await updateOrderStatus(orderId, newStatus);
            // Reload kanban board
            await loadKanbanBoard();
        } catch (error) {
            console.error('Error updating order status:', error);
            alert('Erro ao atualizar status do pedido.');
        }
    }
    
    return false;
}

// Update order status via API
async function updateOrderStatus(orderId, newStatus) {
    try {
        const response = await fetch('/api/orders.php?action=update-status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: orderId,
                status: newStatus
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao atualizar status do pedido');
        }
        
        console.log(`Order ${orderId} status updated to ${newStatus}`);
        return true;
        
    } catch (error) {
        console.error('Error updating order status:', error);
        alert('Erro ao atualizar status do pedido: ' + error.message);
        return false;
    }
}

// ==========================================
// MENU MANAGEMENT FUNCTIONS
// ==========================================

async function loadMenuManagement() {
    const container = document.getElementById('menu-management');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando cardápio...</p>';
        
        // Fetch groups and items from API
        const [groupsResponse, itemsResponse] = await Promise.all([
            fetch('/api/admin/menu.php?action=groups'),
            fetch('/api/admin/menu.php?action=items')
        ]);
        
        const groupsData = await groupsResponse.json();
        const itemsData = await itemsResponse.json();
        
        if (!groupsData.success || !itemsData.success) {
            throw new Error('Erro ao carregar dados do cardápio');
        }
        
        const groups = groupsData.data || [];
        const items = itemsData.data || [];
        
        // Separate parent groups and subgroups
        const parentGroups = groups.filter(g => !g.parent_id);
        const subgroups = groups.filter(g => g.parent_id);
        
        if (parentGroups.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhum grupo cadastrado ainda. Clique em "Adicionar Grupo" para criar um grupo de menu.</p>';
            return;
        }
        
        let html = '';
        
        // Render parent groups with their subgroups and items
        parentGroups.forEach(group => {
            const groupSubgroups = subgroups.filter(sg => sg.parent_id == group.id);
            const directItems = items.filter(item => item.group_id == group.id);
            
            html += `
                <div class="menu-group">
                    <div class="menu-group-header">
                        <div>
                            <h3 style="color: #e8c13f; margin-bottom: 5px;">${group.name}</h3>
                            ${group.description ? `<p style="color: #666; font-size: 0.9rem;">${group.description}</p>` : ''}
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn" onclick="editGroup(${group.id})" style="padding: 8px 16px;"> Editar</button>
                            <button class="btn btn-danger" onclick="deleteGroup(${group.id})" style="padding: 8px 16px;">Excluir</button>
                        </div>
                    </div>
                    
                    ${directItems.length > 0 ? `
                        <div style="margin-top: 15px;">
                            ${directItems.map(item => renderMenuItem(item)).join('')}
                        </div>
                    ` : ''}
                    
                    ${groupSubgroups.length > 0 ? `
                        <div style="margin-top: 20px; margin-left: 30px;">
                            ${groupSubgroups.map(subgroup => {
                                const subgroupItems = items.filter(item => item.group_id == subgroup.id);
                                return `
                                    <div style="border-left: 3px solid #e8c13f; padding-left: 15px; margin-bottom: 20px;">
                                        <div class="menu-group-header">
                                            <div>
                                                <h4 style="color: #333; margin-bottom: 5px;">↳ ${subgroup.name}</h4>
                                                ${subgroup.description ? `<p style="color: #666; font-size: 0.9rem;">${subgroup.description}</p>` : ''}
                                            </div>
                                            <div style="display: flex; gap: 10px;">
                                                <button class="btn" onclick="editGroup(${subgroup.id})" style="padding: 6px 12px; font-size: 0.9rem;"> Editar</button>
                                                <button class="btn btn-danger" onclick="deleteGroup(${subgroup.id})" style="padding: 6px 12px; font-size: 0.9rem;">Excluir</button>
                                            </div>
                                        </div>
                                        ${subgroupItems.length > 0 ? `
                                            <div style="margin-top: 10px;">
                                                ${subgroupItems.map(item => renderMenuItem(item)).join('')}
                                            </div>
                                        ` : '<p style="color: #999; font-style: italic; margin-top: 10px;">Nenhum item neste subgrupo</p>'}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    ` : ''}
                    
                    ${directItems.length === 0 && groupSubgroups.length === 0 ? '<p style="color: #999; font-style: italic; margin-top: 10px;">Nenhum item ou subgrupo neste grupo</p>' : ''}
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading menu management:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar cardápio: ${error.message}</p>`;
    }
}

function renderMenuItem(item) {
    const imageUrl = `/api/dish-image.php?id=${item.id}`;
    return `
        <div class="menu-item">
            ${item.image_data || item.image_url ? `
                <div class="menu-item-image" style="margin-right: 15px;">
                    <img src="${imageUrl}" alt="${item.name}" 
                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #e8c13f;">
                </div>
            ` : ''}
            <div class="menu-item-info">
                <h4 style="color: #333; margin-bottom: 5px;">${item.name}</h4>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">${item.description || ''}</p>
                <p style="color: #e8c13f; font-weight: bold; font-size: 1.1rem;">R$ ${Number(item.price || 0).toFixed(2)}</p>
                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    ${item.is_available ? '<span style="color: #28a745; font-size: 0.85rem;">Disponível</span>' : '<span style="color: #dc3545; font-size: 0.85rem;">Indisponível</span>'}
                </div>
            </div>
            <div class="menu-item-actions">
                <button class="btn" onclick="editItem(${item.id})" style="padding: 8px 16px;" aria-label="Editar ${item.name}">Editar</button>
                <button class="btn btn-danger" onclick="deleteItem(${item.id})" style="padding: 8px 16px;" aria-label="Excluir ${item.name}">Excluir</button>
            </div>
        </div>
    `;
}

async function showAddGroupModal() {
    const modal = document.getElementById('group-modal');
    const modalTitle = document.getElementById('group-modal-title');
    const form = document.getElementById('group-form');
    const parentSelect = document.getElementById('group-parent');
    
    if (modal && modalTitle && form && parentSelect) {
        modalTitle.textContent = 'Adicionar Grupo';
        form.reset();
        document.getElementById('group-id').value = '';
        
        // Populate parent group select
        try {
            const response = await fetch('/api/admin/menu.php?action=groups');
            const data = await response.json();
            
            if (data.success) {
                const groups = data.data || [];
                const parentGroups = groups.filter(g => !g.parent_id);
                
                parentSelect.innerHTML = '<option value="">Grupo Principal (sem grupo pai)</option>';
                parentGroups.forEach(group => {
                    parentSelect.innerHTML += `<option value="${group.id}">${group.name}</option>`;
                });
            }
        } catch (error) {
            console.error('Error loading groups:', error);
        }
        
        modal.style.display = 'block';
    }
}

function closeGroupModal() {
    const modal = document.getElementById('group-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

async function saveGroup(event) {
    event.preventDefault();
    
    const groupId = document.getElementById('group-id')?.value;
    const parentId = document.getElementById('group-parent')?.value;
    const name = document.getElementById('group-name')?.value;
    const description = document.getElementById('group-description')?.value;
    
    if (!name) {
        alert('Por favor, informe o nome do grupo.');
        return;
    }
    
    try {
        const groupData = {
            name,
            description: description || null,
            parent_id: parentId || null,
            is_active: true
        };
        
        let response;
        if (groupId) {
            // Update existing group
            groupData.id = parseInt(groupId);
            response = await fetch('/api/admin/menu.php?action=update-group', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(groupData)
            });
        } else {
            // Create new group
            response = await fetch('/api/admin/menu.php?action=create-group', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(groupData)
            });
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao salvar grupo');
        }
        
        closeGroupModal();
        await loadMenuManagement();
        alert('Grupo salvo com sucesso!');
        
    } catch (error) {
        console.error('Error saving group:', error);
        alert('Erro ao salvar grupo: ' + error.message);
    }
}

async function editGroup(groupId) {
    try {
        const response = await fetch('/api/admin/menu.php?action=groups');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Erro ao carregar grupos');
        }
        
        const groups = data.data || [];
        const group = groups.find(g => g.id == groupId);
        
        if (!group) {
            alert('Grupo não encontrado!');
            return;
        }
        
        const modal = document.getElementById('group-modal');
        const modalTitle = document.getElementById('group-modal-title');
        const parentSelect = document.getElementById('group-parent');
        
        if (modal && modalTitle && parentSelect) {
            modalTitle.textContent = 'Editar Grupo';
            document.getElementById('group-id').value = group.id;
            document.getElementById('group-name').value = group.name;
            document.getElementById('group-description').value = group.description || '';
            
            // Populate parent group select (excluding self and descendants)
            // Get all descendants of this group to prevent circular references
            const getDescendants = (parentId, allGroups) => {
                const descendants = [];
                const directChildren = allGroups.filter(g => g.parent_id == parentId);
                descendants.push(...directChildren);
                directChildren.forEach(child => {
                    descendants.push(...getDescendants(child.id, allGroups));
                });
                return descendants;
            };
            
            const descendants = getDescendants(groupId, groups);
            const descendantIds = descendants.map(d => d.id);
            
            // Only show parent groups that are not this group or its descendants
            const parentGroups = groups.filter(g => !g.parent_id && g.id != groupId && !descendantIds.includes(g.id));
            parentSelect.innerHTML = '<option value="">Grupo Principal (sem grupo pai)</option>';
            parentGroups.forEach(pg => {
                const selected = pg.id == group.parent_id ? 'selected' : '';
                parentSelect.innerHTML += `<option value="${pg.id}" ${selected}>${pg.name}</option>`;
            });
            
            modal.style.display = 'block';
        }
        
    } catch (error) {
        console.error('Error loading group for edit:', error);
        alert('Erro ao carregar grupo: ' + error.message);
    }
}

async function deleteGroup(groupId) {
    try {
        // Check if group has items or subgroups
        const [groupsResponse, itemsResponse] = await Promise.all([
            fetch('/api/admin/menu.php?action=groups'),
            fetch('/api/admin/menu.php?action=items')
        ]);
        
        const groupsData = await groupsResponse.json();
        const itemsData = await itemsResponse.json();
        
        if (!groupsData.success || !itemsData.success) {
            throw new Error('Erro ao verificar dependências do grupo');
        }
        
        const groups = groupsData.data || [];
        const items = itemsData.data || [];
        
        const subgroups = groups.filter(g => g.parent_id == groupId);
        const groupItems = items.filter(item => item.group_id == groupId);
        
        if (subgroups.length > 0) {
            alert(`Este grupo possui ${subgroups.length} subgrupo(s). Por favor, remova ou mova os subgrupos primeiro.`);
            return;
        }
        
        if (groupItems.length > 0) {
            if (!confirm(`Este grupo possui ${groupItems.length} item(ns). Todos os itens serão excluídos. Tem certeza?`)) {
                return;
            }
            // Delete all items first
            let deleteErrors = [];
            for (const item of groupItems) {
                const itemResponse = await fetch(`/api/admin/menu.php?action=delete-item&id=${item.id}`, {
                    method: 'DELETE'
                });
                const itemData = await itemResponse.json();
                if (!itemData.success) {
                    deleteErrors.push(item.name);
                }
            }
            if (deleteErrors.length > 0) {
                throw new Error(`Falha ao excluir itens: ${deleteErrors.join(', ')}`);
            }
        } else {
            if (!confirm('Tem certeza que deseja excluir este grupo?')) {
                return;
            }
        }
        
        const response = await fetch(`/api/admin/menu.php?action=delete-group&id=${groupId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao excluir grupo');
        }
        
        await loadMenuManagement();
        alert('Grupo excluído com sucesso!');
        
    } catch (error) {
        console.error('Error deleting group:', error);
        alert('Erro ao excluir grupo: ' + error.message);
    }
}

async function showAddItemModal() {
    try {
        const response = await fetch('/api/admin/menu.php?action=groups');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Erro ao carregar grupos');
        }
        
        const groups = data.data || [];
        
        if (groups.length === 0) {
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
            document.getElementById('item-available').checked = true;
            document.getElementById('item-delivery-enabled').checked = true;
            
            // Hide image preview
            document.getElementById('image-preview').style.display = 'none';
            
            // Populate group select with hierarchy
            groupSelect.innerHTML = '<option value="">Selecione um grupo</option>';
            
            const parentGroups = groups.filter(g => !g.parent_id);
            const subgroups = groups.filter(g => g.parent_id);
            
            parentGroups.forEach(parent => {
                groupSelect.innerHTML += `<option value="${parent.id}">${parent.name}</option>`;
                
                // Add subgroups indented
                const parentSubgroups = subgroups.filter(sg => sg.parent_id == parent.id);
                parentSubgroups.forEach(sub => {
                    groupSelect.innerHTML += `<option value="${sub.id}">   ↳ ${sub.name}</option>`;
                });
            });
            
            // Add image preview event listener
            setupImagePreview();
            
            modal.style.display = 'block';
        }
        
    } catch (error) {
        console.error('Error loading groups:', error);
        alert('Erro ao carregar grupos: ' + error.message);
    }
}

function setupImagePreview() {
    const imageUpload = document.getElementById('item-image-upload');
    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('Arquivo muito grande! O tamanho máximo é 5MB.');
                    e.target.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Tipo de arquivo inválido! Use JPEG, PNG ou WebP.');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

function closeItemModal() {
    const modal = document.getElementById('item-modal');
    if (modal) {
        modal.style.display = 'none';
        // Clear file input and preview
        const imageUpload = document.getElementById('item-image-upload');
        if (imageUpload) {
            imageUpload.value = '';
        }
        document.getElementById('image-preview').style.display = 'none';
    }
}

async function saveItem(event) {
    event.preventDefault();
    
    // Get raw values from form
    const itemId = document.getElementById('item-id')?.value;
    const groupIdValue = document.getElementById('item-group')?.value;
    const nameValue = document.getElementById('item-name')?.value;
    const description = document.getElementById('item-description')?.value;
    const priceValue = document.getElementById('item-price')?.value;
    const imageUrl = document.getElementById('item-image')?.value;
    const imageFile = document.getElementById('item-image-upload')?.files[0];
    const available = document.getElementById('item-available')?.checked || false;
    const deliveryEnabled = document.getElementById('item-delivery-enabled')?.checked || false;
    
    // Enhanced validation and sanitization
    // Clean and validate name
    const name = nameValue ? nameValue.trim() : '';
    if (!name) {
        alert('Por favor, informe o nome do prato.');
        return;
    }
    
    // Parse and validate group_id
    const groupId = parseInt(groupIdValue);
    if (!groupIdValue || isNaN(groupId) || groupId <= 0) {
        alert('Por favor, selecione um grupo válido.');
        return;
    }
    
    // Parse and validate price
    const price = parseFloat(priceValue);
    if (isNaN(price) || price < 0) {
        alert('Por favor, informe um preço válido (maior ou igual a zero).');
        return;
    }
    
    if (DEBUG_MODE) {
        console.log('📝 saveItem - Validated data:', {
            itemId: itemId || '(new)',
            groupId,
            name,
            price,
            hasImage: !!imageFile
        });
    }
    
    try {
        // If there's an image file, use FormData to upload
        if (imageFile) {
            const formData = new FormData();
            
            // Append with explicit string conversion to ensure proper formatting
            formData.append('group_id', String(groupId));
            formData.append('name', String(name));
            formData.append('description', String(description || ''));
            formData.append('price', String(price));
            formData.append('is_available', available ? '1' : '0');
            formData.append('delivery_enabled', deliveryEnabled ? '1' : '0');
            formData.append('image', imageFile);
            
            if (itemId && itemId.trim()) {
                formData.append('id', String(itemId));
            }
            
            if (DEBUG_MODE) {
                console.log('📤 Sending FormData (with image) to API...');
                
                // Debug: log FormData contents
                for (let pair of formData.entries()) {
                    console.log(`  ${pair[0]}: ${pair[1]}`);
                }
            }
            
            const action = itemId && itemId.trim() ? 'update-item' : 'create-item';
            const method = 'POST'; // POST for file upload
            
            const response = await fetch(`/api/admin/menu.php?action=${action}`, {
                method: method,
                body: formData
            });
            
            const data = await response.json();
            
            if (DEBUG_MODE) {
                console.log('📥 API Response (FormData):', data);
            }
            
            if (!data.success) {
                throw new Error(data.error || data.message || 'Erro ao salvar item');
            }
        } else {
            // No file upload, use JSON
            const itemData = {
                group_id: groupId,
                name,
                description: description || null,
                price,
                image_url: imageUrl || null,
                is_available: available,
                delivery_enabled: deliveryEnabled
            };
            
            if (DEBUG_MODE) {
                console.log('📤 Sending JSON (no image) to API...');
                console.log('  Data:', JSON.stringify(itemData, null, 2));
            }
            
            let response;
            if (itemId && itemId.trim()) {
                // Update existing item
                itemData.id = parseInt(itemId);
                response = await fetch('/api/admin/menu.php?action=update-item', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(itemData)
                });
            } else {
                // Create new item
                response = await fetch('/api/admin/menu.php?action=create-item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(itemData)
                });
            }
            
            const data = await response.json();
            
            if (DEBUG_MODE) {
                console.log('📥 API Response (JSON):', data);
            }
            
            if (!data.success) {
                throw new Error(data.error || 'Erro ao salvar item');
            }
        }
        
        closeItemModal();
        await loadMenuManagement();
        alert('Item salvo com sucesso!');
        
    } catch (error) {
        console.error('Error saving item:', error);
        alert('Erro ao salvar item: ' + error.message);
    }
}

async function editItem(itemId) {
    try {
        const [groupsResponse, itemsResponse] = await Promise.all([
            fetch('/api/admin/menu.php?action=groups'),
            fetch('/api/admin/menu.php?action=items')
        ]);
        
        const groupsData = await groupsResponse.json();
        const itemsData = await itemsResponse.json();
        
        if (!groupsData.success || !itemsData.success) {
            throw new Error('Erro ao carregar dados');
        }
        
        const groups = groupsData.data || [];
        const items = itemsData.data || [];
        const item = items.find(i => i.id == itemId);
        
        if (!item) {
            alert('Item não encontrado!');
            return;
        }
        
        const modal = document.getElementById('item-modal');
        const modalTitle = document.getElementById('item-modal-title');
        const groupSelect = document.getElementById('item-group');
        
        if (modal && modalTitle && groupSelect) {
            modalTitle.textContent = 'Editar Item';
            
            // Populate group select with hierarchy
            groupSelect.innerHTML = '<option value="">Selecione um grupo</option>';
            
            const parentGroups = groups.filter(g => !g.parent_id);
            const subgroups = groups.filter(g => g.parent_id);
            
            parentGroups.forEach(parent => {
                const selected = parent.id == item.group_id ? 'selected' : '';
                groupSelect.innerHTML += `<option value="${parent.id}" ${selected}>${parent.name}</option>`;
                
                // Add subgroups indented
                const parentSubgroups = subgroups.filter(sg => sg.parent_id == parent.id);
                parentSubgroups.forEach(sub => {
                    const subSelected = sub.id == item.group_id ? 'selected' : '';
                    groupSelect.innerHTML += `<option value="${sub.id}" ${subSelected}>   ↳ ${sub.name}</option>`;
                });
            });
            
            // Fill form
            document.getElementById('item-id').value = item.id;
            document.getElementById('item-name').value = item.name;
            document.getElementById('item-description').value = item.description || '';
            document.getElementById('item-price').value = item.price;
            document.getElementById('item-image').value = item.image_url || '';
            document.getElementById('item-available').checked = item.is_available;
            document.getElementById('item-delivery-enabled').checked = item.delivery_enabled !== false; // Default to true if not set
            
            modal.style.display = 'block';
        }
        
    } catch (error) {
        console.error('Error loading item for edit:', error);
        alert('Erro ao carregar item: ' + error.message);
    }
}

async function deleteItem(itemId) {
    if (!confirm('Tem certeza que deseja excluir este item?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/menu.php?action=delete-item&id=${itemId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao excluir item');
        }
        
        await loadMenuManagement();
        alert('Item excluído com sucesso!');
        
    } catch (error) {
        console.error('Error deleting item:', error);
        alert('Erro ao excluir item: ' + error.message);
    }
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

async function generateReport() {
    const reportType = document.getElementById('report-type')?.value;
    const dateFrom = document.getElementById('report-date-from')?.value;
    const dateTo = document.getElementById('report-date-to')?.value;
    const resultsDiv = document.getElementById('report-results');
    
    if (!resultsDiv) return;
    
    resultsDiv.innerHTML = '<p style="color: #666; text-align: center; padding: 40px;">Gerando relatório...</p>';
    
    // Fetch orders from API
    try {
        const orders = await getOrders();
        
        if (reportType === 'revenue') {
            generateRevenueReport(orders, dateFrom, dateTo, resultsDiv);
        } else if (reportType === 'popular-items') {
            generatePopularItemsReport(orders, resultsDiv);
        } else if (reportType === 'customer-flow') {
            generateCustomerFlowReport(orders, resultsDiv);
        }
    } catch (error) {
        console.error('Error generating report:', error);
        resultsDiv.innerHTML = '<p style="color: #dc3545; text-align: center; padding: 40px;">Erro ao gerar relatório.</p>';
    }
}

function generateRevenueReport(orders, dateFrom, dateTo, container) {
    const filteredOrders = orders.filter(order => {
        const orderDate = new Date(order.date).toISOString().split('T')[0];
        return orderDate >= dateFrom && orderDate <= dateTo;
    });
    
    const totalRevenue = filteredOrders.reduce((sum, order) => sum + (Number(order.total) || 0), 0);
    const completedOrders = filteredOrders.filter(o => o.status === 'concluido' || o.status === 'finalizado');
    const completedRevenue = completedOrders.reduce((sum, order) => sum + (Number(order.total) || 0), 0);
    
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
                    <div class="stat-value">R$ ${Number(totalRevenue).toFixed(2)}</div>
                    <div class="stat-label">Receita Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ ${Number(completedRevenue).toFixed(2)}</div>
                    <div class="stat-label">Receita Concluída</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">R$ ${Number(completedRevenue / completedOrders.length || 0).toFixed(2)}</div>
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
                    <span style="color: #28a745; font-weight: bold;">R$ ${Number(data.revenue || 0).toFixed(2)}</span>
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

async function loadResumes() {
    const container = document.getElementById('resumes-list');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando currículos...</p>';
        
        const statusFilter = document.getElementById('resume-status-filter')?.value || '';
        const params = new URLSearchParams({ action: 'list' });
        
        if (statusFilter) {
            params.append('status', statusFilter);
        }
        
        const response = await fetch(`/api/admin/resumes.php?${params.toString()}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar currículos');
        }
        
        const resumes = data.data;
        
        if (!resumes || resumes.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhum currículo recebido ainda. Os currículos enviados pelo formulário aparecerão aqui.</p>';
            return;
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        resumes.forEach(resume => {
            const statusMap = {
                'em_analise': { color: '#ffc107', label: 'Em Análise' },
                'aprovado': { color: '#28a745', label: 'Aprovado' },
                'rejeitado': { color: '#dc3545', label: 'Rejeitado' }
            };
            
            const status = statusMap[resume.status] || statusMap['em_analise'];
            
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">
                                ${resume.full_name}
                                <span style="background: ${status.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; margin-left: 10px;">${status.label}</span>
                            </h3>
                            <p style="color: #666; margin: 0 0 5px 0;">📧 ${resume.email}</p>
                            <p style="color: #666; margin: 0 0 5px 0;">📱 ${resume.phone}</p>
                            <p style="color: #666; margin: 0 0 5px 0;">💼 Cargo Desejado: ${resume.desired_position}</p>
                            ${resume.cover_letter ? `<p style="color: #666; margin: 10px 0 5px 0;"><strong>Carta de Apresentação:</strong><br>${resume.cover_letter}</p>` : ''}
                            ${resume.notes ? `<p style="color: #999; margin: 10px 0 5px 0; font-style: italic;"><strong>Notas:</strong> ${resume.notes}</p>` : ''}
                            <small style="color: #999;">Enviado em: ${new Date(resume.created_at).toLocaleString('pt-BR')}</small>
                        </div>
                        <div style="display: flex; gap: 5px; flex-direction: column;">
                            ${resume.resume_file_path ? `<a href="${resume.resume_file_path}" target="_blank" class="btn">📄 Ver Currículo</a>` : ''}
                            <button class="btn btn-secondary" onclick="updateResumeStatus(${resume.id}, 'em_analise')">Em Análise</button>
                            <button class="btn" style="background: #28a745; border-color: #28a745;" onclick="updateResumeStatus(${resume.id}, 'aprovado')">Aprovar</button>
                            <button class="btn btn-danger" onclick="updateResumeStatus(${resume.id}, 'rejeitado')">Rejeitar</button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading resumes:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar currículos: ${error.message}</p>`;
    }
}

/**
 * Update resume status
 */
async function updateResumeStatus(resumeId, newStatus) {
    try {
        const notes = prompt('Adicionar notas (opcional):');
        
        const response = await fetch('/api/admin/resumes.php?action=update-status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: resumeId,
                status: newStatus,
                notes: notes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Status atualizado com sucesso!');
            loadResumes();
        } else {
            alert('Erro ao atualizar status: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating resume status:', error);
        alert('Erro ao atualizar status');
    }
}

// ==========================================
// USERS MANAGEMENT FUNCTIONS
// ==========================================

let adminUserSearchTimeout;

/**
 * Debounce user search to avoid too many API calls
 */
function debounceUserSearch() {
    clearTimeout(userSearchTimeout);
    userSearchTimeout = setTimeout(() => {
        loadUsers();
    }, 500); // Wait 500ms after user stops typing
}

/**
 * Load users based on search and filters
 */
async function loadUsers() {
    const container = document.getElementById('users-list');
    if (!container) return;
    
    try {
        const searchInput = document.getElementById('user-search')?.value || '';
        const roleFilter = document.getElementById('user-role-filter')?.value || '';
        const statusFilter = document.getElementById('user-status-filter')?.value || '';
        
        // Only load if there's a search query
        if (!searchInput.trim() && !roleFilter && !statusFilter) {
            container.innerHTML = `
                <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px; margin-top: 20px;">
                    <div style="font-size: 3rem; margin-bottom: 20px;">Search</div>
                    <h3 style="color: #666; margin-bottom: 10px;">Pesquise para encontrar usuários</h3>
                    <p style="color: #999;">Use a barra de pesquisa acima para buscar usuários por nome ou email.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = '<p style="color: #666;">Carregando usuários...</p>';
        
        const params = new URLSearchParams({ action: 'list' });
        if (searchInput.trim()) params.append('search', searchInput.trim());
        if (roleFilter) params.append('role', roleFilter);
        if (statusFilter) params.append('status', statusFilter);
        
        const response = await fetch(`/api/admin/users.php?${params.toString()}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar usuários');
        }
        
        // Handle both array and object response formats
        const users = Array.isArray(data.data) ? data.data : (data.data?.users || []);
        
        if (users.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 12px;">
                    <p style="color: #666; margin: 0;">Nenhum usuário encontrado com os filtros aplicados.</p>
                </div>
            `;
            return;
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        users.forEach(user => {
            const statusBadge = user.is_active 
                ? '<span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">Ativo</span>'
                : '<span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">Inativo</span>';
            
            const lastLogin = user.last_login 
                ? new Date(user.last_login).toLocaleString('pt-BR')
                : 'Nunca';
            
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">
                                ${user.full_name || 'Sem nome'}
                                ${statusBadge}
                            </h3>
                            <p style="color: #666; margin: 0 0 5px 0;"><strong>Email:</strong> ${user.email}</p>
                            <p style="color: #666; margin: 0 0 5px 0;"><strong>Cargos:</strong> ${user.roles || 'Nenhum cargo'}</p>
                            <p style="color: #999; margin: 0; font-size: 0.9rem;">Último login: ${lastLogin}</p>
                            <small style="color: #999;">Cadastrado em: ${new Date(user.created_at).toLocaleString('pt-BR')}</small>
                        </div>
                        <div style="display: flex; gap: 5px; flex-direction: column;">
                            <button class="btn" onclick="editUser(${user.id})" style="padding: 8px 16px;"> Editar</button>
                            <button class="btn btn-secondary" onclick="manageUserRoles(${user.id})" style="padding: 8px 16px;">Cargos</button>
                            ${user.is_active 
                                ? `<button class="btn btn-danger" onclick="toggleUserStatus(${user.id}, false)" style="padding: 8px 16px;">Desativar</button>`
                                : `<button class="btn" onclick="toggleUserStatus(${user.id}, true)" style="padding: 8px 16px; background: #28a745; border-color: #28a745;">Ativar</button>`
                            }
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        // Show pagination info if available
        if (data.pagination) {
            html += `
                <div style="margin-top: 20px; text-align: center; color: #666;">
                    Mostrando ${users.length} de ${data.pagination.total} usuário(s)
                </div>
            `;
        }
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading users:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar usuários: ${error.message}</p>`;
    }
}

/**
 * Toggle user active status
 */
async function toggleUserStatus(userId, makeActive) {
    const action = makeActive ? 'ativar' : 'desativar';
    
    if (!confirm(`Tem certeza que deseja ${action} este usuário?`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/users.php?action=update-status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: userId,
                is_active: makeActive
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Status do usuário atualizado com sucesso!');
            loadUsers();
        } else {
            alert('Erro ao atualizar status: ' + (data.error || data.message));
        }
    } catch (error) {
        console.error('Error toggling user status:', error);
        alert('Erro ao atualizar status do usuário');
    }
}

/**
 * Edit user information
 */
async function editUser(userId) {
    try {
        // Fetch user data
        const response = await fetch(`/api/admin/users.php?action=get&id=${userId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar usuário');
        }
        
        const user = data.data;
        
        // Show edit dialog using prompt (simple implementation)
        const newName = prompt('Nome completo do usuário:', user.full_name);
        
        if (newName === null) return; // User cancelled
        
        if (!newName.trim()) {
            alert('Nome não pode ser vazio!');
            return;
        }
        
        // Update user
        const updateResponse = await fetch('/api/admin/users.php?action=update', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: userId,
                full_name: newName.trim()
            })
        });
        
        const updateData = await updateResponse.json();
        
        if (updateData.success) {
            alert('Usuário atualizado com sucesso!');
            loadUsers();
        } else {
            alert('Erro ao atualizar usuário: ' + (updateData.error || updateData.message));
        }
        
    } catch (error) {
        console.error('Error editing user:', error);
        alert('Erro ao editar usuário: ' + error.message);
    }
}

/**
 * Manage user roles
 */
async function manageUserRoles(userId) {
    try {
        // Fetch user data and available roles
        const [userResponse, rolesResponse, userRolesResponse] = await Promise.all([
            fetch(`/api/admin/users.php?action=get&id=${userId}`),
            fetch('/api/admin/roles.php?action=list'),
            fetch(`/api/admin/roles.php?action=user-roles&user_id=${userId}`)
        ]);
        
        const userData = await userResponse.json();
        const rolesData = await rolesResponse.json();
        const userRolesData = await userRolesResponse.json();
        
        if (!userData.success || !rolesData.success || !userRolesData.success) {
            throw new Error('Erro ao carregar dados');
        }
        
        const user = userData.data;
        const allRoles = rolesData.data;
        const userRoles = userRolesData.data;
        const userRoleIds = userRoles.map(r => r.id);
        
        // Build role selection dialog
        let message = `Gerenciar cargos para ${user.full_name}\n\n`;
        message += 'Cargos atuais: ' + (userRoles.length > 0 ? userRoles.map(r => r.name).join(', ') : 'Nenhum') + '\n\n';
        message += 'Cargos disponíveis:\n';
        allRoles.forEach((role, index) => {
            const hasRole = userRoleIds.includes(role.id);
            message += `${index + 1}. [${hasRole ? 'X' : ' '}] ${role.name} - ${role.description || 'Sem descrição'}\n`;
        });
        message += '\nDigite o número do cargo para adicionar/remover (ou deixe em branco para cancelar):';
        
        const selection = prompt(message);
        
        if (selection === null || selection.trim() === '') return; // User cancelled
        
        const selectedIndex = parseInt(selection) - 1;
        
        if (isNaN(selectedIndex) || selectedIndex < 0 || selectedIndex >= allRoles.length) {
            alert('Seleção inválida!');
            return;
        }
        
        const selectedRole = allRoles[selectedIndex];
        const hasRole = userRoleIds.includes(selectedRole.id);
        
        // Add or remove role
        if (hasRole) {
            // Remove role
            const response = await fetch(`/api/admin/roles.php?action=unassign-user&user_id=${userId}&role_id=${selectedRole.id}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(`Cargo "${selectedRole.name}" removido com sucesso!`);
                loadUsers();
            } else {
                alert('Erro ao remover cargo: ' + (data.error || data.message));
            }
        } else {
            // Add role
            const response = await fetch('/api/admin/roles.php?action=assign-user', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    role_id: selectedRole.id
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(`Cargo "${selectedRole.name}" atribuído com sucesso!`);
                loadUsers();
            } else {
                alert('Erro ao atribuir cargo: ' + (data.error || data.message));
            }
        }
        
    } catch (error) {
        console.error('Error managing user roles:', error);
        alert('Erro ao gerenciar cargos: ' + error.message);
    }
}

// ==========================================
// ROLES MANAGEMENT FUNCTIONS
// ==========================================

/**
 * Load roles list
 */
async function loadRoles() {
    const container = document.getElementById('roles-list');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando cargos...</p>';
        
        const response = await fetch('/api/admin/roles.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar cargos');
        }
        
        const roles = data.data;
        
        if (!roles || roles.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhum cargo cadastrado ainda.</p>';
            return;
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        roles.forEach(role => {
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">${role.name}</h3>
                            <p style="color: #666; margin: 0 0 5px 0;">${role.description || 'Sem descrição'}</p>
                            <p style="color: #999; margin: 0; font-size: 0.9rem;">${role.user_count} usuário(s) com este cargo</p>
                            <small style="color: #999;">Criado em: ${new Date(role.created_at).toLocaleString('pt-BR')}</small>
                        </div>
                        <div style="display: flex; gap: 5px; flex-direction: column;">
                            <button class="btn" onclick="viewRolePermissions(${role.id})" style="padding: 8px 16px;">Ver Permissões</button>
                            <button class="btn btn-secondary" onclick="editRole(${role.id})" style="padding: 8px 16px;"> Editar</button>
                            ${role.user_count === 0 ? `
                                <button class="btn btn-danger" onclick="deleteRole(${role.id})" style="padding: 8px 16px;">Excluir</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading roles:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar cargos: ${error.message}</p>`;
    }
}

/**
 * View role permissions
 */
async function viewRolePermissions(roleId) {
    try {
        const response = await fetch(`/api/admin/roles.php?action=get&id=${roleId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar cargo');
        }
        
        const role = data.data;
        const permissions = role.permissions || [];
        
        let message = `Cargo: ${role.name}\n\n`;
        message += `Descrição: ${role.description || 'Sem descrição'}\n\n`;
        message += 'Permissões:\n';
        
        if (permissions.length === 0) {
            message += '  Nenhuma permissão atribuída\n';
        } else {
            permissions.forEach(perm => {
                message += `  • ${perm.name} - ${perm.description || 'Sem descrição'}\n`;
            });
        }
        
        alert(message);
        
    } catch (error) {
        console.error('Error viewing role permissions:', error);
        alert('Erro ao carregar permissões: ' + error.message);
    }
}

/**
 * Edit role
 */
async function editRole(roleId) {
    try {
        const response = await fetch(`/api/admin/roles.php?action=get&id=${roleId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar cargo');
        }
        
        const role = data.data;
        
        const newName = prompt('Nome do cargo:', role.name);
        if (newName === null) return; // User cancelled
        
        if (!newName.trim()) {
            alert('Nome não pode ser vazio!');
            return;
        }
        
        const newDescription = prompt('Descrição do cargo:', role.description || '');
        
        // Update role
        const updateResponse = await fetch('/api/admin/roles.php?action=update', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: roleId,
                name: newName.trim(),
                description: newDescription
            })
        });
        
        const updateData = await updateResponse.json();
        
        if (updateData.success) {
            alert('Cargo atualizado com sucesso!');
            loadRoles();
        } else {
            alert('Erro ao atualizar cargo: ' + (updateData.error || updateData.message));
        }
        
    } catch (error) {
        console.error('Error editing role:', error);
        alert('Erro ao editar cargo: ' + error.message);
    }
}

/**
 * Delete role
 */
async function deleteRole(roleId) {
    if (!confirm('Tem certeza que deseja excluir este cargo?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/roles.php?action=delete&id=${roleId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Cargo excluído com sucesso!');
            loadRoles();
        } else {
            alert('Erro ao excluir cargo: ' + (data.error || data.message));
        }
    } catch (error) {
        console.error('Error deleting role:', error);
        alert('Erro ao excluir cargo: ' + error.message);
    }
}

// ==========================================
// OUVIDORIA FUNCTIONS
// ==========================================

async function loadOuvidoriaMessages() {
    const container = document.getElementById('ouvidoria-list');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando mensagens...</p>';
        
        const statusFilter = document.getElementById('ouvidoria-status-filter')?.value || '';
        const params = new URLSearchParams({ action: 'list' });
        
        if (statusFilter) {
            params.append('status', statusFilter);
        }
        
        const response = await fetch(`/api/ouvidoria.php?${params.toString()}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar mensagens');
        }
        
        const messages = data.data;
        
        if (!messages || messages.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhuma mensagem recebida ainda. As mensagens da ouvidoria aparecerão aqui.</p>';
            return;
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        messages.forEach(message => {
            const statusMap = {
                'pendente': { color: '#ffc107', label: 'Pendente' },
                'em_atendimento': { color: '#17a2b8', label: 'Em Atendimento' },
                'resolvido': { color: '#28a745', label: 'Resolvido' }
            };
            
            const status = statusMap[message.status] || statusMap['pendente'];
            
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">
                                ${message.protocol_number}
                                <span style="background: ${status.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; margin-left: 10px;">${status.label}</span>
                            </h3>
                            <p style="color: #666; margin: 0 0 5px 0;"><strong>Assunto:</strong> ${message.subject}</p>
                            <p style="color: #666; margin: 0 0 5px 0;"><strong>De:</strong> ${message.full_name} (${message.email})</p>
                            ${message.phone ? `<p style="color: #666; margin: 0 0 5px 0;"><strong>Tel:</strong> ${message.phone}</p>` : ''}
                            <p style="color: #666; margin: 10px 0;"><strong>Mensagem:</strong><br>${message.message}</p>
                            ${message.response ? `<div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                                <strong style="color: #0066cc;">Resposta:</strong><br>${message.response}
                                ${message.responded_by_name ? `<br><small style="color: #666;">Por: ${message.responded_by_name}</small>` : ''}
                            </div>` : ''}
                            <small style="color: #999;">Enviado em: ${new Date(message.created_at).toLocaleString('pt-BR')}</small>
                            ${message.updated_at && message.updated_at !== message.created_at ? `<br><small style="color: #999;">Atualizado em: ${new Date(message.updated_at).toLocaleString('pt-BR')}</small>` : ''}
                        </div>
                        <div style="display: flex; gap: 5px; flex-direction: column;">
                            <button class="btn" onclick="respondOuvidoria(${message.id})">Responder</button>
                            <button class="btn btn-secondary" onclick="updateOuvidoriaStatus(${message.id}, 'em_atendimento')">Em Atendimento</button>
                            <button class="btn" style="background: #28a745; border-color: #28a745;" onclick="updateOuvidoriaStatus(${message.id}, 'resolvido')">Resolver</button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading ouvidoria messages:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar mensagens: ${error.message}</p>`;
    }
}

/**
 * Respond to ouvidoria message
 */
async function respondOuvidoria(messageId) {
    const response = prompt('Digite sua resposta:');
    
    if (!response) return;
    
    try {
        const result = await fetch('/api/ouvidoria.php?action=respond', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: messageId,
                response: response
            })
        });
        
        const data = await result.json();
        
        if (data.success) {
            alert('Resposta enviada com sucesso!');
            loadOuvidoriaMessages();
        } else {
            alert('Erro ao enviar resposta: ' + (data.error || data.message || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Error responding to ouvidoria:', error);
        alert('Erro ao enviar resposta: ' + error.message);
    }
}

/**
 * Update ouvidoria status
 */
async function updateOuvidoriaStatus(messageId, newStatus) {
    try {
        const response = await fetch('/api/ouvidoria.php?action=update-status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: messageId,
                status: newStatus
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Status atualizado com sucesso!');
            loadOuvidoriaMessages();
        } else {
            alert('Erro ao atualizar status: ' + (data.error || data.message || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Error updating ouvidoria status:', error);
        alert('Erro ao atualizar status: ' + error.message);
    }
}

// ==========================================
// TIME PERIODS MANAGEMENT FUNCTIONS
// ==========================================

let timePeriodCounters = {
    kitchen: 0,
    pizza: 0,
    delivery: 0
};

/**
 * Load time periods for a service type
 */
function loadTimePeriods(serviceType, periods) {
    const container = document.getElementById(`${serviceType}-periods`);
    if (!container) return;
    
    // Clear existing periods
    container.innerHTML = '';
    timePeriodCounters[serviceType] = 0;
    
    // Add periods
    if (periods && periods.length > 0) {
        periods.forEach(period => {
            addTimePeriod(serviceType, period.start, period.end);
        });
    } else {
        // Add one default empty period
        addTimePeriod(serviceType);
    }
}

/**
 * Add a time period row
 */
function addTimePeriod(serviceType, startTime = '', endTime = '') {
    const container = document.getElementById(`${serviceType}-periods`);
    if (!container) return;
    
    const periodId = timePeriodCounters[serviceType]++;
    
    const periodRow = document.createElement('div');
    periodRow.className = 'time-period-row';
    periodRow.id = `${serviceType}-period-${periodId}`;
    periodRow.innerHTML = `
        <span style="color: #666; min-width: 30px;">#${periodId + 1}</span>
        <input type="time" 
               class="${serviceType}-start" 
               value="${startTime}" 
               placeholder="Início"
               style="width: 120px;">
        <span style="color: #666;">até</span>
        <input type="time" 
               class="${serviceType}-end" 
               value="${endTime}" 
               placeholder="Fim"
               style="width: 120px;">
        <button type="button" 
                class="btn btn-danger" 
                onclick="removeTimePeriod('${serviceType}', ${periodId})" 
                style="padding: 6px 12px; font-size: 0.9rem;">
            Remover
        </button>
    `;
    
    container.appendChild(periodRow);
}

/**
 * Remove a time period row
 */
function removeTimePeriod(serviceType, periodId) {
    const container = document.getElementById(`${serviceType}-periods`);
    const periodRow = document.getElementById(`${serviceType}-period-${periodId}`);
    
    if (periodRow && container) {
        // Don't allow removing if it's the last period
        const remainingPeriods = container.querySelectorAll('.time-period-row');
        if (remainingPeriods.length <= 1) {
            alert('Deve haver pelo menos um período de horário configurado.');
            return;
        }
        
        periodRow.remove();
    }
}

/**
 * Get all time periods for a service type
 */
function getTimePeriods(serviceType) {
    const periods = [];
    const startInputs = document.querySelectorAll(`.${serviceType}-start`);
    const endInputs = document.querySelectorAll(`.${serviceType}-end`);
    
    for (let i = 0; i < startInputs.length; i++) {
        const start = startInputs[i].value;
        const end = endInputs[i].value;
        
        if (start && end) {
            periods.push({ start, end });
        }
    }
    
    return periods;
}

// ==========================================
// SETTINGS FUNCTIONS
// ==========================================

async function loadSettings() {
    try {
        // Load current settings from API
        const response = await fetch('/api/admin/settings.php?action=all');
        const data = await response.json();
        
        if (!data.success) {
            console.error('Error loading settings:', data.error);
            return;
        }
        
        const settings = data.data || {};
        
        // Restaurant status
        const restaurantStatus = document.getElementById('restaurant-status');
        const statusLabel = document.getElementById('restaurant-status-label');
        
        if (restaurantStatus && statusLabel) {
            const isOpen = settings.is_open?.value || false;
            restaurantStatus.checked = isOpen;
            statusLabel.textContent = isOpen ? 'Aberto' : 'Fechado';
            statusLabel.style.color = isOpen ? '#28a745' : '#dc3545';
        }
        
        // Load time periods for kitchen, pizza, and delivery
        // Kitchen hours with default 11:00 opening
        const kitchenPeriods = settings.kitchen_hours?.value || [{ start: '11:00', end: '22:00' }];
        loadTimePeriods('kitchen', Array.isArray(kitchenPeriods) ? kitchenPeriods : [kitchenPeriods]);
        
        // Pizza hours
        const pizzaPeriods = settings.pizza_hours?.value || [{ start: '18:00', end: '23:00' }];
        loadTimePeriods('pizza', Array.isArray(pizzaPeriods) ? pizzaPeriods : [pizzaPeriods]);
        
        // Delivery hours
        const deliveryPeriods = settings.delivery_hours?.value || [{ start: '11:00', end: '23:00' }];
        loadTimePeriods('delivery', Array.isArray(deliveryPeriods) ? deliveryPeriods : [deliveryPeriods]);
        
        // Max delivery distance
        if (settings.max_delivery_distance?.value) {
            document.getElementById('max-delivery-distance').value = settings.max_delivery_distance.value;
        }
        
        // Maintenance mode
        const maintenanceMode = document.getElementById('maintenance-mode');
        if (maintenanceMode && settings.maintenance_mode?.value) {
            maintenanceMode.checked = settings.maintenance_mode.value;
            if (settings.maintenance_mode.value) {
                document.getElementById('maintenance-options').style.display = 'block';
                
                if (settings.maintenance_config?.value) {
                    const config = settings.maintenance_config.value;
                    if (config.restrictAll) {
                        document.getElementById('restrict-all').checked = true;
                        toggleRestrictAll();
                    }
                    if (config.restrictedPages) {
                        config.restrictedPages.forEach(page => {
                            const checkbox = document.querySelector(`.maintenance-page[value="${page}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    if (config.message) {
                        document.getElementById('maintenance-message').value = config.message;
                    }
                    if (config.eta) {
                        document.getElementById('maintenance-eta').value = config.eta;
                    }
                }
            }
        }
        
    } catch (error) {
        console.error('Error loading settings:', error);
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

async function saveSettings() {
    try {
        const settings = {};
        
        // Restaurant status
        const isOpen = document.getElementById('restaurant-status')?.checked || false;
        settings.is_open = { value: isOpen, type: 'boolean' };
        
        // Get time periods for each service
        const kitchenPeriods = getTimePeriods('kitchen');
        const pizzaPeriods = getTimePeriods('pizza');
        const deliveryPeriods = getTimePeriods('delivery');
        
        // Validate that at least one period is set for each
        if (kitchenPeriods.length === 0) {
            alert('Por favor, configure pelo menos um período para a Cozinha.');
            return;
        }
        if (pizzaPeriods.length === 0) {
            alert('Por favor, configure pelo menos um período para a Pizzaria.');
            return;
        }
        if (deliveryPeriods.length === 0) {
            alert('Por favor, configure pelo menos um período para as Entregas.');
            return;
        }
        
        // Save time periods as arrays
        settings.kitchen_hours = {
            value: kitchenPeriods,
            type: 'json'
        };
        settings.pizza_hours = {
            value: pizzaPeriods,
            type: 'json'
        };
        settings.delivery_hours = {
            value: deliveryPeriods,
            type: 'json'
        };
        
        // Max delivery distance
        const maxDistance = document.getElementById('max-delivery-distance')?.value;
        if (maxDistance) {
            settings.max_delivery_distance = {
                value: parseFloat(maxDistance),
                type: 'number'
            };
        }
        
        // Maintenance mode
        const maintenanceMode = document.getElementById('maintenance-mode')?.checked || false;
        settings.maintenance_mode = { value: maintenanceMode, type: 'boolean' };
        
        if (maintenanceMode) {
            const maintenanceConfig = {
                restrictAll: document.getElementById('restrict-all')?.checked || false,
                restrictedPages: [],
                message: document.getElementById('maintenance-message')?.value || '',
                eta: document.getElementById('maintenance-eta')?.value || null
            };
            
            document.querySelectorAll('.maintenance-page:checked').forEach(cb => {
                maintenanceConfig.restrictedPages.push(cb.value);
            });
            
            settings.maintenance_config = {
                value: maintenanceConfig,
                type: 'json'
            };
        }
        
        // Save to API
        const response = await fetch('/api/admin/settings.php?action=update-multiple', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ settings })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Configurações salvas com sucesso!');
            // Reload settings to ensure UI is in sync
            await loadSettings();
        } else {
            alert('Erro ao salvar configurações: ' + (data.error || data.message || 'Erro desconhecido'));
        }
        
    } catch (error) {
        console.error('Error saving settings:', error);
        alert('Erro ao salvar configurações: ' + error.message);
    }
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
                    <button class="btn" onclick="editNote(${note.id})" style="flex: 1;"> Editar</button>
                    <button class="btn ${note.active ? 'btn-secondary' : ''}" 
                            onclick="toggleNoteStatus(${note.id})" style="flex: 1;">
                        ${note.active ? 'Desativar' : 'Ativar'}
                    </button>
                    <button class="btn btn-danger" onclick="deleteNote(${note.id})" style="flex: 1;">Excluir</button>
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
            document.getElementById('review-average').textContent = Number(stats.average_rating || 0).toFixed(1);
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
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Invalid response format - expected JSON but got: ' + contentType);
        }
        
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
                            Aprovar
                        </button>
                        <button class="btn btn-danger" onclick="updateReviewStatus(${review.id}, 'rejeitado')">
                            Rejeitar
                        </button>
                    ` : ''}
                    ${review.status !== 'arquivado' ? `
                        <button class="btn btn-secondary" onclick="updateReviewStatus(${review.id}, 'arquivado')">
                            Arquivar
                        </button>
                    ` : ''}
                    <button class="btn btn-danger" onclick="deleteReview(${review.id})">
                        Deletar
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
        'pendente': '<span style="background: #ffc107; color: #856404; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">Pendente</span>',
        'aprovado': '<span style="background: #28a745; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">Aprovado</span>',
        'rejeitado': '<span style="background: #dc3545; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">Rejeitado</span>',
        'arquivado': '<span style="background: #6c757d; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.85rem;">Arquivado</span>'
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
            alert('Status atualizado com sucesso!');
            loadReviews(); // Reload the list
        } else {
            alert('Erro ao atualizar status: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating review status:', error);
        alert('Erro ao atualizar status');
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
            alert('Avaliação deletada com sucesso!');
            loadReviews();
        } else {
            alert('Erro ao deletar avaliação: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting review:', error);
        alert('Erro ao deletar avaliação');
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
                <h3 style="color: #333; margin-bottom: 15px;">${userData.user_name}</h3>
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
                                    <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.85rem;" onclick="editSchedule(${schedule.id})">Editar</button>
                                    <button class="btn btn-danger" style="padding: 5px 10px; font-size: 0.85rem;" onclick="deleteSchedule(${schedule.id})">Excluir</button>
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
            alert('Horário deletado com sucesso!');
            loadSchedules();
        } else {
            alert('Erro ao deletar horário: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting schedule:', error);
        alert('Erro ao deletar horário');
    }
}

/**
 * Capitalize first letter
 */
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// ==========================================
// ROLE PERMISSIONS MODAL FUNCTIONS
// ==========================================

let currentRoleIdForPermissions = null;

/**
 * Open role permissions modal
 */
async function openRolePermissionsModal(roleId) {
    currentRoleIdForPermissions = roleId;
    
    try {
        // Load role details
        const roleResponse = await fetch(`/api/admin/roles.php?action=get&id=${roleId}`);
        const roleData = await roleResponse.json();
        
        if (!roleData.success) {
            throw new Error(roleData.error || 'Erro ao carregar cargo');
        }
        
        const role = roleData.data;
        document.getElementById('rolePermissionsName').textContent = role.name;
        
        // Load all available permissions
        const permsResponse = await fetch('/api/admin/permissions.php?action=by-resource');
        const permsData = await permsResponse.json();
        
        if (!permsData.success) {
            throw new Error(permsData.error || 'Erro ao carregar permissões');
        }
        
        const allPermissions = permsData.data;
        const rolePermissionIds = role.permissions.map(p => p.id);
        
        // Build permissions grid
        const grid = document.getElementById('permissionsGrid');
        grid.innerHTML = '';
        
        // Group permissions by resource
        for (const [resource, perms] of Object.entries(allPermissions)) {
            const categoryDiv = document.createElement('div');
            categoryDiv.className = 'permission-category';
            
            categoryDiv.innerHTML = `
                <h3>${resource}</h3>
            `;
            
            perms.forEach(perm => {
                const isChecked = rolePermissionIds.includes(perm.id);
                const permItem = document.createElement('div');
                permItem.className = 'permission-item';
                permItem.innerHTML = `
                    <input type="checkbox" id="perm_${perm.id}" value="${perm.id}" ${isChecked ? 'checked' : ''}>
                    <label for="perm_${perm.id}">
                        <div class="permission-name">${perm.name}</div>
                        <div class="permission-description">${perm.description || 'Sem descrição'}</div>
                    </label>
                `;
                categoryDiv.appendChild(permItem);
            });
            
            grid.appendChild(categoryDiv);
        }
        
        // Show modal
        document.getElementById('rolePermissionsModal').style.display = 'block';
        
    } catch (error) {
        console.error('Error opening role permissions modal:', error);
        alert('Erro ao abrir modal: ' + error.message);
    }
}

/**
 * Close role permissions modal
 */
function closeRolePermissionsModal() {
    document.getElementById('rolePermissionsModal').style.display = 'none';
    currentRoleIdForPermissions = null;
}

/**
 * Save role permissions
 */
async function saveRolePermissions() {
    if (!currentRoleIdForPermissions) return;
    
    const checkboxes = document.querySelectorAll('#permissionsGrid input[type="checkbox"]:checked');
    const permissionIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    try {
        const response = await fetch('/api/admin/roles.php?action=update-permissions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                role_id: currentRoleIdForPermissions,
                permission_ids: permissionIds
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Permissões atualizadas com sucesso!');
            closeRolePermissionsModal();
            loadRoles();
        } else {
            alert('Erro ao atualizar permissões: ' + (data.error || data.message));
        }
    } catch (error) {
        console.error('Error saving permissions:', error);
        alert('Erro ao salvar permissões');
    }
}

// Update the viewRolePermissions function to use the modal
async function viewRolePermissions(roleId) {
    openRolePermissionsModal(roleId);
}

// ==========================================
// USER ROLES MODAL FUNCTIONS
// ==========================================

let currentUserIdForRoles = null;

/**
 * Open user roles modal
 */
async function openUserRolesModal(userId) {
    currentUserIdForRoles = userId;
    
    try {
        // Load user details
        const userResponse = await fetch(`/api/admin/users.php?action=get&id=${userId}`);
        const userData = await userResponse.json();
        
        if (!userData.success) {
            throw new Error(userData.error || 'Erro ao carregar usuário');
        }
        
        const user = userData.data;
        document.getElementById('userRolesName').textContent = user.full_name || user.email;
        
        // Load user's current roles
        const userRolesResponse = await fetch(`/api/admin/roles.php?action=user-roles&user_id=${userId}`);
        const userRolesData = await userRolesResponse.json();
        
        if (!userRolesData.success) {
            throw new Error(userRolesData.error || 'Erro ao carregar cargos do usuário');
        }
        
        const userRoleIds = userRolesData.data.map(r => r.id);
        
        // Load all available roles
        const rolesResponse = await fetch('/api/admin/roles.php?action=list');
        const rolesData = await rolesResponse.json();
        
        if (!rolesData.success) {
            throw new Error(rolesData.error || 'Erro ao carregar cargos');
        }
        
        const allRoles = rolesData.data;
        
        // Build roles checkboxes
        const container = document.getElementById('rolesCheckboxes');
        container.innerHTML = '';
        
        allRoles.forEach(role => {
            const isChecked = userRoleIds.includes(role.id);
            const roleItem = document.createElement('div');
            roleItem.className = 'role-checkbox-item';
            roleItem.innerHTML = `
                <input type="checkbox" id="role_${role.id}" value="${role.id}" ${isChecked ? 'checked' : ''}>
                <label for="role_${role.id}">
                    <div class="role-checkbox-name">${role.name}</div>
                    <div class="role-checkbox-description">${role.description || 'Sem descrição'}</div>
                </label>
            `;
            container.appendChild(roleItem);
        });
        
        // Show modal
        document.getElementById('userRolesModal').style.display = 'block';
        
    } catch (error) {
        console.error('Error opening user roles modal:', error);
        alert('Erro ao abrir modal: ' + error.message);
    }
}

/**
 * Close user roles modal
 */
function closeUserRolesModal() {
    document.getElementById('userRolesModal').style.display = 'none';
    currentUserIdForRoles = null;
}

/**
 * Save user roles
 */
async function saveUserRoles() {
    if (!currentUserIdForRoles) return;
    
    const checkboxes = document.querySelectorAll('#rolesCheckboxes input[type="checkbox"]:checked');
    const roleIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    try {
        const response = await fetch('/api/admin/roles.php?action=assign-roles', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUserIdForRoles,
                role_ids: roleIds
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Cargos atualizados com sucesso!');
            closeUserRolesModal();
            loadUsers();
        } else {
            alert('Erro ao atualizar cargos: ' + (data.error || data.message));
        }
    } catch (error) {
        console.error('Error saving roles:', error);
        alert('Erro ao salvar cargos');
    }
}

// Update the manageUserRoles function to use the modal
async function manageUserRoles(userId) {
    openUserRolesModal(userId);
}

// Event listeners for modal buttons
document.addEventListener('DOMContentLoaded', function() {
    // Role permissions modal
    const savePermissionsBtn = document.getElementById('saveRolePermissions');
    if (savePermissionsBtn) {
        savePermissionsBtn.addEventListener('click', saveRolePermissions);
    }
    
    // User roles modal
    const saveUserRolesBtn = document.getElementById('saveUserRoles');
    if (saveUserRolesBtn) {
        saveUserRolesBtn.addEventListener('click', saveUserRoles);
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const rolePermModal = document.getElementById('rolePermissionsModal');
        const userRolesModal = document.getElementById('userRolesModal');
        
        if (event.target === rolePermModal) {
            closeRolePermissionsModal();
        }
        if (event.target === userRolesModal) {
            closeUserRolesModal();
        }
    });
});

