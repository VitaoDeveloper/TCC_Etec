<?php
$page_title = 'Formulário de Produto - Royal Tech';
include 'auth_check.php';
include '../../database/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/image_helpers.php';
$activePage = 'products';

$productId = (int) ($_GET['id'] ?? 0);
$product = ['category_id' => '', 'name' => '', 'description' => '', 'brand' => '', 'price' => '', 'old_price' => '', 'stock' => 0, 'is_featured' => 0, 'package_size_id' => '', 'weight_kg' => '', 'height_cm' => '', 'width_cm' => '', 'length_cm' => ''];
$errorMessage = null;
$currentImagePath = '';

if ($productId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM e5_products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $result = $stmt->fetch();
    if ($result) {
        $product = $result;
    }

    $imgStmt = $pdo->prepare('SELECT image_path FROM e5_product_images WHERE product_id = :id ORDER BY is_primary DESC, id ASC LIMIT 1');
    $imgStmt->execute([':id' => $productId]);
    $imgRow = $imgStmt->fetch();
    $currentImagePath = $imgRow['image_path'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid();
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $imagePathInput = normalizeImagePath((string) ($_POST['image_path'] ?? ''));

    if ($categoryId <= 0 || $name === '' || (float) ($_POST['price'] ?? 0) <= 0) {
        $errorMessage = 'Preencha corretamente categoria, nome e preço do produto.';
    } else {
        $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $packageSizeId = (int) ($_POST['package_size_id'] ?? 0);
        $weightKg = trim((string) ($_POST['weight_kg'] ?? ''));
        $heightCm = trim((string) ($_POST['height_cm'] ?? ''));
        $widthCm = trim((string) ($_POST['width_cm'] ?? ''));
        $lengthCm = trim((string) ($_POST['length_cm'] ?? ''));
        $payload = [
            ':category_id' => $categoryId,
            ':name' => $name,
            ':description' => trim((string) $_POST['description']) ?: null,
            ':brand' => trim((string) $_POST['brand']) ?: null,
            ':price' => (float) $_POST['price'],
            ':old_price' => $_POST['old_price'] !== '' ? (float) $_POST['old_price'] : null,
            ':stock' => max(0, (int) $_POST['stock']),
            ':is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            ':package_size_id' => $packageSizeId > 0 ? $packageSizeId : null,
            ':weight_kg' => ($weightKg !== '' && (float) $weightKg >= 0) ? (float) $weightKg : null,
            ':height_cm' => ($heightCm !== '' && (float) $heightCm > 0) ? (float) $heightCm : null,
            ':width_cm' => ($widthCm !== '' && (float) $widthCm > 0) ? (float) $widthCm : null,
            ':length_cm' => ($lengthCm !== '' && (float) $lengthCm > 0) ? (float) $lengthCm : null,
        ];

        try {
            if ($productId > 0) {
                $payload[':id'] = $productId;
                $sql = 'UPDATE e5_products SET category_id=:category_id, name=:name, description=:description, brand=:brand, price=:price, old_price=:old_price, stock=:stock, is_featured=:is_featured, package_size_id=:package_size_id, weight_kg=:weight_kg, height_cm=:height_cm, width_cm=:width_cm, length_cm=:length_cm WHERE id=:id';
            } else {
                $payload[':slug'] = $slugBase . '-' . time();
                $sql = 'INSERT INTO e5_products (category_id, name, slug, description, brand, price, old_price, stock, is_featured, package_size_id, weight_kg, height_cm, width_cm, length_cm) VALUES (:category_id, :name, :slug, :description, :brand, :price, :old_price, :stock, :is_featured, :package_size_id, :weight_kg, :height_cm, :width_cm, :length_cm)';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($payload);

            if ($productId <= 0) {
                $productId = (int) $pdo->lastInsertId();
            }

            $finalImagePath = $imagePathInput;

            $hasFile = isset($_FILES['product_image']);
            $uploadError = $hasFile ? (int) $_FILES['product_image']['error'] : UPLOAD_ERR_NO_FILE;
            $hasUploadAttempt = $uploadError !== UPLOAD_ERR_NO_FILE;

            if ($hasUploadAttempt) {
                if ($uploadError !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(uploadErrorMessage($uploadError));
                }

                if ((int) $_FILES['product_image']['size'] > 2097152) {
                    throw new RuntimeException('A imagem deve ter no máximo 2MB.');
                }

                $uploadDirAbsolute = realpath(__DIR__ . '/../../assets/img');
                if ($uploadDirAbsolute === false) {
                    throw new RuntimeException('Diretório de imagens não encontrado.');
                }

                $targetDir = $uploadDirAbsolute . '/products';
                if (!is_dir($targetDir)) {
                    if (!@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                        throw new RuntimeException('Não foi possível criar o diretório de uploads (' . $targetDir . '). Verifique as permissões de escrita da pasta assets/img.');
                    }
                }
                if (!is_writable($targetDir)) {
                    throw new RuntimeException('O diretório de uploads não tem permissão de escrita (' . $targetDir . ').');
                }

                $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                $originalName = $_FILES['product_image']['name'] ?? '';
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExt, true)) {
                    throw new RuntimeException('Formato de imagem inválido. Use JPG, PNG ou WEBP.');
                }

                $pathFileName = $slugBase . '-' . time() . '.' . $extension;

                $targetAbsolute = $targetDir . '/' . $pathFileName;
                if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $targetAbsolute)) {
                    throw new RuntimeException('Falha no upload da imagem.');
                }

                $finalImagePath = '/assets/img/products/' . $pathFileName;
            }

            if ($finalImagePath !== '') {
                $pdo->prepare('UPDATE e5_product_images SET is_primary = 0 WHERE product_id = :product_id')->execute([':product_id' => $productId]);

                $existingImgStmt = $pdo->prepare('SELECT id FROM e5_product_images WHERE product_id = :product_id AND image_path = :image_path LIMIT 1');
                $existingImgStmt->execute([':product_id' => $productId, ':image_path' => $finalImagePath]);
                $existingImage = $existingImgStmt->fetch();

                if ($existingImage) {
                    $pdo->prepare('UPDATE e5_product_images SET is_primary = 1 WHERE id = :id')->execute([':id' => (int) $existingImage['id']]);
                } else {
                    $pdo->prepare('INSERT INTO e5_product_images (product_id, image_path, is_primary) VALUES (:product_id, :image_path, 1)')
                        ->execute([':product_id' => $productId, ':image_path' => $finalImagePath]);
                }
            }

            $_SESSION['admin_message'] = $productId > 0 ? 'Produto atualizado com sucesso.' : 'Produto criado com sucesso.';
            header('Location: products.php');
            exit;
        } catch (Throwable $e) {
            error_log('Product form error: ' . $e->getMessage());
            $errorMessage = 'Erro ao salvar produto/imagem: ' . $e->getMessage();
        }
    }
}

$categories = $pdo->query('SELECT id, name FROM e5_categories ORDER BY name')->fetchAll();
$packageSizes = $pdo->query('SELECT id, name, height_cm, width_cm, length_cm, max_weight_kg FROM e5_package_sizes WHERE is_active = 1 ORDER BY max_weight_kg ASC, name ASC')->fetchAll();
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
                    <h2><?php echo $productId > 0 ? 'Editar Produto' : 'Novo Produto'; ?></h2>
                    <p>Preencha os dados do produto</p>
                </div>
                <div class="admin-actions">
                    <?php include 'header_user_inc.php'; ?>
                </div>
            </header>

            <?php if ($errorMessage): ?>
            <div class="auth-feedback auth-feedback-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="admin-table-container">
                <div class="admin-table-header">
                    <h3>Dados do produto</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding:20px 25px;">
                    <div class="admin-form-group">
                        <label for="category_id">Categoria</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Selecione a categoria</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $product['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="admin-form-group">
                        <label for="product_name">Nome do produto</label>
                        <input type="text" id="product_name" name="name" placeholder="Ex: Smartphone X Pro" value="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="admin-form-group">
                        <label for="description">Descrição <small>(opcional)</small></label>
                        <textarea id="description" name="description" placeholder="Descrição detalhada do produto"><?php echo htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="brand">Marca <small>(opcional)</small></label>
                            <input type="text" id="brand" name="brand" placeholder="Ex: Apple, Samsung" value="<?php echo htmlspecialchars($product['brand'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="stock">Estoque</label>
                            <input type="number" id="stock" min="0" name="stock" placeholder="Qtd. em estoque" value="<?php echo (int) $product['stock']; ?>" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="admin-form-group">
                            <label for="price">Preço</label>
                            <input type="number" id="price" step="0.01" min="0.01" name="price" placeholder="0,00" value="<?php echo htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label for="old_price">Preço anterior <small>(opcional)</small></label>
                            <input type="number" id="old_price" step="0.01" min="0" name="old_price" placeholder="0,00" value="<?php echo htmlspecialchars((string) ($product['old_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label class="checkbox-label" style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="is_featured" <?php echo (int) $product['is_featured'] === 1 ? 'checked' : ''; ?> style="width:auto;">
                            <span>Produto em destaque</span>
                        </label>
                    </div>

                    <hr style="border:none; border-top:1px solid var(--color-border); margin:20px 0;">

                    <div class="admin-form-group">
                        <label for="package_size_id">Embalagem pré-definida <small>(opcional)</small></label>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <select id="package_size_id" name="package_size_id" style="flex:1;">
                                <option value="">Personalizado (informar medidas abaixo)</option>
                                <?php foreach ($packageSizes as $ps): ?>
                                <option value="<?php echo (int) $ps['id']; ?>"
                                    data-height="<?php echo (float) $ps['height_cm']; ?>"
                                    data-width="<?php echo (float) $ps['width_cm']; ?>"
                                    data-length="<?php echo (float) $ps['length_cm']; ?>"
                                    data-weight="<?php echo (float) $ps['max_weight_kg']; ?>"
                                    <?php echo (int) ($product['package_size_id'] ?? 0) === (int) $ps['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ps['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo rtrim(rtrim((string) $ps['width_cm'], '0'), '.'); ?> x <?php echo rtrim(rtrim((string) $ps['height_cm'], '0'), '.'); ?> x <?php echo rtrim(rtrim((string) $ps['length_cm'], '0'), '.'); ?> cm, até <?php echo (float) $ps['max_weight_kg']; ?> kg)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="package-sizes.php" class="btn btn-secondary">Gerenciar embalagens</a>
                        </div>
                        <small style="color:var(--color-gray);">Se escolher uma embalagem pré-definida, as medidas usadas no cálculo de frete são as dela. Caso contrário, o sistema usa as medidas personalizadas abaixo.</small>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px;" id="pkg-custom-dims">
                        <div class="admin-form-group">
                            <label for="height_cm">Altura (cm)</label>
                            <input type="number" id="height_cm" name="height_cm" step="0.1" min="0" placeholder="15" value="<?php echo htmlspecialchars((string) ($product['height_cm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="width_cm">Largura (cm)</label>
                            <input type="number" id="width_cm" name="width_cm" step="0.1" min="0" placeholder="10" value="<?php echo htmlspecialchars((string) ($product['width_cm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="admin-form-group">
                            <label for="length_cm">Comprimento (cm)</label>
                            <input type="number" id="length_cm" name="length_cm" step="0.1" min="0" placeholder="20" value="<?php echo htmlspecialchars((string) ($product['length_cm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label for="weight_kg">Peso unitário (kg) <small>(opcional)</small></label>
                        <input type="number" id="weight_kg" name="weight_kg" step="0.01" min="0" placeholder="0,50" value="<?php echo htmlspecialchars((string) ($product['weight_kg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <small style="color:var(--color-gray);">Com embalagem pré-definida, se o peso ficar vazio usamos o peso máximo dela. Sem embalagem, o peso é obrigatório para um frete correto — mesmo assim, sem registro o sistema usa o valor padrão de 0,5 kg.</small>
                    </div>

                    <hr style="border:none; border-top:1px solid var(--color-border); margin:20px 0;">

                    <div class="admin-form-group">
                        <label for="image_path">Caminho da imagem</label>
                        <input type="text" id="image_path" name="image_path" data-auto-path="<?php echo $currentImagePath === '' ? '1' : '0'; ?>" placeholder="/assets/img/products/meu-produto.jpg" value="<?php echo htmlspecialchars($currentImagePath, ENT_QUOTES, 'UTF-8'); ?>">
                        <small style="color:var(--color-gray);">Informe apenas o nome do arquivo (ex: produto.jpg) que o sistema completa o caminho. Usado quando nenhum arquivo é enviado no upload abaixo.</small>
                    </div>

                    <div class="admin-form-group">
                        <label for="product_image">Upload de imagem (JPG, PNG, WEBP)</label>
                        <input type="file" id="product_image" name="product_image" accept=".jpg,.jpeg,.png,.webp">
                        <small style="color:var(--color-gray); display:block; margin-top:6px;">Ao enviar um arquivo neste campo, ele substitui o caminho do campo acima.</small>
                        <?php if ($currentImagePath): ?>
                        <small style="color:var(--color-gray); display:block; margin-top:6px;">Imagem atual: <?php echo htmlspecialchars($currentImagePath, ENT_QUOTES, 'UTF-8'); ?></small>
                        <?php endif; ?>
                    </div>

                    <?php echo csrf_field(); ?>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Salvar</button>
                        <a class="btn btn-secondary" href="products.php">Voltar</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
    <script>
    (function () {
      const nameInput = document.getElementById('product_name');
      const pathInput = document.getElementById('image_path');
      if (!nameInput || !pathInput) return;

      const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

      const refreshPath = () => {
        if (pathInput.dataset.autoPath !== '1') return;
        const slug = slugify(nameInput.value);
        pathInput.value = slug ? `/assets/img/products/${slug}.jpg` : '';
      };

      pathInput.addEventListener('input', () => {
        const value = pathInput.value.trim();
        pathInput.dataset.autoPath = value === '' ? '1' : '0';
      });

      nameInput.addEventListener('input', refreshPath);
      refreshPath();
    })();
    </script>
    <script>
    (function () {
      const presetSelect = document.getElementById('package_size_id');
      const dimInputs = document.querySelectorAll('#pkg-custom-dims input');
      if (!presetSelect || !dimInputs.length) return;
      const updateDims = () => {
        const isPreset = presetSelect.value !== '';
        dimInputs.forEach((input) => { input.disabled = isPreset; });
      };
      presetSelect.addEventListener('change', updateDims);
      updateDims();
    })();
    </script>
</body>
</html>
