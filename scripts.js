/**
 * Portuga Restaurant - Main JavaScript
 * 
 * Google Maps API Configuration (Optional):
 * To enable Google Maps geocoding, add this line before including scripts.js:
 * <script>window.GOOGLE_MAPS_API_KEY = 'your_api_key_here';</script>
 * 
 * Without an API key, the system will use OpenStreetMap Nominatim (free, no key required)
 */

const WHATSAPP_NUMBER = '5513996379775'; // '5513997597759'; número do alemão 

// ============================================
// INLINE MESSAGE FUNCTIONS (Replacing alert())
// ============================================

/**
 * Show inline message near a specific element
 * @param {string} message - Message text to display
 * @param {string} type - Message type: 'success', 'error', 'warning', 'info'
 * @param {HTMLElement} targetElement - Element to show message near (default: body)
 * @param {number} duration - Auto-hide duration in ms (0 = no auto-hide)
 */
function showInlineMessage(message, type = 'info', targetElement = null, duration = 5000) {
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `inline-message ${type}`;
    messageDiv.innerHTML = `
        <span>${message}</span>
        <button class="inline-message-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Insert near target element or at top of body
    if (targetElement) {
        targetElement.insertAdjacentElement('afterend', messageDiv);
    } else {
        document.body.insertBefore(messageDiv, document.body.firstChild);
    }
    
    // Auto-hide after duration
    if (duration > 0) {
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => messageDiv.remove(), 300);
            }
        }, duration);
    }
    
    return messageDiv;
}

// Add slideOut animation to CSS dynamically if not exists
if (!document.getElementById('inline-message-animations')) {
    const style = document.createElement('style');
    style.id = 'inline-message-animations';
    style.textContent = `
        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }
    `;
    document.head.appendChild(style);
}

// ============================================
// DYNAMIC NAVIGATION - Hide current page button
// ============================================

/**
 * Hide navigation button for current page
 */
function setupDynamicNavigation() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('nav a');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.html')) {
            link.parentElement.classList.add('nav-current-page');
        }
    });
}

// Run on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupDynamicNavigation);
    document.addEventListener('DOMContentLoaded', checkAndCacheTableNumber);
} else {
    setupDynamicNavigation();
    checkAndCacheTableNumber();
}

// ============================================
// TABLE NUMBER CACHE (via URL ?mesa=X)
// ============================================

/**
 * Check URL for mesa parameter and cache in sessionStorage
 */
function checkAndCacheTableNumber() {
    const urlParams = new URLSearchParams(window.location.search);
    const mesaNumber = urlParams.get('mesa');
    
    if (mesaNumber && !isNaN(parseInt(mesaNumber))) {
        sessionStorage.setItem('mesaNumber', mesaNumber);
        console.log('Table number cached:', mesaNumber);
    }
}

/**
 * Get cached table number from sessionStorage
 */
function getCachedTableNumber() {
    return sessionStorage.getItem('mesaNumber');
}

/**
 * Clear cached table number
 */
function clearCachedTableNumber() {
    sessionStorage.removeItem('mesaNumber');
}

// ============================================
// CART FUNCTIONS
// ============================================ 

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
    
    // Show inline message instead of alert
    // Try to find the button that was clicked to show message near it
    const activeButton = event?.target?.closest('button');
    showInlineMessage(`✓ ${name} adicionado ao carrinho!`, 'success', activeButton, 3000);
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
const RESTAURANT_ADDRESS = "Av. Beira Mar, 13050 - Balneário Britânia, Ilha Comprida - SP, 11925-000";
let calculatedDistance = 0;

function toggleDeliveryFields() {
    const checkbox = document.getElementById('for-delivery');
    const fields = document.getElementById('delivery-fields');
    const paymentOptions = document.getElementById('payment-options');
    const tableNumberField = document.getElementById('table-number-field');
    
    if (checkbox && fields) {
        if (checkbox.checked) {
            fields.style.display = 'block';
            if (paymentOptions) paymentOptions.style.display = 'block';
            if (tableNumberField) tableNumberField.style.display = 'none';
        } else {
            fields.style.display = 'none';
            if (paymentOptions) paymentOptions.style.display = 'none';
            
            // Show table number field if user is logged in and not for delivery
            // and there's no mesa parameter
            const mesaNumber = sessionStorage.getItem('mesaNumber');
            if (tableNumberField && !mesaNumber && window.isUserLoggedIn) {
                const isLoggedIn = isUserLoggedIn();
                if (isLoggedIn) {
                    tableNumberField.style.display = 'block';
                }
            }
            
            // Reset delivery fee
            const deliveryFeeRow = document.getElementById('delivery-fee-row');
            const deliveryError = document.getElementById('delivery-error');
            const distanceResult = document.getElementById('distance-result');
            if (deliveryFeeRow) deliveryFeeRow.style.display = 'none';
            if (deliveryError) deliveryError.style.display = 'none';
            if (distanceResult) distanceResult.style.display = 'none';
            calculatedDistance = 0;
            renderCart();
        }
    }
}

function showManualDistanceInput() {
    const manualInput = document.getElementById('manual-distance-input');
    if (manualInput) {
        manualInput.style.display = 'block';
    }
}

function toggleChangeField() {
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked');
    const changeField = document.getElementById('change-amount-field');
    
    if (changeField && paymentMethod) {
        if (paymentMethod.value === 'cash-with-change') {
            changeField.style.display = 'block';
        } else {
            changeField.style.display = 'none';
        }
    }
}

// Geolocation API integration using Nominatim (OpenStreetMap)
async function calculateDistanceFromAddress() {
    const street = document.getElementById('delivery-street')?.value || '';
    const number = document.getElementById('delivery-number')?.value || '';
    const neighborhood = document.getElementById('delivery-neighborhood')?.value || '';
    const city = document.getElementById('delivery-city')?.value || '';
    
    const deliveryError = document.getElementById('delivery-error');
    const distanceResult = document.getElementById('distance-result');
    const calculatedDistanceEl = document.getElementById('calculated-distance');
    const btn = document.getElementById('calculate-distance-btn');
    
    // Validate required fields
    if (!street.trim() || !number.trim() || !neighborhood.trim() || !city.trim()) {
        if (deliveryError) {
            deliveryError.textContent = 'Por favor, preencha todos os campos obrigatórios do endereço.';
            deliveryError.style.display = 'block';
        }
        return;
    }
    
    // Clear previous errors
    if (deliveryError) deliveryError.style.display = 'none';
    
    // Show loading state
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Calculando...';
    }
    
    try {
        // Build full address
        const fullAddress = `${street}, ${number}, ${neighborhood}, ${city}, SP, Brazil`;
        
        // Geocode customer address
        const customerCoords = await geocodeAddress(fullAddress);
        
        if (!customerCoords) {
            throw new Error('Não foi possível localizar o endereço informado.');
        }
        
        // Geocode restaurant address
        const restaurantCoords = await geocodeAddress(RESTAURANT_ADDRESS);
        
        if (!restaurantCoords) {
            throw new Error('Erro ao localizar endereço do restaurante.');
        }
        
        // Calculate distance using Haversine formula
        const distance = calculateHaversineDistance(
            restaurantCoords.lat,
            restaurantCoords.lon,
            customerCoords.lat,
            customerCoords.lon
        );
        
        calculatedDistance = distance;
        
        // Display result
        if (calculatedDistanceEl) {
            calculatedDistanceEl.textContent = distance.toFixed(1);
        }
        if (distanceResult) {
            distanceResult.style.display = 'block';
        }
        
        // Calculate and display delivery fee
        calculateDeliveryFeeFromDistance(distance);
        
    } catch (error) {
        console.error('Geocoding error:', error);
        if (deliveryError) {
            deliveryError.textContent = `Erro ao calcular distância: ${error.message}. Use o campo manual abaixo.`;
            deliveryError.style.display = 'block';
        }
        showManualDistanceInput();
    } finally {
        // Reset button
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Calcular Distância e Taxa';
        }
    }
}

// Geocode address using Nominatim API
async function geocodeAddress(address) {
    // Check if Google Maps API key is available
    const googleMapsKey = window.GOOGLE_MAPS_API_KEY || null;
    
    if (googleMapsKey) {
        // Use Google Maps Geocoding API
        try {
            const url = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(address)}&key=${googleMapsKey}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.status === 'OK' && data.results.length > 0) {
                const location = data.results[0].geometry.location;
                return {
                    lat: location.lat,
                    lon: location.lng
                };
            }
        } catch (error) {
            console.error('Google Maps API error, falling back to OpenStreetMap:', error);
        }
    }
    
    // Fallback to OpenStreetMap Nominatim API (free, no key required)
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`;
    
    try {
        const response = await fetch(url, {
            headers: {
                'User-Agent': 'PortugaRestaurant/1.0 (https://thxdust.github.io/test-portuga.github.io/)'
            }
        });
        
        if (!response.ok) {
            if (response.status === 429) {
                throw new Error('Muitas requisições. Por favor, tente novamente em alguns instantes.');
            }
            throw new Error('Falha na requisição de geolocalização');
        }
        
        const data = await response.json();
        
        if (data && data.length > 0) {
            return {
                lat: parseFloat(data[0].lat),
                lon: parseFloat(data[0].lon)
            };
        }
        
        return null;
    } catch (error) {
        console.error('Geocoding API error:', error);
        throw error;
    }
}

// Calculate distance using Haversine formula
function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const distance = R * c;
    
    return distance;
}

function toRad(degrees) {
    return degrees * (Math.PI / 180);
}

function calculateDeliveryFeeFromDistance(distance) {
    const deliveryFeeRow = document.getElementById('delivery-fee-row');
    const deliveryFeeEl = document.getElementById('delivery-fee');
    const deliveryError = document.getElementById('delivery-error');
    
    if (!deliveryFeeRow || !deliveryFeeEl) return;
    
    let fee = 0;
    let error = '';
    
    // Fixed delivery fee based on distance (system requirement)
    // Using explicit range checking for clarity and to avoid gaps
    if (distance > 0 && distance <= 5) {
        fee = 5.00;
    } else if (distance > 5 && distance <= 7) {
        fee = 7.00;
    } else if (distance > 7 && distance <= 10) {
        fee = 10.00;
    } else if (distance > 10 && distance <= 15) {
        fee = 15.00;
    } else if (distance > 15 && distance <= 18) {
        fee = 18.00;
    } else if (distance > 18) {
        error = 'Desculpe, não realizamos entregas para essa distância (acima de 18 km).';
        fee = 0;
    } else {
        // Distance <= 0 or invalid
        error = 'Por favor, informe uma distância válida.';
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

function calculateDeliveryFee() {
    const distanceInput = document.getElementById('manual-distance');
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
    
    calculatedDistance = distance;
    calculateDeliveryFeeFromDistance(distance);
}

function getDeliveryFee() {
    const checkbox = document.getElementById('for-delivery');
    
    if (!checkbox || !checkbox.checked) return 0;
    
    // Use calculated distance from API or manual input
    const distance = calculatedDistance;
    
    if (!distance || distance <= 0) return 0;
    
    if (distance <= 5) return 5.00;
    if (distance <= 7) return 7.00;
    if (distance <= 9) return 9.00;
    if (distance <= 11) return 11.00;
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
        
        const imageSrc = item.image || '/images/default.png';
        
        html += `
            <div class="cart-item">
                <img src="${imageSrc}" alt="${item.name}" class="cart-item-image" 
                     onerror="this.src='/images/default.png'">
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

async function finalizeOrder() {
    const cart = getCart();
    
    if (cart.length === 0) {
        alert('Seu carrinho está vazio!');
        return;
    }
    
    // Check if restaurant is open before proceeding
    const canPlaceOrder = await validateCanPlaceOrder();
    if (!canPlaceOrder) {
        return;
    }
    
    // Check for mesa parameter
    const mesaNumber = sessionStorage.getItem('mesaNumber');
    
    // Check if user is logged in (unless ordering with mesa parameter)
    if (!mesaNumber && window.isUserLoggedIn && !isUserLoggedIn()) {
        // User is not logged in and not ordering from a table
        const currentUrl = window.location.pathname + window.location.search;
        const redirectUrl = `/login.html?redirect=${encodeURIComponent(currentUrl)}`;
        
        if (confirm('Você precisa estar logado para fazer um pedido. Deseja fazer login agora?')) {
            window.location.href = redirectUrl;
        }
        return;
    }
    
    const forDelivery = document.getElementById('for-delivery')?.checked || false;
    const deliveryFee = getDeliveryFee();
    let tableNumber = null;
    let userId = null;
    
    // Get user ID if logged in
    if (window.getCurrentUser) {
        const user = getCurrentUser();
        if (user) {
            userId = user.id;
        }
    }
    
    // Handle table number
    if (mesaNumber) {
        // Using mesa parameter from URL
        tableNumber = parseInt(mesaNumber);
    } else if (!forDelivery) {
        // For local orders when logged in, get table number from field
        const tableNumberInput = document.getElementById('table-number');
        if (tableNumberInput && tableNumberInput.value) {
            tableNumber = parseInt(tableNumberInput.value);
            if (!tableNumber || tableNumber <= 0) {
                alert('Por favor, informe um número de mesa válido.');
                return;
            }
        }
    }
    
    // Get pickup/delivery time (OPTIONAL)
    const pickupTime = document.getElementById('pickup-time')?.value || '';
    
    // Validate pickup time IF provided (11:00 - 23:00)
    if (pickupTime) {
        const [hours, minutes] = pickupTime.split(':').map(Number);
        if (hours < 11 || hours >= 23) {
            alert('Horário fora do período de funcionamento (11:00 - 23:00).');
            return;
        }
    }
    
    // Validate delivery info if delivery is checked
    let deliveryAddress = '';
    if (forDelivery) {
        const street = document.getElementById('delivery-street')?.value || '';
        const number = document.getElementById('delivery-number')?.value || '';
        const neighborhood = document.getElementById('delivery-neighborhood')?.value || '';
        const city = document.getElementById('delivery-city')?.value || '';
        const complement = document.getElementById('delivery-complement')?.value || '';
        
        if (!street || !number || !neighborhood || !city) {
            alert('Por favor, preencha todos os campos obrigatórios do endereço.');
            return;
        }
        
        if (!calculatedDistance || calculatedDistance <= 0) {
            alert('Por favor, calcule a distância antes de finalizar o pedido.');
            return;
        }
        
        if (calculatedDistance > 18) {
            alert('Desculpe, não realizamos entregas para distâncias acima de 18 km.');
            return;
        }
        
        // Build full address
        deliveryAddress = `${street}, ${number} - ${neighborhood}, ${city}`;
        if (complement) {
            deliveryAddress += ` (${complement})`;
        }
        
        // Get payment method
        const paymentMethod = document.querySelector('input[name="payment-method"]:checked');
        if (!paymentMethod) {
            alert('Por favor, selecione a forma de pagamento.');
            return;
        }
        
        // Validate change amount if needed
        if (paymentMethod.value === 'cash-with-change') {
            const changeAmount = document.getElementById('change-amount')?.value || '';
            if (!changeAmount || parseFloat(changeAmount) <= 0) {
                alert('Por favor, informe o valor para o troco.');
                return;
            }
        }
    }
    
    // Build WhatsApp message
    let message = '*Novo Pedido - Restaurante Portuga*\n\n';
    
    // Add table number if applicable
    if (tableNumber) {
        message += `*🪑 Mesa: ${tableNumber}*\n\n`;
    }
    
    message += '*📋 Itens do Pedido:*\n';
    
    let subtotal = 0;
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        message += `${index + 1}. ${item.name}\n`;
        message += `   Quantidade: ${item.quantity}x\n`;
        message += `   Preço unitário: R$ ${item.price.toFixed(2)}\n`;
        message += `   Subtotal: R$ ${itemTotal.toFixed(2)}\n\n`;
    });
    
    message += '*💵 Valores:*\n';
    message += `Subtotal: R$ ${subtotal.toFixed(2)}\n`;
    
    // Add delivery info if applicable
    if (forDelivery && deliveryFee > 0) {
        message += `Taxa de Entrega: R$ ${deliveryFee.toFixed(2)} (${calculatedDistance.toFixed(1)} km)\n`;
    }
    
    const total = subtotal + deliveryFee;
    message += `*Total: R$ ${total.toFixed(2)}*\n\n`;
    
    // Add delivery info or pickup info
    if (forDelivery) {
        message += '*Endereço de Entrega:*\n';
        message += `${deliveryAddress}\n`;
        message += `Distância: ${calculatedDistance.toFixed(1)} km\n\n`;
    } else {
        message += '*Tipo:*\n';
        if (tableNumber) {
            message += `Retirada no local - Mesa ${tableNumber}\n\n`;
        } else {
            message += 'Retirada no local\n\n';
        }
    }
    
    // Add pickup time (only if provided)
    if (pickupTime) {
        message += '*⏰ Horário de Retirada/Entrega:*\n';
        message += `${pickupTime}\n\n`;
    }
    
    // Add payment method (only for delivery)
    if (forDelivery) {
        const paymentMethod = document.querySelector('input[name="payment-method"]:checked');
        message += '*💳 Forma de Pagamento:*\n';
        
        if (paymentMethod.value === 'card') {
            message += 'Cartão (Maquininha)\n\n';
        } else if (paymentMethod.value === 'cash-with-change') {
            const changeAmount = document.getElementById('change-amount')?.value || '';
            message += `Dinheiro - Troco para R$ ${parseFloat(changeAmount).toFixed(2)}\n\n`;
        } else {
            message += 'Dinheiro - Não preciso de troco\n\n';
        }
    }
    
    message += '---\n';
    message += '_Por favor, confirme o pedido!_';
    
    // Save order with all info (await API call)
    await saveOrder(cart, total, {
        forDelivery,
        deliveryDistance: calculatedDistance,
        deliveryAddress,
        deliveryFee,
        pickupTime,
        paymentMethod: forDelivery ? document.querySelector('input[name="payment-method"]:checked')?.value : null,
        changeAmount: forDelivery && document.querySelector('input[name="payment-method"]:checked')?.value === 'cash-with-change' 
            ? document.getElementById('change-amount')?.value : null,
        tableNumber: tableNumber,
        userId: userId
    });
    
    const encodedMessage = encodeURIComponent(message);
    
    const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
    
    clearCart();
    renderCart();
}

async function saveOrder(cart, total, deliveryInfo = {}) {
    try {
        // Prepare order data for API
        const orderData = {
            user_id: deliveryInfo.userId || null,
            order_number: 'WEB' + Date.now(),
            order_type: deliveryInfo.forDelivery ? 'viagem' : 'local',
            table_number: deliveryInfo.tableNumber || null,
            status: 'recebido',
            payment_method: deliveryInfo.paymentMethod ? 
                (deliveryInfo.paymentMethod === 'card' ? 'cartao_debito' : 'dinheiro') : 'dinheiro',
            change_for: deliveryInfo.changeAmount ? parseFloat(deliveryInfo.changeAmount) : null,
            delivery_address: deliveryInfo.deliveryAddress || null,
            delivery_distance: deliveryInfo.deliveryDistance || null,
            delivery_fee: deliveryInfo.deliveryFee || 0,
            pickup_time: deliveryInfo.pickupTime || null,
            subtotal: total - (deliveryInfo.deliveryFee || 0),
            total: total,
            items: cart.map(item => ({
                menu_item_id: null,
                name: item.name,
                price: item.price,
                quantity: item.quantity,
                subtotal: item.price * item.quantity
            })),
            notes: 'Pedido via website/WhatsApp'
        };
        
        console.log('📤 Sending order to API:', orderData);
        
        const response = await fetch('/api/orders.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('❌ Error saving order:', data.error);
            // Even if API fails, still allow WhatsApp order (fallback)
        } else {
            console.log('✅ Order saved to database successfully:', data);
        }
    } catch (error) {
        console.error('❌ Error saving order:', error);
        // Even if API fails, still allow WhatsApp order (fallback)
    }
}

// Fetch orders from API instead of localStorage
async function getOrders() {
    try {
        const response = await fetch('/api/orders.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            console.error('Error fetching orders:', data.error);
            return [];
        }
        
        return data.data || [];
    } catch (error) {
        console.error('Error fetching orders:', error);
        return [];
    }
}

// Update order status via API instead of localStorage
async function updateOrderStatus(orderId, newStatus) {
    try {
        const response = await fetch('/api/orders.php?action=update-status', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: orderId,
                status: newStatus
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('Error updating order status:', data.error);
            throw new Error(data.error);
        }
        
        return data;
    } catch (error) {
        console.error('Error updating order status:', error);
        throw error;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateCartBadge();
    
    // Hamburger menu toggle
    const hamburgerBtn = document.getElementById('hamburger-btn');
    // Support both nav-menu (main site) and admin-nav (admin panel)
    const navMenu = document.getElementById('nav-menu') || document.getElementById('admin-nav');
    
    if (hamburgerBtn && navMenu) {
        hamburgerBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
            navMenu.classList.toggle('show');
        });
        
        // Close menu when clicking on a link
        const navLinks = navMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                hamburgerBtn.classList.remove('active');
                navMenu.classList.remove('active');
                navMenu.classList.remove('show');
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const isClickInsideNav = navMenu.contains(event.target);
            const isClickOnHamburger = hamburgerBtn.contains(event.target);
            
            if (!isClickInsideNav && !isClickOnHamburger && navMenu.classList.contains('show')) {
                navMenu.classList.remove('show');
                navMenu.classList.remove('active');
                hamburgerBtn.classList.remove('active');
            }
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

// ============================================
// MAINTENANCE MODE CHECK
// ============================================

/**
 * Check if current page is in maintenance mode
 * Should be called on every page load
 */
async function checkMaintenanceMode() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    // Admin pages are never blocked
    if (currentPage === 'admin.html' || currentPage === 'login.html') {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/maintenance.php?action=check&page=' + currentPage);
        const data = await response.json();
        
        if (data.success && data.maintenance && data.maintenance.is_active) {
            const maintenance = data.maintenance;
            
            // Check if this specific page is restricted
            let isRestricted = maintenance.restrict_all;
            
            if (!isRestricted && maintenance.restricted_pages) {
                const restrictedPages = JSON.parse(maintenance.restricted_pages);
                isRestricted = restrictedPages.includes(currentPage);
            }
            
            if (isRestricted) {
                showMaintenanceOverlay(maintenance.custom_message, maintenance.estimated_return);
            }
        }
    } catch (error) {
        console.error('Error checking maintenance mode:', error);
        // Don't block on error
    }
}

/**
 * Display maintenance overlay
 */
function showMaintenanceOverlay(message, eta) {
    const overlay = document.createElement('div');
    overlay.id = 'maintenance-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    `;
    
    const content = document.createElement('div');
    content.style.cssText = `
        background: white;
        padding: 50px;
        border-radius: 20px;
        text-align: center;
        max-width: 600px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    `;
    
    const icon = document.createElement('div');
    icon.style.cssText = 'font-size: 5rem; margin-bottom: 20px;';
    icon.textContent = '🔧';
    
    const title = document.createElement('h2');
    title.style.cssText = 'color: #e8c13f; margin-bottom: 20px; font-size: 2rem;';
    title.textContent = 'Em Manutenção';
    
    const messageEl = document.createElement('p');
    messageEl.style.cssText = 'color: #666; font-size: 1.2rem; line-height: 1.6; margin-bottom: 20px;';
    messageEl.textContent = message || 'Estamos realizando melhorias no sistema. Por favor, volte mais tarde.';
    
    content.appendChild(icon);
    content.appendChild(title);
    content.appendChild(messageEl);
    
    if (eta) {
        const etaEl = document.createElement('p');
        etaEl.style.cssText = 'color: #999; font-size: 1rem; margin-top: 15px;';
        const etaDate = new Date(eta);
        etaEl.textContent = `⏰ Previsão de retorno: ${etaDate.toLocaleString('pt-BR')}`;
        content.appendChild(etaEl);
    }
    
    overlay.appendChild(content);
    document.body.appendChild(overlay);
    
    // Disable scrolling
    document.body.style.overflow = 'hidden';
}

// Auto-check maintenance mode on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkMaintenanceMode);
} else {
    checkMaintenanceMode();
}

// ============================================
// RESTAURANT STATUS CHECK
// ============================================

/**
 * Check if restaurant is currently open
 * @returns {Promise<boolean>}
 */
async function checkRestaurantStatus() {
    try {
        const response = await fetch('/api/admin/settings.php?action=all');
        const data = await response.json();
        
        if (data.success && data.data) {
            // Settings are returned as objects with value, type, etc.
            const isOpenSetting = data.data.is_open;
            if (isOpenSetting && typeof isOpenSetting === 'object') {
                return isOpenSetting.value === true || isOpenSetting.value === 'true' || isOpenSetting.value === '1';
            }
            // Fallback for backward compatibility
            return data.data.is_open === true || data.data.is_open === '1';
        }
        
        // If settings not available, assume open
        return true;
    } catch (error) {
        console.error('Error checking restaurant status:', error);
        // On error, assume open to not block orders
        return true;
    }
}

/**
 * Show or hide the closed restaurant banner
 */
async function updateRestaurantBanner() {
    const banner = document.getElementById('restaurant-closed-banner');
    if (!banner) return;
    
    const isOpen = await checkRestaurantStatus();
    
    if (!isOpen) {
        banner.style.display = 'block';
        document.body.classList.add('restaurant-closed');
    } else {
        banner.style.display = 'none';
        document.body.classList.remove('restaurant-closed');
    }
}

/**
 * Validate if orders can be placed (restaurant must be open)
 * @returns {Promise<boolean>}
 */
async function validateCanPlaceOrder() {
    const isOpen = await checkRestaurantStatus();
    
    if (!isOpen) {
        alert('❌ Restaurante Fechado\n\nDesculpe, o restaurante está fechado no momento e não estamos aceitando pedidos. Por favor, tente novamente mais tarde.');
        return false;
    }
    
    return true;
}

// Check restaurant status on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateRestaurantBanner);
} else {
    updateRestaurantBanner();
}

// Refresh banner status every 2 minutes
const BANNER_UPDATE_INTERVAL_MS = 120000; // 2 minutes
setInterval(updateRestaurantBanner, BANNER_UPDATE_INTERVAL_MS);
