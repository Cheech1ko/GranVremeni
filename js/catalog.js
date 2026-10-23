const Catalog = {
    products: [],
    currentCategory: 'all',
    currentPage: 1,
    itemsPerPage: 20,
    filteredProducts: [],
    currentView: 'grid',
    priceMin: 0,
    priceMax: Infinity,
    sortBy: 'default',
    searchQuery: '',

    init() {
        this.loadProducts();
        this.setupFilters();
        this.setupSort();
        this.setupViewToggle();
        this.setupSearch();
        this.restoreStateFromURL();
    },

    loadProducts() {
        const self = this;

        if (typeof loadProducts === 'function') {
            loadProducts(function(products) {
                self.products = products.filter(function(p) { return !p.hidden; });
                self.applyFilters();
            });
        } else if (typeof PRODUCTS !== 'undefined') {
            this.products = PRODUCTS.filter(function(p) { return !p.hidden; });
            this.applyFilters();
        } else {
            fetch('/products.json')
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Не удалось загрузить products.json');
                    }
                    return response.json();
                })
                .then(function(data) {
                    self.products = data.filter(function(p) { return !p.hidden; });
                    self.applyFilters();
                })
                .catch(function(error) {
                    console.error('Ошибка загрузки товаров:', error);
                    self.products = [];
                    self.applyFilters();
                });
        }
    },

    // ============================================================
    //  ГЛАВНОЕ ИСПРАВЛЕНИЕ — ПРОВЕРКА КАТЕГОРИЙ
    // ============================================================
    productHasCategory(product, categoryKey) {
        if (!product) return false;
        // Если есть categories (массив) — проверяем по нему
        if (product.categories && Array.isArray(product.categories)) {
            return product.categories.includes(categoryKey);
        }
        // Если есть category (строка) — проверяем по нему
        if (product.category) {
            return product.category === categoryKey;
        }
        return false;
    },

    applyFilters() {
        let result = this.products;

        if (this.currentCategory !== 'all') {
            const self = this;
            result = result.filter(function(p) {
                return self.productHasCategory(p, self.currentCategory);
            });
        }

        result = result.filter(function(p) {
            const price = p.price || 0;
            return price >= this.priceMin && price <= this.priceMax;
        }, this);

        if (this.searchQuery) {
            const q = this.searchQuery.trim().toLowerCase();
            if (q) {
                result = result.filter(function(p) {
                    const nameMatch = (p.name || '').toLowerCase().includes(q);
                    const skuMatch = (p.sku || '').toLowerCase().includes(q);
                    return nameMatch || skuMatch;
                });
            }
        }

        this.filteredProducts = result;
        this.updateCounters();
        this.applySort();
        this.currentPage = 1;
        this.render();
    },

    applySort() {
        switch (this.sortBy) {
            case 'price_asc':
                this.filteredProducts.sort(function(a, b) {
                    return (a.price || 0) - (b.price || 0);
                });
                break;
            case 'price_desc':
                this.filteredProducts.sort(function(a, b) {
                    return (b.price || 0) - (a.price || 0);
                });
                break;
            case 'name_asc':
                this.filteredProducts.sort(function(a, b) {
                    return a.name.localeCompare(b.name);
                });
                break;
            default:
                this.filteredProducts.sort(function(a, b) {
                    return (a.sortOrder || 0) - (b.sortOrder || 0);
                });
                break;
        }
    },

    render() {
        const grid = document.getElementById('productGrid');
        const count = document.getElementById('totalCount');
        const pagination = document.getElementById('pagination');

        if (!grid) return;

        const total = this.filteredProducts.length;
        if (count) {
            count.textContent = 'Найдено ' + total + ' товаров';
        }

        const totalPages = Math.ceil(total / this.itemsPerPage);
        if (this.currentPage > totalPages) {
            this.currentPage = totalPages > 0 ? totalPages : 1;
        }
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const paginated = this.filteredProducts.slice(start, start + this.itemsPerPage);

        if (paginated.length === 0) {
            grid.innerHTML = `
                <div class="catalog-empty">
                    <i class="fas fa-box-open"></i>
                    <p>По вашим фильтрам ничего не найдено</p>
                    <button onclick="Catalog.clearAllFilters()" style="margin-top:12px; padding:8px 20px; background:var(--gold); border:none; border-radius:6px; font-weight:700; cursor:pointer;">Сбросить фильтры</button>
                </div>
            `;
        } else {
            const viewClass = this.currentView === 'list' ? 'list-view' : '';
            grid.className = 'catalog-grid ' + viewClass;

            let html = '';
            for (let i = 0; i < paginated.length; i++) {
                html += this.renderProductCard(paginated[i]);
            }
            grid.innerHTML = html;
        }

        if (pagination) {
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let pagHtml = '';
            for (let i = 1; i <= totalPages; i++) {
                const activeClass = i === this.currentPage ? 'active' : '';
                pagHtml += '<button class="' + activeClass + '" onclick="Catalog.goToPage(' + i + ')">' + i + '</button>';
            }
            pagination.innerHTML = pagHtml;
        }
    },

    renderProductCard(product) {
        const photoUrl = this.getPhotoUrl(product);
        const badgeHtml = product.badge ? '<span class="badge">' + this.escapeHtml(product.badge) + '</span>' : '';

        let imgHtml = '';
        if (photoUrl) {
            imgHtml = `
                <img src="${photoUrl}" alt="${this.escapeHtml(product.name)}" loading="lazy" 
                     onerror="this.style.display='none'; this.parentNode.querySelector('.no-image').style.display='flex'">
                <div class="no-image" style="display:none;"><i class="fas fa-${this.escapeHtml(product.imageIcon || 'mountain')}"></i></div>
            `;
        } else {
            const icon = product.imageIcon || 'mountain';
            imgHtml = '<div class="no-image"><i class="fas fa-' + this.escapeHtml(icon) + '"></i></div>';
        }

        let firstSize = 'Стандартный';
        try {
            if (product.sizes && product.sizes.length > 0) {
                if (typeof product.sizes[0] === 'object' && product.sizes[0] !== null) {
                    firstSize = product.sizes[0].label || 'Стандартный';
                } else {
                    firstSize = product.sizes[0] || 'Стандартный';
                }
            }
        } catch (e) {
            // Игнорируем ошибку
        }

        const links = (typeof getMessengerLinks === 'function') ?
            getMessengerLinks(product.name, product.price) :
            { tg: 'https://t.me/granvremenirf', wa: 'https://wa.me/79362200900' };

        return `
            <div class="catalog-item" onclick="window.location.href='../product/?id=${product.id}'">
                <div class="catalog-item-img">
                    ${imgHtml}
                    ${badgeHtml}
                </div>
                <div class="catalog-item-body">
                    <h3>${this.escapeHtml(product.name)}</h3>
                    <div class="dims">${this.escapeHtml(firstSize)}</div>
                    <div class="price"><span>От</span> ${product.price.toLocaleString('ru-RU')}<span> ₽</span></div>
                    <div class="item-actions">
                        <a href="../product/?id=${product.id}" class="btn-card btn-detail" onclick="event.stopPropagation();">
                            <i class="fas fa-calculator"></i> Рассчитать
                        </a>
                        <a href="${links.tg}" target="_blank" rel="noopener noreferrer" class="btn-icon btn-icon-tg" onclick="event.stopPropagation();" title="Telegram">
                            ${this.getTelegramSvg()}
                        </a>
                        <a href="${links.wa}" target="_blank" rel="noopener noreferrer" class="btn-icon btn-icon-wa" onclick="event.stopPropagation();" title="WhatsApp">
                            ${this.getWhatsappSvg()}
                        </a>
                    </div>
                </div>
            </div>
        `;
    },

    getPhotoUrl(product) {
        if (!product.photoURL) return null;
        if (product.photoURL.startsWith('http') || product.photoURL.startsWith('/')) {
            return product.photoURL;
        }
        let path = product.photoURL;
        if (path.startsWith('img/products/')) {
            path = path.replace('img/products/', 'img/products-img/');
        }
        return '../' + path;
    },

    getTelegramSvg() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';
    },

    getWhatsappSvg() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    updateCounters() {
        const self = this;
        const categories = ['all', 'vertical', 'horizontal', 'carved', 'double', 'combined', 'cross', 'complex', 'fence', 'socle', 'vases',
            'decor-acrylic', 'decor-composite', 'decor-bronze', 'decor-metal', 'decor-sculpture', 'decor-flowers',
            'design-photo', 'design-glass', 'design-angels', 'design-branches', 'design-military', 'design-animals',
            'design-crosses', 'design-moon', 'design-music', 'design-nature', 'design-frames', 'design-candles',
            'design-flowers', 'design-icons', 'design-churches', 'design-mosques'
        ];

        categories.forEach(function(cat) {
            const count = self.products.filter(function(p) {
                if (cat === 'all') return true;
                return self.productHasCategory(p, cat);
            }).length;

            const blockId = 'count' + cat.charAt(0).toUpperCase() + cat.slice(1);
            const blockEl = document.getElementById(blockId);
            if (blockEl) blockEl.textContent = count;

            const filterId = 'count' + cat.charAt(0).toUpperCase() + cat.slice(1) + '2';
            const filterEl = document.getElementById(filterId);
            if (filterEl) filterEl.textContent = count;
        });
    },

    setupFilters() {
        const self = this;

        document.querySelectorAll('.filter-btn[data-filter="category"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const value = this.dataset.value;
                self.currentCategory = value;
                document.querySelectorAll('.filter-btn[data-filter="category"]').forEach(function(b) {
                    b.classList.toggle('active', b.dataset.value === value);
                });
                document.querySelectorAll('.block-cat-link').forEach(function(link) {
                    link.classList.toggle('active', link.dataset.category === value);
                });
                const url = new URL(window.location);
                if (value === 'all') {
                    url.searchParams.delete('category');
                } else {
                    url.searchParams.set('category', value);
                }
                window.history.pushState({}, '', url);
                self.applyFilters();
            });
        });

        const priceMin = document.getElementById('priceMin');
        const priceMax = document.getElementById('priceMax');
        if (priceMin) {
            priceMin.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') self.applyPriceFilter();
            });
        }
        if (priceMax) {
            priceMax.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') self.applyPriceFilter();
            });
        }
        const applyBtn = document.querySelector('.filter-range button');
        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                self.applyPriceFilter();
            });
        }
    },

    applyPriceFilter() {
        const minInput = document.getElementById('priceMin');
        const maxInput = document.getElementById('priceMax');
        this.priceMin = parseInt(minInput.value) || 0;
        this.priceMax = parseInt(maxInput.value) || Infinity;
        this.applyFilters();
    },

    setupSort() {
        const self = this;
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                self.sortBy = this.value;
                self.applySort();
                self.render();
            });
        }
    },

    setupSearch() {
        const self = this;
        const input = document.getElementById('catalogSearch');
        if (!input) return;

        let debounceTimer = null;
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const value = this.value;
            debounceTimer = setTimeout(function() {
                self.applySearch(value);
            }, 300);
        });

        const clearBtn = document.getElementById('catalogSearchClear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                input.value = '';
                self.applySearch('');
                input.focus();
            });
        }
    },

    applySearch(value) {
        this.searchQuery = value || '';

        const clearBtn = document.getElementById('catalogSearchClear');
        if (clearBtn) {
            clearBtn.style.display = this.searchQuery ? 'flex' : 'none';
        }

        const url = new URL(window.location);
        if (this.searchQuery.trim()) {
            url.searchParams.set('q', this.searchQuery.trim());
        } else {
            url.searchParams.delete('q');
        }
        window.history.pushState({}, '', url);

        this.applyFilters();
    },

    setupViewToggle() {
        const self = this;
        document.querySelectorAll('.view button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const view = this.querySelector('.fa-th') ? 'grid' : 'list';
                self.setView(view);
            });
        });
    },

    setView(view) {
        this.currentView = view;
        document.querySelectorAll('.view button').forEach(function(btn) {
            btn.classList.remove('active');
        });
        if (view === 'grid') {
            const gridBtn = document.querySelector('.view button:first-child');
            if (gridBtn) gridBtn.classList.add('active');
        } else {
            const listBtn = document.querySelector('.view button:last-child');
            if (listBtn) listBtn.classList.add('active');
        }
        this.render();
    },

    goToPage(page) {
        this.currentPage = page;
        this.render();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    clearAllFilters() {
        this.currentCategory = 'all';
        document.querySelectorAll('.filter-btn[data-filter="category"]').forEach(function(btn) {
            btn.classList.toggle('active', btn.dataset.value === 'all');
        });
        document.querySelectorAll('.block-cat-link').forEach(function(link) {
            link.classList.toggle('active', link.dataset.category === 'all');
        });
        document.getElementById('priceMin').value = '';
        document.getElementById('priceMax').value = '';
        this.priceMin = 0;
        this.priceMax = Infinity;
        document.getElementById('sortSelect').value = 'default';
        this.sortBy = 'default';
        this.searchQuery = '';
        const searchInput = document.getElementById('catalogSearch');
        if (searchInput) searchInput.value = '';
        const clearBtn = document.getElementById('catalogSearchClear');
        if (clearBtn) clearBtn.style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('category');
        url.searchParams.delete('q');
        window.history.pushState({}, '', url);
        this.applyFilters();
    },

    restoreStateFromURL() {
        const params = new URLSearchParams(window.location.search);
        const catFromUrl = params.get('category');
        if (catFromUrl) {
            this.currentCategory = catFromUrl;
            document.querySelectorAll('.filter-btn[data-filter="category"]').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.value === catFromUrl);
            });
            document.querySelectorAll('.block-cat-link').forEach(function(link) {
                link.classList.toggle('active', link.dataset.category === catFromUrl);
            });
        }
        const qFromUrl = params.get('q');
        if (qFromUrl) {
            this.searchQuery = qFromUrl;
            const searchInput = document.getElementById('catalogSearch');
            if (searchInput) searchInput.value = qFromUrl;
            const clearBtn = document.getElementById('catalogSearchClear');
            if (clearBtn) clearBtn.style.display = 'flex';
        }
    }
};

document.addEventListener('DOMContentLoaded', function() {
    Catalog.init();
});

Catalog.toggleFilters = function() {
    const body = document.getElementById('filterBody');
    const toggle = document.getElementById('filterToggle');
    if (body) {
        body.classList.toggle('open');
        if (toggle) {
            toggle.classList.toggle('open');
            toggle.textContent = body.classList.contains('open') ? '▲' : '▼';
        }
    }
};

function initFiltersMobile() {
    const body = document.getElementById('filterBody');
    const toggle = document.getElementById('filterToggle');
    if (window.innerWidth > 992) {
        if (body) body.classList.add('open');
        if (toggle) {
            toggle.classList.add('open');
            toggle.textContent = '▲';
        }
    } else {
        if (body) body.classList.remove('open');
        if (toggle) {
            toggle.classList.remove('open');
            toggle.textContent = '▼';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initFiltersMobile();
});

window.addEventListener('resize', function() {
    initFiltersMobile();
});

window.Catalog = Catalog;