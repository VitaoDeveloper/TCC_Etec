# Anchored Summary — Royal Tech TCC Etec

## Progress
### Done
- **Stock**: `validateStock()` / `decrementStock()` in `includes/cart_functions.php`. Guard on cart add, update, order creation. Detail shows quantity / "Esgotado", card shows "Esgotado" badge + disabled button.
- **Shipping calculator**: CEP-based region pricing (CAP / Interior SP / other), free above R$ 500, PAC/Sedex selection.
- **Payment**: 4 methods (Pix 5% off, Boleto, Credit Card, Pay on Delivery) with fake Pix payload / boleto number.
- **Wishlist**: Table, functions, AJAX toggle, display page, header badge, product-card button.
- **Admin dashboard**: All stats from real DB queries (orders, revenue, customers, products, low-stock, recent orders, top products).
- **Product gallery**: Thumbnail navigation, swap main image via JS. Responsive CSS grid.
- **Responsive CSS**: Media queries at 1200 / 992 / 768px covering all major components.
- **Admin panels**: Contacts (list + delete), Newsletter (list + delete + CSV export), Order Detail view, Customers (search + delete), Settings (6 functional tabs: Loja, E-mails, Pagamentos, Frete, Segurança, Usuários).
- **Email sending**: `sendMail()` via `mail()`. Used in password reset and checkout confirmation.
- **Homepage dynamic**: Featured products, new arrivals, categories grid from DB.
- **Social links**: Header/footer read from `settings.json`.
- **Cart +/- buttons**: Quantity inc/dec with AJAX update.
- **Mobile drawer**: Slide-in nav with backdrop, Escape to close.
- **Category icons**: Slug→FontAwesome mapping.
- **Toast notifications**: `showToast(msg, type)` on `window`, slide-in animation.
- **Form validation styles**: `:valid`/`:invalid` states, `.form-error` utilities.
- **Image fallback**: `onerror` → placeholder SVG on cards and gallery.
- **Enhanced checkout summary**: Item count, delivery estimate, installment display.
- **Customer cancel order**: Cancel button on pending orders with stock restoration.
- **Enhanced breadcrumbs**: Support for `$breadcrumb_items` array (label + url) for full trail.
- **Input masks**: Generic `.cpf-mask` and `.cep-mask` JS handlers on `input`.
- **Commits pushed**: 15 commits to `origin/development`.

## Key Decisions
- Payment uses fake payloads, real gateway deferrable.
- Shipping uses region-based heuristic, no external API.
- Settings tabs use `?tab=` GET + PHP conditionals.
- Toast replaces `alert()` progressively.
- Cancel order restores stock in same transaction block.

## Relevant Files
- `includes/cart_functions.php`, `includes/wishlist_functions.php`, `includes/mail.php`
- `assets/css/style.css`, `assets/js/script.js`
- `components/header.php`, `components/footer.php`, `components/product-card.php`
- `pages/admin/settings.php`, `pages/admin/index.php`, `pages/admin/order-detail.php`, `pages/admin/contacts.php`, `pages/admin/newsletter.php`, `pages/admin/customers.php`
- `pages/auth/order-detail.php`, `pages/cart/checkout.php`, `pages/products/product-detail.php`
