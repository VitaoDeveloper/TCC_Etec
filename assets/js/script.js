/**
 * Royal Tech - JavaScript Principal
 * Site de E-commerce Premium
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // Mobile Menu Toggle
    // ========================================
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            this.classList.toggle('active');
        });
    }
    
    // ========================================
    // Add to Cart Button — AJAX
    // ========================================
    const addToCartBtns = document.querySelectorAll('.btn-add-cart');
    const loggedFlag = document.body.getAttribute('data-logged-in') === '1';
    const basePath = document.body.getAttribute('data-base-path') || '';

    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!loggedFlag) return;

            const card = this.closest('[data-product-id]');
            const productId = card ? card.getAttribute('data-product-id') : null;
            if (!productId) return;

            const originalText = this.innerHTML;

            fetch(basePath + 'pages/cart/add.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({product_id: productId, quantity: 1})
            })
            .then(r => r.json())
            .then(data => {
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
                } else {
                    alert(data.message);
                }
            })
            .catch(() => { alert('Erro ao adicionar ao carrinho.'); });
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
            alert('Para acessar ' + target + ', você precisa fazer login.');
            window.location.href = loginUrl;
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


