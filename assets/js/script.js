/**
 * Royal Tech - JavaScript Principal
 * Site de E-commerce Premium
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Mobile Menu Drawer
    // ========================================
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    let navBackdrop = document.querySelector('.nav-backdrop');

    if (mobileMenuBtn && mainNav) {
        if (!navBackdrop) {
            navBackdrop = document.createElement('div');
            navBackdrop.className = 'nav-backdrop';
            document.body.appendChild(navBackdrop);
        }

        function toggleNav(open) {
            const isOpen = open !== undefined ? open : !mainNav.classList.contains('active');
            mainNav.classList.toggle('active', isOpen);
            navBackdrop.classList.toggle('active', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        mobileMenuBtn.addEventListener('click', function() { toggleNav(); });
        navBackdrop.addEventListener('click', function() { toggleNav(false); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mainNav.classList.contains('active')) toggleNav(false);
        });
    }
    
    const loggedFlag = document.body.getAttribute('data-logged-in') === '1';
    const basePath = document.body.getAttribute('data-base-path') || '';

    // ========================================
    // Wishlist Toggle — AJAX
    // ========================================
    document.querySelectorAll('.btn-wishlist').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!loggedFlag) return;
            const productId = this.dataset.productId;
            if (!productId) return;
            this.classList.add('btn-loading');
            fetch(basePath + 'pages/wishlist/toggle.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({product_id: productId})
            })
            .then(r => r.json())
            .then(data => {
                this.classList.remove('btn-loading');
                if (data.success) {
                    this.querySelector('i').className = data.active ? 'fas fa-heart' : 'far fa-heart';
                    const badge = document.querySelector('.wishlist-btn .cart-badge');
                    if (badge) {
                        badge.textContent = data.count;
                    } else if (data.count > 0) {
                        const wb = document.querySelector('.wishlist-btn');
                        if (wb) {
                            const span = document.createElement('span');
                            span.className = 'cart-badge';
                            span.textContent = data.count;
                            wb.appendChild(span);
                        }
                    }
                } else if (window.showToast) {
                    showToast(data.message, 'error');
                }
            }).catch(() => { this.classList.remove('btn-loading'); if (window.showToast) { showToast('Erro ao favoritar.', 'error'); } });
        });
    });

    // ========================================
    // Add to Cart Button — AJAX
    // ========================================
    const addToCartBtns = document.querySelectorAll('.btn-add-cart');

    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!loggedFlag) return;

            const card = this.closest('[data-product-id]');
            const productId = card ? card.getAttribute('data-product-id') : null;
            if (!productId) return;

            const originalText = this.innerHTML;
            this.classList.add('btn-loading');

            fetch(basePath + 'pages/cart/add.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({product_id: productId, quantity: 1})
            })
            .then(r => r.json())
            .then(data => {
                this.classList.remove('btn-loading');
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
                    this.style.background = 'var(--color-primary)';
                    this.style.color = 'var(--color-black)';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.background = '';
                        this.style.color = '';
                    }, 2000);
                    const badge = document.querySelector('.cart-badge');
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        const cartBtn = document.querySelector('.cart-btn');
                        if (cartBtn) {
                            const span = document.createElement('span');
                            span.className = 'cart-badge';
                            span.textContent = data.count;
                            cartBtn.appendChild(span);
                        }
                    }
                } else if (window.showToast) {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => { this.classList.remove('btn-loading'); if (window.showToast) { showToast('Erro ao adicionar ao carrinho.', 'error'); } });
        });
});

// ========================================
// Input Masks (CPF, CEP)
// ========================================
document.querySelectorAll('.cpf-mask').forEach(function(el) {
    el.addEventListener('input', function() {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
    });
});
document.querySelectorAll('.cep-mask').forEach(function(el) {
    el.addEventListener('input', function() {
        var v = this.value.replace(/\D/g, '').slice(0, 8);
        v = v.replace(/(\d{5})(\d)/, '$1-$2');
        this.value = v;
    });
});


    // ========================================
    // Require authentication for ecommerce actions
    // ========================================
    const protectedActions = document.querySelectorAll('.js-require-auth');

    protectedActions.forEach((btn) => {
        btn.addEventListener('click', function(e) {
            if (loggedFlag) return;

            e.preventDefault();
            const target = this.getAttribute('data-auth-target') || 'recurso';
            const loginUrl = basePath + 'pages/auth/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
            if (window.showToast) { showToast('Faça login para acessar ' + target + '.', 'info'); }
            setTimeout(function() { window.location.href = loginUrl; }, 1500);
        });
    });


    
    // ========================================
    // Admin Sidebar Toggle + Backdrop
    // ========================================
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    let sidebarBackdrop = document.querySelector('.admin-sidebar-backdrop');
    
    if (sidebarToggle && adminSidebar && !sidebarBackdrop) {
        sidebarBackdrop = document.createElement('div');
        sidebarBackdrop.className = 'admin-sidebar-backdrop';
        document.body.appendChild(sidebarBackdrop);
    }
    
    function toggleSidebar(open) {
        if (!adminSidebar || !sidebarBackdrop) return;
        const isOpen = open !== undefined ? open : !adminSidebar.classList.contains('active');
        adminSidebar.classList.toggle('active', isOpen);
        sidebarBackdrop.classList.toggle('active', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    // ========================================
    // Toast Notification
    // ========================================
    window.showToast = function(message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) return;
        var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i> ' + message;
        container.appendChild(toast);
        requestAnimationFrame(function() { toast.classList.add('show'); });
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.remove(); }, 400);
        }, 3500);
    };
    
    if (sidebarToggle && adminSidebar && sidebarBackdrop) {
        sidebarToggle.addEventListener('click', function() {
            toggleSidebar();
        });
        
        sidebarBackdrop.addEventListener('click', function() {
            toggleSidebar(false);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && adminSidebar.classList.contains('active')) {
                toggleSidebar(false);
            }
        });
    }
    
});


