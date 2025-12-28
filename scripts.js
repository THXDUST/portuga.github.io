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

// Delivery fee calculation
function toggleDeliveryFields() {
    const checkbox = document.getElementById('for-delivery');
    const fields = document.getElementById('delivery-fields');
    
    if (checkbox && fields) {
        if (checkbox.checked) {
            fields.style.display = 'block';
        } else {
            fields.style.display = 'none';
            // Reset delivery fee
            const deliveryFeeRow = document.getElementById('delivery-fee-row');
            const deliveryError = document.getElementById('delivery-error');
            if (deliveryFeeRow) deliveryFeeRow.style.display = 'none';
            if (deliveryError) deliveryError.style.display = 'none';
            renderCart();
        }
    }
}

function calculateDeliveryFee() {
    const distanceInput = document.getElementById('delivery-distance');
    const deliveryFeeRow = document.getElementById('delivery-fee-row');
    const deliveryFeeEl = document.getElementById('delivery-fee');
    const deliveryError = document.getElementById('delivery-error');
    const checkbox = document.getElementById('for-delivery');
    
    if (!distanceInput || !deliveryFeeRow || !deliveryFeeEl || !checkbox) return;
    
    const distance = parseFloat(distanceInput.value);
    
    if (!checkbox.checked || !distance || distance <= 0) {
        deliveryFeeRow.style.display = 'none';
        if (deliveryError) deliveryError.style.display = 'none';
        renderCart();
        return;
    }
    
    let fee = 0;
    let error = '';
    
    // Fixed delivery fee based on distance
    if (distance <= 5) {
        fee = 5.00;
    } else if (distance <= 7) {
        fee = 7.00;
    } else if (distance <= 9) {
        fee = 9.00;
    } else if (distance <= 18) {
        fee = 18.00;
    } else {
        error = 'Desculpe, não realizamos entregas para essa distância (acima de 18 km).';
        fee = 0;
    }
    
    if (error) {
        if (deliveryError) {
            deliveryError.textContent = error;
            deliveryError.style.display = 'block';
        }
        deliveryFeeRow.style.display = 'none';
    } else {
        if (deliveryError) deliveryError.style.display = 'none';
        deliveryFeeEl.textContent = `R$ ${fee.toFixed(2)}`;
        deliveryFeeRow.style.display = 'flex';
    }
    
    renderCart();
}

function getDeliveryFee() {
    const checkbox = document.getElementById('for-delivery');
    const distanceInput = document.getElementById('delivery-distance');
    
    if (!checkbox || !checkbox.checked || !distanceInput) return 0;
    
    const distance = parseFloat(distanceInput.value);
    
    if (!distance || distance <= 0) return 0;
    
    if (distance <= 5) return 5.00;
    if (distance <= 7) return 7.00;
    if (distance <= 9) return 9.00;
    if (distance <= 18) return 18.00;
    
    return 0; // Above 18 km, no delivery
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
    
    // Calculate delivery fee
    const deliveryFee = getDeliveryFee();
    const total = subtotal + deliveryFee;
    
    if (subtotalEl) subtotalEl.textContent = `R$ ${subtotal.toFixed(2)}`;
    if (totalEl) totalEl.textContent = `R$ ${total.toFixed(2)}`;
}

function finalizeOrder() {
    const cart = getCart();
    
    if (cart.length === 0) {
        alert('Seu carrinho está vazio!');
        return;
    }
    
    const forDelivery = document.getElementById('for-delivery')?.checked || false;
    const deliveryDistance = document.getElementById('delivery-distance')?.value || '';
    const deliveryAddress = document.getElementById('delivery-address')?.value || '';
    const deliveryFee = getDeliveryFee();
    
    // Validate delivery info if delivery is checked
    if (forDelivery) {
        if (!deliveryDistance || parseFloat(deliveryDistance) <= 0) {
            alert('Por favor, informe a distância para entrega.');
            return;
        }
        if (parseFloat(deliveryDistance) > 18) {
            alert('Desculpe, não realizamos entregas para distâncias acima de 18 km.');
            return;
        }
        if (!deliveryAddress || deliveryAddress.trim() === '') {
            alert('Por favor, informe o endereço de entrega.');
            return;
        }
    }
    
    let message = '*🍽️🍕 Novo Pedido - Restaurante Portuga*\n\n';
    message += '*Itens do Pedido:*\n';
    
    let subtotal = 0;
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        message += `${index + 1}. ${item.name}\n`;
        message += `   Quantidade: ${item.quantity}x\n`;
        message += `   Preço unitário: R$ ${item.price.toFixed(2)}\n`;
        message += `   Subtotal: R$ ${itemTotal.toFixed(2)}\n\n`;
    });
    
    message += `*Subtotal: R$ ${subtotal.toFixed(2)}*\n\n`;
    
    // Add delivery info if applicable
    if (forDelivery && deliveryFee > 0) {
        message += '*🚗 Informações de Entrega:*\n';
        message += `Distância: ${deliveryDistance} km\n`;
        message += `Endereço: ${deliveryAddress}\n`;
        message += `Taxa de Entrega: R$ ${deliveryFee.toFixed(2)}\n\n`;
    }
    
    const total = subtotal + deliveryFee;
    message += `*💰 Total do Pedido: R$ ${total.toFixed(2)}*\n\n`;
    
    if (!forDelivery) {
        message += '_Retirada no local_\n';
    }
    message += '_Por favor, confirme o pedido!_';
    
    saveOrder(cart, total, {
        forDelivery,
        deliveryDistance,
        deliveryAddress,
        deliveryFee
    });
    
    const encodedMessage = encodeURIComponent(message);
    
    const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
    
    clearCart();
    renderCart();
}

function saveOrder(cart, total, deliveryInfo = {}) {
    const orders = getOrders();
    const order = {
        id: Date.now(),
        date: new Date().toISOString(),
        items: cart,
        total: total,
        status: 'pendente',
        delivery: deliveryInfo
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
    
    // Hamburger menu toggle
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const navMenu = document.getElementById('nav-menu');
    
    if (hamburgerBtn && navMenu) {
        hamburgerBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
        
        // Close menu when clicking on a link
        const navLinks = navMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                hamburgerBtn.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    }
    
    // Show notes popup on index page
    if (window.location.pathname.endsWith('index.html') || window.location.pathname === '/' || window.location.pathname.endsWith('/')) {
        showNotesPopup();
    }
});

// Notes popup functions
function showNotesPopup() {
    // Check if popup was already shown in this session
    if (sessionStorage.getItem('notesPopupShown')) {
        return;
    }
    
    const notes = getActiveNotes();
    
    if (notes.length === 0) {
        return; // No active notes to show
    }
    
    const popup = document.getElementById('notes-popup');
    const content = document.getElementById('notes-popup-content');
    
    if (!popup || !content) return;
    
    let html = '<div style="text-align: center;">';
    html += '<h2 style="color: #e8c13f; margin-bottom: 20px;">📢 Comunicados</h2>';
    
    notes.forEach((note, index) => {
        html += `
            <div style="text-align: left; margin-bottom: ${index < notes.length - 1 ? '20px' : '0'}; 
                        padding-bottom: ${index < notes.length - 1 ? '20px' : '0'}; 
                        border-bottom: ${index < notes.length - 1 ? '1px solid #e9ecef' : 'none'};">
                <h3 style="color: #333; margin-bottom: 10px;">${note.title}</h3>
                <p style="color: #666; line-height: 1.6; white-space: pre-line;">${note.content}</p>
            </div>
        `;
    });
    
    html += '<button class="btn" onclick="closeNotesPopup()" style="margin-top: 20px; width: 100%;">Entendi</button>';
    html += '</div>';
    
    content.innerHTML = html;
    popup.style.display = 'block';
}

function closeNotesPopup() {
    const popup = document.getElementById('notes-popup');
    if (popup) {
        popup.style.display = 'none';
        // Mark as shown in this session
        sessionStorage.setItem('notesPopupShown', 'true');
    }
}

function getActiveNotes() {
    const notes = localStorage.getItem('admin_notes');
    if (!notes) return [];
    
    const allNotes = JSON.parse(notes);
    return allNotes.filter(note => note.active === true);
}
