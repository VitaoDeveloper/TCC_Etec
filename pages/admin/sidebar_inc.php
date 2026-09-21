<?php
$activePage = $activePage ?? '';

require_once dirname(dirname(__DIR__)) . '/includes/config.php';

// Caminho base do site (ex.: /TCC_Etec/) calculado a partir do script atual,
// para links absolutos como "Voltar ao site" (mesma lógica usada em pages/404.php).
$adminSiteBase = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? 'index.php'))), '/\\') . '/';

$siteLogo = get_site_logo();

$navItems = [
    'dashboard'  => ['href' => 'index.php',     'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
    'products'   => ['href' => 'products.php',   'icon' => 'fa-box',           'label' => 'Produtos'],
    'categories' => ['href' => 'categories.php', 'icon' => 'fa-tags',          'label' => 'Categorias'],
    'package-sizes' => ['href' => 'package-sizes.php', 'icon' => 'fa-box-open', 'label' => 'Embalagens'],
    'coupons'    => ['href' => 'coupons.php',     'icon' => 'fa-tag',           'label' => 'Cupons'],
    'orders'     => ['href' => 'orders.php',     'icon' => 'fa-shopping-cart', 'label' => 'Pedidos'],
    'customers'  => ['href' => 'customers.php',  'icon' => 'fa-users',         'label' => 'Clientes'],
    'contacts'   => ['href' => 'contacts.php',   'icon' => 'fa-envelope',      'label' => 'Contatos'],
    'newsletter' => ['href' => 'newsletter.php', 'icon' => 'fa-newspaper',     'label' => 'Newsletter'],
    'banners'    => ['href' => 'banners.php',    'icon' => 'fa-images',        'label' => 'Banners'],
    'reports'    => ['href' => 'reports.php',    'icon' => 'fa-chart-bar',     'label' => 'Relatórios'],
    'settings'   => ['href' => 'settings.php',   'icon' => 'fa-cogs',          'label' => 'Configurações'],
];
?>
<aside class="admin-sidebar">
    <div class="admin-logo">
        <a href="index.php">
            <?php if ($siteLogo !== ''): ?>
            <img src="<?php echo htmlspecialchars($adminSiteBase . ltrim($siteLogo, '/'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-logo-img" alt="<?php echo htmlspecialchars(store_config('store_name') ?: 'Royal Tech', ENT_QUOTES, 'UTF-8'); ?>">
            <?php else: ?>
            <span class="logo-icon"><i class="fas fa-crown"></i></span>
            <span class="logo-text">Royal<span>Tech</span></span>
            <?php endif; ?>
        </a>
    </div>
    <nav class="admin-nav">
<?php foreach ($navItems as $key => $item): ?>
        <div class="admin-nav-item">
            <a href="<?php echo $item['href']; ?>"
               class="admin-nav-link<?php echo $activePage === $key ? ' active' : ''; ?>">
                <i class="fas <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        </div>
<?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-footer">
        <a href="<?php echo $adminSiteBase; ?>index.php" class="admin-nav-link admin-site-link" title="Voltar ao site">
            <i class="fas fa-store"></i>
            <span>Voltar ao site</span>
            <i class="fas fa-external-link-alt admin-site-external"></i>
        </a>
    </div>
</aside>
