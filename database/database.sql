DROP DATABASE IF EXISTS royaltech;
CREATE DATABASE royaltech;
USE royaltech;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(5) PRIMARY KEY AUTO_INCREMENT NOT NULL,
  `name` varchar(80) NOT NULL,
  `email` varchar(50) UNIQUE NOT NULL,
  `username` varchar(30) UNIQUE NOT NULL,
  `password` varchar(80) NOT NULL,	
  `postal_code` int(20) NOT NULL,
  `street` varchar(30) NOT NULL,
  `number` int(5) NOT NULL,
  `complement` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
