<?php
$page_title = 'Gerenciar Banners - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
$activePage = 'banners';

function normalizeBannerPath(string $rawPath): string
{
    $path = trim($rawPath);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path[0] !== '/') {
        $path = 'assets/img/banners/' . ltrim($path, '/');
    }
    return $path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create', 'edit'], true)) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? '')) ?: null;
        $linkUrl = trim((string) ($_POST['link_url'] ?? '')) ?: null;
        $imageUrlInput = normalizeBannerPath((string) ($_POST['image_path'] ?? ''));
        $editId = $action === 'edit' ? (int) ($_POST['banner_id'] ?? 0) : 0;

        if ($title === '') {
            $_SESSION['admin_message'] = 'O título é obrigatório.';
            $_SESSION['admin_message_type'] = 'error';
        } else {
            try {
                $finalImagePath = $imageUrlInput;

                if (isset($_FILES['banner_image']) && is_uploaded_file($_FILES['banner_image']['tmp_name'])) {
                    $uploadDirAbsolute = realpath(__DIR__ . '/../../assets/img');
                    if ($uploadDirAbsolute === false) {
                        throw new RuntimeException('Diretório de imagens não encontrado.');
                    }

                    $targetDir = $uploadDirAbsolute . '/banners';
                    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                        throw new RuntimeException('Não foi possível criar diretório de upload.');
                    }

                    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                    $originalName = $_FILES['banner_image']['name'] ?? '';
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                    if (!in_array($extension, $allowedExt, true)) {
                        throw new RuntimeException('Formato de imagem inválido. Use JPG, PNG ou WEBP.');
                    }

                    $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
                    $pathFileName = $slugBase . '-' . time() . '.' . $extension;

                    $targetAbsolute = $targetDir . '/' . $pathFileName;
                    if (!move_uploaded_file($_FILES['banner_image']['tmp_name'], $targetAbsolute)) {
                        throw new RuntimeException('Falha no upload da imagem.');
                    }

                    $finalImagePath = '/assets/img/banners/' . $pathFileName;
                }

                if ($finalImagePath === '') {
                    $_SESSION['admin_message'] = 'Informe o caminho da imagem ou faça upload.';
                    $_SESSION['admin_message_type'] = 'error';
                } else {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare('INSERT INTO e5_banners (title, subtitle, image_path, link_url) VALUES (:title, :subtitle, :image_path, :link_url)');
                        $stmt->execute([':title' => $title, ':subtitle' => $subtitle, ':image_path' => $finalImagePath, ':link_url' => $linkUrl]);
                        $_SESSION['admin_message'] = 'Banner criado com sucesso.';
                    } else {
                        $stmt = $pdo->prepare('UPDATE e5_banners SET title = :title, subtitle = :subtitle, image_path = :image_path, link_url = :link_url WHERE id = :id');
                        $stmt->execute([':title' => $title, ':subtitle' => $subtitle, ':image_path' => $finalImagePath, ':link_url' => $linkUrl, ':id' => $editId]);
                        $_SESSION['admin_message'] = 'Banner atualizado com sucesso.';
                    }
                    $_SESSION['admin_message_type'] = 'success';
                }
            } catch (Throwable $e) {
                error_log('Banner error: ' . $e->getMessage());
                $_SESSION['admin_message'] = 'Erro ao salvar banner: ' . $e->getMessage();
                $_SESSION['admin_message_type'] = 'error';
            }
        }

        header('Location: banners.php');
        exit;
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
$messageType = $_SESSION['admin_message_type'] ?? 'success';
unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);

$editBanner = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($banners as $b) {
        if ((int) $b['id'] === $editId) {
            $editBanner = $b;
            break;
        }
    }
}
?><!DOCTYPE html>
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
    <button class="sidebar-toggle" aria-label="Abrir menu"><i class="fas fa-bars"></i></button>
    <div class="admin-wrapper">
        <?php include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Gerenciar Banners</h2>
                    <p><?php echo count($banners); ?> banner(es) cadastrado(s)</p>
                </div>
                <a class="btn btn-primary" href="banners.php">
                    <i class="fas fa-plus"></i> Novo Banner
                </a>
            </header>

            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <?php if ($editBanner || !isset($_GET['edit'])): ?>
            <div class="admin-table-container" style="margin-bottom:30px;">
                <div class="admin-table-header">
                    <h3><?php echo $editBanner ? 'Editar banner' : 'Novo banner'; ?></h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding:20px 25px;">
                    <input type="hidden" name="action" value="<?php echo $editBanner ? 'edit' : 'create'; ?>">
                    <?php echo csrf_field(); ?>
                    <?php if ($editBanner): ?>
                    <input type="hidden" name="banner_id" value="<?php echo (int) $editBanner['id']; ?>">
                    <?php endif; ?>

                    <div class="admin-form-group">
                        <label for="banner_title">Título</label>
                        <input type="text" id="banner_title" name="title" placeholder="Ex: Promoção Especial"
                               value="<?php echo $editBanner ? htmlspecialchars($editBanner['title'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>

                    <div class="admin-form-group">
                        <label for="banner_subtitle">Subtítulo <small>(opcional)</small></label>
                        <input type="text" id="banner_subtitle" name="subtitle" placeholder="Ex: Aproveite ofertas exclusivas"
                               value="<?php echo $editBanner ? htmlspecialchars($editBanner['subtitle'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>

                    <div class="admin-form-group">
                        <label for="banner_link">Link de destino <small>(opcional)</small></label>
                        <input type="text" id="banner_link" name="link_url" placeholder="Ex: /produtos ou https://..."
                               value="<?php echo $editBanner ? htmlspecialchars($editBanner['link_url'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>

                    <hr style="border:none; border-top:1px solid var(--color-border); margin:20px 0;">

                    <div class="admin-form-group">
                        <label for="image_path">Caminho da imagem</label>
                        <input type="text" id="image_path" name="image_path"
                               placeholder="/assets/img/banners/meu-banner.jpg"
                               value="<?php echo $editBanner ? htmlspecialchars($editBanner['image_path'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <small style="color:var(--color-gray);">Informe o caminho completo ou apenas o nome do arquivo.</small>
                    </div>

                    <div class="admin-form-group">
                        <label for="banner_image">Upload de imagem (JPG, PNG, WEBP)</label>
                        <input type="file" id="banner_image" name="banner_image" accept=".jpg,.jpeg,.png,.webp">
                        <?php if ($editBanner && $editBanner['image_path']): ?>
                        <small style="color:var(--color-gray); display:block; margin-top:6px;">
                            Imagem atual: <?php echo htmlspecialchars($editBanner['image_path'], ENT_QUOTES, 'UTF-8'); ?>
                        </small>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <?php echo $editBanner ? 'Atualizar' : 'Salvar'; ?></button>
                        <?php if ($editBanner): ?>
                        <a class="btn btn-secondary" href="banners.php">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:25px;">
                <?php if (empty($banners)): ?>
                <div class="admin-table-container" style="grid-column:1/-1; text-align:center; padding:60px; color:var(--color-gray);">
                    <i class="fas fa-image" style="font-size:3rem; margin-bottom:15px; color:var(--color-primary);"></i>
                    <p>Nenhum banner cadastrado. Crie um banner acima.</p>
                </div>
                <?php else: foreach ($banners as $b): ?>
                <div class="admin-table-container" style="overflow:hidden;">
                    <?php
                    $imgPath = $b['image_path'];
                    $imgUrl = '';
                    if (preg_match('#^https?://#i', $imgPath)) {
                        $imgUrl = $imgPath;
                    } elseif ($imgPath !== '') {
                        $imgUrl = '../../' . ltrim($imgPath, '/');
                    }
                    ?>
                    <div style="height:180px; background:#1a1a1a; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                        <?php if ($imgUrl && file_exists(str_replace('../../', __DIR__ . '/../../', $imgUrl))): ?>
                        <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?>"
                             style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                        <i class="fas fa-image" style="font-size:2.5rem; color:var(--color-primary);"></i>
                        <span style="position:absolute; bottom:8px; left:8px; color:var(--color-gray); font-size:0.7rem; background:rgba(0,0,0,0.7); padding:2px 8px; border-radius:4px;">
                            <?php echo htmlspecialchars(basename($imgPath), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php endif; ?>
                        <div style="position:absolute; top:8px; right:8px;">
                            <span class="status-badge <?php echo $b['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $b['is_active'] ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </div>
                    </div>
                    <div style="padding:20px;">
                        <h4><?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <?php if ($b['subtitle']): ?>
                        <p style="color:var(--color-gray); font-size:0.85rem; margin:5px 0;"><?php echo htmlspecialchars($b['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <div style="display:flex; gap:10px; margin-top:15px;">
                            <form method="POST" style="flex:1;">
                                <input type="hidden" name="action" value="toggle">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="banner_id" value="<?php echo (int) $b['id']; ?>">
                                <button type="submit" class="btn btn-secondary" style="width:100%; padding:8px;" aria-label="Ativar ou desativar banner">
                                    <i class="fas <?php echo $b['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                </button>
                            </form>
                            <a href="?edit=<?php echo (int) $b['id']; ?>" class="btn btn-secondary" style="flex:1; padding:8px; text-align:center; text-decoration:none;" aria-label="Editar banner">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="flex:1;" onsubmit="return confirm('Remover banner?');">
                                <input type="hidden" name="action" value="delete">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="banner_id" value="<?php echo (int) $b['id']; ?>">
                                <button type="submit" class="btn btn-secondary delete" style="width:100%; padding:8px; color:#f44336;" aria-label="Excluir banner">
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
