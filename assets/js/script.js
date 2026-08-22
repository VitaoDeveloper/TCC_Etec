/**
 * Royal Tech - JavaScript Principal
 * Site de E-commerce Premium — Estilo Mercado Livre
 */

document.addEventListener('DOMContentLoaded', function() {

    var loggedFlag = document.body.getAttribute('data-logged-in') === '1';
    var basePath = document.body.getAttribute('data-base-path') || '';

    // ========================================
    // ML Carousel
    // ========================================
    var carousel = document.getElementById('ml-carousel');
    var carouselTrack = document.getElementById('ml-carousel-track');
    var prevBtn = document.getElementById('ml-carousel-prev');
    var nextBtn = document.getElementById('ml-carousel-next');
    var dotsContainer = document.getElementById('ml-carousel-dots');

    if (carousel && carouselTrack) {
        var slides = carouselTrack.querySelectorAll('.ml-carousel-slide');
        var currentSlide = 0;
        var totalSlides = slides.length;
        var autoPlayInterval = null;

        function goToSlide(index) {
            if (index < 0) index = totalSlides - 1;
            if (index >= totalSlides) index = 0;
            currentSlide = index;
            carouselTrack.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';

            if (dotsContainer) {
                var dots = dotsContainer.querySelectorAll('.ml-carousel-dot');
                dots.forEach(function(dot, i) {
                    dot.classList.toggle('active', i === currentSlide);
                });
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                goToSlide(currentSlide - 1);
                resetAutoPlay();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                goToSlide(currentSlide + 1);
                resetAutoPlay();
            });
        }

        if (dotsContainer) {
            dotsContainer.querySelectorAll('.ml-carousel-dot').forEach(function(dot) {
                dot.addEventListener('click', function() {
                    goToSlide(parseInt(this.getAttribute('data-index')));
                    resetAutoPlay();
                });
            });
        }

        function startAutoPlay() {
            autoPlayInterval = setInterval(function() {
                goToSlide(currentSlide + 1);
            }, 5000);
        }

        function resetAutoPlay() {
            clearInterval(autoPlayInterval);
            startAutoPlay();
        }

        if (totalSlides > 1) {
            startAutoPlay();

            carousel.addEventListener('mouseenter', function() {
                clearInterval(autoPlayInterval);
            });

            carousel.addEventListener('mouseleave', function() {
                startAutoPlay();
            });
        }

        // Touch swipe support
        var touchStartX = 0;
        var touchEndX = 0;

        carousel.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        carousel.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) {
                    goToSlide(currentSlide + 1);
                } else {
                    goToSlide(currentSlide - 1);
                }
                resetAutoPlay();
            }
        }, { passive: true });
    }

    // ========================================
    // Wishlist Toggle — AJAX
    // ========================================
    document.querySelectorAll('.ml-card-wishlist, .btn-wishlist').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!loggedFlag) {
                if (!this.classList.contains('js-require-auth')) {
                    var wishlistLoginUrl = basePath + 'pages/auth/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
                    if (window.showToast) showToast('Faça login para favoritar produtos.', 'info');
                    setTimeout(function() { window.location.href = wishlistLoginUrl; }, 1500);
                }
                return;
            }
            var productId = this.dataset.productId;
            if (!productId) return;
            var self = this;
            self.classList.add('btn-loading');
            fetch(basePath + 'pages/wishlist/toggle.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({product_id: productId})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                self.classList.remove('btn-loading');
                if (data.success) {
                    var icon = self.querySelector('i');
                    if (icon) {
                        icon.className = data.active ? 'fas fa-heart' : 'far fa-heart';
                    }
                    self.classList.toggle('is-active', data.active);

                    var badge = document.querySelector('.ml-wishlist-link .ml-badge, .wishlist-btn .cart-badge');
                    if (badge) {
                        badge.textContent = data.count;
                        if (data.count <= 0) badge.remove();
                    } else if (data.count > 0) {
                        var wb = document.querySelector('.ml-wishlist-link, .wishlist-btn');
                        if (wb) {
                            var span = document.createElement('span');
                            span.className = 'ml-badge';
                            span.textContent = data.count;
                            wb.appendChild(span);
                        }
                    }
                } else if (window.showToast) {
                    showToast(data.message, 'error');
                }
            }).catch(function() {
                self.classList.remove('btn-loading');
                if (window.showToast) showToast('Erro ao favoritar.', 'error');
            });
        });
    });

    // ========================================
    // Add to Cart Button — AJAX
    // ========================================
    document.querySelectorAll('.btn-add-cart').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!loggedFlag) {
                if (!this.classList.contains('js-require-auth')) {
                    var cartLoginUrl = basePath + 'pages/auth/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
                    if (window.showToast) showToast('Faça login para adicionar ao carrinho.', 'info');
                    setTimeout(function() { window.location.href = cartLoginUrl; }, 1500);
                }
                return;
            }

            var card = this.closest('[data-product-id]');
            var productId = card ? card.getAttribute('data-product-id') : null;
            if (!productId) return;

            var originalText = this.innerHTML;
            var self = this;
            self.classList.add('btn-loading');

            fetch(basePath + 'pages/cart/add.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({product_id: productId, quantity: (parseInt((document.getElementById('pdp-qty') || {}).value, 10) || 1)})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                self.classList.remove('btn-loading');
                if (data.success) {
                    self.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
                    self.style.background = 'var(--ml-accent, #d4af37)';
                    self.style.color = 'var(--ml-bg, #1a1a1a)';
                    setTimeout(function() {
                        self.innerHTML = originalText;
                        self.style.background = '';
                        self.style.color = '';
                    }, 2000);
                    var badge = document.querySelector('.ml-cart-link .ml-badge, .cart-btn .cart-badge');
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        var cartBtn = document.querySelector('.ml-cart-link, .cart-btn');
                        if (cartBtn) {
                            var span = document.createElement('span');
                            span.className = 'ml-badge';
                            span.textContent = data.count;
                            cartBtn.appendChild(span);
                        }
                    }
                } else if (window.showToast) {
                    showToast(data.message, 'error');
                }
            })
            .catch(function() {
                self.classList.remove('btn-loading');
                if (window.showToast) showToast('Erro ao adicionar ao carrinho.', 'error');
            });
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
    document.querySelectorAll('.js-require-auth').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (loggedFlag) return;

            e.preventDefault();
            var target = this.getAttribute('data-auth-target') || 'recurso';
            var loginUrl = basePath + 'pages/auth/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
            if (window.showToast) showToast('Faça login para acessar ' + target + '.', 'info');
            setTimeout(function() { window.location.href = loginUrl; }, 1500);
        });
    });

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

    // ========================================
    // Admin Sidebar Toggle + Backdrop
    // ========================================
    var sidebarToggle = document.querySelector('.sidebar-toggle');
    var adminSidebar = document.querySelector('.admin-sidebar');
    var sidebarBackdrop = document.querySelector('.admin-sidebar-backdrop');

    if (sidebarToggle && adminSidebar && !sidebarBackdrop) {
        sidebarBackdrop = document.createElement('div');
        sidebarBackdrop.className = 'admin-sidebar-backdrop';
        document.body.appendChild(sidebarBackdrop);
    }

    function toggleSidebar(open) {
        if (!adminSidebar || !sidebarBackdrop) return;
        var isOpen = open !== undefined ? open : !adminSidebar.classList.contains('active');
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
