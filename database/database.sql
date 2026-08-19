DROP DATABASE IF EXISTS e5_royaltech;
CREATE DATABASE e5_royaltech;
USE e5_royaltech;

CREATE TABLE IF NOT EXISTS e5_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) UNIQUE NOT NULL,
  username VARCHAR(40) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  postal_code VARCHAR(10) NOT NULL,
  street VARCHAR(120) NOT NULL,
  number INT NOT NULL,
  complement VARCHAR(80) DEFAULT NULL,
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
);

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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES e5_categories(id)
);

CREATE TABLE IF NOT EXISTS e5_product_images (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES e5_products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS e5_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  status ENUM('pending','paid','shipped','delivered','canceled') NOT NULL DEFAULT 'pending',
  total DECIMAL(10,2) NOT NULL,
  shipping_method VARCHAR(50) NULL,
  shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(50) NULL,
  payment_status ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  shipping_neighborhood VARCHAR(80) NULL,
  shipping_city VARCHAR(80) NULL,
  shipping_state VARCHAR(40) NULL,
  shipping_postal_code VARCHAR(10) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES e5_users(id)
);

CREATE TABLE IF NOT EXISTS e5_order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES e5_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES e5_products(id)
);

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
);

CREATE TABLE IF NOT EXISTS e5_contacts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NULL,
  subject VARCHAR(60) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS e5_wishlist (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_wishlist_item (user_id, product_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES e5_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES e5_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ======================================================================
-- REGISTROS DE EXEMPLO (SEED) -- senha padrão: password123 (hash bcrypt)
-- ======================================================================

INSERT INTO e5_users (name, email, username, password, role, postal_code, street, number, complement) VALUES
('Maria Silva', 'maria.silva@email.com', 'maria.silva', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '01310-100', 'Av. Paulista', 1000, 'Apto 72'),
('João Pereira', 'joao.pereira@email.com', 'joao.pereira', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '20040-020', 'Rua da Carioca', 250, 'Sala 5'),
('Ana Costa', 'ana.costa@email.com', 'ana.costa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '30130-010', 'Rua da Bahia', 120, NULL),
('Carlos Souza', 'carlos.souza@email.com', 'carlos.souza', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '40020-000', 'Av. Sete de Setembro', 340, 'Fundos'),
('Fernanda Lima', 'fernanda.lima@email.com', 'fernanda.lima', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '50050-000', 'Av. Boa Viagem', 900, 'Apto 101'),
('Rafael Almeida', 'rafael.almeida@email.com', 'rafael.almeida', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '60060-000', 'Av. Beira Mar', 1500, 'Cobertura'),
('Juliana Rocha', 'juliana.rocha@email.com', 'juliana.rocha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '70070-000', 'SIG Sul', 10, 'Loja 12'),
('Pedro Martins', 'pedro.martins@email.com', 'pedro.martins', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '80080-000', 'Av. Batel', 200, 'Apto 33'),
('Beatriz Nunes', 'beatriz.nunes@email.com', 'beatriz.nunes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '90090-000', 'Av. Ipiranga', 500, NULL),
('admin', 'admin@royaltech.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '01310-100', 'Av. Paulista', 1, 'Sede');

INSERT INTO e5_categories (name, slug, description) VALUES
('Smartphones', 'smartphones', 'Celulares, smartphones e acessórios móveis'),
('Notebooks', 'notebooks', 'Notebooks, ultrabooks e laptops'),
('Periféricos', 'perifericos', 'Mouses, teclados, headsets e acessórios'),
('Componentes', 'componentes', 'Processadores, placas de vídeo e memórias'),
('Áudio', 'audio', 'Fones de ouvido, caixas de som e soundbars');

INSERT INTO e5_products (category_id, name, slug, description, brand, price, old_price, stock, is_featured) VALUES
(1, 'Smartphone Galaxy S25 256GB', 'smartphone-galaxy-s25-256gb', 'Smartphone premium com tela AMOLED 6.2" e câmera 200MP.', 'Samsung', 4599.90, 4999.00, 25, 1),
(1, 'iPhone 16 128GB', 'iphone-16-128gb', 'iPhone 16 com chip A18 e sistema de câmeras avançado.', 'Apple', 5299.00, NULL, 15, 1),
(2, 'Notebook Nitro V15 i7', 'notebook-nitro-v15-i7', 'Notebook gamer com RTX 4060, 16GB RAM e SSD 512GB.', 'Acer', 4899.99, 5399.00, 10, 1),
(2, 'Ultrabook Zenbook 14 OLED', 'ultrabook-zenbook-14-oled', 'Ultrabook leve com tela OLED 2.8K e bateria de longa duração.', 'ASUS', 6499.00, NULL, 8, 0),
(3, 'Mouse Gamer Logitech G502', 'mouse-gamer-logitech-g502', 'Mouse gamer com sensor HERO 25K e 11 botões programáveis.', 'Logitech', 349.90, 399.90, 80, 0),
(3, 'Teclado Mecânico Redragon', 'teclado-mecanico-redragon', 'Teclado mecânico RGB com switches Redragon e layout ABNT2.', 'Redragon', 259.90, NULL, 60, 0),
(4, 'Processador Ryzen 7 7800X3D', 'processador-ryzen-7-7800x3d', 'Processador de 8 núcleos para games com cache 3D.', 'AMD', 2699.90, 2899.90, 20, 1),
(4, 'Placa de Vídeo RTX 4070 Super', 'placa-de-video-rtx-4070-super', 'GPU com 12GB GDDR6X e suporte a DLSS 3.', 'NVIDIA', 4399.90, NULL, 12, 0),
(5, 'Headset Gamer HyperX Cloud III', 'headset-gamer-hyperx-cloud-iii', 'Headset com som 7.1 surround e microfone com cancelamento de ruído.', 'HyperX', 699.90, 799.90, 45, 1),
(5, 'Caixa de Som JBL Flip 7', 'caixa-de-som-jbl-flip-7', 'Caixa bluetooth portátil à prova d\'água com 12h de bateria.', 'JBL', 549.90, NULL, 35, 0);

INSERT INTO e5_product_images (product_id, image_path, is_primary) VALUES
(1, '/assets/img/products/galaxy-s25.jpg', 1),
(2, '/assets/img/products/iphone-16.jpg', 1),
(3, '/assets/img/products/nitro-v15.jpg', 1),
(4, '/assets/img/products/zenbook-14.jpg', 1),
(5, '/assets/img/products/g502.jpg', 1),
(6, '/assets/img/products/teclado-redragon.jpg', 1),
(7, '/assets/img/products/ryzen-7800x3d.jpg', 1),
(8, '/assets/img/products/rtx-4070-super.jpg', 1),
(9, '/assets/img/products/cloud-iii.jpg', 1),
(10, '/assets/img/products/flip-7.jpg', 1),
(1, '/assets/img/products/galaxy-s25-2.jpg', 0),
(5, '/assets/img/products/g502-2.jpg', 0);

INSERT INTO e5_orders (user_id, status, total, shipping_method, shipping_cost, payment_method, payment_status, shipping_neighborhood, shipping_city, shipping_state, shipping_postal_code) VALUES
(1, 'delivered', 4999.00, 'correios', 29.90, 'pix', 'paid', 'Bela Vista', 'São Paulo', 'SP', '01310-100'),
(2, 'pending', 259.90, 'correios', 19.90, 'boleto', 'pending', 'Centro', 'Rio de Janeiro', 'RJ', '20040-020'),
(3, 'shipped', 4899.99, 'sedex', 49.90, 'cartao', 'paid', 'Savassi', 'Belo Horizonte', 'MG', '30130-010'),
(4, 'paid', 349.90, 'correios', 24.90, 'pix', 'paid', 'Comércio', 'Salvador', 'BA', '40020-000'),
(5, 'canceled', 699.90, 'correios', 19.90, 'cartao', 'refunded', 'Boa Viagem', 'Recife', 'PE', '50050-000');

INSERT INTO e5_order_items (order_id, product_id, quantity, unit_price) VALUES
(1, 2, 1, 4999.00),
(2, 6, 1, 259.90),
(3, 3, 1, 4899.99),
(4, 5, 1, 349.90),
(5, 9, 1, 699.90);

INSERT INTO e5_cart (user_id, product_id, quantity) VALUES
(1, 5, 1),
(2, 7, 2),
(3, 10, 1);

INSERT INTO e5_contacts (name, email, phone, subject, message) VALUES
('Lucas Ferreira', 'lucas.ferreira@email.com', '(11) 98888-1111', 'Dúvida sobre envio', 'Quanto tempo demora o envio para o interior de SP?'),
('Camila Dias', 'camila.dias@email.com', '(21) 97777-2222', 'Troca de produto', 'Gostaria de saber como faço para trocar um produto com defeito.'),
('Bruno Carvalho', 'bruno.carvalho@email.com', NULL, 'Garantia', 'A garantia do notebook cobre queda de tela?'),
('Isabela Ramos', 'isabela.ramos@email.com', '(31) 96666-3333', 'Orçamento', 'Vocês fazem orçamento para compra de 50 mouses para empresa?');

INSERT INTO e5_newsletter (email) VALUES
('news1@email.com'),
('news2@email.com'),
('news3@email.com');

INSERT INTO e5_banners (title, subtitle, image_path, link_url, is_active) VALUES
('Promoção Smartphones', 'Até 30% OFF em smartphones selecionados', '/assets/img/banners/smartphones.jpg', '/produtos/smartphones', 1),
('Semana do Consumidor', 'Ofertas imperdíveis por tempo limitado', '/assets/img/banners/semana-consumidor.jpg', '/ofertas', 1),
('Frete Grátis', 'Em compras acima de R$ 499,00', '/assets/img/banners/frete-gratis.jpg', NULL, 0);

INSERT INTO e5_wishlist (user_id, product_id) VALUES
(1, 3),
(1, 7),
(2, 9),
(3, 1);