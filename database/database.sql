-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: e5_royaltech
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `e5_banners`
--

DROP TABLE IF EXISTS `e5_banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(120) NOT NULL,
  `subtitle` varchar(180) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_banners`
--

LOCK TABLES `e5_banners` WRITE;
/*!40000 ALTER TABLE `e5_banners` DISABLE KEYS */;
INSERT INTO `e5_banners` VALUES (1,'Promoção Smartphones','Até 30% OFF em smartphones selecionados','/assets/img/banners/smartphones.jpg','/produtos/smartphones',1,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(2,'Semana do Consumidor','Ofertas imperdíveis por tempo limitado','/assets/img/banners/semana-consumidor.jpg','/ofertas',1,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(3,'Frete Grátis','Em compras acima de R$ 499,00','/assets/img/banners/frete-gratis.jpg',NULL,0,'2026-08-22 02:33:52','2026-08-22 02:33:52');
/*!40000 ALTER TABLE `e5_banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_cart`
--

DROP TABLE IF EXISTS `e5_cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`),
  KEY `fk_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `e5_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_cart`
--

LOCK TABLES `e5_cart` WRITE;
/*!40000 ALTER TABLE `e5_cart` DISABLE KEYS */;
INSERT INTO `e5_cart` VALUES (1,1,5,1,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(2,2,7,2,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(3,3,10,1,'2026-08-22 02:33:52','2026-08-22 02:33:52');
/*!40000 ALTER TABLE `e5_cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_categories`
--

DROP TABLE IF EXISTS `e5_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_categories`
--

LOCK TABLES `e5_categories` WRITE;
/*!40000 ALTER TABLE `e5_categories` DISABLE KEYS */;
INSERT INTO `e5_categories` VALUES (1,'Smartphones','smartphones','Celulares, smartphones e acessórios móveis','2026-08-22 02:33:52','2026-08-22 02:33:52'),(2,'Notebooks','notebooks','Notebooks, ultrabooks e laptops','2026-08-22 02:33:52','2026-08-22 02:33:52'),(3,'Periféricos','perifericos','Mouses, teclados, headsets e acessórios','2026-08-22 02:33:52','2026-08-22 02:33:52'),(4,'Componentes','componentes','Processadores, placas de vídeo e memórias','2026-08-22 02:33:52','2026-08-22 02:33:52'),(5,'Áudio','audio','Fones de ouvido, caixas de som e soundbars','2026-08-22 02:33:52','2026-08-22 02:33:52');
/*!40000 ALTER TABLE `e5_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_checkout_sessions`
--

DROP TABLE IF EXISTS `e5_checkout_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_checkout_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `gateway_locked` varchar(50) DEFAULT NULL,
  `tax_regime_locked` enum('CPF','MEI') DEFAULT NULL,
  `cart_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cart_snapshot`)),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `e5_checkout_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_checkout_sessions`
--

LOCK TABLES `e5_checkout_sessions` WRITE;
/*!40000 ALTER TABLE `e5_checkout_sessions` DISABLE KEYS */;
INSERT INTO `e5_checkout_sessions` VALUES (2,'b35jdt1tc0cvtedr5sapqg214o',NULL,'mercadopago','CPF',NULL,'2026-08-27 17:37:58','2026-08-27 17:07:58'),(4,'op390g2nqffitu8vvehnb3j9q8',NULL,'mercadopago','CPF',NULL,'2026-08-28 00:34:53','2026-08-28 00:04:53'),(5,'18hjviu2kq2diueav336hvkail',NULL,'mercadopago','CPF',NULL,'2026-08-28 00:47:06','2026-08-28 00:17:06'),(6,'ed1phqjfjr9h6vd49i75vvgs0c',NULL,'mercadopago','CPF',NULL,'2026-08-28 00:50:26','2026-08-28 00:20:26'),(7,'55d8eo04v29ormrf34chdqcreh',NULL,'mercadopago','CPF',NULL,'2026-08-28 01:05:12','2026-08-28 00:35:12'),(8,'dmkvn0jt86hmo1iodf1b8cplr4',NULL,'mercadopago','CPF',NULL,'2026-08-28 01:21:17','2026-08-28 00:51:17'),(9,'1o0tmqi6hhkkv54ie2h7oulf5p',NULL,'mercadopago','CPF',NULL,'2026-08-28 03:06:37','2026-08-28 02:36:37'),(10,'jpjq63r5sdk6l043nkj7ovv322',NULL,'mercadopago','CPF',NULL,'2026-08-28 03:08:47','2026-08-28 02:38:47');
/*!40000 ALTER TABLE `e5_checkout_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_contacts`
--

DROP TABLE IF EXISTS `e5_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(60) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_contacts`
--

LOCK TABLES `e5_contacts` WRITE;
/*!40000 ALTER TABLE `e5_contacts` DISABLE KEYS */;
INSERT INTO `e5_contacts` VALUES (1,'Lucas Ferreira','lucas.ferreira@email.com','(11) 98888-1111','Dúvida sobre envio','Quanto tempo demora o envio para o interior de SP?','2026-08-22 02:33:52'),(2,'Camila Dias','camila.dias@email.com','(21) 97777-2222','Troca de produto','Gostaria de saber como faço para trocar um produto com defeito.','2026-08-22 02:33:52'),(3,'Bruno Carvalho','bruno.carvalho@email.com',NULL,'Garantia','A garantia do notebook cobre queda de tela?','2026-08-22 02:33:52'),(4,'Isabela Ramos','isabela.ramos@email.com','(31) 96666-3333','Orçamento','Vocês fazem orçamento para compra de 50 mouses para empresa?','2026-08-22 02:33:52');
/*!40000 ALTER TABLE `e5_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_cpf_revenue_tracking`
--

DROP TABLE IF EXISTS `e5_cpf_revenue_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_cpf_revenue_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `month_year` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `total_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `order_count` int(11) NOT NULL DEFAULT 0,
  `last_order_id` int(11) DEFAULT NULL,
  `alert_threshold_reached` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_month` (`month_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_cpf_revenue_tracking`
--

LOCK TABLES `e5_cpf_revenue_tracking` WRITE;
/*!40000 ALTER TABLE `e5_cpf_revenue_tracking` DISABLE KEYS */;
/*!40000 ALTER TABLE `e5_cpf_revenue_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_encrypted_settings`
--

DROP TABLE IF EXISTS `e5_encrypted_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_encrypted_settings` (
  `setting_key` varchar(64) NOT NULL,
  `encrypted_value` text NOT NULL,
  `encryption_version` varchar(10) NOT NULL DEFAULT 'v1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_encrypted_settings`
--

LOCK TABLES `e5_encrypted_settings` WRITE;
/*!40000 ALTER TABLE `e5_encrypted_settings` DISABLE KEYS */;
INSERT INTO `e5_encrypted_settings` VALUES ('mercadopago_access_token','WU8msaRDke380HnomjsLYV8OEGwHfEVo683Ez9lZYPWoY5hpFDk8fudubr6FDAVpL1q+jaDHePE4qnGMJIA=','v1','2026-08-27 15:30:22','2026-08-27 15:30:22'),('nfe_api_key','x7/Med+9n3GOXsm699v0h/2WzBJ3kWs9pEppzZ1OqyZ0fIGuqvtgY0Efcq00PSTOtHNKy7I/gZ0=','v1','2026-08-27 15:26:36','2026-08-27 15:26:36'),('test_key_1787838852','PLWZf6U2RiXLAFqulJV4wnKVRnF+O3cZzVGlxVFxGQNqMlYvOqr0qUmqxZNFc123gKV7vBs=','v1','2026-08-27 13:54:12','2026-08-27 13:54:12'),('test_key_1787844253','+Qo8uBfFIYPe7bWBRNklOhELBWfOBwhYCQajviTRkrlO5I0MwxfovvFXHL3s28jKdWDf954=','v1','2026-08-27 15:24:13','2026-08-27 15:24:13');
/*!40000 ALTER TABLE `e5_encrypted_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_gateway_fees`
--

DROP TABLE IF EXISTS `e5_gateway_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_gateway_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) NOT NULL COMMENT 'mercadopago, asaas, etc',
  `document_type` enum('CPF','CNPJ') NOT NULL,
  `fee_percentage` decimal(5,2) NOT NULL,
  `fee_fixed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `source_url` varchar(255) DEFAULT NULL COMMENT 'Link to official pricing page',
  `verified_at` timestamp NULL DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `verification_status` enum('current','outdated','unverified') NOT NULL DEFAULT 'unverified',
  `is_estimate` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_gateway_fees`
--

LOCK TABLES `e5_gateway_fees` WRITE;
/*!40000 ALTER TABLE `e5_gateway_fees` DISABLE KEYS */;
INSERT INTO `e5_gateway_fees` VALUES (1,'mercadopago','CPF',3.99,0.00,NULL,NULL,NULL,'unverified',1,'Estimativa - verificar em https://www.mercadopago.com.br/costs','2026-08-27 01:48:27','2026-08-27 01:48:27'),(2,'mercadopago','CNPJ',2.99,0.00,NULL,NULL,NULL,'unverified',1,'Estimativa - verificar com representante comercial','2026-08-27 01:48:27','2026-08-27 01:48:27'),(3,'asaas','CPF',3.49,0.00,NULL,NULL,NULL,'unverified',1,'Estimativa - verificar em https://www.asaas.com/precos','2026-08-27 01:48:27','2026-08-27 01:48:27'),(4,'asaas','CNPJ',2.99,0.00,NULL,NULL,NULL,'unverified',1,'Estimativa - verificar com representante comercial','2026-08-27 01:48:27','2026-08-27 01:48:27');
/*!40000 ALTER TABLE `e5_gateway_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_newsletter`
--

DROP TABLE IF EXISTS `e5_newsletter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_newsletter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_newsletter`
--

LOCK TABLES `e5_newsletter` WRITE;
/*!40000 ALTER TABLE `e5_newsletter` DISABLE KEYS */;
INSERT INTO `e5_newsletter` VALUES (1,'news1@email.com','2026-08-22 02:33:52'),(2,'news2@email.com','2026-08-22 02:33:52'),(3,'news3@email.com','2026-08-22 02:33:52');
/*!40000 ALTER TABLE `e5_newsletter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_order_items`
--

DROP TABLE IF EXISTS `e5_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_items_order` (`order_id`),
  KEY `fk_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `e5_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `e5_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_order_items`
--

LOCK TABLES `e5_order_items` WRITE;
/*!40000 ALTER TABLE `e5_order_items` DISABLE KEYS */;
INSERT INTO `e5_order_items` VALUES (1,1,2,1,4999.00),(2,2,6,1,259.90),(3,3,3,1,4899.99),(4,4,5,1,349.90),(5,5,9,1,699.90),(6,6,2,1,5299.00),(7,7,1,1,4599.90),(8,8,2,1,5299.00),(10,11,6,1,259.90),(15,16,10,1,549.90),(16,17,3,1,4899.99),(17,18,2,1,5299.00),(18,19,5,2,349.90),(19,19,6,1,259.90),(20,20,5,2,349.90),(21,20,6,1,259.90),(22,21,3,1,4899.99);
/*!40000 ALTER TABLE `e5_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_orders`
--

DROP TABLE IF EXISTS `e5_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','paid','shipped','delivered','canceled') NOT NULL DEFAULT 'pending',
  `total` decimal(10,2) NOT NULL,
  `shipping_method` varchar(50) DEFAULT NULL,
  `shipping_carrier` varchar(80) DEFAULT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_delivery_time` varchar(40) DEFAULT NULL,
  `shipping_is_estimated` tinyint(1) NOT NULL DEFAULT 0,
  `payment_method` varchar(50) DEFAULT NULL,
  `gateway_used` varchar(50) DEFAULT NULL,
  `gateway_transaction_id` varchar(255) DEFAULT NULL,
  `gateway_captured_at` timestamp NULL DEFAULT current_timestamp(),
  `payment_status` enum('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `tax_regime_snapshot` enum('CPF','MEI') NOT NULL DEFAULT 'CPF',
  `regime_captured_at` timestamp NULL DEFAULT current_timestamp(),
  `invoice_number` varchar(20) DEFAULT NULL COMMENT 'NF-e number',
  `invoice_key` varchar(44) DEFAULT NULL COMMENT 'NF-e access key',
  `invoice_pdf_url` varchar(255) DEFAULT NULL COMMENT 'NF-e PDF download URL',
  `invoice_xml_url` varchar(255) DEFAULT NULL COMMENT 'NF-e XML download URL',
  `invoice_status` enum('pending','issued','error','canceled') DEFAULT 'pending',
  `invoice_error_message` text DEFAULT NULL COMMENT 'Error details if emission failed',
  `shipping_neighborhood` varchar(80) DEFAULT NULL,
  `shipping_city` varchar(80) DEFAULT NULL,
  `shipping_state` varchar(40) DEFAULT NULL,
  `shipping_postal_code` varchar(10) DEFAULT NULL,
  `coupon_code` varchar(40) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_orders_user` (`user_id`),
  KEY `idx_gateway_used` (`gateway_used`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_orders`
--

LOCK TABLES `e5_orders` WRITE;
/*!40000 ALTER TABLE `e5_orders` DISABLE KEYS */;
INSERT INTO `e5_orders` VALUES (1,1,'delivered',4999.00,'correios',NULL,29.90,NULL,0,'pix','mercadopago',NULL,'2026-08-22 02:33:52','paid','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,'Bela Vista','São Paulo','SP','01310-100','2026-08-22 02:33:52','2026-08-27 02:06:46'),(2,2,'pending',259.90,'correios',NULL,19.90,NULL,0,'boleto','mercadopago',NULL,'2026-08-22 02:33:52','pending','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,'Centro','Rio de Janeiro','RJ','20040-020','2026-08-22 02:33:52','2026-08-27 02:06:46'),(3,3,'shipped',4899.99,'sedex',NULL,49.90,NULL,0,'cartao','mercadopago',NULL,'2026-08-22 02:33:52','paid','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,'Savassi','Belo Horizonte','MG','30130-010','2026-08-22 02:33:52','2026-08-27 02:06:46'),(4,4,'paid',349.90,'correios',NULL,24.90,NULL,0,'pix','mercadopago',NULL,'2026-08-22 02:33:52','paid','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,'Comércio','Salvador','BA','40020-000','2026-08-22 02:33:52','2026-08-27 02:06:46'),(5,5,'canceled',699.90,'correios',NULL,19.90,NULL,0,'cartao','mercadopago',NULL,'2026-08-22 02:33:52','refunded','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,'Boa Viagem','Recife','PE','50050-000','2026-08-22 02:33:52','2026-08-27 02:06:46'),(6,11,'pending',5034.05,'Sedex',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-27 01:33:46','paid','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,'12053831','2026-08-27 01:33:46','2026-08-27 02:06:46'),(7,11,'pending',4369.90,'PAC',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-27 01:34:08','paid','CPF','2026-08-27 01:48:26',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,'12053831','2026-08-27 01:34:08','2026-08-27 02:06:46'),(8,11,'pending',5299.00,'Sedex',NULL,0.00,NULL,0,'credit','mercadopago',NULL,'2026-08-27 02:03:07','pending','CPF','2026-08-27 02:03:07',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,'12053831','2026-08-27 02:03:07','2026-08-27 02:06:46'),(11,11,'pending',261.80,'PAC',NULL,14.90,NULL,0,'pix','mercadopago',NULL,'2026-08-27 18:16:17','paid','CPF','2026-08-27 18:16:17',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,'12053831','2026-08-27 18:16:17','2026-08-27 18:16:17'),(16,11,'pending',522.40,'PAC',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-28 00:35:18','paid','CPF','2026-08-28 00:35:18',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,'12053831','2026-08-28 00:35:18','2026-08-28 00:35:18'),(17,17,'pending',4654.99,'PAC',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-28 00:51:17','paid','CPF','2026-08-28 00:51:17','COMP-000007',NULL,NULL,NULL,'issued','email_sent',NULL,NULL,NULL,'01310-100','2026-08-28 00:51:17','2026-08-28 12:34:12'),(18,11,'pending',5034.05,'PAC',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-28 02:05:11','paid','CPF','2026-08-28 02:05:11','COMP-000022',NULL,NULL,NULL,'issued','email_failed: SMTP connect() failed. https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting SMTP server error: Failed to connect to server SMTP code: 111 Additional SMTP info: Conexão recusada',NULL,NULL,NULL,'12053831','2026-08-28 02:05:11','2026-08-28 13:06:03'),(19,19,'pending',911.71,'PAC',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-28 02:36:37','paid','CPF','2026-08-28 02:36:37','COMP-000009',NULL,NULL,NULL,'issued','email_sent',NULL,NULL,NULL,'01310100','2026-08-28 02:36:37','2026-08-28 02:36:37'),(20,20,'pending',911.71,'PAC',NULL,0.00,NULL,0,'pix','mercadopago',NULL,'2026-08-28 02:38:47','paid','CPF','2026-08-28 02:38:47','COMP-000019',NULL,NULL,NULL,'issued','email_sent',NULL,NULL,NULL,'01310100','2026-08-28 02:38:47','2026-08-28 12:31:26'),(21,20,'pending',4899.99,'Sedex',NULL,0.00,NULL,0,'credit','mercadopago',NULL,'2026-08-28 02:38:49','pending','CPF','2026-08-28 02:38:49','COMP-000024',NULL,NULL,NULL,'issued','email_sent',NULL,NULL,NULL,'01310100','2026-08-28 02:38:49','2026-08-28 20:51:19');
/*!40000 ALTER TABLE `e5_orders` ENABLE KEYS */;
UNLOCK TABLES;
LOCK TABLES e5_orders WRITE;
ALTER TABLE e5_orders ADD guest_name VARCHAR(120) NULL AFTER user_id;
ALTER TABLE e5_orders ADD guest_email VARCHAR(120) NULL AFTER guest_name;
UNLOCK TABLES;
LOCK TABLES e5_orders WRITE;
ALTER TABLE e5_orders MODIFY user_id int(11) NULL;
UNLOCK TABLES;

--
-- Table structure for table `e5_coupons`
--

DROP TABLE IF EXISTS `e5_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expires_at` timestamp NULL DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `uses_current` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `e5_password_reset_tokens`
--

DROP TABLE IF EXISTS `e5_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_reset_user` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_password_reset_tokens`
--

LOCK TABLES `e5_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `e5_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `e5_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_payment_gateways`
--

DROP TABLE IF EXISTS `e5_payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_payment_gateways` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) NOT NULL COMMENT 'mercadopago, asaas',
  `display_name` varchar(100) NOT NULL COMMENT 'Nome amigável para exibição',
  `access_token_key` varchar(100) DEFAULT NULL COMMENT 'Reference key for encrypted token',
  `public_key_key` varchar(100) DEFAULT NULL COMMENT 'Reference key for encrypted public key',
  `webhook_secret_key` varchar(100) DEFAULT NULL COMMENT 'Reference key for encrypted webhook secret',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `is_configured` tinyint(1) NOT NULL DEFAULT 0,
  `last_health_check` timestamp NULL DEFAULT NULL,
  `health_check_status` enum('success','failure','pending') DEFAULT NULL,
  `health_check_message` text DEFAULT NULL,
  `supports_cpf` tinyint(1) NOT NULL DEFAULT 1,
  `supports_cnpj` tinyint(1) NOT NULL DEFAULT 1,
  `webhook_url` varchar(255) DEFAULT NULL,
  `api_base_url` varchar(255) DEFAULT NULL,
  `sandbox_mode` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_name` (`gateway_name`),
  KEY `idx_active` (`is_active`),
  KEY `idx_configured` (`is_configured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_payment_gateways`
--

LOCK TABLES `e5_payment_gateways` WRITE;
/*!40000 ALTER TABLE `e5_payment_gateways` DISABLE KEYS */;
/*!40000 ALTER TABLE `e5_payment_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_product_images`
--

DROP TABLE IF EXISTS `e5_product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `e5_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_product_images`
--

LOCK TABLES `e5_product_images` WRITE;
/*!40000 ALTER TABLE `e5_product_images` DISABLE KEYS */;
INSERT INTO `e5_product_images` VALUES (1,1,'/assets/img/products/galaxy-s25.jpg',1,'2026-08-22 02:33:52'),(2,2,'/assets/img/products/iphone-16.jpg',1,'2026-08-22 02:33:52'),(3,3,'/assets/img/products/nitro-v15.jpg',1,'2026-08-22 02:33:52'),(4,4,'/assets/img/products/zenbook-14.jpg',1,'2026-08-22 02:33:52'),(5,5,'/assets/img/products/g502.jpg',1,'2026-08-22 02:33:52'),(6,6,'/assets/img/products/teclado-redragon.jpg',1,'2026-08-22 02:33:52'),(7,7,'/assets/img/products/ryzen-7800x3d.jpg',1,'2026-08-22 02:33:52'),(8,8,'/assets/img/products/rtx-4070-super.jpg',1,'2026-08-22 02:33:52'),(9,9,'/assets/img/products/cloud-iii.jpg',1,'2026-08-22 02:33:52'),(10,10,'/assets/img/products/flip-7.jpg',1,'2026-08-22 02:33:52'),(11,1,'/assets/img/products/galaxy-s25-2.jpg',0,'2026-08-22 02:33:52'),(12,5,'/assets/img/products/g502-2.jpg',0,'2026-08-22 02:33:52');
/*!40000 ALTER TABLE `e5_product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_products`
--

DROP TABLE IF EXISTS `e5_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `e5_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_products`
--

LOCK TABLES `e5_products` WRITE;
/*!40000 ALTER TABLE `e5_products` DISABLE KEYS */;
INSERT INTO `e5_products` VALUES (1,1,'Smartphone Galaxy S25 256GB','smartphone-galaxy-s25-256gb','Smartphone premium com tela AMOLED 6.2\" e câmera 200MP.','Samsung',4599.90,4999.00,23,1,'2026-08-22 02:33:52','2026-08-27 17:07:58'),(2,1,'iPhone 16 128GB','iphone-16-128gb','iPhone 16 com chip A18 e sistema de câmeras avançado.','Apple',5299.00,NULL,11,1,'2026-08-22 02:33:52','2026-08-28 02:05:11'),(3,2,'Notebook Nitro V15 i7','notebook-nitro-v15-i7','Notebook gamer com RTX 4060, 16GB RAM e SSD 512GB.','Acer',4899.99,5399.00,5,1,'2026-08-22 02:33:52','2026-08-28 02:38:49'),(4,2,'Ultrabook Zenbook 14 OLED','ultrabook-zenbook-14-oled','Ultrabook leve com tela OLED 2.8K e bateria de longa duração.','ASUS',6499.00,NULL,8,0,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(5,3,'Mouse Gamer Logitech G502','mouse-gamer-logitech-g502','Mouse gamer com sensor HERO 25K e 11 botões programáveis.','Logitech',349.90,399.90,76,0,'2026-08-22 02:33:52','2026-08-28 02:38:47'),(6,3,'Teclado Mecânico Redragon','teclado-mecanico-redragon','Teclado mecânico RGB com switches Redragon e layout ABNT2.','Redragon',259.90,NULL,57,0,'2026-08-22 02:33:52','2026-08-28 02:38:47'),(7,4,'Processador Ryzen 7 7800X3D','processador-ryzen-7-7800x3d','Processador de 8 núcleos para games com cache 3D.','AMD',2699.90,2899.90,20,1,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(8,4,'Placa de Vídeo RTX 4070 Super','placa-de-video-rtx-4070-super','GPU com 12GB GDDR6X e suporte a DLSS 3.','NVIDIA',4399.90,NULL,12,0,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(9,5,'Headset Gamer HyperX Cloud III','headset-gamer-hyperx-cloud-iii','Headset com som 7.1 surround e microfone com cancelamento de ruído.','HyperX',699.90,799.90,45,1,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(10,5,'Caixa de Som JBL Flip 7','caixa-de-som-jbl-flip-7','Caixa bluetooth portátil à prova d\'água com 12h de bateria.','JBL',549.90,NULL,34,0,'2026-08-22 02:33:52','2026-08-28 00:35:18');
/*!40000 ALTER TABLE `e5_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_seller_profile`
--

DROP TABLE IF EXISTS `e5_seller_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_seller_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type` enum('CPF','CNPJ') NOT NULL DEFAULT 'CPF',
  `document_number` varchar(18) NOT NULL COMMENT 'CPF: 000.000.000-00 | CNPJ: 00.000.000/0000-00',
  `legal_name` varchar(120) DEFAULT NULL COMMENT 'Razão Social (MEI only)',
  `trade_name` varchar(120) DEFAULT NULL COMMENT 'Nome Fantasia (MEI only)',
  `state_registration` varchar(20) DEFAULT NULL COMMENT 'Inscrição Estadual (optional)',
  `tax_regime` enum('CPF','MEI') NOT NULL DEFAULT 'CPF',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `nfe_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'NF-e emission active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_document` (`document_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_seller_profile`
--

LOCK TABLES `e5_seller_profile` WRITE;
/*!40000 ALTER TABLE `e5_seller_profile` DISABLE KEYS */;
INSERT INTO `e5_seller_profile` VALUES (1,'CPF','000.000.000-00',NULL,NULL,NULL,'CPF',1,0,'2026-08-27 01:27:46','2026-08-27 15:27:06');
/*!40000 ALTER TABLE `e5_seller_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_settings`
--

DROP TABLE IF EXISTS `e5_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` mediumtext DEFAULT NULL COMMENT 'Encrypted for sensitive keys (nfe_api_key, superfrete_token)',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_settings`
--

LOCK TABLES `e5_settings` WRITE;
/*!40000 ALTER TABLE `e5_settings` DISABLE KEYS */;
INSERT INTO `e5_settings` VALUES ('comprovante_counter','24','2026-08-28 20:51:19'),('fee_disclaimer','Taxas são estimativas. Confirme com seu gateway de pagamento.','2026-08-27 01:48:27'),('nfe_environment','homologacao','2026-08-27 01:27:46'),('nfe_provider','disabled','2026-08-27 16:16:43'),('payment_fee_cpf_ESTIMATE','3.99','2026-08-27 01:48:27'),('payment_fee_mei_ESTIMATE','2.99','2026-08-27 01:48:27'),('payment_gateway','mercadopago','2026-08-27 01:27:46'),('superfrete_sandbox','1','2026-08-29 00:00:00'),('tax_regime','CPF','2026-08-27 16:16:43');
/*!40000 ALTER TABLE `e5_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_system_change_log`
--

DROP TABLE IF EXISTS `e5_system_change_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_system_change_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `change_type` enum('regime','gateway','credentials') NOT NULL DEFAULT 'regime',
  `user_id` int(11) NOT NULL,
  `regime_anterior` enum('CPF','MEI') NOT NULL,
  `regime_novo` enum('CPF','MEI') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `gateway_name` varchar(50) DEFAULT NULL,
  `config_before` text DEFAULT NULL,
  `config_after` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_regime_log_user` (`user_id`),
  KEY `idx_change_type` (`change_type`),
  CONSTRAINT `fk_regime_log_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_system_change_log`
--

LOCK TABLES `e5_system_change_log` WRITE;
/*!40000 ALTER TABLE `e5_system_change_log` DISABLE KEYS */;
INSERT INTO `e5_system_change_log` VALUES (1,'regime',1,'CPF','MEI','0.0.0.0','',1,NULL,'11222333000181',NULL,NULL,NULL,'2026-08-27 13:52:02'),(2,'regime',1,'CPF','MEI','0.0.0.0','',0,'Melhor Envio: Token inválido ou expirado.','11222333000181',NULL,NULL,NULL,'2026-08-27 13:52:25'),(3,'regime',1,'CPF','MEI','0.0.0.0','',1,NULL,'11222333000181',NULL,NULL,NULL,'2026-08-27 15:26:36'),(4,'regime',1,'CPF','MEI','0.0.0.0','',0,'Melhor Envio: Token inválido ou expirado.','11222333000181',NULL,NULL,NULL,'2026-08-27 15:27:07'),(5,'regime',1,'CPF','MEI','0.0.0.0','',0,'NF-e: Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.','11222333000181',NULL,NULL,NULL,'2026-08-27 15:44:00'),(6,'regime',1,'CPF','MEI','0.0.0.0','',0,'NF-e: Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.','11222333000181',NULL,NULL,NULL,'2026-08-27 15:45:02'),(7,'regime',1,'CPF','MEI','0.0.0.0','',0,'Melhor Envio: Token inválido ou expirado.','11222333000181',NULL,NULL,NULL,'2026-08-27 15:45:02'),(8,'regime',1,'CPF','MEI','0.0.0.0','',0,'NF-e: Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.; Melhor Envio: Token inválido ou expirado.','11222333000181',NULL,NULL,NULL,'2026-08-27 15:45:03'),(9,'regime',1,'CPF','MEI','0.0.0.0','',0,'NF-e: Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.','11222333000181',NULL,NULL,NULL,'2026-08-27 16:16:43'),(10,'regime',10,'CPF','MEI','::1','curl/8.21.0',0,'NF-e: Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.','11222333000181',NULL,NULL,NULL,'2026-08-27 23:30:21'),(11,'regime',10,'CPF','MEI','::1','curl/8.21.0',0,'NF-e: Focus NFe: Chave API inválida ou expirada. Verifique sua credencial.','11222333000181',NULL,NULL,NULL,'2026-08-27 23:39:57');
/*!40000 ALTER TABLE `e5_system_change_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_users`
--

DROP TABLE IF EXISTS `e5_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `email` varchar(120) NOT NULL,
  `username` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `postal_code` varchar(10) NOT NULL,
  `street` varchar(120) NOT NULL,
  `number` int(11) NOT NULL,
  `complement` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `privacy_accepted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_users`
--

LOCK TABLES `e5_users` WRITE;
/*!40000 ALTER TABLE `e5_users` DISABLE KEYS */;
INSERT INTO `e5_users` VALUES (1,'Maria Silva','maria.silva@email.com','maria.silva','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','01310-100','Av. Paulista',1000,'Apto 72','2026-08-22 02:33:52','2026-08-22 02:33:52'),(2,'João Pereira','joao.pereira@email.com','joao.pereira','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','20040-020','Rua da Carioca',250,'Sala 5','2026-08-22 02:33:52','2026-08-22 02:33:52'),(3,'Ana Costa','ana.costa@email.com','ana.costa','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','30130-010','Rua da Bahia',120,NULL,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(4,'Carlos Souza','carlos.souza@email.com','carlos.souza','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','40020-000','Av. Sete de Setembro',340,'Fundos','2026-08-22 02:33:52','2026-08-22 02:33:52'),(5,'Fernanda Lima','fernanda.lima@email.com','fernanda.lima','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','50050-000','Av. Boa Viagem',900,'Apto 101','2026-08-22 02:33:52','2026-08-22 02:33:52'),(6,'Rafael Almeida','rafael.almeida@email.com','rafael.almeida','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','60060-000','Av. Beira Mar',1500,'Cobertura','2026-08-22 02:33:52','2026-08-22 02:33:52'),(7,'Juliana Rocha','juliana.rocha@email.com','juliana.rocha','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','70070-000','SIG Sul',10,'Loja 12','2026-08-22 02:33:52','2026-08-22 02:33:52'),(8,'Pedro Martins','pedro.martins@email.com','pedro.martins','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','80080-000','Av. Batel',200,'Apto 33','2026-08-22 02:33:52','2026-08-22 02:33:52'),(9,'Beatriz Nunes','beatriz.nunes@email.com','beatriz.nunes','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer','90090-000','Av. Ipiranga',500,NULL,'2026-08-22 02:33:52','2026-08-22 02:33:52'),(10,'admin','admin@royaltech.com','admin','$2y$12$zLtyKbhRCwBLR.sVBT1AYe4KwczxxHj/vnDjTE2d89nqAddu.ztsi','admin','01310-100','Av. Paulista',1,'Sede','2026-08-22 02:33:52','2026-08-28 14:32:29'),(11,'kaua caetano','kauametradol@gmail.com','Caetano','$2y$10$emPY3aZ3.X3tG5y5qw9XW.MEjhkynIrk8OUUqfiQJIfBYD6gyRA.e','customer','12053831','Avenida Ameletto Marino',301,NULL,'2026-08-22 02:37:31','2026-08-22 02:37:31'),(17,'Cliente TCC','tcc_pdf@test.dev','tcc_pdf','$2y$10$pRPBvNg1v1uERJvDo8w3S.fuIK2PKwYnM7y7sthyqNgVXwMtZhlrW','customer','01310-100','Av Paulista',1000,NULL,'2026-08-28 00:51:17','2026-08-28 00:51:17'),(18,'E2E Tester','e2e_test_20260827_233350@test.dev','e2e_tester_20260827_233350','$2y$10$NIfxwVVAiceh6NBG5c4r6uBCUglplJDAdQM8RNqwCZ7XVj/D3QrOy','customer','01310-100','Av. Paulista',1000,'Sala 42','2026-08-28 02:33:50','2026-08-28 02:33:50'),(19,'E2E Tester','e2e_test_20260827_233636@test.dev','e2e_tester_20260827_233636','$2y$10$OREYK4GI2ttZsPvvPVfOCuu11DltexvZ0EotF8POmCOqUJZk5iM0W','customer','01310-100','Av. Paulista',1000,'Sala 42','2026-08-28 02:36:36','2026-08-28 02:36:36'),(20,'E2E Tester','e2e_test_20260827_233846@test.dev','e2e_tester_20260827_233846','$2y$10$e7VMujx8AVvnInGxYbIyBuENrKaH.SLMp7ut7twl4e9AcFKo1H/Iq','customer','01310-100','Av. Paulista',1000,'Sala 42','2026-08-28 02:38:47','2026-08-28 02:38:47');
/*!40000 ALTER TABLE `e5_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_webhook_log`
--

DROP TABLE IF EXISTS `e5_webhook_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_webhook_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_id` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `signature` varchar(512) DEFAULT NULL,
  `signature_valid` tinyint(1) DEFAULT NULL,
  `processing_status` enum('pending','processed','failed','ignored') NOT NULL DEFAULT 'pending',
  `processing_attempts` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gateway` (`gateway_name`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_status` (`processing_status`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `e5_webhook_log_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `e5_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_webhook_log`
--

LOCK TABLES `e5_webhook_log` WRITE;
/*!40000 ALTER TABLE `e5_webhook_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `e5_webhook_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_wishlist`
--

DROP TABLE IF EXISTS `e5_wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e5_wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist_item` (`user_id`,`product_id`),
  KEY `fk_wishlist_product` (`product_id`),
  CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `e5_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_wishlist`
--

LOCK TABLES `e5_wishlist` WRITE;
/*!40000 ALTER TABLE `e5_wishlist` DISABLE KEYS */;
INSERT INTO `e5_wishlist` VALUES (1,1,3,'2026-08-22 02:33:52'),(2,1,7,'2026-08-22 02:33:52'),(3,2,9,'2026-08-22 02:33:52'),(4,3,1,'2026-08-22 02:33:52');
mysqldump: Couldn't execute 'SHOW FUNCTION STATUS WHERE Db = 'e5_royaltech'': Column count of mysql.proc is wrong. Expected 21, found 20. Created with MariaDB 100108, now running 100432. Please use mysql_upgrade to fix this error (1558)
/*!40000 ALTER TABLE `e5_wishlist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_pending_nfe_orders`
--

DROP TABLE IF EXISTS `v_pending_nfe_orders`;
/*!50001 DROP VIEW IF EXISTS `v_pending_nfe_orders`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_pending_nfe_orders` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `total`,
  1 AS `tax_regime_snapshot`,
  1 AS `status`,
  1 AS `payment_status`,
  1 AS `invoice_status`,
  1 AS `created_at`,
  1 AS `days_pending`,
  1 AS `customer_name`,
  1 AS `customer_email` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'e5_royaltech'
--
