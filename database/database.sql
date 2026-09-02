-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: e5_royaltech
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_banners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `subtitle` varchar(180) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `link_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_checkout_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `gateway_locked` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tax_regime_locked` enum('CPF','MEI') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cart_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `e5_checkout_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `e5_checkout_sessions_chk_1` CHECK (json_valid(`cart_snapshot`))
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subject` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
-- Table structure for table `e5_coupons`
--

DROP TABLE IF EXISTS `e5_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `discount_type` enum('percentage','fixed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expires_at` timestamp NULL DEFAULT NULL,
  `max_uses` int DEFAULT NULL COMMENT 'NULL = unlimited',
  `uses_current` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_coupons`
--

LOCK TABLES `e5_coupons` WRITE;
/*!40000 ALTER TABLE `e5_coupons` DISABLE KEYS */;
INSERT INTO `e5_coupons` VALUES (1,'WELCOME10','percentage',10.00,NULL,NULL,1,1,'2026-09-02 20:58:46');
/*!40000 ALTER TABLE `e5_coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_cpf_revenue_tracking`
--

DROP TABLE IF EXISTS `e5_cpf_revenue_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_cpf_revenue_tracking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `month_year` varchar(7) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Format: YYYY-MM',
  `total_revenue` decimal(12,2) NOT NULL DEFAULT '0.00',
  `order_count` int NOT NULL DEFAULT '0',
  `last_order_id` int DEFAULT NULL,
  `alert_threshold_reached` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_encrypted_settings` (
  `setting_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `encrypted_value` text COLLATE utf8mb4_general_ci NOT NULL,
  `encryption_version` varchar(10) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'v1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_encrypted_settings`
--

LOCK TABLES `e5_encrypted_settings` WRITE;
/*!40000 ALTER TABLE `e5_encrypted_settings` DISABLE KEYS */;
INSERT INTO `e5_encrypted_settings` VALUES ('mercadopago_access_token','gx3zAWAsEobRQx4D0Shz/+/ZHeCBSDlXxwcbpiYGKKJBx8XL4zL/vXo8L8CqIvZqJmqle4LvcxWfJ4qCwMuyapZC4Z9ZjLsChPVOKCalebYp0sDXI48tpeKHSr/DdsNCMe8RDq3fXCvWVQc=','v2','2026-08-27 15:30:22','2026-09-02 21:49:06'),('mercadopago_public_key','fwAaD+H06SufDQWLiqIKmru9Xj5ttnhmlvFC4dzN6O1QuQrBv9jeXnjGBojkgjFOsa0EXuyv2377b7EZHXKw9cu9muZGJjS2zR0bVg==','v2','2026-09-02 21:49:06','2026-09-02 21:49:06'),('mercadopago_webhook_secret','dHM7l82fzFFOnETS6LgQZ0Kw0tb0ah3Mailo7fn2mkJy7Xq3hAV3PwOtkGm0/oe35hS6pZVvrHNZ/4W/pfRPen6mqP4b1HDAhRt5+syZdAe6LWcbo/V3kg6gP+DQd94m','v2','2026-09-02 21:01:44','2026-09-02 21:49:06'),('nfe_api_key','x7/Med+9n3GOXsm699v0h/2WzBJ3kWs9pEppzZ1OqyZ0fIGuqvtgY0Efcq00PSTOtHNKy7I/gZ0=','v1','2026-08-27 15:26:36','2026-08-27 15:26:36'),('test_key_1787838852','PLWZf6U2RiXLAFqulJV4wnKVRnF+O3cZzVGlxVFxGQNqMlYvOqr0qUmqxZNFc123gKV7vBs=','v1','2026-08-27 13:54:12','2026-08-27 13:54:12'),('test_key_1787844253','+Qo8uBfFIYPe7bWBRNklOhELBWfOBwhYCQajviTRkrlO5I0MwxfovvFXHL3s28jKdWDf954=','v1','2026-08-27 15:24:13','2026-08-27 15:24:13');
/*!40000 ALTER TABLE `e5_encrypted_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_gateway_fees`
--

DROP TABLE IF EXISTS `e5_gateway_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_gateway_fees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'mercadopago, asaas, etc',
  `document_type` enum('CPF','CNPJ') COLLATE utf8mb4_general_ci NOT NULL,
  `fee_percentage` decimal(5,2) NOT NULL,
  `fee_fixed` decimal(10,2) NOT NULL DEFAULT '0.00',
  `source_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Link to official pricing page',
  `verified_at` timestamp NULL DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `verification_status` enum('current','outdated','unverified') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unverified',
  `is_estimate` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_newsletter` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_items_order` (`order_id`),
  KEY `fk_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `e5_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `e5_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_order_items`
--

LOCK TABLES `e5_order_items` WRITE;
/*!40000 ALTER TABLE `e5_order_items` DISABLE KEYS */;
INSERT INTO `e5_order_items` VALUES (1,1,2,1,4999.00),(2,2,6,1,259.90),(3,3,3,1,4899.99),(4,4,5,1,349.90),(5,5,9,1,699.90),(6,6,2,1,5299.00),(7,7,1,1,4599.90),(8,8,2,1,5299.00),(10,11,6,1,259.90),(15,16,10,1,549.90),(16,17,3,1,4899.99),(17,18,2,1,5299.00),(18,19,5,2,349.90),(19,19,6,1,259.90),(20,20,5,2,349.90),(21,20,6,1,259.90),(22,21,3,1,4899.99),(25,22,5,1,349.90),(26,22,6,1,259.90),(27,24,2,1,5299.00);
/*!40000 ALTER TABLE `e5_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_orders`
--

DROP TABLE IF EXISTS `e5_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `guest_name` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `guest_email` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','paid','shipped','delivered','canceled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `total` decimal(10,2) NOT NULL,
  `shipping_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shipping_carrier` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping_delivery_time` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shipping_is_estimated` tinyint(1) NOT NULL DEFAULT '0',
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gateway_used` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gateway_transaction_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gateway_captured_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_status` enum('pending','paid','refunded') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `tax_regime_snapshot` enum('CPF','MEI') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'CPF',
  `regime_captured_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `invoice_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'NF-e number',
  `invoice_key` varchar(44) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'NF-e access key',
  `invoice_pdf_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'NF-e PDF download URL',
  `invoice_xml_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'NF-e XML download URL',
  `invoice_status` enum('pending','issued','error','canceled') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `invoice_error_message` text COLLATE utf8mb4_general_ci COMMENT 'Error details if emission failed',
  `shipping_neighborhood` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shipping_city` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shipping_state` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shipping_postal_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `coupon_code` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_orders_user` (`user_id`),
  KEY `idx_gateway_used` (`gateway_used`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_orders`
--

LOCK TABLES `e5_orders` WRITE;
/*!40000 ALTER TABLE `e5_orders` DISABLE KEYS */;
INSERT INTO `e5_orders` VALUES (22,1,'Teste Convidado','teste@email.com','paid',499.90,'PAC','Correios',29.90,NULL,0,'pix','mercadopago',NULL,'2026-09-02 21:07:32','paid','CPF','2026-09-02 21:07:32','COMP-000000',NULL,NULL,NULL,'issued','email_sent',NULL,NULL,NULL,NULL,NULL,'2026-09-02 21:07:32','2026-09-02 21:08:49'),(23,NULL,'Cliente Teste','cliente@teste.com','pending',100.00,'PAC','Correios',20.00,NULL,0,'pix','mercadopago',NULL,'2026-09-02 21:14:33','pending','CPF','2026-09-02 21:14:33',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-02 21:14:33','2026-09-02 21:14:33'),(24,1,NULL,NULL,'paid',5299.00,'PAC','Correios',29.90,NULL,0,'pix','mercadopago','PAY01M1J1P0NP1ZP6AVVE4KY0G8XM','2026-09-02 21:51:54','paid','CPF','2026-09-02 21:51:54',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-02 21:51:54','2026-09-02 21:51:54');
/*!40000 ALTER TABLE `e5_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_password_reset_tokens`
--

DROP TABLE IF EXISTS `e5_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_password_reset_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_payment_gateways` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'mercadopago, asaas',
  `display_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nome amigável para exibição',
  `access_token_key` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Reference key for encrypted token',
  `public_key_key` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Reference key for encrypted public key',
  `webhook_secret_key` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Reference key for encrypted webhook secret',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_configured` tinyint(1) NOT NULL DEFAULT '0',
  `last_health_check` timestamp NULL DEFAULT NULL,
  `health_check_status` enum('success','failure','pending') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `health_check_message` text COLLATE utf8mb4_general_ci,
  `supports_cpf` tinyint(1) NOT NULL DEFAULT '1',
  `supports_cnpj` tinyint(1) NOT NULL DEFAULT '1',
  `webhook_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_base_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sandbox_mode` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
INSERT INTO `e5_product_images` VALUES (1,1,'/assets/img/products/galaxy-s25.jpg',1,'2026-08-28 17:09:06'),(2,2,'/assets/img/products/iphone-16.jpg',1,'2026-08-28 17:09:06'),(3,3,'/assets/img/products/nitro-v15.jpg',1,'2026-08-28 17:09:06'),(4,4,'/assets/img/products/zenbook-14.jpg',1,'2026-08-28 17:09:06'),(5,5,'/assets/img/products/g502.jpg',1,'2026-08-28 17:09:06'),(6,6,'/assets/img/products/teclado-redragon.jpg',1,'2026-08-28 17:09:06'),(7,7,'/assets/img/products/ryzen-7800x3d.jpg',1,'2026-08-28 17:09:06'),(8,8,'/assets/img/products/rtx-4070-super.jpg',1,'2026-08-28 17:09:06'),(9,9,'/assets/img/products/cloud-iii.jpg',1,'2026-08-28 17:09:06'),(10,10,'/assets/img/products/flip-7.jpg',1,'2026-08-28 17:09:06'),(11,1,'/assets/img/products/galaxy-s25-2.jpg',0,'2026-08-28 17:09:06'),(12,5,'/assets/img/products/g502-2.jpg',0,'2026-08-28 17:09:06');
/*!40000 ALTER TABLE `e5_product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_products`
--

DROP TABLE IF EXISTS `e5_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `brand` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
INSERT INTO `e5_products` VALUES (1,1,'Smartphone Galaxy S25 256GB','smartphone-galaxy-s25-256gb','Smartphone premium com tela AMOLED 6.2\" e cÃ¢mera 200MP.','Samsung',4599.90,4999.00,25,1,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(2,1,'iPhone 16 128GB','iphone-16-128gb','iPhone 16 com chip A18 e sistema de cÃ¢meras avanÃ§ado.','Apple',5299.00,NULL,15,1,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(3,2,'Notebook Nitro V15 i7','notebook-nitro-v15-i7','Notebook gamer com RTX 4060, 16GB RAM e SSD 512GB.','Acer',4899.99,5399.00,10,1,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(4,2,'Ultrabook Zenbook 14 OLED','ultrabook-zenbook-14-oled','Ultrabook leve com tela OLED 2.8K e bateria de longa duraÃ§Ã£o.','ASUS',6499.00,NULL,8,0,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(5,3,'Mouse Gamer Logitech G502','mouse-gamer-logitech-g502','Mouse gamer com sensor HERO 25K e 11 botÃµes programÃ¡veis.','Logitech',349.90,399.90,80,0,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(6,3,'Teclado MecÃ¢nico Redragon','teclado-mecanico-redragon','Teclado mecÃ¢nico RGB com switches Redragon e layout ABNT2.','Redragon',259.90,NULL,60,0,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(7,4,'Processador Ryzen 7 7800X3D','processador-ryzen-7-7800x3d','Processador de 8 nÃºcleos para games com cache 3D.','AMD',2699.90,2899.90,20,1,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(8,4,'Placa de VÃ­deo RTX 4070 Super','placa-de-video-rtx-4070-super','GPU com 12GB GDDR6X e suporte a DLSS 3.','NVIDIA',4399.90,NULL,12,0,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(9,5,'Headset Gamer HyperX Cloud III','headset-gamer-hyperx-cloud-iii','Headset com som 7.1 surround e microfone com cancelamento de ruÃ­do.','HyperX',699.90,799.90,45,1,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(10,5,'Caixa de Som JBL Flip 7','caixa-de-som-jbl-flip-7','Caixa bluetooth portÃ¡til Ã  prova d\'Ã¡gua com 12h de bateria.','JBL',549.90,NULL,35,0,'2026-08-28 17:09:06','2026-08-28 17:09:06');
/*!40000 ALTER TABLE `e5_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_seller_profile`
--

DROP TABLE IF EXISTS `e5_seller_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_seller_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_type` enum('CPF','CNPJ') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'CPF',
  `document_number` varchar(18) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'CPF: 000.000.000-00 | CNPJ: 00.000.000/0000-00',
  `legal_name` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Razão Social (MEI only)',
  `trade_name` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nome Fantasia (MEI only)',
  `state_registration` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Inscrição Estadual (optional)',
  `tax_regime` enum('CPF','MEI') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'CPF',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `nfe_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'NF-e emission active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_document` (`document_number`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_seller_profile`
--

LOCK TABLES `e5_seller_profile` WRITE;
/*!40000 ALTER TABLE `e5_seller_profile` DISABLE KEYS */;
INSERT INTO `e5_seller_profile` VALUES (1,'CPF','000.000.000-00',NULL,NULL,NULL,'CPF',1,0,'2026-08-28 17:09:06','2026-08-28 17:09:06');
/*!40000 ALTER TABLE `e5_seller_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_settings`
--

DROP TABLE IF EXISTS `e5_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_settings` (
  `setting_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` mediumtext COLLATE utf8mb4_general_ci COMMENT 'Encrypted for sensitive keys (nfe_api_key, melhor_envio_token)',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_settings`
--

LOCK TABLES `e5_settings` WRITE;
/*!40000 ALTER TABLE `e5_settings` DISABLE KEYS */;
INSERT INTO `e5_settings` VALUES ('comprovante_counter','1','2026-09-02 21:08:33'),('fee_disclaimer','Taxas sÃ£o estimativas. Confirme com seu gateway de pagamento.','2026-08-28 17:09:06'),('melhor_envio_table','public','2026-08-28 17:09:06'),('nfe_environment','homologacao','2026-08-28 17:09:06'),('nfe_provider','disabled','2026-08-28 17:09:06'),('payment_fee_cpf','3.99','2026-08-28 17:09:06'),('payment_fee_mei','2.99','2026-08-28 17:09:06'),('payment_gateway','mercadopago','2026-08-28 17:09:06'),('tax_regime','CPF','2026-08-28 17:09:06');
/*!40000 ALTER TABLE `e5_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_system_change_log`
--

DROP TABLE IF EXISTS `e5_system_change_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_system_change_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `change_type` enum('regime','gateway','credentials') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'regime',
  `user_id` int NOT NULL,
  `regime_anterior` enum('CPF','MEI') COLLATE utf8mb4_general_ci NOT NULL,
  `regime_novo` enum('CPF','MEI') COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `success` tinyint(1) NOT NULL DEFAULT '1',
  `error_message` text COLLATE utf8mb4_general_ci,
  `cnpj` varchar(18) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gateway_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `config_before` text COLLATE utf8mb4_general_ci,
  `config_after` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_regime_log_user` (`user_id`),
  KEY `idx_change_type` (`change_type`),
  CONSTRAINT `fk_regime_log_user` FOREIGN KEY (`user_id`) REFERENCES `e5_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_system_change_log`
--

LOCK TABLES `e5_system_change_log` WRITE;
/*!40000 ALTER TABLE `e5_system_change_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `e5_system_change_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_users`
--

DROP TABLE IF EXISTS `e5_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('customer','admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'customer',
  `postal_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `street` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `number` int NOT NULL,
  `complement` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_users`
--

LOCK TABLES `e5_users` WRITE;
/*!40000 ALTER TABLE `e5_users` DISABLE KEYS */;
INSERT INTO `e5_users` VALUES (1,'Maria Silva','maria.silva@email.com','maria.silva','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','01310-100','Av. Paulista',1000,'Apto 72','2026-08-28 17:09:06','2026-08-28 17:09:06'),(2,'JoÃ£o Pereira','joao.pereira@email.com','joao.pereira','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','20040-020','Rua da Carioca',250,'Sala 5','2026-08-28 17:09:06','2026-08-28 17:09:06'),(3,'Ana Costa','ana.costa@email.com','ana.costa','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','30130-010','Rua da Bahia',120,NULL,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(4,'Carlos Souza','carlos.souza@email.com','carlos.souza','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','40020-000','Av. Sete de Setembro',340,'Fundos','2026-08-28 17:09:06','2026-08-28 17:09:06'),(5,'Fernanda Lima','fernanda.lima@email.com','fernanda.lima','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','50050-000','Av. Boa Viagem',900,'Apto 101','2026-08-28 17:09:06','2026-08-28 17:09:06'),(6,'Rafael Almeida','rafael.almeida@email.com','rafael.almeida','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','60060-000','Av. Beira Mar',1500,'Cobertura','2026-08-28 17:09:06','2026-08-28 17:09:06'),(7,'Juliana Rocha','juliana.rocha@email.com','juliana.rocha','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','70070-000','SIG Sul',10,'Loja 12','2026-08-28 17:09:06','2026-08-28 17:09:06'),(8,'Pedro Martins','pedro.martins@email.com','pedro.martins','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','80080-000','Av. Batel',200,'Apto 33','2026-08-28 17:09:06','2026-08-28 17:09:06'),(9,'Beatriz Nunes','beatriz.nunes@email.com','beatriz.nunes','$2y$12$uepQrlzkKUwKi9LlJz27huh/tEBb.Vd5mtDdBW2/AoFi7tluip3LK','customer','90090-000','Av. Ipiranga',500,NULL,'2026-08-28 17:09:06','2026-08-28 17:09:06'),(10,'admin','admin@royaltech.com','admin','$2y$12$UehN4Klg1RkiCF401Cyz/OlvtpiFCFb1DoBQdq1N3b0XXwd4zmRyW','admin','01310-100','Av. Paulista',1,'Sede','2026-08-28 17:09:06','2026-08-28 17:09:06');
/*!40000 ALTER TABLE `e5_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_webhook_log`
--

DROP TABLE IF EXISTS `e5_webhook_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_webhook_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `event_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `signature` varchar(512) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `signature_valid` tinyint(1) DEFAULT NULL,
  `processing_status` enum('pending','processed','failed','ignored') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `processing_attempts` int NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_general_ci,
  `order_id` int DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gateway` (`gateway_name`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_status` (`processing_status`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `e5_webhook_log_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `e5_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `e5_webhook_log_chk_1` CHECK (json_valid(`payload`))
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e5_webhook_log`
--

LOCK TABLES `e5_webhook_log` WRITE;
/*!40000 ALTER TABLE `e5_webhook_log` DISABLE KEYS */;
INSERT INTO `e5_webhook_log` VALUES (1,'mercadopago','unknown',NULL,'{\"test\":\"payload\"}',NULL,NULL,'processed',0,NULL,NULL,NULL,'2026-09-01 23:58:23'),(2,'mercadopago','unknown',NULL,'{\"test\":\"without_signature\"}',NULL,NULL,'failed',0,'Missing x-signature header',NULL,NULL,'2026-09-02 19:10:11'),(3,'mercadopago','unknown',NULL,'{\"action\":\"payment.created\",\"data\":{\"id\":\"test123\",\"type\":\"payment\"}}','ts=1234567890,v1=fake_invalid_signature',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 19:38:53'),(4,'mercadopago','unknown',NULL,'{\"type\":\"payment\",\"data\":{\"id\":\"123456789\"}}',NULL,NULL,'failed',0,'Missing x-signature header',NULL,NULL,'2026-09-02 20:54:59'),(5,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788385963,v1=20256853a0180657b5b137e3aa3a6c60b4ca766b2c380957cc5ce7768a0ec729',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:52:50'),(6,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788385963,v1=20256853a0180657b5b137e3aa3a6c60b4ca766b2c380957cc5ce7768a0ec729',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:53:26'),(7,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788385963,v1=20256853a0180657b5b137e3aa3a6c60b4ca766b2c380957cc5ce7768a0ec729',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:53:54'),(8,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788386051,v1=a56d247e4d0b0539c4343227fa6750daf9365e652a6033c975a518b3e1d02ede',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:54:17'),(9,'mercadopago','payment',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788386051,v1=a56d247e4d0b0539c4343227fa6750daf9365e652a6033c975a518b3e1d02ede',1,'processed',0,NULL,NULL,NULL,'2026-09-02 21:55:05'),(10,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788386051,v1=a56d247e4d0b0539c4343227fa6750daf9365e652a6033c975a518b3e1d02ede',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:55:17'),(11,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788386051,v1=a56d247e4d0b0539c4343227fa6750daf9365e652a6033c975a518b3e1d02ede',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:55:32'),(12,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J1P0NP1ZP6AVVE4KY0G8XM\"}}','ts=1788386051,v1=a56d247e4d0b0539c4343227fa6750daf9365e652a6033c975a518b3e1d02ede',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 21:56:48'),(13,'mercadopago','unknown',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J3HEDCNBXFX4N87XA71DY4\"}}','ts=1788387868,v1=1d4269b961caac59f61e4a5634c4069d2077d846b2c693133a8afefa2f3f9e34',0,'failed',0,'Invalid signature',NULL,NULL,'2026-09-02 22:24:33'),(14,'mercadopago','payment',NULL,'{\"id\":123456789,\"type\":\"payment\",\"data\":{\"id\":\"PAY01M1J3HEDCNBXFX4N87XA71DY4\"}}','ts=1788387868,v1=1d4269b961caac59f61e4a5634c4069d2077d846b2c693133a8afefa2f3f9e34',1,'processed',0,NULL,NULL,NULL,'2026-09-02 22:25:07');
/*!40000 ALTER TABLE `e5_webhook_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e5_wishlist`
--

DROP TABLE IF EXISTS `e5_wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `e5_wishlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
INSERT INTO `e5_wishlist` VALUES (1,1,3,'2026-08-28 17:09:06'),(2,1,7,'2026-08-28 17:09:06'),(3,2,9,'2026-08-28 17:09:06'),(4,3,1,'2026-08-28 17:09:06');
/*!40000 ALTER TABLE `e5_wishlist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_pending_nfe_orders`
--

DROP TABLE IF EXISTS `v_pending_nfe_orders`;
/*!50001 DROP VIEW IF EXISTS `v_pending_nfe_orders`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
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
 1 AS `customer_email`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_pending_nfe_orders`
--

/*!50001 DROP VIEW IF EXISTS `v_pending_nfe_orders`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_pending_nfe_orders` AS select `o`.`id` AS `id`,`o`.`user_id` AS `user_id`,`o`.`total` AS `total`,`o`.`tax_regime_snapshot` AS `tax_regime_snapshot`,`o`.`status` AS `status`,`o`.`payment_status` AS `payment_status`,`o`.`invoice_status` AS `invoice_status`,`o`.`created_at` AS `created_at`,(to_days(now()) - to_days(`o`.`created_at`)) AS `days_pending`,`u`.`name` AS `customer_name`,`u`.`email` AS `customer_email` from (`e5_orders` `o` join `e5_users` `u` on((`u`.`id` = `o`.`user_id`))) where ((`o`.`invoice_status` = 'pending') and (`o`.`payment_status` = 'paid') and (`o`.`status` in ('paid','shipped','delivered')) and (`o`.`tax_regime_snapshot` = 'MEI')) order by `o`.`created_at` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-02 22:27:43
