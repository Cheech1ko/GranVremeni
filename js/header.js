(function() {
    'use strict';

    const CONFIG = {
        siteName:  'ГраньВремени.рф',
        phone:     '+79362200900',
        phoneDisplay: '+7 (936) 2200-900',
        tgLink:    'https://t.me/granvremenirf',
        waLink:    'https://wa.me/79362200900',
        maxLink:   'https://max.ru/u/f9LHodD0cOJ4fY0Yk90kATZwhCRAgNrlUY7v9LG-XOLpI5FXH233ff9Q-6A',
    };

    function getBasePath() {
        const path = window.location.pathname;
        if (path.includes('/catalog/') || path.includes('/product/') || path.includes('/cart/')) {
            return '../';
        }
        return '';
    }

    function img(name) {
        return getBasePath() + 'img/' + name;
    }

    function getPrefix() {
        const path = window.location.pathname;
        if (path.includes('/catalog/') || path.includes('/product/') || path.includes('/cart/')) {
            return '../';
        }
        return '';
    }

    function isActive(href) {
        const path = window.location.pathname;
        const prefix = getPrefix();
        if (href === prefix + 'index.html' || href === prefix) {
            return path === '/' || path.endsWith('/index.html');
        }
        return path.includes(href.replace(prefix, ''));
    }

    function navLink(href, label, extra) {
        const cls = 'nav-link' + (isActive(href) ? ' active' : '');
        return '<a href="' + href + '" class="' + cls + '"' + (extra || '') + '>' + label + '</a>';
    }

    function socialIcons(size) {
        size = size || 24;
        const icons = [
            { href: CONFIG.tgLink,  src: img('telegram.svg'), alt: 'Telegram',  title: 'Написать в Telegram'  },
            { href: CONFIG.waLink,  src: img('whatsapp.svg'), alt: 'WhatsApp',  title: 'Написать в WhatsApp'  },
            { href: CONFIG.maxLink, src: img('max.svg'),      alt: 'Max',       title: 'Написать в Max'       },
        ];
        return icons.map(s => {
            return '<a href="' + s.href + '" target="_blank" rel="noopener noreferrer" ' +
                   'class="social-icon" title="' + s.title + '" aria-label="' + s.alt + '">' +
                   '<img src="' + s.src + '" alt="' + s.alt + '" width="' + size + '" height="' + size + '" loading="lazy">' +
                   '</a>';
        }).join('');
    }

    function renderHeader() {
        const el = document.getElementById('site-header');
        if (!el) return;

        const isProductPage = window.location.pathname.includes('/product/');
        const ctaOnclick = isProductPage ? 'openOrderModal()' : 'openModal()';
        const ctaText = 'Оставить заявку';
        const prefix = getPrefix();

        const categories = [
            ['vertical', 'Вертикальные'],
            ['horizontal', 'Горизонтальные'],
            ['carved', 'Резные'],
            ['double', 'Двойные'],
            ['combined', 'Комбинированные'],
            ['cross', 'С крестом'],
            ['complex', 'Комплексы'],
            ['fence', 'Ограды'],
            ['socle', 'Цоколи'],
        ];

        const catalogDropdownHtml = categories.map(c => {
            return '<a href="' + prefix + 'catalog/?category=' + c[0] + '">' + c[1] + '</a>';
        }).join('');

        el.innerHTML = `
            <div class="header-wrapper">
                <div class="topbar">
                    <div class="container">
                        <div class="topbar-contacts">
                            <span><i class="far fa-clock" style="margin-right:6px;"></i> 8:00–20:00 ежедневно | без выходных</span>
                        </div>
                        <div class="topbar-right">
                            <a href="tel:${CONFIG.phone}" class="phone"><i class="fas fa-phone" style="margin-right:6px;"></i> ${CONFIG.phoneDisplay}</a>
                            <span><i class="fas fa-map-marker-alt" style="margin-right:6px;"></i> Работаем по всей Москве и МО</span>
                        </div>
                    </div>
                </div>

                <div class="header-inner">
                    <a href="${prefix}index.html" class="logo">
                        <div class="logo-icon">
                            <img src="${img('logo.svg')}" alt="ГраньВремени.рф" 
                                 style="width:100%;height:100%;object-fit:contain;display:block;" 
                                 onerror="this.style.display='none'; this.parentElement.textContent='ГВ'">
                        </div>
                        <div class="logo-text">
                            <div class="logo-name">${CONFIG.siteName}</div>
                        </div>
                    </a>

                    <nav class="desktop-nav">
                        ${navLink(prefix + 'index.html#about', 'О компании')}
                        ${navLink(prefix + 'catalog/', 'Каталог')}
                        ${navLink(prefix + 'index.html#faq', 'FAQ')}
                        ${navLink(prefix + 'index.html#gallery', 'Работы')}
                        ${navLink(prefix + 'index.html#contact', 'Контакты')}
                    </nav>

                    <div class="social-icons">${socialIcons(22)}</div>

                    <div class="header-cart-wrap" style="display:flex;align-items:center;gap:8px;">
                        <button class="header-cta desktop-cta" onclick="${ctaOnclick}" 
                                style="border:none;cursor:pointer;background:var(--gold);font-weight:700;font-size:14px;padding:10px 20px;border-radius:var(--radius);font-family:'Montserrat',sans-serif;color:var(--dark);transition:background .2s;">
                            ${ctaText}
                        </button>
                        <a href="/cart/cart.html" class="cart-toggle" style="position:relative;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:4px 8px;display:flex;align-items:center;gap:4px;font-family:'Montserrat',sans-serif;text-decoration:none;">
                            <i class="fas fa-shopping-bag"></i>
                            <span class="cart-badge">0</span>
                        </a>
                    </div>
                </div>

                <div class="mobile-bottom-nav" id="mobileBottomNav">
                    <div class="mobile-nav-item" id="catalogToggle" onclick="toggleCatalogDropdown()">
                        <i class="fas fa-th-list"></i>
                        <span>Каталог</span>
                    </div>
                    <div class="mobile-logo-divider">
                        <img src="${img('logo.svg')}" alt="ГраньВремени" width="80" height="40" loading="lazy" 
                             onerror="this.style.display='none'">
                    </div>
                    <div class="mobile-nav-item" id="menuToggle" onclick="toggleMenu()">
                        <i class="fas fa-bars"></i>
                        <span>Меню</span>
                    </div>

                    <div class="mobile-dropdown catalog-dropdown" id="catalogDropdown">
                        <div class="dropdown-header">Категории</div>
                        <a href="${prefix}catalog/" class="dropdown-all">Все товары</a>
                        ${catalogDropdownHtml}
                    </div>

                    <nav class="mobile-dropdown menu-dropdown" id="menuDropdown">
                        <div class="dropdown-header">Меню</div>
                        <a href="${prefix}index.html#about" onclick="closeAllDropdowns()">О компании</a>
                        <a href="${prefix}catalog/" onclick="closeAllDropdowns()">Каталог</a>
                        <a href="${prefix}index.html#faq" onclick="closeAllDropdowns()">FAQ</a>
                        <a href="${prefix}index.html#gallery" onclick="closeAllDropdowns()">Галерея работ</a>
                        <a href="${prefix}index.html#contact" onclick="closeAllDropdowns()">Контакты</a>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-social">
                            <a href="${CONFIG.tgLink}" target="_blank" rel="noopener noreferrer">
                                <img src="${img('telegram.svg')}" width="20" height="20" alt="Telegram"> Telegram
                            </a>
                            <a href="${CONFIG.waLink}" target="_blank" rel="noopener noreferrer">
                                <img src="${img('whatsapp.svg')}" width="20" height="20" alt="WhatsApp"> WhatsApp
                            </a>
                            <a href="${CONFIG.maxLink}" target="_blank" rel="noopener noreferrer">
                                <img src="${img('max.svg')}" width="20" height="20" alt="Max"> Max
                            </a>
                        </div>
                        <a href="tel:${CONFIG.phone}" class="dropdown-phone">
                            <i class="fas fa-phone"></i> ${CONFIG.phoneDisplay}
                        </a>
                        <button class="dropdown-cta" onclick="${ctaOnclick}; closeAllDropdowns();">
                            <i class="fas fa-pen"></i> ${ctaText}
                        </button>
                    </nav>
                </div>
            </div>
        `;
    }

    function renderFooter() {
        const el = document.getElementById('site-footer');
        if (!el) return;

        const prefix = getPrefix();
        const cats = [
            ['vertical',   'Вертикальные'],
            ['horizontal', 'Горизонтальные'],
            ['carved',     'Резные'],
            ['double',     'Двойные'],
            ['combined',   'Комбинированные'],
            ['cross',      'С крестом'],
            ['complex',    'Комплексы'],
            ['fence',      'Ограды'],
            ['socle',      'Цоколи'],
        ];

        el.innerHTML = `
            <div class="footer-inner">
                <div class="footer-brand">
                    <div class="logo" style="margin-bottom:14px">
                        <div class="logo-icon">
                            <img src="${img('logo.svg')}" alt="ГраньВремени.рф" 
                                 style="width:100%;height:100%;object-fit:contain;display:block;" 
                                 onerror="this.style.display='none'">
                        </div>
                        <div class="logo-text"><div class="logo-name">${CONFIG.siteName}</div></div>
                    </div>
                    <p>Проектирование, изготовление и установка памятников в Москве и МО с 2019 года.</p>
                    <div class="footer-social"></div>
                </div>

                <div class="footer-col">
                    <h4>Каталог</h4><ul>
                    ${cats.map(c => {
                        return '<li><a href="' + prefix + 'catalog/?category=' + c[0] + '">' + c[1] + '</a></li>';
                    }).join('')}
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Контакты</h4>
                    <ul>
                        <li><a href="tel:${CONFIG.phone}">${CONFIG.phoneDisplay}</a></li>
                        <li><a href="#" onclick="event.preventDefault();openEmailModal&&openEmailModal()">info@granvremeni.ru</a></li>
                    </ul>
                    <h4 style="margin-top:16px">Мессенджеры</h4>
                    <div class="footer-social">${socialIcons(30)}</div>
                </div>
            </div>

            <div class="footer-bottom">
                <span>${CONFIG.siteName} © 2019–${new Date().getFullYear()}</span>
                <a href="${prefix}privacy-policy.html" style="color:rgba(255,255,255,0.4);font-size:12px;">
                    Политика конфиденциальности
                </a>
            </div>
        `;
    }

    function setDropdownBottom(dropdown) {
        const nav = document.getElementById('mobileBottomNav');
        if (nav && dropdown) {
            const navH = nav.getBoundingClientRect().height;
            dropdown.style.bottom = navH + 'px';
        }
    }

    window.toggleCatalogDropdown = function() {
        const dropdown = document.getElementById('catalogDropdown');
        const menuDropdown = document.getElementById('menuDropdown');
        if (!dropdown) return;

        if (menuDropdown && menuDropdown.classList.contains('open')) {
            menuDropdown.classList.remove('open');
        }

        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) {
            setDropdownBottom(dropdown);
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    };

    window.toggleMenu = function() {
        const menuDropdown = document.getElementById('menuDropdown');
        const catalogDropdown = document.getElementById('catalogDropdown');
        if (!menuDropdown) return;

        if (catalogDropdown && catalogDropdown.classList.contains('open')) {
            catalogDropdown.classList.remove('open');
        }

        menuDropdown.classList.toggle('open');
        if (menuDropdown.classList.contains('open')) {
            setDropdownBottom(menuDropdown);
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    };

    window.closeAllDropdowns = function() {
        const catalogDropdown = document.getElementById('catalogDropdown');
        const menuDropdown = document.getElementById('menuDropdown');
        if (catalogDropdown) catalogDropdown.classList.remove('open');
        if (menuDropdown) menuDropdown.classList.remove('open');
        document.body.style.overflow = '';
    };

    document.addEventListener('click', function(e) {
        const catalogDropdown = document.getElementById('catalogDropdown');
        const menuDropdown = document.getElementById('menuDropdown');
        const catalogBtn = document.getElementById('catalogToggle');
        const menuBtn = document.getElementById('menuToggle');

        if (catalogDropdown && catalogDropdown.classList.contains('open')) {
            if (!catalogDropdown.contains(e.target) && (!catalogBtn || !catalogBtn.contains(e.target))) {
                catalogDropdown.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        if (menuDropdown && menuDropdown.classList.contains('open')) {
            if (!menuDropdown.contains(e.target) && (!menuBtn || !menuBtn.contains(e.target))) {
                menuDropdown.classList.remove('open');
                document.body.style.overflow = '';
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllDropdowns();
        }
    });

    // ============================================================
    // ========== 🍪 COOKIE NOTIFICATION ===========================
    // ============================================================
    (function initCookieNotice() {
        try {
            if (document.getElementById('cookieNotice')) return;
            if (localStorage.getItem('cookies_accepted') === 'true') return;

            const prefix = getBasePath();

            const notice = document.createElement('div');
            notice.id = 'cookieNotice';
            notice.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                max-width: 360px;
                width: 100%;
                background: #1a1a1a;
                color: #fff;
                padding: 16px 18px 18px;
                z-index: 9999;
                border-radius: 12px;
                box-shadow: 0 8px 40px rgba(0,0,0,0.4);
                border: 1px solid rgba(255,255,255,0.08);
                font-family: 'Open Sans', sans-serif;
                animation: cookieSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                transition: opacity 0.4s ease, transform 0.4s ease;
            `;

            if (!document.getElementById('cookieNoticeStyles')) {
                const style = document.createElement('style');
                style.id = 'cookieNoticeStyles';
                style.textContent = `
                    @keyframes cookieSlideIn {
                        from { opacity: 0; transform: translateY(30px) scale(0.95); }
                        to { opacity: 1; transform: translateY(0) scale(1); }
                    }
                    @keyframes cookieSlideOut {
                        from { opacity: 1; transform: translateY(0) scale(1); }
                        to { opacity: 0; transform: translateY(30px) scale(0.95); }
                    }
                `;
                document.head.appendChild(style);
            }

            notice.innerHTML = `
                <div style="display:flex;align-items:flex-start;gap:10px;">
                    <div style="flex-shrink:0;width:32px;height:32px;background:rgba(184,148,74,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:2px;">
                        <i class="fas fa-cookie-bite" style="color:#b8944a;font-size:14px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:3px;font-family:'Montserrat',sans-serif;">
                            🍪 Мы используем cookies
                        </div>
                        <p style="font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;margin:0 0 12px 0;">
                            Для улучшения работы сайта.
                            <a href="${prefix}cookies.html" target="_blank" style="color:#b8944a;font-weight:600;text-decoration:underline;">
                                Подробнее
                            </a>
                        </p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button id="cookieAcceptBtn" style="flex:1;min-width:70px;background:#b8944a;border:none;color:#1a1a1a;font-weight:700;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:12px;transition:background .2s;font-family:'Montserrat',sans-serif;">
                                Принять
                            </button>
                            <button id="cookieDeclineBtn" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);font-weight:600;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:12px;transition:background .2s;font-family:'Montserrat',sans-serif;">
                                Отклонить
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(notice);

            document.getElementById('cookieAcceptBtn').addEventListener('click', function() {
                try {
                    localStorage.setItem('cookies_accepted', 'true');
                } catch(e) {}
                const el = document.getElementById('cookieNotice');
                if (el) {
                    el.style.animation = 'cookieSlideOut 0.4s ease forwards';
                    setTimeout(function() {
                        el.style.display = 'none';
                    }, 400);
                }
            });

            document.getElementById('cookieDeclineBtn').addEventListener('click', function() {
                const el = document.getElementById('cookieNotice');
                if (el) {
                    el.style.animation = 'cookieSlideOut 0.4s ease forwards';
                    setTimeout(function() {
                        el.style.display = 'none';
                    }, 400);
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const el = document.getElementById('cookieNotice');
                    if (el && el.style.display !== 'none') {
                        el.style.animation = 'cookieSlideOut 0.4s ease forwards';
                        setTimeout(function() {
                            el.style.display = 'none';
                        }, 400);
                    }
                }
            });

        } catch(e) {
            // Если что-то пошло не так — просто не показываем уведомление
        }
    })();

    // ============================================================
    // ========== ГЛОБАЛЬНЫЕ ФУНКЦИИ ДЛЯ МОДАЛОК ==================
    // ============================================================
    
    window.openModal = function() {
        const overlay = document.getElementById('modalOverlay') || document.getElementById('orderOverlay');
        if (overlay) {
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function() {
        const overlay = document.getElementById('modalOverlay');
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    window.closeModalOutside = function(event) {
        if (event.target === document.getElementById('modalOverlay')) closeModal();
    };

    window.openOrderModal = function() {
        const overlay = document.getElementById('orderOverlay');
        if (overlay) {
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        } else {
            openModal();
        }
    };

    window.closeOrderModal = function() {
        const overlay = document.getElementById('orderOverlay');
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    window.closeOrderModalOutside = function(event) {
        if (event.target === document.getElementById('orderOverlay')) {
            closeOrderModal();
        }
    };

    window.openPhoneModal = function() {
        const overlay = document.getElementById('phoneModalOverlay');
        if (overlay) {
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        } else {
            window.location.href = 'tel:+79362200900';
        }
    };

    window.closePhoneModal = function() {
        const overlay = document.getElementById('phoneModalOverlay');
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    window.closePhoneModalOutside = function(event) {
        if (event.target === document.getElementById('phoneModalOverlay')) closePhoneModal();
    };

    window.openEmailModal = function() {
        const overlay = document.getElementById('emailModalOverlay');
        if (overlay) {
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeEmailModal = function() {
        const overlay = document.getElementById('emailModalOverlay');
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    window.closeEmailModalOutside = function(event) {
        if (event.target === document.getElementById('emailModalOverlay')) closeEmailModal();
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('modalOverlay')?.classList.contains('open')) closeModal();
            if (document.getElementById('orderOverlay')?.classList.contains('open')) closeOrderModal();
            if (document.getElementById('phoneModalOverlay')?.classList.contains('open')) closePhoneModal();
            if (document.getElementById('emailModalOverlay')?.classList.contains('open')) closeEmailModal();
        }
    });

    // CSS для хедера и футера
    const css = `
    .header-wrapper {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--dark);
    }
    .header-wrapper .topbar {
        background: var(--dark);
        color: rgba(255,255,255,0.6);
        font-size: clamp(11px, 0.8vw, 13px);
        padding: clamp(4px, 0.5vh, 8px) 0;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .header-wrapper .header-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: clamp(8px, 1vh, 14px) clamp(12px, 2vw, 20px);
        max-width: 1200px;
        margin: 0 auto;
        gap: clamp(8px, 1.5vw, 16px);
        flex-wrap: nowrap;
        min-height: clamp(50px, 7vh, 70px);
        background: var(--dark);
    }
    .social-icons {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: 12px;
    }
    .social-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        overflow: hidden;
        transition: transform .2s, opacity .2s;
        background: rgba(255,255,255,0.06);
    }
    .social-icon:hover {
        background: var(--gold);
        transform: scale(1.08);
    }
    .social-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    .header-cta {
        background: var(--gold);
        color: var(--dark);
        font-weight: 700;
        font-size: 14px;
        padding: 10px 20px;
        border-radius: var(--radius);
        white-space: nowrap;
        transition: background .2s;
        font-family: 'Montserrat', sans-serif;
        border: none;
        cursor: pointer;
    }
    .header-cta:hover {
        background: var(--gold-light);
    }
    .desktop-nav {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .desktop-nav .nav-link {
        color: rgba(255,255,255,0.8);
        font-size: 14px;
        font-weight: 600;
        padding: 8px 14px;
        border-radius: 4px;
        transition: background .2s, color .2s;
        white-space: nowrap;
        font-family: 'Montserrat', sans-serif;
        text-decoration: none;
    }
    .desktop-nav .nav-link:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
    }
    .desktop-nav .nav-link.active {
        color: var(--gold-light);
    }
    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: #fff;
        flex-shrink: 0;
    }
    .logo-icon {
        width: 100px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }
    .logo-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    .logo-name {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: .5px;
        font-family: 'Montserrat', sans-serif;
        white-space: nowrap;
    }
    .mobile-bottom-nav {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999;
        background: var(--dark);
        border-top: 1px solid rgba(255,255,255,0.08);
        justify-content: space-around;
        align-items: center;
        padding: 6px 0 env(safe-area-inset-bottom, 8px);
        box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
    }
    .mobile-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        padding: 4px 20px;
        cursor: pointer;
        color: rgba(255,255,255,0.6);
        transition: color .2s;
        font-size: 10px;
        font-weight: 600;
        font-family: 'Montserrat', sans-serif;
        border: none;
        background: none;
        flex: 1;
    }
    .mobile-nav-item i {
        font-size: 22px;
        color: rgba(255,255,255,0.6);
        transition: color .2s;
    }
    .mobile-nav-item:hover {
        color: #fff;
    }
    .mobile-nav-item:hover i {
        color: var(--gold-light);
    }
    .mobile-nav-item span {
        font-size: 10px;
        letter-spacing: 0.3px;
    }
    .mobile-logo-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        flex-shrink: 0;
        pointer-events: none;
    }
    .mobile-logo-divider img {
        display: block;
        width: 80px;
        height: 40px;
        object-fit: contain;
        opacity: 0.8;
    }
    .mobile-dropdown {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--dark);
        padding: 0 0 20px;
        border-radius: 16px 16px 0 0;
        box-shadow: 0 -8px 30px rgba(0,0,0,0.5);
        z-index: 998;
        flex-direction: column;
        max-height: 65vh;
        overflow-y: auto;
        animation: slideUp .3s ease;
    }
    .mobile-dropdown.open {
        display: flex;
    }
    @keyframes slideUp {
        from { transform: translateY(100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .dropdown-header {
        padding: 16px 20px 12px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: rgba(255,255,255,0.4);
        border-bottom: 1px solid rgba(255,255,255,0.06);
        font-family: 'Montserrat', sans-serif;
    }
    .mobile-dropdown a {
        display: block;
        color: rgba(255,255,255,0.85);
        padding: 12px 20px;
        font-size: 15px;
        font-weight: 500;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        text-decoration: none;
        transition: background .2s;
    }
    .mobile-dropdown a:hover {
        background: rgba(255,255,255,0.06);
    }
    .mobile-dropdown a:last-child {
        border-bottom: none;
    }
    .dropdown-all {
        font-weight: 700 !important;
        color: var(--gold-light) !important;
    }
    .dropdown-divider {
        height: 1px;
        background: rgba(255,255,255,0.08);
        margin: 6px 20px;
    }
    .dropdown-social {
        display: flex;
        gap: 16px;
        padding: 12px 20px;
        flex-wrap: wrap;
    }
    .dropdown-social a {
        color: rgba(255,255,255,0.7);
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        padding: 4px 0;
        border-bottom: none !important;
        transition: color .2s;
    }
    .dropdown-social a:hover {
        color: #fff;
    }
    .dropdown-social img {
        border-radius: 50%;
        flex-shrink: 0;
    }
    .dropdown-phone {
        color: var(--gold-light) !important;
        font-size: 17px !important;
        font-weight: 700 !important;
        padding: 12px 20px !important;
        border-top: 1px solid rgba(255,255,255,0.08);
        margin-top: 4px;
    }
    .dropdown-phone i {
        margin-right: 10px;
    }
    .dropdown-cta {
        margin: 12px 20px 4px;
        background: var(--gold);
        color: var(--dark);
        font-weight: 700;
        font-size: 15px;
        padding: 14px;
        border-radius: var(--radius);
        border: none;
        cursor: pointer;
        font-family: 'Montserrat', sans-serif;
        transition: background .2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .dropdown-cta:hover {
        background: var(--gold-light);
    }
    .footer-social {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .header-cart-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }
    .cart-toggle {
        position: relative;
        background: none;
        border: none;
        color: #fff;
        font-size: 22px;
        cursor: pointer;
        padding: 4px 8px;
        transition: color .2s;
        display: flex;
        align-items: center;
        gap: 4px;
        font-family: 'Montserrat', sans-serif;
    }
    .cart-toggle:hover {
        color: var(--gold-light);
    }
    .cart-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: var(--gold);
        color: var(--dark);
        font-size: 10px;
        font-weight: 800;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-family: 'Montserrat', sans-serif;
    }
    .cart-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 360px;
        max-height: 480px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        overflow: hidden;
        z-index: 1000;
        flex-direction: column;
    }
    .cart-dropdown.open {
        display: flex;
    }
    .cart-dropdown .cart-empty {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-light);
    }
    .cart-dropdown .cart-empty i {
        font-size: 48px;
        color: var(--border);
        margin-bottom: 12px;
        display: block;
    }
    .cart-dropdown .cart-empty p {
        margin-bottom: 16px;
    }
    .cart-dropdown .cart-empty .btn-primary {
        background: var(--gold);
        color: var(--dark);
        font-weight: 700;
        padding: 8px 20px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-family: 'Montserrat', sans-serif;
        font-size: 13px;
        text-decoration: none;
        display: inline-block;
    }
    .cart-dropdown .cart-empty .btn-primary:hover {
        background: var(--gold-light);
    }

    @media (max-width: 1150px) {
        .header-cta { display: none !important; }
        .social-icons { gap: 3px; }
        .social-icon { width: 28px; height: 28px; }
        .desktop-nav .nav-link { font-size: 12px; padding: 4px 8px; }
    }

    @media (max-width: 900px) {
        .topbar .container { justify-content: center; text-align: center; }
        .topbar-right { display: none; }
        .topbar-contacts { justify-content: center; }
    }

    @media (max-width: 768px) {
        .header-wrapper .header-inner {
            display: flex;
            align-items: center;
            justify-content: center !important;
            padding: 8px 16px;
            min-height: 56px;
            gap: 0;
        }
        .logo {
            margin: 0 auto;
            gap: 8px;
            justify-content: center;
            flex: 1;
        }
        .logo-icon {
            width: 70px;
            height: 35px;
            flex-shrink: 0;
        }
        .logo-name {
            font-size: 14px !important;
            white-space: nowrap;
        }
        .logo-text .logo-sub {
            display: none;
        }
        .desktop-nav { display: none !important; }
        .social-icons { display: none !important; }
        .desktop-cta { display: none !important; }
        .mobile-bottom-nav { display: flex !important; }
        .mobile-dropdown { bottom: 64px; }
        body { padding-bottom: 64px; }
        .header-cart-wrap .cart-dropdown {
            right: -20px;
            width: 300px;
        }
        #cookieNotice {
            bottom: 76px !important;
            right: 12px !important;
            left: 12px !important;
            max-width: none !important;
            width: auto !important;
            padding: 14px 16px 16px !important;
            border-radius: 12px !important;
        }
        #cookieNotice .fa-cookie-bite {
            display: none !important;
        }
        #cookieNotice > div {
            gap: 8px !important;
        }
        #cookieNotice p {
            font-size: 12px !important;
            margin-bottom: 10px !important;
        }
        #cookieAcceptBtn, #cookieDeclineBtn {
            font-size: 12px !important;
            padding: 6px 14px !important;
        }
    }

    @media (max-width: 480px) {
        .header-wrapper .header-inner {
            padding: 6px 12px;
            min-height: 48px;
        }
        .logo-icon { width: 55px; height: 28px; }
        .logo-name { font-size: 12px !important; }
        .logo { gap: 6px; }
        .mobile-bottom-nav {
            padding: 4px 0 env(safe-area-inset-bottom, 6px);
        }
        .mobile-nav-item { padding: 2px 8px; }
        .mobile-nav-item i { font-size: 20px; }
        .mobile-nav-item span { font-size: 9px; }
        .mobile-logo-divider img { width: 55px; height: 28px; }
        .mobile-dropdown { bottom: 56px; }
        body { padding-bottom: 56px; }
        .header-cart-wrap .cart-dropdown {
            right: -40px;
            width: 280px;
        }
        #cookieNotice {
            bottom: 68px !important;
            right: 8px !important;
            left: 8px !important;
            padding: 12px 14px 14px !important;
        }
        #cookieNotice p {
            font-size: 11px !important;
        }
        #cookieAcceptBtn, #cookieDeclineBtn {
            font-size: 11px !important;
            padding: 5px 12px !important;
        }
    }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            renderHeader();
            renderFooter();
        });
    } else {
        renderHeader();
        renderFooter();
    }

})();