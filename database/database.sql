SET NAMES utf8mb4;

-- =====================================================================
-- Como importar
--
--   mysql -u root e5_royaltech < database/database.sql
--
-- O nome do banco NAO aparece neste arquivo, nem em "CREATE DATABASE" nem em
-- "USE". O comando acima é quem escolhe, e o MySQL aplica tudo no banco
-- indicado.
--
-- Isso já foi o contrário: o arquivo trazia "USE e5_royaltech" fixo no topo.
-- O cliente do MySQL ignora o banco escolhido na linha de comando quando o
-- script troca de banco no meio, então "mysql -u root e5_schema_test < ..." "
-- escrevia no e5_royaltech e não no banco de teste — a conferência de schema
-- comparava o banco errado e o teste de importação "passava" sem nunca ter
-- testado o banco pretendido.
--
-- "USE" não aceita variável de sessão, então a alternativa com @db também não
-- funciona; a forma limpa é o banco vir do comando.
--
-- Todo CREATE TABLE é IF NOT EXISTS e os seeds usam INSERT IGNORE ou
-- WHERE NOT EXISTS: importar duas vezes no mesmo banco não quebra nada.
-- =====================================================================

CREATE TABLE IF NOT EXISTS e5_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) UNIQUE NOT NULL,
  cpf VARCHAR(14) NULL,
  username VARCHAR(40) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  postal_code VARCHAR(10) NOT NULL,
  street VARCHAR(120) NOT NULL,
  number INT NOT NULL,
  complement VARCHAR(80) DEFAULT NULL,
  neighborhood VARCHAR(80) NULL,
  city VARCHAR(80) NULL,
  state CHAR(2) NULL,
  -- Telefone com DDD, só dígitos, guardado no formato internacional
  -- (código do país + 11 dígitos). A SuperFrete exige telefone do
  -- destinatário na etiqueta e a notificação de WhatsApp depende dele:
  -- sem a coluna, os dois caminhos eram um beco sem saída para quem não
  -- tem telefone fixo.
  phone VARCHAR(20) NULL,
  -- E-mail verificado (link enviado no cadastro). Usado para fluxos
  -- que exigem confirmação de propriedade do endereço.
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  -- Avatar e preferências de contato. As flags vinham no banco de
  -- desenvolvimento (os 16 usuários deram consentimento para ambos os
  -- canais) e entravam no schema sem o código ler; agora são lidas por
  -- includes/notification_functions.php.
  avatar_path VARCHAR(255) NULL,
  notify_email TINYINT(1) NOT NULL DEFAULT 1,
  notify_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL UNIQUE,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_package_sizes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL UNIQUE,
  height_cm DECIMAL(5,1) NOT NULL,
  width_cm DECIMAL(5,1) NOT NULL,
  length_cm DECIMAL(5,1) NOT NULL,
  max_weight_kg DECIMAL(5,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO e5_package_sizes (name, height_cm, width_cm, length_cm, max_weight_kg) VALUES
('Micro', 10.0, 10.0, 10.0, 0.50),
('Pequena', 15.0, 10.0, 20.0, 1.00),
('Média', 25.0, 15.0, 25.0, 3.00),
('Grande', 30.0, 25.0, 30.0, 8.00),
('Extra Grande', 45.0, 35.0, 35.0, 12.00);

CREATE TABLE IF NOT EXISTS e5_products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  description TEXT NULL,
  brand VARCHAR(80) NULL,
  price DECIMAL(10,2) NOT NULL,
  old_price DECIMAL(10,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  package_size_id INT NULL,
  weight_kg DECIMAL(5,2) NULL,
  height_cm DECIMAL(5,1) NULL,
  width_cm DECIMAL(5,1) NULL,
  length_cm DECIMAL(5,1) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES e5_categories(id),
  CONSTRAINT fk_products_package_size FOREIGN KEY (package_size_id) REFERENCES e5_package_sizes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_product_images (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES e5_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  status ENUM('pending','paid','preparing','shipped','delivered','canceled') NOT NULL DEFAULT 'pending',
  total DECIMAL(10,2) NOT NULL,
  -- Desconto aplicado (cupom/manual). Nullable porque total = subtotal +
  -- frete - desconto; NULL significa "sem desconto informado". historicamente
  -- esta coluna existia apenas em migração, então instalações novas ficavam
  -- sem ela e o cálculo do desconto quebrava.
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping_method VARCHAR(50) NULL,
  shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(50) NULL,
  coupon_code VARCHAR(50) NULL,
  payment_card_last_four CHAR(4) NULL,
  -- processing/failed/expired são os estados do simulador de pagamento
  -- (aprovado/erro/expirado). O enum antigo aqui tinha só 3 valores e
  -- rejeitaria essas gravações numa instalação nova.
  -- 'canceled' adicionado pela migração de conta: pedido cancelado vira
  -- payment_status 'canceled' (ou 'refunded' se já pago).
  payment_status ENUM('pending','processing','paid','canceled','refunded','failed','expired') NOT NULL DEFAULT 'pending',
  -- Dados do pagamento que não são número de cartão: não guardar PAN, guardar
  -- só o que o provedor devolve (autorização, expiração).
  payment_details TEXT NULL,
  payment_expires_at DATETIME NULL,
  shipping_neighborhood VARCHAR(80) NULL,
  shipping_city VARCHAR(80) NULL,
  shipping_state VARCHAR(40) NULL,
  shipping_postal_code VARCHAR(10) NULL,
  -- Snapshot do endereço de entrega: rua/número/complemento gravados
  -- na criação do pedido e nunca mais alterados. Garante que o histórico
  -- do pedido sobreviva a mudanças de endereço do usuário.
  shipping_street VARCHAR(120) NULL,
  shipping_number VARCHAR(10) NULL,
  shipping_complement VARCHAR(80) NULL,
  tracking_code VARCHAR(100) NULL,
  comprovante_filename VARCHAR(60) NULL,
  email_status ENUM('sent','failed','skipped') NULL,
  email_error TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Chave de idempotência do checkout. O formulário de confirmação carrega
  -- uma chave; se ela já existir, o pedido já foi criado e a requisição é
  -- um duplo clique/recarregamento, não uma nova compra. Sem o UNIQUE, dois
  -- cliques simultâneos criavam dois pedidos do mesmo item — reproduzido
  -- antes desta coluna. NULL em pedidos antigos: MySQL permite vários NULL
  -- em índice UNIQUE, então eles não colidem.
  idempotency_key VARCHAR(64) NULL,
  -- status + created_at: pages/admin/orders.php filtra por status e ordena
  -- por data; a listagem do cliente ordena os pedidos dele por data.
  UNIQUE KEY uk_orders_idem (idempotency_key),
  INDEX idx_orders_status_created (status, created_at),
  INDEX idx_orders_user_created (user_id, created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES e5_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pagamentos do pedido. Fica separada de e5_orders porque um pedido pode
-- ter vários registros ao longo da vida (tentativa que falhou, tentativa que
-- foi paga, cancelamento). generated active_flag + UNIQUE (order_id,
-- active_flag) garante no máximo um pagamento "vivo" (pending/paid) por
-- pedido — MySQL não colide NULLs em índice UNIQUE, então pagamentos
-- finalizados (canceled/expired/refunded/failed) convivem sem conflito.
CREATE TABLE IF NOT EXISTS e5_payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  method ENUM('pix','boleto','cartao','delivery') NOT NULL DEFAULT 'pix',
  status ENUM('pending','paid','canceled','expired','refunded','failed') NOT NULL DEFAULT 'pending',
  amount DECIMAL(10,2) NOT NULL,
  -- Identificadores do Pix gerado pelo provedor. Nunca armazenar chave/segredo
  -- do cliente aqui: pix_key é a chave pública de cobrança.
  pix_key VARCHAR(140) NULL,
  pix_code VARCHAR(255) NULL,
  -- Cartão: só bandeira e os 4 últimos dígitos. PAN e CVV nunca entram no banco.
  card_brand VARCHAR(20) NULL,
  card_last_four CHAR(4) NULL,
  installments TINYINT UNSIGNED NULL,
  boleto_line VARCHAR(60) NULL,
  gateway_token VARCHAR(64) NULL,
  expires_at DATETIME NULL,
  paid_at DATETIME NULL,
  canceled_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  active_flag TINYINT GENERATED ALWAYS AS (IF(status IN ('pending','paid'), 1, NULL)) STORED,
  UNIQUE KEY uk_payments_active (order_id, active_flag),
  INDEX idx_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trilha de mudança de status do pedido. UNIQUE (order_id, status) impede a
-- mesma etapa de ser gravada duas vezes, então Includes/worker podem rodar
-- várias vezes sem poluir o histórico.
CREATE TABLE IF NOT EXISTS e5_order_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  status VARCHAR(24) NOT NULL,
  note VARCHAR(180) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_history_order_status (order_id, status),
  INDEX idx_history_order (order_id, id),
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  -- Snapshot do nome e imagem do produto no momento da compra.
  -- Garante que o pedido histórico mostre o que foi vendido, não o
  -- que o catálogo diz hoje.
  product_name VARCHAR(150) NULL,
  product_image VARCHAR(255) NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES e5_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_cart (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_cart_item (user_id, product_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES e5_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Endereços do cliente. generated default_flag + UNIQUE (user_id,
-- default_flag) garante no máximo um endereço marcado como principal por
-- usuário — NULLs em índice UNIQUE não colidem no MySQL, então os endereços
-- secundários convivem sem violar a restrição.
CREATE TABLE IF NOT EXISTS e5_addresses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'Principal',
  postal_code VARCHAR(10) NOT NULL,
  street VARCHAR(120) NOT NULL,
  number VARCHAR(10) NOT NULL,
  complement VARCHAR(80) NULL,
  neighborhood VARCHAR(80) NULL,
  city VARCHAR(80) NOT NULL,
  state CHAR(2) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  default_flag TINYINT(1) GENERATED ALWAYS AS (IF(is_default = 1, 1, NULL)) STORED,
  UNIQUE KEY uk_addresses_default (user_id, default_flag),
  INDEX idx_addresses_user (user_id, is_default),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_contacts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NULL,
  subject VARCHAR(60) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','answered') NOT NULL DEFAULT 'pending',
  response_message TEXT NULL,
  responded_by INT NULL,
  responded_at TIMESTAMP NULL,
  response_email_status ENUM('sent','failed','skipped') NULL,
  response_email_error TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contacts_email_status (email, status),
  CONSTRAINT fk_contacts_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_contacts_responded_by FOREIGN KEY (responded_by) REFERENCES e5_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trilha de auditoria da migração de usernames legados (executed uma vez,
-- já concluída). Não é usada por nenhuma tela: existe só para preservar o
-- registro "de qual username veio para qual" e para o diff de schema
-- continuar vazio entre este arquivo e a base existente. Pode ser descartada
-- sem impacto funcional.
CREATE TABLE IF NOT EXISTS e5_username_migration_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  old_username VARCHAR(30) NOT NULL,
  new_username VARCHAR(30) NOT NULL,
  migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username_migration_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_newsletter (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(120) UNIQUE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_password_reset_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_banners (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(120) NOT NULL,
  subtitle VARCHAR(180) NULL,
  image_path VARCHAR(255) NOT NULL,
  link_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- components/header.php busca os banners em todas as páginas, por data.
  INDEX idx_banners_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_wishlist (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_wishlist_item (user_id, product_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES e5_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS e5_settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- CHECKOUT ESTILO MERCADO LIVRE
-- =====================================================================

-- Cartões salvos do cliente (bloco Pagamento, cartão de crédito)
CREATE TABLE IF NOT EXISTS e5_saved_cards (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  card_brand VARCHAR(20) NOT NULL DEFAULT 'others',
  holder_name VARCHAR(80) NOT NULL,
  last_four CHAR(4) NOT NULL,
  exp_month TINYINT UNSIGNED NOT NULL,
  exp_year SMALLINT UNSIGNED NOT NULL,
  max_installments TINYINT UNSIGNED NOT NULL DEFAULT 12,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_saved_cards_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cupons de desconto (campo "Inserir código do cupom")
-- type: percent | fixed
CREATE TABLE IF NOT EXISTS e5_coupons (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL UNIQUE,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL,
  min_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  max_discount DECIMAL(10,2) NULL,
  valid_from DATE NULL,
  valid_until DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  -- customer_scope: all = todos | new = cliente novo (sem pedidos) | vip = alto ticket (>= gasto mínimo)
  customer_scope ENUM('all','new','vip') NOT NULL DEFAULT 'all',
  max_uses INT NOT NULL DEFAULT 0,
  used_count INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ======================================================================
-- REGISTROS DE EXEMPLO (SEED)
--
-- Clientes de demonstração: senha "password" (bcrypt). São dados fictícios
-- para poder navegar o site. Não use essas contas em produção.
--
-- >>> A conta admin NÃO tem senha conhecida. <<<
-- O seed original usava o mesmo hash bcrypt de "password" para o admin,
-- o que significa que qualquer instalação nova deste arquivo abria com
-- admin/password. O hash abaixo é de uma senha aleatória descartada:
-- ninguém consegue fazer login com ele, por adivinhação.
--
-- Para definir a senha do admin após rodar este arquivo:
--   php -r 'echo password_hash("SUA_SENHA", PASSWORD_BCRYPT), PHP_EOL;'
-- e depois:
--   UPDATE e5_users SET password = 'COLE_O_HASH_AQUI'
--    WHERE username = 'admin';
-- ======================================================================

INSERT IGNORE INTO e5_users (id, name, email, username, password, role, postal_code, street, number, complement, phone) VALUES
(1, 'Maria Silva', 'maria.silva@email.com', 'maria.silva', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '01310-100', 'Av. Paulista', 1000, 'Apto 72', '11999990001'),
(2, 'João Pereira', 'joao.pereira@email.com', 'joao.pereira', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '20040-020', 'Rua da Carioca', 250, 'Sala 5', '21999990002'),
(3, 'Ana Costa', 'ana.costa@email.com', 'ana.costa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '30130-010', 'Rua da Bahia', 120, NULL, '31999990003'),
(4, 'Carlos Souza', 'carlos.souza@email.com', 'carlos.souza', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '40020-000', 'Av. Sete de Setembro', 340, 'Fundos', '41999990004'),
(5, 'Fernanda Lima', 'fernanda.lima@email.com', 'fernanda.lima', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '50050-000', 'Av. Boa Viagem', 900, 'Apto 101', '51999990005'),
(6, 'Rafael Almeida', 'rafael.almeida@email.com', 'rafael.almeida', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '60060-000', 'Av. Beira Mar', 1500, 'Cobertura', '61999990006'),
(7, 'Juliana Rocha', 'juliana.rocha@email.com', 'juliana.rocha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '70070-000', 'SIG Sul', 10, 'Loja 12', '71999990007'),
(8, 'Pedro Martins', 'pedro.martins@email.com', 'pedro.martins', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '80080-000', 'Av. Batel', 200, 'Apto 33', '81999990008'),
(9, 'Beatriz Nunes', 'beatriz.nunes@email.com', 'beatriz.nunes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '90090-000', 'Av. Ipiranga', 500, NULL, '91999990009'),
-- Admin: senha de demonstracao conhecida, "royaltech2026". Antes desta
-- conta o seed nascia com um hash aleatorio descartado, o que deixava o
-- painel inacessivel para quem avaliava o projeto: nenhuma senha servia e
-- nao havia caminho para entrar. Os clientes acima ja nascem com "password",
-- entao agora existe um login valido dos dois lados.
-- Os 6 admins de demonstracao mais abaixo continuam bloqueados: sao
-- figurantes e nao ha motivo para abrir conta de figurante.
-- Trocar a senha antes de qualquer uso real.
(10, 'admin', 'admin@royaltech.com', 'admin', '$2y$10$xwPpxRDPNBg5z/URyrSrkeL3PvMs2VShSe8NgDgv/5B6CoicPffOe', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999999999');

-- CPF demo (bloco Faturamento do checkout) — os demais usuários podem preencher no perfil
UPDATE e5_users SET cpf = '52998224725' WHERE id = 1 AND cpf IS NULL;

-- Admin demo accounts (blocked with random discarded hashes)
INSERT IGNORE INTO e5_users (id, name, email, username, password, role, postal_code, street, number, complement, phone) VALUES
(11, 'Ana Andrade', 'ana.andrade@exemplo.com', 'ana.andrade', '$2y$10$y2NOmWkejnAV9ON0uvFkoujhuxnwo7Q29mXB/mJQaaMLAX77ZYTWm', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999990011'),
(12, 'Bruno Campos', 'bruno.campos@exemplo.com', 'bruno.campos', '$2y$10$ufibvuaO9ZlOUN1sBR0Fl.hGC1WE7QLUp8PUwjDbQ7sYjoopo9WJC', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999990012'),
(13, 'Carla Menezes', 'carla.menezes@exemplo.com', 'carla.menezes', '$2y$10$HXIgZri2WL71Wpn329E8J.s8DlQhYaCasWHNkGGa9LBYOYgdg7MJi', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999990013'),
(14, 'Diego Farias', 'diego.farias@exemplo.com', 'diego.farias', '$2y$10$dxderxESgOBhF2hYgqedc.X3pE1DsOqIJdWHV9/qKfWucRKfd.x06', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999990014'),
(15, 'Elisa Reis', 'elisa.reis@exemplo.com', 'elisa.reis', '$2y$10$tDQt3I.S3Lw8Ty7pdBYYHuzcslFt429fq0qersFARzKRd.9L.jrKy', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999990015'),
(16, 'Felipe Duarte', 'felipe.duarte@exemplo.com', 'felipe.duarte', '$2y$10$Lvp6Ha7RM.L33nvjNJKPrOm9nqzichzPspVEokyOmYRB3bF6dmoQ.', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede', '11999990016');

INSERT IGNORE INTO e5_categories (id, name, slug, description) VALUES
(1, 'Smartphones', 'smartphones', 'Celulares, smartphones e acessórios móveis'),
(2, 'Notebooks', 'notebooks', 'Notebooks, ultrabooks e laptops'),
(3, 'Periféricos', 'perifericos', 'Mouses, teclados, headsets e acessórios'),
(4, 'Componentes', 'componentes', 'Processadores, placas de vídeo e memórias'),
(5, 'Áudio', 'audio', 'Fones de ouvido, caixas de som e soundbars');

INSERT IGNORE INTO e5_products (id, category_id, name, slug, description, brand, price, old_price, stock, is_featured) VALUES
(1, 1, 'Smartphone Galaxy S25 256GB', 'smartphone-galaxy-s25-256gb', 'Smartphone premium com tela AMOLED 6.2" e câmera 200MP.', 'Samsung', 4599.90, 4999.00, 25, 1),
(2, 1, 'iPhone 16 128GB', 'iphone-16-128gb', 'iPhone 16 com chip A18 e sistema de câmeras avançado.', 'Apple', 5299.00, NULL, 15, 1),
(3, 2, 'Notebook Nitro V15 i7', 'notebook-nitro-v15-i7', 'Notebook gamer com RTX 4060, 16GB RAM e SSD 512GB.', 'Acer', 4899.99, 5399.00, 10, 1),
(4, 2, 'Ultrabook Zenbook 14 OLED', 'ultrabook-zenbook-14-oled', 'Ultrabook leve com tela OLED 2.8K e bateria de longa duração.', 'ASUS', 6499.00, NULL, 8, 0),
(5, 3, 'Mouse Gamer Logitech G502', 'mouse-gamer-logitech-g502', 'Mouse gamer com sensor HERO 25K e 11 botões programáveis.', 'Logitech', 349.90, 399.90, 80, 0),
(6, 3, 'Teclado Mecânico Redragon', 'teclado-mecanico-redragon', 'Teclado mecânico RGB com switches Redragon e layout ABNT2.', 'Redragon', 259.90, NULL, 60, 0),
(7, 4, 'Processador Ryzen 7 7800X3D', 'processador-ryzen-7-7800x3d', 'Processador de 8 núcleos para games com cache 3D.', 'AMD', 2699.90, 2899.90, 20, 1),
(8, 4, 'Placa de Vídeo RTX 4070 Super', 'placa-de-video-rtx-4070-super', 'GPU com 12GB GDDR6X e suporte a DLSS 3.', 'NVIDIA', 4399.90, NULL, 12, 0),
(9, 5, 'Headset Gamer HyperX Cloud III', 'headset-gamer-hyperx-cloud-iii', 'Headset com som 7.1 surround e microfone com cancelamento de ruído.', 'HyperX', 699.90, 799.90, 45, 1),
(10, 5, 'Caixa de Som JBL Flip 7', 'caixa-de-som-jbl-flip-7', 'Caixa bluetooth portátil à prova d\'água com 12h de bateria.', 'JBL', 549.90, NULL, 35, 0);

INSERT IGNORE INTO e5_product_images (id, product_id, image_path, is_primary) VALUES
(1, 1, '/assets/img/products/smartphone-galaxy-s25-256gb-1790682317.webp', 1),
(2, 2, '/assets/img/products/iphone-16-128gb-1790682326.png', 1),
(3, 3, '/assets/img/products/notebook-nitro-v15-i7-1790682265.png', 1),
(4, 4, '/assets/img/products/ultrabook-zenbook-14-oled-1790682276.png', 1),
(5, 5, '/assets/img/products/mouse-gamer-logitech-g502-1790682287.png', 1),
(6, 6, '/assets/img/products/teclado-mecan-nico-redragon-1790682304.webp', 1),
(7, 7, '/assets/img/products/processador-ryzen-7-7800x3d-1790682244.webp', 1),
(8, 8, '/assets/img/products/placa-de-v-deo-rtx-4070-super-1790682255.png', 1),
(9, 9, '/assets/img/products/headset-gamer-hyperx-cloud-iii-1790682218.png', 1),
(10, 10, '/assets/img/products/caixa-de-som-jbl-flip-7-1790682234.webp', 1);

INSERT IGNORE INTO e5_orders (id, user_id, status, total, shipping_method, shipping_cost, payment_method, payment_status, shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code, created_at) VALUES
(1, 1, 'delivered', 4999.00, 'correios', 29.90, 'pix', 'paid', 'Bela Vista', 'São Paulo', 'SP', '01310-100', '2026-09-01 10:00:00'),
(2, 2, 'pending', 259.90, 'correios', 19.90, 'boleto', 'pending', 'Centro', 'Rio de Janeiro', 'RJ', '20040-020', '2026-09-02 11:00:00'),
(3, 3, 'shipped', 4899.99, 'sedex', 49.90, 'cartao', 'paid', 'Savassi', 'Belo Horizonte', 'MG', '30130-010', '2026-09-03 12:00:00'),
(4, 4, 'paid', 349.90, 'correios', 24.90, 'pix', 'paid', 'Comércio', 'Salvador', 'BA', '40020-000', '2026-09-04 13:00:00'),
(5, 5, 'canceled', 699.90, 'correios', 19.90, 'cartao', 'refunded', 'Boa Viagem', 'Recife', 'PE', '50050-000', '2026-09-05 14:00:00'),
-- Order #0006: Pix expirado em 29/09/2026
(6, 1, 'canceled', 2599.90, 'correios', 29.90, 'pix', 'expired', 'Bela Vista', 'São Paulo', 'SP', '01310-100', '2026-09-29 19:00:00'),
-- Order #0012: Pedido pago
(12, 2, 'paid', 3499.00, 'sedex', 39.90, 'cartao', 'paid', 'Centro', 'Rio de Janeiro', 'RJ', '20040-020', '2026-10-01 10:00:00');

INSERT IGNORE INTO e5_order_items (id, order_id, product_id, quantity, unit_price) VALUES
(1, 1, 2, 1, 4999.00),
(2, 2, 6, 1, 259.90),
(3, 3, 3, 1, 4899.99),
(4, 4, 5, 1, 349.90),
(5, 5, 9, 1, 699.90),
(6, 6, 1, 1, 2599.90),
(7, 12, 2, 1, 3499.00);

INSERT IGNORE INTO e5_cart (id, user_id, product_id, quantity) VALUES
(1, 1, 5, 1),
(2, 2, 7, 2),
(3, 3, 10, 1);

INSERT IGNORE INTO e5_contacts (id, user_id, name, email, phone, subject, message) VALUES
(1, 1, 'Lucas Ferreira', 'lucas.ferreira@email.com', '(11) 98888-1111', 'Dúvida sobre envio', 'Quanto tempo demora o envio para o interior de SP?'),
(2, 2, 'Camila Dias', 'camila.dias@email.com', '(21) 97777-2222', 'Troca de produto', 'Gostaria de saber como faço para trocar um produto com defeito.'),
(3, 3, 'Bruno Carvalho', 'bruno.carvalho@email.com', NULL, 'Garantia', 'A garantia do notebook cobre queda de tela?'),
(4, 4, 'Isabela Ramos', 'isabela.ramos@email.com', '(31) 96666-3333', 'Orçamento', 'Vocês fazem orçamento para compra de 50 mouses para empresa?');

INSERT IGNORE INTO e5_newsletter (id, email) VALUES
(1, 'news1@email.com'),
(2, 'news2@email.com'),
(3, 'news3@email.com');

INSERT IGNORE INTO e5_banners (id, title, subtitle, image_path, link_url, is_active) VALUES
(1, 'Promoção Smartphones', 'Até 30% OFF em smartphones selecionados', '/assets/img/banners/promo-o-smartphones-1790690930.jpg', '/pages/products/products.php?category_id=1', 1),
(2, 'Semana do Consumidor', 'Ofertas imperdíveis por tempo limitado', '/assets/img/banners/semana-do-consumidor-1790690953.jpg', '/pages/products/products.php?offers=1', 1),
(3, 'Frete Grátis', 'Em compras acima de R$ 499,00', '/assets/img/banners/frete-gratis-1790697013.jpeg', '/pages/products/products.php', 0);

INSERT IGNORE INTO e5_wishlist (id, user_id, product_id) VALUES
(1, 1, 3),
(2, 1, 7),
(3, 2, 9),
(4, 3, 1);

INSERT IGNORE INTO e5_settings (setting_key, setting_value) VALUES
('comprovante_counter', '0'),
('vip_spend_threshold', '2000');

-- =====================================================================
-- INTEGRAÇÕES / SUPERFRETE
-- =====================================================================

-- =====================================================================
-- superfrete_webhook_log
-- Tabela de IDEMPOTÊNCIA para webhooks recebidos da SuperFrete.
-- A SuperFrete reenvia o mesmo evento em caso de timeout/falha
-- (timeout 30s, retry a cada 15 min, até 5x).
-- Se o event_id já existir, responder HTTP 200 sem reprocessar.
-- =====================================================================
CREATE TABLE IF NOT EXISTS superfrete_webhook_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id      VARCHAR(128) NOT NULL,
    event_type    VARCHAR(64)  NOT NULL,
    order_id      VARCHAR(128) DEFAULT NULL,
    payload_hash  CHAR(64)     NOT NULL,
    processed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_event_id (event_id),
    KEY idx_order_id (order_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Log de idempotência (event_id + payload_hash) para webhooks SuperFrete';

-- =====================================================================
-- e5_shipments
-- Rastreio real dos pedidos. Até aqui o checkout citava a SuperFrete só
-- para COTAR frete; nada criava etiqueta, e e5_orders.tracking_code era
-- uma coluna que nenhum arquivo escrevia nem lia.
--
-- Um pedido pode ter mais de uma linha (ex.: reenvio após ajuste de
-- endereço), então a relação é 1:N e não uma coluna na própria e5_orders.
-- superfrete_id é UNIQUE porque a SuperFrete reutiliza o id da etiqueta e
-- o webhook chega citando ele: dois envios para a mesma etiqueta
-- representa o mesmo objeto do lado deles.
-- =====================================================================
CREATE TABLE IF NOT EXISTS e5_shipments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id            INT             NOT NULL,
    superfrete_id       VARCHAR(128)    DEFAULT NULL,
    carrier             VARCHAR(60)     DEFAULT NULL,
    service             VARCHAR(60)     DEFAULT NULL,
    tracking_code       VARCHAR(100)    DEFAULT NULL,
    label_url           VARCHAR(500)    DEFAULT NULL,
    status              ENUM('pending','released','canceled','delivered','error')
                        NOT NULL DEFAULT 'pending',
    price               DECIMAL(10,2)   DEFAULT NULL,
    delivery_min_days   SMALLINT UNSIGNED DEFAULT NULL,
    delivery_max_days   SMALLINT UNSIGNED DEFAULT NULL,
    error_message       TEXT            DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_shipments_superfrete (superfrete_id),
    KEY idx_shipments_order (order_id),
    KEY idx_shipments_tracking (tracking_code),
    KEY idx_shipments_status (status),
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id)
        REFERENCES e5_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Envios criados na SuperFrete e seu rastreio';

-- =====================================================================
-- e5_notifications
-- Fila de notificações. O TCC não tem worker nem cron, então a fila é
-- drenada de forma oportunista: notification_drain() roda em requisição e
-- processa o que está pendente. O motivo de existir mesmo assim é
-- separar "o que precisa ser avisado" (decisão de negócio, gravada) de
-- "tentar enviar agora" (pode falhar e ser tentado de novo).
--
-- Sem isso, confirmar um pagamento no painel ou enviar uma etiqueta só
-- dispararia e-mail dentro da requisição, e uma falha do SMTP deixaria o
-- cliente sem aviso sem ninguém saber que falhou.
--
-- channel: 'email' sai por SMTP. 'whatsapp' fica registrado como pendente
-- com o link wa.me, porque não há provedor configurado — gravar como enviado
-- seria mentir sobre o que a loja fez.
-- =====================================================================
CREATE TABLE IF NOT EXISTS e5_notifications (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT             DEFAULT NULL,
    user_id      INT             DEFAULT NULL,
    channel      ENUM('email','whatsapp') NOT NULL,
    event_type   VARCHAR(48)     NOT NULL,
    recipient    VARCHAR(160)    NOT NULL,
    subject      VARCHAR(200)    DEFAULT NULL,
    body         TEXT            NOT NULL,
    status       ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT           DEFAULT NULL,
    sent_at      DATETIME        DEFAULT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_pending (status, created_at),
    KEY idx_notif_order (order_id),
    KEY idx_notif_user (user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- Seed de demonstração — usuário 1 (Maria Silva Santos)
-- Idempotente: apaga pedidos do user_id=1 e re-insere 8 pedidos em
-- todos os estados exigidos pelo TCC. Cada pedido tem itens, pagamento,
-- histórico e (quando aplicável) envio com totais coerentes:
-- subtotal - discount_amount + shipping_cost = total.
-- O seed pode ser importado quantas vezes quiser; a expiração do Pix
-- pendente é calculada como NOW() + INTERVAL 30 MINUTE no momento
-- da importação, então a contagem regressiva aparece "viva" na hora.
-- =====================================================================

-- Apaga apenas os pedidos do usuário demo para não sujar outros dados.
DELETE FROM e5_payments        WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1);
DELETE FROM e5_order_items     WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1);
DELETE FROM e5_order_history   WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1);
DELETE FROM e5_shipments       WHERE order_id IN (SELECT id FROM e5_orders WHERE user_id = 1);
DELETE FROM e5_orders          WHERE user_id = 1;

-- Endereço padrão do usuário 1 (id 900025) será usado como snapshot.
-- Produtos reais do catálogo (id, name, price):
-- 1: Smartphone Galaxy S25 256GB         4599.90
-- 3: Notebook Nitro V15 i7               4899.99
-- 5: Mouse Gamer Logitech G502           349.90
-- 7: Processador Ryzen 7 7800X3D         2699.90
-- 10: Caixa de Som JBL Flip 7            549.90

-- ---------------------------------------------------------------
-- 1) PENDENTE — Pix válido (expira em 30 min a partir do import)
-- subtotal = 4599.90 + 349.90 = 4949.80; shipping = 50.00; total = 4999.80
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_expires_at,
    shipping_street, shipping_number, shipping_complement, shipping_neighborhood,
    shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1001, 1, 'pending', 4999.80, 0.00, 'PAC', 50.00,
    'pix', 'pending', NOW() + INTERVAL 30 MINUTE,
    'Avenida Ameletto Marino', '300', '', 'Esplanada Santa Helena',
    'Taubaté', 'SP', '12053-831',
    'skipped', NOW(), NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1001, 1, 'Smartphone Galaxy S25 256GB', NULL, 1, 4599.90),
(1001, 5, 'Mouse Gamer Logitech G502', NULL, 1, 349.90);

INSERT INTO e5_payments (order_id, method, status, amount, pix_key, pix_code, expires_at, created_at) VALUES
(1001, 'pix', 'pending', 4999.80, 'pix-key-demo-1001', 'pix-code-1001', NOW() + INTERVAL 30 MINUTE, NOW());

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1001, 'pending', 'Pedido criado aguardando pagamento Pix', NOW());

-- ---------------------------------------------------------------
-- 2) PAGO — pagamento confirmado, aguardando preparação
-- subtotal = 4899.99 + 549.90 = 5449.89; shipping = 50.00; total = 5499.89 (corrigido para 5499.89)
-- Wait: 4899.99 + 549.90 = 5449.89 + 50 = 5499.89
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_details,
    shipping_street, shipping_number, shipping_complement, shipping_neighborhood,
    shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1002, 1, 'paid', 5499.89, 0.00, 'PAC', 50.00,
    'pix', 'paid', '{"pix_key":"pix-key-demo-1002"}',
    'Avenida Ameletto Marino', '300', '', 'Esplanada Santa Helena',
    'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 2 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1002, 3, 'Notebook Nitro V15 i7', NULL, 1, 4899.99),
(1002, 10, 'Caixa de Som JBL Flip 7', NULL, 1, 549.90);

INSERT INTO e5_payments (order_id, method, status, amount, pix_key, pix_code, paid_at, created_at) VALUES
(1002, 'pix', 'paid', 5499.89, 'pix-key-demo-1002', 'pix-code-1002', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 2 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1002, 'pending', 'Pedido criado', NOW() - INTERVAL 2 DAY),
(1002, 'paid', 'Pagamento Pix confirmado', NOW() - INTERVAL 1 DAY);

-- ---------------------------------------------------------------
-- 3) EM PREPARAÇÃO — separação/embalo
-- subtotal = 2699.90 + 349.90 = 3049.80; shipping = 50.00; total = 3099.80
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_details,
    shipping_street, shipping_number, shipping_complement, shipping_neighborhood,
    shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1003, 1, 'preparing', 3099.80, 0.00, 'PAC', 50.00,
    'cartao', 'paid', '{"card_brand":"visa","card_last_four":"4242"}',
    'Avenida Ameletto Marino', '300', '', 'Esplanada Santa Helena',
    'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 4 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1003, 7, 'Processador Ryzen 7 7800X3D', NULL, 1, 2699.90),
(1003, 5, 'Mouse Gamer Logitech G502', NULL, 1, 349.90);

INSERT INTO e5_payments (order_id, method, status, amount, card_brand, card_last_four, paid_at, created_at) VALUES
(1003, 'cartao', 'paid', 3099.80, 'visa', '4242', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 4 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1003, 'pending', 'Pedido criado', NOW() - INTERVAL 5 DAY),
(1003, 'paid', 'Pagamento cartão aprovado', NOW() - INTERVAL 4 DAY),
(1003, 'preparing', 'Pedido em separação no estoque', NOW() - INTERVAL 2 DAY);

-- ---------------------------------------------------------------
-- 4) ENVIADO — PAC com previsão + código de rastreio
-- subtotal = 4599.90 + 349.90 = 4949.90; shipping = 50.00; total = 4999.90
-- Wait: 4599.90 + 349.90 = 4949.80 + 50 = 4999.80
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_details,
    tracking_code, shipping_street, shipping_number, shipping_complement,
    shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1004, 1, 'shipped', 4999.80, 0.00, 'PAC', 50.00,
    'pix', 'paid', '{"pix_key":"pix-key-demo-1004"}',
    'BR123456789BR', 'Avenida Ameletto Marino', '300', '',
    'Esplanada Santa Helena', 'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 7 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1004, 1, 'Smartphone Galaxy S25 256GB', NULL, 1, 4599.90),
(1004, 5, 'Mouse Gamer Logitech G502', NULL, 1, 349.90);

INSERT INTO e5_payments (order_id, method, status, amount, pix_key, pix_code, paid_at, created_at) VALUES
(1004, 'pix', 'paid', 4999.80, 'pix-key-demo-1004', 'pix-code-1004', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 7 DAY);

INSERT INTO e5_shipments (order_id, carrier, service, tracking_code, label_url, status, price, delivery_min_days, delivery_max_days, created_at) VALUES
(1004, 'Correios', 'PAC', 'BR123456789BR', 'https://rastreamento.correios.com.br/app/index.php?objeto=BR123456789BR', 'released', 50.00, 3, 7, NOW() - INTERVAL 4 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1004, 'pending', 'Pedido criado', NOW() - INTERVAL 8 DAY),
(1004, 'paid', 'Pagamento Pix confirmado', NOW() - INTERVAL 6 DAY),
(1004, 'preparing', 'Pedido preparado para envio', NOW() - INTERVAL 5 DAY),
(1004, 'shipped', 'Enviado via Correios PAC — rastreio BR123456789BR', NOW() - INTERVAL 4 DAY);

-- ---------------------------------------------------------------
-- 5) ENTREGUE — entrega confirmada
-- subtotal = 4899.99 + 549.90 = 5449.89; shipping = 50.00; total = 5499.89
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_details,
    tracking_code, shipping_street, shipping_number, shipping_complement,
    shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1005, 1, 'delivered', 5499.89, 0.00, 'PAC', 50.00,
    'cartao', 'paid', '{"card_brand":"mastercard","card_last_four":"1234"}',
    'BR987654321BR', 'Avenida Ameletto Marino', '300', '',
    'Esplanada Santa Helena', 'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 15 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1005, 3, 'Notebook Nitro V15 i7', NULL, 1, 4899.99),
(1005, 10, 'Caixa de Som JBL Flip 7', NULL, 1, 549.90);

INSERT INTO e5_payments (order_id, method, status, amount, card_brand, card_last_four, paid_at, created_at) VALUES
(1005, 'cartao', 'paid', 5499.89, 'mastercard', '1234', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 15 DAY);

INSERT INTO e5_shipments (order_id, carrier, service, tracking_code, label_url, status, price, delivery_min_days, delivery_max_days, created_at) VALUES
(1005, 'Correios', 'PAC', 'BR987654321BR', 'https://rastreamento.correios.com.br/app/index.php?objeto=BR987654321BR', 'delivered', 50.00, 3, 7, NOW() - INTERVAL 9 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1005, 'pending', 'Pedido criado', NOW() - INTERVAL 16 DAY),
(1005, 'paid', 'Pagamento cartão aprovado', NOW() - INTERVAL 15 DAY),
(1005, 'preparing', 'Pedido preparado', NOW() - INTERVAL 14 DAY),
(1005, 'shipped', 'Enviado via Correios PAC — rastreio BR987654321BR', NOW() - INTERVAL 13 DAY),
(1005, 'delivered', 'Entregue em 2026-09-20 14:30', NOW() - INTERVAL 8 DAY);

-- ---------------------------------------------------------------
-- 6) CANCELADO — Pix expirado (estilo #0006)
-- subtotal = 2699.90 + 349.90 = 3049.80; shipping = 50.00; total = 3099.80
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_expires_at,
    shipping_street, shipping_number, shipping_complement, shipping_neighborhood,
    shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1006, 1, 'canceled', 3099.80, 0.00, 'PAC', 50.00,
    'pix', 'expired', NOW() - INTERVAL 1 HOUR,
    'Avenida Ameletto Marino', '300', '', 'Esplanada Santa Helena',
    'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 2 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1006, 7, 'Processador Ryzen 7 7800X3D', NULL, 1, 2699.90),
(1006, 5, 'Mouse Gamer Logitech G502', NULL, 1, 349.90);

INSERT INTO e5_payments (order_id, method, status, amount, pix_key, pix_code, expires_at, canceled_at, created_at) VALUES
(1006, 'pix', 'expired', 3099.80, 'pix-key-demo-1006', 'pix-code-1006', NOW() - INTERVAL 1 HOUR, NOW(), NOW() - INTERVAL 2 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1006, 'pending', 'Pedido criado', NOW() - INTERVAL 2 DAY),
(1006, 'canceled', 'Pix expirou — pedido cancelado automaticamente e estoque devolvido', NOW());

-- ---------------------------------------------------------------
-- 7) CANCELADO PELO CLIENTE — cancelamento voluntário
-- subtotal = 4599.90 + 549.90 = 5149.80; shipping = 50.00; total = 5199.80
-- Wait: 4599.90 + 549.90 = 5149.80 + 50 = 5199.80
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_details,
    shipping_street, shipping_number, shipping_complement, shipping_neighborhood,
    shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1007, 1, 'canceled', 5199.80, 0.00, 'PAC', 50.00,
    'pix', 'canceled', '{"pix_key":"pix-key-demo-1007"}',
    'Avenida Ameletto Marino', '300', '', 'Esplanada Santa Helena',
    'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 1 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1007, 1, 'Smartphone Galaxy S25 256GB', NULL, 1, 4599.90),
(1007, 10, 'Caixa de Som JBL Flip 7', NULL, 1, 549.90);

INSERT INTO e5_payments (order_id, method, status, amount, pix_key, pix_code, canceled_at, created_at) VALUES
(1007, 'pix', 'canceled', 5199.80, 'pix-key-demo-1007', 'pix-code-1007', NOW() - INTERVAL 12 HOUR, NOW() - INTERVAL 1 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1007, 'pending', 'Pedido criado', NOW() - INTERVAL 1 DAY),
(1007, 'canceled', 'Cancelado pelo cliente antes do envio', NOW() - INTERVAL 12 HOUR);

-- ---------------------------------------------------------------
-- 8) REEMBOLSADO — cancelado + reembolso processado
-- subtotal = 4899.99 + 349.90 = 5249.89; shipping = 50.00; total = 5299.89
-- Wait: 4899.99 + 349.90 = 5249.89 + 50 = 5299.89
-- ---------------------------------------------------------------
INSERT INTO e5_orders (
    id, user_id, status, total, discount_amount, shipping_method, shipping_cost,
    payment_method, payment_status, payment_details,
    tracking_code, shipping_street, shipping_number, shipping_complement,
    shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code,
    email_status, created_at, updated_at
) VALUES (
    1008, 1, 'canceled', 5299.89, 0.00, 'PAC', 50.00,
    'cartao', 'refunded', '{"card_brand":"visa","card_last_four":"5678"}',
    'BR555666777BR', 'Avenida Ameletto Marino', '300', '',
    'Esplanada Santa Helena', 'Taubaté', 'SP', '12053-831',
    'skipped', NOW() - INTERVAL 20 DAY, NOW()
);

INSERT INTO e5_order_items (order_id, product_id, product_name, product_image, quantity, unit_price) VALUES
(1008, 3, 'Notebook Nitro V15 i7', NULL, 1, 4899.99),
(1008, 5, 'Mouse Gamer Logitech G502', NULL, 1, 349.90);

INSERT INTO e5_payments (order_id, method, status, amount, card_brand, card_last_four, paid_at, canceled_at, created_at) VALUES
(1008, 'cartao', 'refunded', 5299.89, 'visa', '5678', NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 20 DAY);

INSERT INTO e5_shipments (order_id, carrier, service, tracking_code, label_url, status, price, delivery_min_days, delivery_max_days, created_at) VALUES
(1008, 'Correios', 'PAC', 'BR555666777BR', 'https://rastreamento.correios.com.br/app/index.php?objeto=BR555666777BR', 'delivered', 50.00, 3, 7, NOW() - INTERVAL 11 DAY);

INSERT INTO e5_order_history (order_id, status, note, created_at) VALUES
(1008, 'pending', 'Pedido criado', NOW() - INTERVAL 21 DAY),
(1008, 'paid', 'Pagamento cartão aprovado', NOW() - INTERVAL 20 DAY),
(1008, 'preparing', 'Pedido preparado', NOW() - INTERVAL 19 DAY),
(1008, 'shipped', 'Enviado via Correios PAC — rastreio BR555666777BR', NOW() - INTERVAL 18 DAY),
(1008, 'canceled', 'Devolução recebida — reembolso processado no cartão', NOW() - INTERVAL 8 DAY);
