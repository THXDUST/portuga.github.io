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
    
    // Load tab content
    currentTab = tabName;
    loadTabContent(tabName);
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
        case 'reports':
            initReportFilters();
            break;
        case 'resumes':
            loadResumes();
            break;
        case 'ouvidoria':
            loadOuvidoriaMessages();
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
    const orders = getOrders();
    
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
    
    card.innerHTML = `
        <div class="kanban-card-header">
            <span class="kanban-card-id">Pedido #${order.id}</span>
            <span class="kanban-card-time">${timeStr}</span>
        </div>
        <div class="kanban-card-content">
            <div class="kanban-card-items">${itemsList}</div>
            <div class="kanban-card-total">R$ ${order.total.toFixed(2)}</div>
        </div>
    `;
    
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

function loadMenuManagement() {
    const container = document.getElementById('menu-management');
    if (!container) return;
    
    container.innerHTML = '<p style="color: #666;">Funcionalidade de gerenciamento de cardápio em desenvolvimento...</p>';
    // In a real implementation, this would load menu groups and items from the API
}

function showAddGroupModal() {
    alert('Funcionalidade de adicionar grupo será implementada em breve!');
}

function showAddItemModal() {
    alert('Funcionalidade de adicionar item será implementada em breve!');
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
    
    if (checkbox && checkbox.checked) {
        if (!confirm('Ativar modo manutenção? Isso pode restringir o acesso ao site.')) {
            checkbox.checked = false;
            return;
        }
        alert('Modo manutenção ativado!');
    } else {
        alert('Modo manutenção desativado!');
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
    
    // In a real implementation, this would save to the API
    console.log('Saving settings:', settings);
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
