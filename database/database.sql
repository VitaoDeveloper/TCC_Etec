CREATE DATABASE royaltech;
USE royaltech;

CREATE TABLE `users` (
  `id` int(5) PRIMARY KEY AUTO_INCREMENT NOT NULL,
  `name` varchar(80) NOT NULL,
  `email` varchar(50) NOT NULL,
  `username` varchar(30) NOT NULL,
  `password_hash` varchar(50) NOT NULL,
  `postal_code` int(20) NOT NULL,
  `street` int(30) NOT NULL,
  `number` int(5) NOT NULL,
  `complement` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;