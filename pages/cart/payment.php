<?php
// Tela de pagamento do pedido. Substitui a confirmação inline que ficava no
// checkout: o QR Pix e a linha digitável sao gerados a partir da chave real
// da loja, gravados em e5_orders.payment_details e expiram de verdade.

declare(strict_types=1);

$page_title = 'Pagamento - Royal Tech';
$current_page = 'carrinho';
$base_path = '../../';

require_once __DIR__ . '/../../includes/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once $base_path . 'vendor/autoload.php';
require_once $base_path . 'database/connection.php';
require_once $base_path . 'includes/payment_functions.php';
require_once $base_path . 'includes/image_helpers.php';

$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order'] ?? 0);

if ($orderId <= 0) {
    header('Location: cart.php');
    exit;
}

// Carrega o pedido SEMPRE escopado ao usuario logado. Sem o user_id na
// cláusula, editar ?order=N na URL expõe o pagamento de qualquer cliente.
$stmt = $pdo->prepare(
    'SELECT id, user_id, status, total, payment_method, payment_status,
            payment_details, payment_expires_at, created_at
     FROM e5_orders WHERE id = :id AND user_id = :u LIMIT 1'
);
$stmt->execute([':id' => $orderId, ':u' => $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ../auth/orders.php');
    exit;
}

// Aplica a expiração na leitura: o prazo é real, não texto de tela.
$order = payment_expire_if_due($pdo, $order);

$details = payment_details($pdo, $orderId) ?? [];
$status  = (string) $order['payment_status'];
$method  = (string) $order['payment_method'];

[$statusLabel, $statusColor, $statusIcon] = payment_status_label($status);

// "Pagar depois" deixa o pedido em aberto sem cobrar nada: é o caminho do
// cliente que vai pagar no app do banco em outro momento. Nada muda no
// pedido além deencerrar a urgência da tela.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'defer') {
    csrf_require_valid();
    header('Location: ../auth/orders.php?deferido=' . $orderId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_require_valid();
    $result = order_cancel($pdo, $orderId, $userId);
    header('Location: ../auth/orders.php?' . ($result['ok'] ? 'cancelado=' : 'erro=') . $orderId);
    exit;
}

$pixCode = (string) ($details['code'] ?? '');
$expiresTs = !empty($order['payment_expires_at']) ? strtotime((string) $order['payment_expires_at']) : null;
$isPaid = $status === 'paid';
$isOpen = in_array($status, ['pending', 'processing'], true) && $status !== 'expired';

include $base_path . 'components/header.php';
?>
<section class="ml-section" style="padding-top: 8px;">
  <div class="container" style="max-width: 720px;">

    <div class="ml-section-header">
      <h2 class="ml-section-title">Pagamento do Pedido #<?php echo str_pad((string) $orderId, 4, '0', STR_PAD_LEFT); ?></h2>
    </div>

    <div class="ml-card" style="text-align: center; margin-bottom: 16px;">
      <i class="fas fa-<?php echo $statusIcon; ?>" style="font-size: 2.6rem; color: <?php echo htmlspecialchars($statusColor, ENT_QUOTES, 'UTF-8'); ?>;"></i>
      <h3 style="margin: 10px 0 4px; color: <?php echo htmlspecialchars($statusColor, ENT_QUOTES, 'UTF-8'); ?>;">
        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
      </h3>
      <p style="color: var(--ml-text-secondary); margin: 0;">
        <?php echo htmlspecialchars(ucfirst($method === 'pix' ? 'Pix' : ($method === 'boleto' ? 'boleto' : $method)), ENT_QUOTES, 'UTF-8'); ?>
        ·
        <strong style="color: var(--ml-text-primary);"><?php echo 'R$ ' . number_format((float) $order['total'], 2, ',', '.'); ?></strong>
      </p>
    </div>

    <?php if ($isPaid): ?>
      <div class="ml-card">
        <p style="color: var(--ml-text-secondary);">
          Recebemos seu pagamento. O pedido já segue para o preparo e você recebe as atualizações por e-mail.
        </p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 16px;">
          <a href="../auth/orders.php" class="ml-btn ml-btn-primary"><i class="fas fa-list"></i> Meus Pedidos</a>
          <a href="../products/products.php" class="ml-btn"><i class="fas fa-store"></i> Continuar Comprando</a>
        </div>
      </div>

    <?php elseif ($isOpen): ?>
      <?php if ($method === 'pix'): ?>
        <div class="ml-card" style="text-align: center;">
          <div class="ml-step-head" style="justify-content: center;">
            <span class="ml-step-num"><i class="fas fa-pix"></i></span>
            <h3 style="margin: 0;">Pague com Pix</h3>
          </div>

          <?php if ($pixCode === ''): ?>
            <div class="auth-feedback auth-feedback-error" style="text-align: left;">
              A chave Pix da loja não está configurada ainda. O administrador precisa
              cadastrar a chave em <strong>Painel › Configurações</strong> para emitir o código.
            </div>
          <?php else: ?>
            <p style="color: var(--ml-text-secondary); font-size: 0.92rem;">
              Escaneie o QR Code com o app do seu banco ou copie o código para pagar.
            </p>

            <div style="background: #fff; padding: 16px; border-radius: 10px; display: inline-block; margin: 8px 0;">
              <canvas id="pixQr" width="240" height="240" style="display: block;"></canvas>
            </div>

            <div class="payment-code-box" style="text-align: left;">
              <code id="pixCode"><?php echo htmlspecialchars($pixCode, ENT_QUOTES, 'UTF-8'); ?></code>
              <button type="button" class="ml-btn" id="copyPix" style="margin-top: 10px;">
                <i class="fas fa-copy"></i> Copiar Código Pix
              </button>
            </div>

            <?php if ($expiresTs): ?>
              <p style="margin-top: 14px; color: var(--ml-text-muted);">
                Válido até <strong id="pixCountdown"><?php echo date('d/m/Y H:i', $expiresTs); ?></strong>
              </p>
            <?php endif; ?>
          <?php endif; ?>
        </div>

      <?php elseif ($method === 'boleto'): ?>
        <div class="ml-card" style="text-align: center;">
          <div class="ml-step-head" style="justify-content: center;">
            <span class="ml-step-num"><i class="fas fa-barcode"></i></span>
            <h3 style="margin: 0;">Boleto Bancário</h3>
          </div>
          <p style="color: var(--ml-text-secondary); font-size: 0.92rem;">
            Pague em qualquer banco, casa lotérica ou aplicativo.
          </p>
          <div class="payment-code-box" style="text-align: left;">
            <code id="boletoCode"><?php echo htmlspecialchars((string) ($details['linha_digitavel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
            <button type="button" class="ml-btn" id="copyBoleto" style="margin-top: 10px;">
              <i class="fas fa-copy"></i> Copiar Linha Digitável
            </button>
          </div>
          <?php if (!empty($details['due_date'])): ?>
            <p style="margin-top: 14px; color: var(--ml-text-muted);">
              Vencimento: <strong><?php echo date('d/m/Y', strtotime((string) $details['due_date'])); ?></strong>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($method !== 'delivery'): ?>
        <div class="ml-card" style="border-style: dashed;">
          <p style="font-size: 0.86rem; color: var(--ml-text-muted); margin: 0 0 12px;">
            Esta loja é uma vitrine de estudo e não integrate um_gateway real.
            O status do pagamento é alterado pelo administrador no painel,
            ou pelo simulador abaixo.
          </p>

          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="ml-btn ml-btn-primary" id="simApprove">
              <i class="fas fa-check"></i> Simular pagamento aprovado
            </button>
            <button type="button" class="ml-btn" id="simFail">
              <i class="fas fa-times"></i> Simular recusa
            </button>
            <button type="button" class="ml-btn" id="simExpire">
              <i class="fas fa-hourglass-end"></i> Simular expiração
            </button>
          </div>
        </div>
      <?php endif; ?>

      <form method="post" class="ml-card" style="border-style: dashed;" onsubmit="return confirm('Pagar depois? O pedido continua aberto e você pode quitar quando quiser.');">
        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="defer">
        <p style="font-size: 0.86rem; color: var(--ml-text-muted); margin: 0 0 12px;">
          Vai pagar pelo app do banco depois? Deixe o pedido em aberto e volte quando quiser.
        </p>
        <button type="submit" class="ml-btn"><i class="fas fa-clock"></i> Pagar depois</button>
      </form>

      <form method="post" onsubmit="return confirm('Cancelar este pedido? O estoque volta para a loja.');">
        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="ml-btn" style="width: 100%;">
          <i class="fas fa-times"></i> Cancelar pedido
        </button>
      </form>

    <?php elseif ($status === 'expired'): ?>
      <div class="ml-card">
        <p style="color: var(--ml-text-secondary);">
          O prazo de pagamento deste pedido expirou. O estoque foi reservado pelo tempo do prazo
          e ainda pode estar disponível, mas o pedido precisa ser refeito.
        </p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 16px;">
          <a href="../products/products.php" class="ml-btn ml-btn-primary"><i class="fas fa-store"></i> Refazer pedido</a>
          <a href="../auth/orders.php" class="ml-btn"><i class="fas fa-list"></i> Meus Pedidos</a>
        </div>
      </div>

    <?php elseif ($status === 'refunded'): ?>
      <div class="ml-card">
        <p style="color: var(--ml-text-secondary);">Este pedido foi cancelado e o pagamento estornado.</p>
        <a href="../products/products.php" class="ml-btn ml-btn-primary" style="margin-top: 12px;">Continuar Comprando</a>
      </div>

    <?php else: ?>
      <div class="ml-card">
        <p style="color: var(--ml-text-secondary);">
          Este pedido não está aguardando pagamento no momento.
        </p>
        <a href="../auth/orders.php" class="ml-btn ml-btn-primary" style="margin-top: 12px;">Meus Pedidos</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php
// A biblioteca mora em assets/js/ e NÃO em assets/vendor/: o .htaccess raiz
// bloqueia qualquer caminho que contenha "vendor/" (RedirectMatch 403, feito
// para proteger o vendor/ do Composer), então servir de assets/vendor/... o
// cliente recebia 403 e o QR nunca aparecia.
$qrFile = dirname(__DIR__, 2) . '/assets/js/qrcode.min.js';
if ($method === 'pix' && $isOpen && $pixCode !== '' && is_file($qrFile)) {
    echo '<script src="' . htmlspecialchars($base_path . 'assets/js/qrcode.min.js', ENT_QUOTES, 'UTF-8') . '?v=' . ASSET_VERSION . '"></script>';
}
?>
<script>
(function () {
  var orderId = <?php echo (int) $orderId; ?>;
  var csrf = <?php echo json_encode(csrf_token()); ?>;
  var expiresTs = <?php echo $expiresTs ? (int) $expiresTs : 'null'; ?>;

  // ---- Copia e cola ----------------------------------------------------
  function bindCopy(buttonId, sourceId) {
    var btn = document.getElementById(buttonId);
    var src = document.getElementById(sourceId);
    if (!btn || !src) return;
    btn.addEventListener('click', function () {
      var text = src.textContent.trim();
      var done = function () {
        var original = btn.innerHTML;
        btn.textContent = 'Copiado!';
        setTimeout(function () { btn.innerHTML = original; }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
      } else {
        fallbackCopy(text, done);
      }
    });
  }

  // navigator.clipboard exige contexto seguro (https ou localhost). Se o
  // site rodar em http:// na rede, a copia silenciosamente falha; o fallback
  // com execCommand cobre esse caso.
  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { /* nada a fazer */ }
    document.body.removeChild(ta);
  }

  bindCopy('copyPix', 'pixCode');
  bindCopy('copyBoleto', 'boletoCode');

  // ---- QR Code ---------------------------------------------------------
  var canvas = document.getElementById('pixQr');
  var pixText = document.getElementById('pixCode');
  if (canvas && pixText && window.QRCode) {
    new window.QRCode(canvas, {
      text: pixText.textContent.trim(),
      width: 240,
      height: 240,
      colorDark: '#000000',
      colorLight: '#ffffff'
    });
  }

  // ---- Contagem regressiva --------------------------------------------
  var countdown = document.getElementById('pixCountdown');
  if (countdown && expiresTs) {
    var tick = function () {
      var left = Math.floor(expiresTs - Date.now() / 1000);
      if (left <= 0) {
        countdown.textContent = 'expirado';
        return;
      }
      var m = Math.floor(left / 60);
      var s = left % 60;
      countdown.textContent = m + 'min ' + (s < 10 ? '0' : '') + s + 's restantes';
    };
    tick();
    setInterval(tick, 1000);
  }

  // ---- Simulador / polling --------------------------------------------
  function post(action) {
    return fetch('payment_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ _csrf_token: csrf, order_id: orderId, action: action })
    }).then(function (r) { return r.json().catch(function () { return {}; }); });
  }

  ['simApprove', 'simFail', 'simExpire'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (!btn) return;
    btn.addEventListener('click', function () {
      btn.disabled = true;
      post(id === 'simApprove' ? 'approve' : (id === 'simFail' ? 'fail' : 'expire'))
        .then(function () { window.location.reload(); });
    });
  });

  // Enquanto o pedido está aberto, pergunta o status periodicamente para
  // pegar a confirmação feita no painel sem exigir F5.
  var active = <?php echo $isOpen ? 'true' : 'false'; ?>;
  if (active) {
    setInterval(function () {
      post('poll').then(function (data) {
        if (data && data.payment_status && data.payment_status !== <?php echo json_encode($status); ?>) {
          window.location.reload();
        }
      });
    }, 20000);
  }
})();
</script>
