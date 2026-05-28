<?php
$page_title = 'Gerenciar Banners - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Sessão expirada. Recarregue a página.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? '')) ?: null;
        $imagePath = trim((string) ($_POST['image_path'] ?? ''));
        $linkUrl = trim((string) ($_POST['link_url'] ?? '')) ?: null;
        if ($title !== '' && $imagePath !== '') {
            $stmt = $pdo->prepare('INSERT INTO e5_banners (title, subtitle, image_path, link_url) VALUES (:title, :subtitle, :image_path, :link_url)');
            $stmt->execute([':title' => $title, ':subtitle' => $subtitle, ':image_path' => $imagePath, ':link_url' => $linkUrl]);
            $_SESSION['admin_message'] = 'Banner criado com sucesso.';
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['banner_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE e5_banners SET is_active = NOT is_active WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['admin_message'] = 'Status do banner alterado.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['banner_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM e5_banners WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['admin_message'] = 'Banner removido.';
        }
    }

    header('Location: banners.php');
    exit;
}

$banners = $pdo->query('SELECT * FROM e5_banners ORDER BY created_at DESC')->fetchAll();
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
            <div class="admin-logo"><a href="index.php"><span class="logo-icon"><i class="fas fa-crown"></i></span><span class="logo-text">Royal<span>Tech</span></span></a></div>
            <nav class="admin-nav">
                <div class="admin-nav-item"><a href="index.php" class="admin-nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></div>
                <div class="admin-nav-item"><a href="products.php" class="admin-nav-link"><i class="fas fa-box"></i><span>Produtos</span></a></div>
                <div class="admin-nav-item"><a href="categories.php" class="admin-nav-link"><i class="fas fa-tags"></i><span>Categorias</span></a></div>
                <div class="admin-nav-item"><a href="orders.php" class="admin-nav-link"><i class="fas fa-shopping-cart"></i><span>Pedidos</span></a></div>
                <div class="admin-nav-item"><a href="customers.php" class="admin-nav-link"><i class="fas fa-users"></i><span>Clientes</span></a></div>
                <div class="admin-nav-item"><a href="banners.php" class="admin-nav-link active"><i class="fas fa-images"></i><span>Banners</span></a></div>
                <div class="admin-nav-item"><a href="reports.php" class="admin-nav-link"><i class="fas fa-chart-bar"></i><span>Relatórios</span></a></div>
                <div class="admin-nav-item"><a href="settings.php" class="admin-nav-link"><i class="fas fa-cogs"></i><span>Configurações</span></a></div>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Banners</h2>
                    <p><?php echo count($banners); ?> banner(es) cadastrado(s)</p>
                </div>
                <div class="admin-actions">
                    <button class="btn btn-primary" onclick="document.getElementById('createForm').style.display='block'"><i class="fas fa-plus"></i> Novo Banner</button>
                </div>
            </header>
            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container" id="createForm" style="display:none; margin-bottom:25px;">
                <h3 style="margin-bottom:15px;">Novo Banner</h3>
                <form method="POST" style="display:grid; gap:12px; max-width:500px;">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrf_field(); ?>
                    <input type="text" name="title" placeholder="Título" required style="padding:10px; border:1px solid var(--color-border); border-radius:5px; background:var(--color-black); color:var(--color-white);">
                    <input type="text" name="subtitle" placeholder="Subtítulo (opcional)" style="padding:10px; border:1px solid var(--color-border); border-radius:5px; background:var(--color-black); color:var(--color-white);">
                    <input type="text" name="image_path" placeholder="Caminho da imagem (ex: /assets/img/banner.jpg)" required style="padding:10px; border:1px solid var(--color-border); border-radius:5px; background:var(--color-black); color:var(--color-white);">
                    <input type="text" name="link_url" placeholder="Link (opcional)" style="padding:10px; border:1px solid var(--color-border); border-radius:5px; background:var(--color-black); color:var(--color-white);">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px;">
                <?php if (empty($banners)): ?>
                <div class="admin-table-container" style="grid-column:1/-1; text-align:center; padding:60px; color:var(--color-gray);">
                    <i class="fas fa-image" style="font-size:3rem; margin-bottom:15px;"></i>
                    <p>Nenhum banner cadastrado. Clique em "Novo Banner" para criar.</p>
                </div>
                <?php else: foreach ($banners as $b): ?>
                <div class="admin-table-container" style="overflow: hidden;">
                    <div style="height: 180px; background: linear-gradient(135deg, <?php echo $b['is_active'] ? '#1a1a1a' : '#333' ?> 0%, #2d2d2d 100%); display: flex; align-items: center; justify-content: center; flex-direction: column;">
                        <i class="fas fa-image" style="font-size: 2.5rem; color: var(--color-primary); margin-bottom: 10px;"></i>
                        <span style="color: var(--color-gray); font-size:0.85rem;"><?php echo htmlspecialchars($b['image_path'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div style="padding: 20px;">
                        <h4><?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <?php if ($b['subtitle']): ?>
                        <p style="color: var(--color-gray); font-size: 0.85rem; margin: 5px 0;"><?php echo htmlspecialchars($b['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <span class="status-badge <?php echo $b['is_active'] ? 'status-active' : 'status-inactive'; ?>" style="margin-top:10px; display:inline-block;">
                            <?php echo $b['is_active'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <form method="POST" style="flex:1;">
                                <input type="hidden" name="action" value="toggle">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="banner_id" value="<?php echo (int) $b['id']; ?>">
                                <button type="submit" class="btn btn-secondary" style="width:100%; padding:8px;">
                                    <i class="fas <?php echo $b['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="flex:1;" onsubmit="return confirm('Remover banner?');">
                                <input type="hidden" name="action" value="delete">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="banner_id" value="<?php echo (int) $b['id']; ?>">
                                <button type="submit" class="btn btn-secondary delete" style="width:100%; padding:8px; color:#f44336;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
