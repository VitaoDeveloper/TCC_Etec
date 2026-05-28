<?php
$page_title = 'Gerenciar Clientes - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';

$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, name, email, username, created_at FROM e5_users WHERE role = :role';
$params = [':role' => 'customer'];
if ($search !== '') {
    $sql .= ' AND (name LIKE :q_name OR email LIKE :q_email OR username LIKE :q_username)';
    $pattern = '%' . $search . '%';
    $params[':q_name'] = $pattern;
    $params[':q_email'] = $pattern;
    $params[':q_username'] = $pattern;
}
$sql .= ' ORDER BY created_at DESC';
$customers = $pdo->prepare($sql);
$customers->execute($params);
$customers = $customers->fetchAll();

$total = count($customers);
$message = $_SESSION['admin_message'] ?? null;
unset($_SESSION['admin_message']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <a href="index.php">
                    <span class="logo-icon"><i class="fas fa-crown"></i></span>
                    <span class="logo-text">Royal<span>Tech</span></span>
                </a>
            </div>
            <nav class="admin-nav">
                <div class="admin-nav-item"><a href="index.php" class="admin-nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></div>
                <div class="admin-nav-item"><a href="products.php" class="admin-nav-link"><i class="fas fa-box"></i><span>Produtos</span></a></div>
                <div class="admin-nav-item"><a href="categories.php" class="admin-nav-link"><i class="fas fa-tags"></i><span>Categorias</span></a></div>
                <div class="admin-nav-item"><a href="orders.php" class="admin-nav-link"><i class="fas fa-shopping-cart"></i><span>Pedidos</span></a></div>
                <div class="admin-nav-item"><a href="customers.php" class="admin-nav-link active"><i class="fas fa-users"></i><span>Clientes</span></a></div>
                <div class="admin-nav-item"><a href="banners.php" class="admin-nav-link"><i class="fas fa-images"></i><span>Banners</span></a></div>
                <div class="admin-nav-item"><a href="reports.php" class="admin-nav-link"><i class="fas fa-chart-bar"></i><span>Relatórios</span></a></div>
                <div class="admin-nav-item"><a href="settings.php" class="admin-nav-link"><i class="fas fa-cogs"></i><span>Configurações</span></a></div>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Clientes</h2>
                    <p><?php echo $total; ?> cliente(s) cadastrado(s)</p>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-secondary" aria-label="Exportar clientes"><i class="fas fa-file-export"></i> Exportar</button>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <form method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="q" placeholder="Buscar por nome, e-mail ou usuário..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 8px 15px; border: 1px solid var(--color-border); border-radius: 5px; background: var(--color-black); color: var(--color-white); width: 300px;">
                        <button type="submit" class="btn btn-secondary" aria-label="Buscar clientes"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>E-mail</th>
                            <th>Usuário</th>
                            <th>Membro Desde</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--color-gray); padding:40px;">Nenhum cliente encontrado.</td></tr>
                        <?php else: foreach ($customers as $c):
                            $name = htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8');
                            $initials = '';
                            foreach (explode(' ', $name) as $part) {
                                if ($part !== '') $initials .= strtoupper($part[0]);
                                if (strlen($initials) >= 2) break;
                            }
                            $created = date('d/m/Y', strtotime($c['created_at']));
                            $since = date('M/Y', strtotime($c['created_at']));
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--color-black); font-weight: 600;"><?php echo $initials; ?></div>
                                    <div>
                                        <strong><?php echo $name; ?></strong>
                                        <br><small style="color: var(--color-gray);">Desde <?php echo $since; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($c['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $created; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="mailto:<?php echo htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary" style="padding:6px 12px;"><i class="fas fa-envelope"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
