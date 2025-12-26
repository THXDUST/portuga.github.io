const WHATSAPP_NUMBER = '5513997597759';

function getCart() {
    const cart = localStorage.getItem('cart');
    return cart ? JSON.parse(cart) : [];
}

function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartBadge();
}

function updateCartBadge() {
    const cart = getCart();
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badges = document.querySelectorAll('#cart-badge');
    
    badges.forEach(badge => {
        if (totalItems > 0) {
            badge.textContent = totalItems;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    });
}

function addToCart(name, price, image) {
    const cart = getCart();
    const existingItem = cart.find(item => item.name === name);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: Date.now(),
            name: name,
            price: price,
            image: image,
            quantity: 1
        });
    }
    
    saveCart(cart);
    alert(`${name} adicionado ao carrinho!`);
}

function removeFromCart(id) {
    let cart = getCart();
    cart = cart.filter(item => item.id !== id);
    saveCart(cart);
    renderCart();
}

function updateQuantity(id, change) {
    const cart = getCart();
    const item = cart.find(item => item.id === id);
    
    if (item) {
        item.quantity += change;
        
        if (item.quantity <= 0) {
            removeFromCart(id);
        } else {
            saveCart(cart);
            renderCart();
        }
    }
}

function clearCart() {
    localStorage.removeItem('cart');
    updateCartBadge();
}

function renderCart() {
    const cart = getCart();
    const container = document.getElementById('cart-items-container');
    const emptyCart = document.getElementById('empty-cart');
    const summary = document.getElementById('cart-summary');
    
    if (!container) return;
    
    if (cart.length === 0) {
        container.innerHTML = '';
        if (emptyCart) emptyCart.style.display = 'block';
        if (summary) summary.style.display = 'none';
        return;
    }
    
    if (emptyCart) emptyCart.style.display = 'none';
    if (summary) summary.style.display = 'block';
    
    let html = '';
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        const imageSrc = item.image || 'https://via.placeholder.com/80x80/e8c13f/333?text=Prato';
        
        html += `
            <div class="cart-item">
                <img src="${imageSrc}" alt="${item.name}" class="cart-item-image" 
                     onerror="this.src='https://via.placeholder.com/80x80/e8c13f/333?text=Prato'">
                <div class="cart-item-info">
                    <h3 class="cart-item-name">${item.name}</h3>
                    <p class="cart-item-price">R$ ${item.price.toFixed(2)}</p>
                </div>
                <div class="cart-item-controls">
                    <button class="quantity-btn" onclick="updateQuantity(${item.id}, -1)">−</button>
                    <span class="quantity-display">${item.quantity}</span>
                    <button class="quantity-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                    <button class="btn btn-danger" style="margin-left: 15px;" onclick="removeFromCart(${item.id})">🗑️</button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    const subtotalEl = document.getElementById('subtotal');
    const totalEl = document.getElementById('total');
    
    if (subtotalEl) subtotalEl.textContent = `R$ ${subtotal.toFixed(2)}`;
    if (totalEl) totalEl.textContent = `R$ ${subtotal.toFixed(2)}`;
}

function finalizeOrder() {
    const cart = getCart();
    
    if (cart.length === 0) {
        alert('Seu carrinho está vazio!');
        return;
    }
    
    let message = '*🍽️🍕 Novo Pedido - Restaurante Portuga*\n\n';
    message += '*Itens do Pedido:*\n';
    
    let total = 0;
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        message += `${index + 1}. ${item.name}\n`;
        message += `   Quantidade: ${item.quantity}x\n`;
        message += `   Preço unitário: R$ ${item.price.toFixed(2)}\n`;
        message += `   Subtotal: R$ ${itemTotal.toFixed(2)}\n\n`;
    });
    
    message += `*Total do Pedido: R$ ${total.toFixed(2)}*\n\n`;
    message += '_Por favor, confirme o pedido e informe o endereço de entrega._';
    
    saveOrder(cart, total);
    const encodedMessage = encodeURIComponent(message);
    
    const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
    
    clearCart();
    renderCart();
}

function saveOrder(cart, total) {
    const orders = getOrders();
    const order = {
        id: Date.now(),
        date: new Date().toISOString(),
        items: cart,
        total: total,
        status: 'pendente'
    };
    
    orders.push(order);
    localStorage.setItem('orders', JSON.stringify(orders));
}

function getOrders() {
    const orders = localStorage.getItem('orders');
    return orders ? JSON.parse(orders) : [];
}

function updateOrderStatus(orderId, newStatus) {
    const orders = getOrders();
    const order = orders.find(o => o.id === orderId);
    
    if (order) {
        order.status = newStatus;
        localStorage.setItem('orders', JSON.stringify(orders));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateCartBadge();
});
