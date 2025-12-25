// Admin configuration
const ADMIN_CREDENTIALS = {
    username: 'admin',
    password: 'portuga123'
};

// Check if user is logged in
function checkAuth() {
    const isLoggedIn = sessionStorage.getItem('adminLoggedIn');
    return isLoggedIn === 'true';
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
