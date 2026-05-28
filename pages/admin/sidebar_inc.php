<?php
$activePage = $activePage ?? '';
$navItems = [
    'dashboard'  => ['href' => 'index.php',     'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
    'products'   => ['href' => 'products.php',   'icon' => 'fa-box',           'label' => 'Produtos'],
    'categories' => ['href' => 'categories.php', 'icon' => 'fa-tags',          'label' => 'Categorias'],
    'orders'     => ['href' => 'orders.php',     'icon' => 'fa-shopping-cart', 'label' => 'Pedidos'],
    'customers'  => ['href' => 'customers.php',  'icon' => 'fa-users',         'label' => 'Clientes'],
    'banners'    => ['href' => 'banners.php',    'icon' => 'fa-images',        'label' => 'Banners'],
    'reports'    => ['href' => 'reports.php',    'icon' => 'fa-chart-bar',     'label' => 'Relatórios'],
    'settings'   => ['href' => 'settings.php',   'icon' => 'fa-cogs',          'label' => 'Configurações'],
];
?>
<aside class="admin-sidebar">
    <div class="admin-logo">
        <a href="index.php">
            <span class="logo-icon"><i class="fas fa-crown"></i></span>
            <span class="logo-text">Royal<span>Tech</span></span>
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
    <div style="padding:20px; margin-top:auto;">
        <a href="logout.php" class="btn btn-secondary" style="width:100%;">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>
</aside>
