const Cart = {
    items: [],
    totalItems: 0,
    totalPrice: 0,

    init() {
        this.loadFromStorage();
        this.renderCartBadge();
        this.setupEventListeners();
    },

    loadFromStorage() {
        try {
            const data = localStorage.getItem('granvremeni_cart');
            if (data) {
                const parsed = JSON.parse(data);
                this.items = parsed.items || [];
                this.updateTotals();
            }
        } catch (e) {
            this.items = [];
        }
    },

    saveToStorage() {
        try {
            localStorage.setItem('granvremeni_cart', JSON.stringify({
                items: this.items
            }));
        } catch (e) {
           
        }
    },

    updateTotals() {
        this.totalItems = this.items.reduce((sum, item) => sum + item.quantity, 0);
        this.totalPrice = this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    },

    addItem(product, size, material, quantity = 1) {
       
        const existingIndex = this.items.findIndex(item =>
            item.id === product.id &&
            item.size === size &&
            item.material === material
        );

        if (existingIndex !== -1) {
            this.items[existingIndex].quantity += quantity;
        } else {
            this.items.push({
                id: product.id,
                sku: product.sku || '',
                name: product.name,
                price: product.price,
                size: size || 'стандартный',
                material: material || 'стандартный',
                photoURL: product.photoURL || null,
                quantity: quantity
            });
        }

        this.updateTotals();
        this.saveToStorage();
        this.renderCartBadge();
        this.renderCartDropdown();
        this.showNotification(`Товар добавлен в корзину`);
    },

    removeItem(index) {
        if (index >= 0 && index < this.items.length) {
            this.items.splice(index, 1);
            this.updateTotals();
            this.saveToStorage();
            this.renderCartBadge();
            this.renderCartDropdown();
        }
    },

    updateQuantity(index, newQuantity) {
        if (index >= 0 && index < this.items.length) {
            if (newQuantity <= 0) {
                this.removeItem(index);
                return;
            }
            this.items[index].quantity = newQuantity;
            this.updateTotals();
            this.saveToStorage();
            this.renderCartBadge();
            this.renderCartDropdown();
        }
    },

    clearCart() {
        this.items = [];
        this.updateTotals();
        this.saveToStorage();
        this.renderCartBadge();
        this.renderCartDropdown();
    },

    renderCartBadge() {
        const badge = document.querySelector('.cart-badge');
        if (badge) {
            badge.textContent = this.totalItems;
            badge.style.display = this.totalItems > 0 ? 'flex' : 'none';
        }
    },

    renderCartDropdown() {
        const container = document.getElementById('cartDropdown');
        if (!container) return;

        if (this.items.length === 0) {
            container.innerHTML = `
                <div class="cart-empty">
                    <i class="fas fa-shopping-bag"></i>
                    <p>Корзина пуста</p>
                    <a href="/catalog/" class="btn-primary btn-sm">Перейти в каталог</a>
                </div>
            `;
            return;
        }

        let html = '<div class="cart-items">';
        this.items.forEach((item, index) => {
            const photoHtml = item.photoURL
                ? `<img src="${item.photoURL}" alt="${item.name}" loading="lazy">`
                : `<i class="fas fa-cube"></i>`;
            html += `
                <div class="cart-item">
                    <div class="cart-item-photo">${photoHtml}</div>
                    <div class="cart-item-info">
                        <div class="cart-item-name">${this.escapeHtml(item.name)}</div>
                        <div class="cart-item-details">
                            <span>${this.escapeHtml(item.size)}</span>
                            <span>•</span>
                            <span>${this.escapeHtml(item.material)}</span>
                        </div>
                        <div class="cart-item-price">${item.price.toLocaleString('ru-RU')} ₽</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="cart-qty-btn" onclick="Cart.updateQuantity(${index}, ${item.quantity - 1})">−</button>
                        <span class="cart-qty">${item.quantity}</span>
                        <button class="cart-qty-btn" onclick="Cart.updateQuantity(${index}, ${item.quantity + 1})">+</button>
                        <button class="cart-remove-btn" onclick="Cart.removeItem(${index})">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        html += `
            <div class="cart-footer">
                <div class="cart-total">
                    <span>Итого:</span>
                    <span class="cart-total-price">${this.totalPrice.toLocaleString('ru-RU')} ₽</span>
                </div>
                <div class="cart-actions">
                    <button class="btn-outline-dark btn-sm" onclick="Cart.clearCart()">Очистить</button>
                    <button class="btn-primary btn-sm" onclick="Cart.checkout()">Оформить заказ</button>
                </div>
            </div>
        `;

        container.innerHTML = html;
    },

    checkout() {
        if (this.items.length === 0) return;

        // Открываем модалку оформления заказа с данными корзины
        if (typeof openOrderModal === 'function') {
            // Передаём данные корзины в модалку
            const orderData = {
                items: this.items,
                total: this.totalPrice
            };
            openOrderModal(orderData);
        } else {
            // Если модалка не доступна, показываем alert
            alert('Оформление заказа:\n\n' + this.items.map(item =>
                `${item.name} (${item.size}, ${item.material}) × ${item.quantity} = ${(item.price * item.quantity).toLocaleString('ru-RU')} ₽`
            ).join('\n') + '\n\nИтого: ' + this.totalPrice.toLocaleString('ru-RU') + ' ₽');
        }
    },

    showNotification(message) {
        const existing = document.querySelector('.cart-notification');
        if (existing) existing.remove();

        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        notification.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    setupEventListeners() {

        const toggle = document.querySelector('.cart-toggle');
        const dropdown = document.getElementById('cartDropdown');
        if (toggle && dropdown) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('open');
                if (dropdown.classList.contains('open')) {
                    this.renderCartDropdown();
                }
            });

            document.addEventListener('click', (e) => {
                if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                }
            });
        }


        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.add-to-cart-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const productId = btn.dataset.productId;
                const size = btn.dataset.size || 'стандартный';
                const material = btn.dataset.material || 'стандартный';
                // Ищем товар в глобальном PRODUCTS
                if (typeof PRODUCTS !== 'undefined') {
                    const product = PRODUCTS.find(p => String(p.id) === String(productId));
                    if (product) {
                        Cart.addItem(product, size, material);
                    }
                } else if (typeof loadProducts === 'function') {
                    loadProducts(function(products) {
                        const product = products.find(p => String(p.id) === String(productId));
                        if (product) {
                            Cart.addItem(product, size, material);
                        }
                    });
                }
            }
        });
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};


document.addEventListener('DOMContentLoaded', () => {
    Cart.init();
});


window.Cart = Cart;