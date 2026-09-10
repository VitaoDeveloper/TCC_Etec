<?php
$page_title = 'Gerenciar Embalagens - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
$activePage = 'package-sizes';

function parseNullableDecimal(array $post, string $field, float $min): ?float
{
    $raw = trim((string) ($post[$field] ?? ''));
    if ($raw === '') return null;
    $value = (float) str_replace(',', '.', $raw);
    return $value >= $min ? $value : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create', 'edit'], true)) {
        $id = (int) ($_POST['package_size_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $height = parseNullableDecimal($_POST, 'height_cm', 0.1);
        $width = parseNullableDecimal($_POST, 'width_cm', 0.1);
        $length = parseNullableDecimal($_POST, 'length_cm', 0.1);
        $maxWeight = parseNullableDecimal($_POST, 'max_weight_kg', 0.01);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name !== '' && $height !== null && $width !== null && $length !== null && $maxWeight !== null) {
            try {
                if ($action === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare('UPDATE e5_package_sizes SET name=:name, height_cm=:height, width_cm=:width, length_cm=:length, max_weight_kg=:weight, is_active=:is_active WHERE id=:id');
                    $stmt->execute([':name' => $name, ':height' => $height, ':width' => $width, ':length' => $length, ':weight' => $maxWeight, ':is_active' => $isActive, ':id' => $id]);
                    $_SESSION['admin_message'] = 'Embalagem atualizada com sucesso.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO e5_package_sizes (name, height_cm, width_cm, length_cm, max_weight_kg, is_active) VALUES (:name, :height, :width, :length, :weight, :is_active)');
                    $stmt->execute([':name' => $name, ':height' => $height, ':width' => $width, ':length' => $length, ':weight' => $maxWeight, ':is_active' => $isActive]);
                    $_SESSION['admin_message'] = 'Embalagem criada com sucesso.';
                }
            } catch (Throwable $e) {
                error_log('Package size save error: ' . $e->getMessage());
                $_SESSION['admin_error'] = 'Não foi possível salvar a embalagem. Verifique se já existe uma com esse nome.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['package_size_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE e5_products SET package_size_id = NULL WHERE package_size_id = :id');
            $stmt->execute([':id' => $id]);
            $stmt = $pdo->prepare('DELETE FROM e5_package_sizes WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $_SESSION['admin_message'] = 'Embalagem removida com sucesso.';
        }
    }

    header('Location: package-sizes.php');
    exit;
}

$packageSizes = $pdo->query('SELECT ps.*, (SELECT COUNT(*) FROM e5_products p WHERE p.package_size_id = ps.id) AS total_products FROM e5_package_sizes ps ORDER BY ps.max_weight_kg ASC, ps.name ASC')->fetchAll();
$message = $_SESSION['admin_message'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_message'], $_SESSION['admin_error']);
$editSize = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($packageSizes as $ps) {
        if ((int) $ps['id'] === $editId) {
            $editSize = $ps;
            break;
        }
    }
}
function fmtDecimal($value): string
{
    return rtrim(rtrim((string) $value, '0'), '.');
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'head_inc.php'; ?>
</head>
<body>
    <button class="sidebar-toggle" aria-label="Abrir menu"><i class="fas fa-bars"></i></button>
    <div class="admin-wrapper">
        <?php include 'sidebar_inc.php'; ?>
        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title">
                    <h2>Embalagens</h2>
                    <p>Tamanhos pré-definidos usados no cálculo de frete</p>
                </div>
                <div class="admin-actions">
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="auth-feedback auth-feedback-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container" style="margin-bottom:30px;">
                <div class="admin-table-header">
                    <h3><?php echo $editSize ? 'Editar embalagem' : 'Nova embalagem'; ?></h3>
                </div>
                <form method="POST" style="padding:20px 25px;">
                    <input type="hidden" name="action" value="<?php echo $editSize ? 'edit' : 'create'; ?>">
                    <?php echo csrf_field(); ?>
                    <?php if ($editSize): ?>
                    <input type="hidden" name="package_size_id" value="<?php echo (int) $editSize['id']; ?>">
                    <?php endif; ?>
                    <div class="admin-form-group">
                        <label for="size_name">Nome da embalagem</label>
                        <input type="text" id="size_name" name="name" placeholder="Ex: Pequena" value="<?php echo $editSize ? htmlspecialchars($editSize['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="size_height">Altura (cm)</label>
                            <input type="number" id="size_height" name="height_cm" step="0.1" min="0.1" placeholder="15" value="<?php echo $editSize ? htmlspecialchars(fmtDecimal($editSize['height_cm']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label for="size_width">Largura (cm)</label>
                            <input type="number" id="size_width" name="width_cm" step="0.1" min="0.1" placeholder="10" value="<?php echo $editSize ? htmlspecialchars(fmtDecimal($editSize['width_cm']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label for="size_length">Comprimento (cm)</label>
                            <input type="number" id="size_length" name="length_cm" step="0.1" min="0.1" placeholder="20" value="<?php echo $editSize ? htmlspecialchars(fmtDecimal($editSize['length_cm']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="size_weight">Peso máximo (kg)</label>
                            <input type="number" id="size_weight" name="max_weight_kg" step="0.01" min="0.01" placeholder="1,00" value="<?php echo $editSize ? htmlspecialchars(fmtDecimal($editSize['max_weight_kg']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="checkbox-label" style="display:flex; align-items:center; gap:10px; cursor:pointer; padding-top:8px;">
                                <input type="checkbox" name="is_active" <?php echo !$editSize || (int) $editSize['is_active'] === 1 ? 'checked' : ''; ?> style="width:auto;">
                                <span>Embalagem ativa</span>
                            </label>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-primary" type="submit"><?php echo $editSize ? 'Atualizar' : 'Cadastrar'; ?></button>
                        <?php if ($editSize): ?>
                        <a class="btn btn-secondary" href="package-sizes.php">Cancelar</a>
                        <?php endif; ?>
                        <a class="btn btn-secondary" href="product-form.php" style="margin-left:auto;">Cadastrar produto com esta embalagem</a>
                    </div>
                </form>
            </div>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Todas as embalagens</h3>
                    <span style="color:var(--color-gray); font-size:0.85rem;"><?php echo count($packageSizes); ?> embalagens</span>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Medidas (A x L x C)</th>
                            <th>Peso máx.</th>
                            <th>Status</th>
                            <th>Produtos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($packageSizes)): ?>
                        <tr><td colspan="7" class="empty-state">Nenhuma embalagem cadastrada.</td></tr>
                        <?php else: foreach ($packageSizes as $ps): ?>
                        <tr>
                            <td><?php echo (int) $ps['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($ps['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo fmtDecimal($ps['height_cm']); ?> x <?php echo fmtDecimal($ps['width_cm']); ?> x <?php echo fmtDecimal($ps['length_cm']); ?> cm</td>
                            <td><?php echo (float) $ps['max_weight_kg']; ?> kg</td>
                            <td><?php echo (int) $ps['is_active'] === 1 ? 'Ativa' : 'Inativa'; ?></td>
                            <td><?php echo (int) $ps['total_products']; ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="?edit=<?php echo (int) $ps['id']; ?>" aria-label="Editar embalagem"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Deseja remover esta embalagem? Os produtos que a usam deixarão de ter embalagem pré-definida.');">
                                        <input type="hidden" name="action" value="delete">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="package_size_id" value="<?php echo (int) $ps['id']; ?>">
                                        <button type="submit" class="delete" aria-label="Excluir embalagem"><i class="fas fa-trash"></i></button>
                                    </form>
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