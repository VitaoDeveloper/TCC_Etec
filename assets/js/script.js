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
    // Search Box Focus Effect
    // ========================================
    const searchBox = document.querySelector('.search-box input');
    
    if (searchBox) {
        searchBox.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        searchBox.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    }
    
    // ========================================
    // Product Card Hover Effects
    // ========================================
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('hovered');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hovered');
        });
    });
    
    // ========================================
    // Quick View Button
    // ========================================
    const quickViewBtns = document.querySelectorAll('.product-actions .action-btn:nth-child(3)');
    
    quickViewBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            // Aqui você pode adicionar a lógica para abrir modal de visualização rápida
            console.log('Quick View acionado');
        });
    });
    
    // ========================================
    // Add to Cart Button
    // ========================================
    const addToCartBtns = document.querySelectorAll('.btn-add-cart');
    
    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Efeito visual de feedback
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
            this.style.background = 'var(--color-primary)';
            this.style.color = 'var(--color-black)';
            
            setTimeout(() => {
                this.innerHTML = originalText;
                this.style.background = '';
                this.style.color = '';
            }, 2000);
        });
    });
    
    // ========================================
    // Wishlist Button
    // ========================================
    const wishlistBtns = document.querySelectorAll('.product-actions .action-btn:nth-child(1), .action-btn[title*="favorito"]');
    
    wishlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const icon = this.querySelector('i');
            
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = '#f44336';
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                icon.style.color = '';
            }
        });
    });
    
    // ========================================
    // Filter Toggle (Mobile)
    // ========================================
    const filterToggle = document.querySelector('.filter-toggle');
    const productsSidebar = document.querySelector('.products-sidebar');
    
    if (filterToggle && productsSidebar) {
        filterToggle.addEventListener('click', function() {
            productsSidebar.classList.toggle('active');
        });
    }
    
    // ========================================
    // View Toggle (Grid/List)
    // ========================================
    const viewBtns = document.querySelectorAll('.view-btn');
    const productsGrid = document.querySelector('.products-grid');
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            viewBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            if (this.querySelector('.fa-list')) {
                productsGrid.classList.add('list-view');
            } else {
                productsGrid.classList.remove('list-view');
            }
        });
    });
    
    // ========================================
    // Newsletter Form
    // ========================================
    const newsletterForm = document.querySelector('.newsletter-form');
    
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            
            if (email) {
                alert('Obrigado por se cadastrar! Você receberá nossas ofertas em: ' + email);
                this.reset();
            }
        });
    }
    
    // ========================================
    // Sticky Header Effect
    // ========================================
    const mainHeader = document.querySelector('.main-header');
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 100) {
            mainHeader.classList.add('scrolled');
        } else {
            mainHeader.classList.remove('scrolled');
        }
        
        lastScroll = currentScroll;
    });
    
    // ========================================
    // Smooth Scroll for Anchor Links
    // ========================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // ========================================
    // Admin Sidebar Toggle
    // ========================================
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!adminSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                adminSidebar.classList.remove('active');
            }
        });
    }
    
    // ========================================
    // Admin Table Checkbox
    // ========================================
    const tableCheckAll = document.querySelector('.table-check-all');
    const tableCheckboxes = document.querySelectorAll('.table-checkbox');
    
    if (tableCheckAll) {
        tableCheckAll.addEventListener('change', function() {
            tableCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
    // ========================================
    // Image Placeholder Click
    // ========================================
    const placeholders = document.querySelectorAll('.placeholder');
    
    placeholders.forEach(placeholder => {
        placeholder.addEventListener('click', function() {
            // Aqui você pode adicionar lógica para upload de imagem
            console.log('Placeholder clicado - área reservada para upload de imagem');
        });
    });
    
    // ========================================
    // Toast Notifications (Simulation)
    // ========================================
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-info-circle"></i>
            <span>${message}</span>
        `;
        
        // Add styles dynamically
        toast.style.cssText = `
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            animation: slideUp 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // ========================================
    // Load More Products (Simulation)
    // ========================================
    const loadMoreBtn = document.querySelector('.load-more-btn');
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
            
            // Simulate loading
            setTimeout(() => {
                this.innerHTML = originalText;
                showToast('Mais produtos carregados!', 'success');
            }, 1500);
        });
    }
    
});

// ========================================
// Utility Functions
// ========================================

/**
 * Format currency value
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Scroll to element
 */
function scrollToElement(selector) {
    const element = document.querySelector(selector);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}
