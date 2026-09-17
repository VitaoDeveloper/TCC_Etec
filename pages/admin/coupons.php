<?php
$page_title = 'Cupons de Desconto - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/pagination.php';
$activePage = 'coupons';

function parseDecimalOrNull(array $post, string $field): ?float
{
    $raw = trim((string) ($post[$field] ?? ''));
    if ($raw === '') return null;
    return (float) str_replace(',', '.', $raw);
}

function parseDecimalOrDefault(array $post, string $field, float $default): float
{
    $value = parseDecimalOrNull($post, $field);
    return $value === null ? $default : $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create', 'edit'], true)) {
        $id = (int) ($_POST['coupon_id'] ?? 0);
        $code = mb_strtoupper(trim((string) ($_POST['code'] ?? '')));
        $type = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value = parseDecimalOrDefault($_POST, 'value', 0.00);
        $minAmount = parseDecimalOrDefault($_POST, 'min_amount', 0.00);
        $maxDiscount = parseDecimalOrNull($_POST, 'max_discount');
        $validFrom = trim((string) ($_POST['valid_from'] ?? ''));
        $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
        $maxUses = (int) ($_POST['max_uses'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $scope = in_array($_POST['customer_scope'] ?? 'all', ['all', 'new', 'vip'], true) ? $_POST['customer_scope'] : 'all';

        if ($code === '' || $value <= 0) {
            $_SESSION['admin_error'] = 'Informe um código e um valor de desconto maior que zero.';
        } else {
            try {
                if ($action === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare('UPDATE e5_coupons SET code=:code, type=:type, value=:value, min_amount=:min, max_discount=:maxd, valid_from=:vf, valid_until=:vu, active=:active, customer_scope=:scope, max_uses=:maxu WHERE id=:id');
                    $ok = $stmt->execute([
                        ':code' => $code, ':type' => $type, ':value' => $value, ':min' => $minAmount,
                        ':maxd' => $maxDiscount, ':vf' => $validFrom !== '' ? $validFrom : null,
                        ':vu' => $validUntil !== '' ? $validUntil : null, ':active' => $active,
                        ':scope' => $scope, ':maxu' => $maxUses, ':id' => $id,
                    ]);
                    if ($ok) $_SESSION['admin_message'] = 'Cupom atualizado com sucesso.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO e5_coupons (code, type, value, min_amount, max_discount, valid_from, valid_until, active, customer_scope, max_uses) VALUES (:code, :type, :value, :min, :maxd, :vf, :vu, :active, :scope, :maxu)');
                    $ok = $stmt->execute([
                        ':code' => $code, ':type' => $type, ':value' => $value, ':min' => $minAmount,
                        ':maxd' => $maxDiscount, ':vf' => $validFrom !== '' ? $validFrom : null,
                        ':vu' => $validUntil !== '' ? $validUntil : null, ':active' => $active,
                        ':scope' => $scope, ':maxu' => $maxUses,
                    ]);
                    if ($ok) $_SESSION['admin_message'] = 'Cupom criado com sucesso.';
                }
            } catch (Throwable $e) {
                error_log('Coupon save error: ' . $e->getMessage());
                $_SESSION['admin_error'] = 'Não foi possível salvar o cupom. Verifique se o código já existe.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['coupon_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM e5_coupons WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $_SESSION['admin_message'] = 'Cupom removido com sucesso.';
        }
    }

    header('Location: coupons.php');
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE code LIKE :q_code';
    $params[':q_code'] = '%' . $search . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM e5_coupons $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$page = pagination_page();
$limit = pagination_limit(20);
$totalPages = max(1, (int) ceil($total / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = pagination_offset($page, $limit);

$couponsStmt = $pdo->prepare("SELECT * FROM e5_coupons $where ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset");
$couponsStmt->execute($params);
$coupons = $couponsStmt->fetchAll();

$message = $_SESSION['admin_message'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_message'], $_SESSION['admin_error']);
$editCoupon = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM e5_coupons WHERE id = :id');
    $editStmt->execute([':id' => (int) $_GET['edit']]);
    $editCoupon = $editStmt->fetch() ?: null;
}
function couponFmtValue(array $coupon): string
{
    if ($coupon['type'] === 'percent') {
        $v = rtrim(rtrim(number_format((float) $coupon['value'], 2, '.', ''), '0'), '.');
        return $v . '%';
    }
    return 'R$ ' . number_format((float) $coupon['value'], 2, ',', '.');
}
function couponFmtDec(?string $val): string
{
    return $val === null || $val === '' ? '' : rtrim(rtrim($val, '0'), '.');
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
                    <h2>Cupons de Desconto</h2>
                    <p>Gerencie os descontos aplicados no checkout (percentual ou valor fixo)</p>
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
                    <h3>Pré-definidos rápidos</h3>
                    <span style="color:var(--color-gray); font-size:0.85rem;">Clique para preencher o formulário abaixo</span>
                </div>
                <div style="padding:20px 25px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px;">
                    <?php
                    $presets = [
                        ['code' => 'ROYAL5',   'type' => 'percent', 'value' => '5',  'min' => '0',   'maxd' => '',      'label' => '5% de desconto'],
                        ['code' => 'ROYAL10',  'type' => 'percent', 'value' => '10', 'min' => '0',   'maxd' => '30',    'label' => '10% de desconto'],
                        ['code' => 'ROYAL15',  'type' => 'percent', 'value' => '15', 'min' => '0',   'maxd' => '50',    'label' => '15% de desconto'],
                        ['code' => 'ROYAL20',  'type' => 'percent', 'value' => '20', 'min' => '0',   'maxd' => '80',    'label' => '20% de desconto'],
                        ['code' => 'OFF10',    'type' => 'fixed',   'value' => '10', 'min' => '50',  'maxd' => '',      'label' => 'R$ 10 de desconto'],
                        ['code' => 'OFF20',    'type' => 'fixed',   'value' => '20', 'min' => '100', 'maxd' => '',      'label' => 'R$ 20 de desconto'],
                        ['code' => 'OFF50',    'type' => 'fixed',   'value' => '50', 'min' => '250', 'maxd' => '',      'label' => 'R$ 50 de desconto'],
                    ];
                    foreach ($presets as $p): ?>
                    <button type="button" class="btn" style="justify-content:flex-start;"
                            onclick="fillPreset('<?php echo $p['code']; ?>', '<?php echo $p['type']; ?>', '<?php echo $p['value']; ?>', '<?php echo $p['min']; ?>', '<?php echo $p['maxd']; ?>')">
                        <i class="fas fa-tag"></i> <?php echo $p['label']; ?>
                        <small style="margin-left:auto; color:var(--color-gray); font-size:0.75rem;"><?php echo $p['code']; ?></small>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-table-container" style="margin-bottom:30px;">
                <div class="admin-table-header">
                    <h3><?php echo $editCoupon ? 'Editar cupom' : 'Novo cupom'; ?></h3>
                </div>
                <form method="POST" style="padding:20px 25px;">
                    <input type="hidden" name="action" value="<?php echo $editCoupon ? 'edit' : 'create'; ?>">
                    <?php echo csrf_field(); ?>
                    <?php if ($editCoupon): ?>
                    <input type="hidden" name="coupon_id" value="<?php echo (int) $editCoupon['id']; ?>">
                    <?php endif; ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="coupon_code">Código do cupom</label>
                            <input type="text" id="coupon_code" name="code" placeholder="Ex: ROYAL10" value="<?php echo $editCoupon ? htmlspecialchars($editCoupon['code'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label for="coupon_type">Tipo de desconto</label>
                            <select id="coupon_type" name="type">
                                <option value="percent" <?php echo $editCoupon && $editCoupon['type'] === 'fixed' ? '' : 'selected'; ?>>Percentual (%)</option>
                                <option value="fixed" <?php echo $editCoupon && $editCoupon['type'] === 'fixed' ? 'selected' : ''; ?>>Valor fixo (R$)</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="coupon_value">Valor do desconto</label>
                            <input type="number" id="coupon_value" name="value" step="0.01" min="0.01" placeholder="10" value="<?php echo $editCoupon ? htmlspecialchars(couponFmtDec($editCoupon['value']), ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            <small style="color:var(--color-gray);">10 = 10% (se percentual) ou R$ 10 (se valor fixo)</small>
                        </div>
                        <div class="admin-form-group">
                            <label for="coupon_min">Pedido mínimo (R$) — opcional</label>
                            <input type="number" id="coupon_min" name="min_amount" step="0.01" min="0" placeholder="0" value="<?php echo $editCoupon ? htmlspecialchars(couponFmtDec($editCoupon['min_amount']), ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="coupon_maxd">Limite do desconto (R$) — só %</label>
                            <input type="number" id="coupon_maxd" name="max_discount" step="0.01" min="0" placeholder="Deixe vazio p/ sem limite" value="<?php echo $editCoupon ? htmlspecialchars(couponFmtDec($editCoupon['max_discount']), ENT_QUOTES, 'UTF-8') : ''; ?>" <?php echo $editCoupon && $editCoupon['type'] === 'fixed' ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="coupon_vf">Válido a partir de — opcional</label>
                            <input type="date" id="coupon_vf" name="valid_from" value="<?php echo $editCoupon ? htmlspecialchars((string) $editCoupon['valid_from'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="coupon_vu">Válido até — opcional</label>
                            <input type="date" id="coupon_vu" name="valid_until" value="<?php echo $editCoupon ? htmlspecialchars((string) $editCoupon['valid_until'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="coupon_maxu">Máx. usos — 0 = ilimitado</label>
                            <input type="number" id="coupon_maxu" name="max_uses" step="1" min="0" placeholder="0" value="<?php echo $editCoupon ? (int) $editCoupon['max_uses'] : ''; ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="coupon_scope">Tipo de cliente</label>
                            <select id="coupon_scope" name="customer_scope">
                                <option value="all" <?php echo !$editCoupon || $editCoupon['customer_scope'] === 'all' ? 'selected' : ''; ?>>Todos os clientes</option>
                                <option value="new" <?php echo $editCoupon && $editCoupon['customer_scope'] === 'new' ? 'selected' : ''; ?>>Cliente novo (primeira compra)</option>
                                <option value="vip" <?php echo $editCoupon && $editCoupon['customer_scope'] === 'vip' ? 'selected' : ''; ?>>Cliente VIP (alto ticket)</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-group" style="margin-top:6px;">
                        <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" name="active" value="1" <?php echo !$editCoupon || (int) $editCoupon['active'] === 1 ? 'checked' : ''; ?>>
                            Cupom ativo no checkout
                        </label>
                    </div>
                    <div style="margin-top:18px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editCoupon ? 'Salvar alterações' : 'Criar cupom'; ?></button>
                        <?php if ($editCoupon): ?>
                        <a href="coupons.php" class="btn"><i class="fas fa-times"></i> Cancelar edição</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Cupons cadastrados <span class="pagination-summary">(<?php echo $total; ?>)</span></h3>
                    <form method="GET" class="admin-filter-bar">
                        <input type="text" name="q" placeholder="Buscar por código..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-secondary" aria-label="Buscar cupons"><i class="fas fa-search"></i></button>
                        <?php if ($search !== ''): ?>
                        <a href="coupons.php" class="btn btn-secondary" aria-label="Limpar busca"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Desconto</th>
                            <th>Pedido mín.</th>
                            <th>Segmento</th>
                            <th>Validade</th>
                            <th>Usos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$coupons): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--color-gray); padding:24px;">Nenhum cupom criado. Use o formulário acima para cadastrar o primeiro.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($coupons as $c): ?>
                        <?php
                            $isExpired = !empty($c['valid_until']) && $c['valid_until'] < date('Y-m-d');
                            $limitReached = (int) $c['max_uses'] > 0 && (int) $c['used_count'] >= (int) $c['max_uses'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo couponFmtValue($c); ?>
                                <?php if ($c['type'] === 'percent' && !empty($c['max_discount']) && (float) $c['max_discount'] > 0): ?>
                                <small style="color:var(--color-gray);"> (máx. R$ <?php echo number_format((float) $c['max_discount'], 2, ',', '.'); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (float) $c['min_amount'] > 0 ? 'R$ ' . number_format((float) $c['min_amount'], 2, ',', '.') : '—'; ?></td>
                            <td>
                                <?php if ($c['customer_scope'] === 'new'): ?>
                                    <span class="status-badge"><i class="fas fa-user-plus"></i> Cliente novo</span>
                                <?php elseif ($c['customer_scope'] === 'vip'): ?>
                                    <span class="status-badge"><i class="fas fa-crown"></i> VIP</span>
                                <?php else: ?>
                                    <span class="status-badge status-active" style="background:var(--color-gray-light); color:var(--color-gray);">Todos</span>
                                <?php endif; ?>
                            </td>
                            <td><?php
                                if (!empty($c['valid_from']) && !empty($c['valid_until'])) {
                                    echo htmlspecialchars(date('d/m/Y', strtotime($c['valid_from'])), ENT_QUOTES, 'UTF-8') . ' a ' . htmlspecialchars(date('d/m/Y', strtotime($c['valid_until'])), ENT_QUOTES, 'UTF-8');
                                } elseif (!empty($c['valid_until'])) {
                                    echo 'até ' . htmlspecialchars(date('d/m/Y', strtotime($c['valid_until'])), ENT_QUOTES, 'UTF-8');
                                } elseif (!empty($c['valid_from'])) {
                                    echo 'desde ' . htmlspecialchars(date('d/m/Y', strtotime($c['valid_from'])), ENT_QUOTES, 'UTF-8');
                                } else {
                                    echo 'Sem prazo';
                                }
                            ?></td>
                            <td><?php echo (int) $c['used_count']; ?> <?php echo (int) $c['max_uses'] > 0 ? '/ ' . (int) $c['max_uses'] : ''; ?></td>
                            <td>
                                <?php if ($isExpired || $limitReached): ?>
                                    <span class="status-badge status-pending"><?php echo $isExpired ? 'Expirado' : 'Esgotado'; ?></span>
                                <?php elseif ((int) $c['active'] === 1): ?>
                                    <span class="status-badge status-active">Ativo</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <a href="coupons.php?edit=<?php echo (int) $c['id']; ?>" class="btn btn-sm"><i class="fas fa-pen"></i> Editar</a>
                                    <form method="POST" onsubmit="return confirm('Remover este cupom?');">
                                        <input type="hidden" name="action" value="delete">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="coupon_id" value="<?php echo (int) $c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php echo pagination_render($page, $totalPages, ['q' => $search]); ?>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
    <script>
    function fillPreset(code, type, value, min, maxd) {
        document.getElementById('coupon_code').value = code;
        document.getElementById('coupon_type').value = type;
        document.getElementById('coupon_value').value = value;
        document.getElementById('coupon_min').value = min;
        document.getElementById('coupon_maxd').value = maxd;
        document.getElementById('coupon_type').dispatchEvent(new Event('change'));
        document.getElementById('coupon_code').focus();
        document.getElementById('coupon_code').scrollIntoView({behavior: 'smooth'});
    }
    const typeSel = document.getElementById('coupon_type');
    if (typeSel) typeSel.addEventListener('change', function () {
        const maxd = document.getElementById('coupon_maxd');
        if (maxd) maxd.disabled = this.value !== 'percent';
    });
    </script>
</body>
</html>