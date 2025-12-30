// Order Tracking Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    loadOrderTracking();
    loadOrderHistory();
});

function loadOrderTracking() {
    const orders = getOrders();
    
    if (orders.length === 0) {
        return;
    }
    
    // Get the most recent order
    const latestOrder = orders[orders.length - 1];
    
    // Update tracking stages
    updateTrackingStages(latestOrder.status);
    
    // Display order details
    displayOrderDetails(latestOrder);
}

function updateTrackingStages(status) {
    const stages = document.querySelectorAll('.tracking-stage');
    
    // Map old status to new
    let currentStatus = status;
    if (status === 'pendente') currentStatus = 'recebido';
    if (status === 'preparo') currentStatus = 'em_andamento';
    if (status === 'concluido') currentStatus = 'finalizado';
    
    const statusOrder = ['recebido', 'em_andamento', 'finalizado'];
    const currentIndex = statusOrder.indexOf(currentStatus);
    
    stages.forEach((stage, index) => {
        if (index <= currentIndex) {
            stage.classList.add('active');
        } else {
            stage.classList.remove('active');
        }
    });
}

function displayOrderDetails(order) {
    const container = document.getElementById('order-info');
    if (!container) return;
    
    const date = new Date(order.date);
    const dateStr = date.toLocaleString('pt-BR');
    
    const statusMap = {
        'pendente': 'Recebido',
        'recebido': 'Recebido',
        'preparo': 'Em Preparo',
        'em_andamento': 'Em Preparo',
        'concluido': 'Finalizado',
        'finalizado': 'Finalizado'
    };
    
    let html = `
        <div style="display: grid; gap: 15px;">
            <div>
                <strong>Pedido #${order.id}</strong>
                <span style="margin-left: 15px; padding: 5px 12px; background: #e8c13f; color: white; border-radius: 20px; font-size: 0.9rem;">
                    ${statusMap[order.status] || order.status}
                </span>
            </div>
            <div><strong>Data:</strong> ${dateStr}</div>
            <div><strong>Itens:</strong></div>
            <ul style="margin-left: 20px;">
                ${order.items.map(item => `
                    <li>${item.quantity}x ${item.name} - R$ ${(item.price * item.quantity).toFixed(2)}</li>
                `).join('')}
            </ul>
            <div style="font-size: 1.3rem; font-weight: bold; color: #28a745;">
                Total: R$ ${order.total.toFixed(2)}
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

function loadOrderHistory() {
    const container = document.getElementById('order-history');
    if (!container) return;
    
    const orders = getOrders();
    
    if (orders.length === 0) {
        container.innerHTML = `
            <p style="color: #666; text-align: center;">
                Você ainda não fez nenhum pedido.<br>
                <a href="menu.html" class="btn" style="margin-top: 15px; display: inline-block;">Ver Cardápio</a>
            </p>
        `;
        return;
    }
    
    // Sort orders by date (newest first)
    const sortedOrders = [...orders].sort((a, b) => new Date(b.date) - new Date(a.date));
    
    let html = '<div style="display: grid; gap: 15px;">';
    
    sortedOrders.forEach(order => {
        const date = new Date(order.date);
        const dateStr = date.toLocaleString('pt-BR');
        
        const statusMap = {
            'pendente': { label: 'Recebido', color: '#ffc107' },
            'recebido': { label: 'Recebido', color: '#ffc107' },
            'preparo': { label: 'Em Preparo', color: '#17a2b8' },
            'em_andamento': { label: 'Em Preparo', color: '#17a2b8' },
            'concluido': { label: 'Finalizado', color: '#28a745' },
            'finalizado': { label: 'Finalizado', color: '#28a745' }
        };
        
        const statusInfo = statusMap[order.status] || { label: order.status, color: '#6c757d' };
        
        html += `
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <strong>Pedido #${order.id}</strong>
                        <span style="margin-left: 10px; color: #666; font-size: 0.9rem;">${dateStr}</span>
                    </div>
                    <span style="padding: 5px 12px; background: ${statusInfo.color}; color: white; border-radius: 20px; font-size: 0.85rem;">
                        ${statusInfo.label}
                    </span>
                </div>
                <div style="color: #666;">
                    ${order.items.length} item(ns) - <strong style="color: #28a745;">R$ ${order.total.toFixed(2)}</strong>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Auto-refresh every 30 seconds
setInterval(function() {
    loadOrderTracking();
    loadOrderHistory();
}, 30000);
