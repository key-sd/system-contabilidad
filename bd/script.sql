CREATE DATABASE IF NOT EXISTS `db_contabilidad` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_contabilidad`;

-- Tabla de Usuarios para el Login
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `usuario` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(50) DEFAULT 'Administrador',
    `estado` ENUM('activo', 'inactivo') DEFAULT 'activo',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- user: admin, contra: admin123 xd
INSERT INTO `usuarios` (`nombre`, `usuario`, `email`, `password`, `rol`, `estado`) 
VALUES (
    'Administrador', 
    'admin', 
    'admin@hogaris.com', 
    '$2y$10$PQ3agvC.bJHkn5ZWSBPtBOyZwoNFIeXsxc7e/743fBWiaSoS1mPFa', 
    'Administrador', 
    'activo'
)
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`);
