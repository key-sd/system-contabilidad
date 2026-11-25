SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `db_contabilidad` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_contabilidad`;

-- =====================================================================
--  HOGARIS · SISTEMA CONTABLE CON INVENTARIO PERPETUO
--  Requiere MariaDB 10.3+ / MySQL 8.0.16+ (CHECK, columnas generadas,
--  funciones de ventana, triggers).
--
--  Idea central: se guardan solo los DOCUMENTOS FUENTE (compras, ventas,
--  planilla, partidas). Los libros y estados financieros del Excel
--  (Mayor, Balanza, Balance, Libros de IVA, Inventario) son VISTAS.
-- =====================================================================

-- =====================================================================
-- 1. SEGURIDAD
-- =====================================================================
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

-- =====================================================================
-- 2. EMPRESA Y PARÁMETROS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `empresa` (
    `id` TINYINT PRIMARY KEY DEFAULT 1,
    `razon_social` VARCHAR(150) NOT NULL,
    `nombre_comercial` VARCHAR(100) NULL,
    `nrc` VARCHAR(20) NULL,
    `nit` VARCHAR(20) NULL,
    `giro` VARCHAR(150) NULL,
    `direccion` VARCHAR(255) NULL,
    CONSTRAINT `chk_empresa_unica` CHECK (`id` = 1)
);

INSERT INTO `empresa` (`id`, `razon_social`, `nombre_comercial`, `nrc`)
VALUES (1, 'Comercial Hogaris, S.A. de C.V.', 'Hogaris', '123456-7')
ON DUPLICATE KEY UPDATE `razon_social` = VALUES(`razon_social`);

-- Tasas que el Excel tiene "quemadas" en las fórmulas
CREATE TABLE IF NOT EXISTS `parametros` (
    `clave` VARCHAR(40) PRIMARY KEY,
    `valor` DECIMAL(10,6) NOT NULL,
    `descripcion` VARCHAR(150) NOT NULL
);

INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
('iva',                0.130000, 'IVA (13%)'),
('pago_a_cuenta',      0.017500, 'Pago a cuenta sobre ingresos (1.75%)'),
('margen_utilidad',    0.500000, 'Margen de utilidad sobre costo con IVA para precio de venta'),
('isss_laboral',       0.030000, 'ISSS retención al empleado (3%)'),
('afp_laboral',        0.072500, 'AFP retención al empleado (7.25%)'),
('isss_patronal',      0.075000, 'ISSS cuota patronal (7.5%)'),
('afp_patronal',       0.087500, 'AFP cuota patronal (8.75%)')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- =====================================================================
-- 3. CATÁLOGO DE CUENTAS
--    nivel 1 = rubro, 2 = grupo, 3 = cuenta de mayor (la del Libro Mayor),
--    4 = subcuenta (las que el Libro Diario muestra en la columna PARCIAL)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `cuentas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(10) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `tipo` ENUM('activo','pasivo','patrimonio','ingreso','costo','gasto','liquidadora') NOT NULL,
    `naturaleza` ENUM('deudora','acreedora') NOT NULL,
    `padre_id` INT NULL,
    `nivel` TINYINT NOT NULL,
    `acepta_movimientos` TINYINT(1) NOT NULL DEFAULT 1,
    `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    CONSTRAINT `fk_cuentas_padre` FOREIGN KEY (`padre_id`) REFERENCES `cuentas`(`id`),
    CONSTRAINT `chk_cuentas_nivel` CHECK (`nivel` BETWEEN 1 AND 4)
);

INSERT INTO `cuentas` (`id`,`codigo`,`nombre`,`tipo`,`naturaleza`,`padre_id`,`nivel`,`acepta_movimientos`) VALUES
(1,'1','ACTIVO','activo','deudora',NULL,1,0),
(2,'11','ACTIVO CORRIENTE','activo','deudora',1,2,0),
(3,'1101','Efectivo y Equivalentes','activo','deudora',2,3,0),
(4,'110101','Caja','activo','deudora',3,4,1),
(5,'110102','Bancos','activo','deudora',3,4,1),
(6,'1102','Cuentas por Cobrar','activo','deudora',2,3,1),
(7,'1103','Documentos por Cobrar','activo','deudora',2,3,1),
(8,'1104','Inventario','activo','deudora',2,3,1),
(9,'1105','IVA Crédito Fiscal','activo','deudora',2,3,1),
(10,'1106','Impuestos Pagados por Anticipado','activo','deudora',2,3,0),
(11,'110601','Pago a cuenta','activo','deudora',10,4,1),
(12,'12','ACTIVO NO CORRIENTE','activo','deudora',1,2,0),
(13,'1201','Depósito de Garantía','activo','deudora',12,3,1),
(14,'1202','Propiedad, Planta y Equipo','activo','deudora',12,3,0),
(15,'120201','Mobiliario y Equipo','activo','deudora',14,4,1),
(16,'120202','Equipo de Transporte','activo','deudora',14,4,1),
(17,'1203','Depreciación Acumulada de PPE','activo','acreedora',12,3,1),
(18,'2','PASIVO','pasivo','acreedora',NULL,1,0),
(19,'21','PASIVO CORRIENTE','pasivo','acreedora',18,2,0),
(20,'2101','Retenciones Legales por Pagar','pasivo','acreedora',19,3,0),
(21,'210101','ISSS','pasivo','acreedora',20,4,1),
(22,'210102','AFP','pasivo','acreedora',20,4,1),
(23,'2102','Obligaciones Laborales por Pagar','pasivo','acreedora',19,3,0),
(24,'210201','ISSS patronal','pasivo','acreedora',23,4,1),
(25,'210202','AFP patronal','pasivo','acreedora',23,4,1),
(26,'2103','Impuestos por Pagar','pasivo','acreedora',19,3,0),
(27,'210301','Pago a cuenta','pasivo','acreedora',26,4,1),
(28,'2104','IVA Débito Fiscal','pasivo','acreedora',19,3,1),
(29,'3','PATRIMONIO','patrimonio','acreedora',NULL,1,0),
(30,'31','PATRIMONIO NETO','patrimonio','acreedora',29,2,0),
(31,'3101','Capital Social','patrimonio','acreedora',30,3,1),
(32,'3102','Pérdida del Ejercicio','patrimonio','deudora',30,3,1),
(33,'4','INGRESOS','ingreso','acreedora',NULL,1,0),
(34,'41','INGRESOS DE OPERACIÓN','ingreso','acreedora',33,2,0),
(35,'4101','Ventas','ingreso','acreedora',34,3,1),
(36,'5','COSTOS','costo','deudora',NULL,1,0),
(37,'51','COSTOS DE OPERACIÓN','costo','deudora',36,2,0),
(38,'5101','Costo de Ventas','costo','deudora',37,3,1),
(39,'6','GASTOS','gasto','deudora',NULL,1,0),
(40,'61','GASTOS DE OPERACIÓN','gasto','deudora',39,2,0),
(41,'6101','Gastos de Ventas','gasto','deudora',40,3,1),
(42,'6102','Gastos de Administración','gasto','deudora',40,3,1),
(43,'7','CUENTAS LIQUIDADORAS','liquidadora','acreedora',NULL,1,0),
(44,'71','CUENTAS DE CIERRE','liquidadora','acreedora',43,2,0),
(45,'7101','Pérdidas y Ganancias','liquidadora','acreedora',44,3,1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- =====================================================================
-- 4. TERCEROS
-- =====================================================================
CREATE TABLE IF NOT EXISTS `proveedores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `nrc` VARCHAR(20) NULL,
    `nit_dui` VARCHAR(20) NULL,
    `telefono` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `direccion` VARCHAR(255) NULL,
    `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_proveedor_nrc` (`nrc`)
);

CREATE TABLE IF NOT EXISTS `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `nrc` VARCHAR(20) NULL,
    `nit_dui` VARCHAR(20) NULL,
    `telefono` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `direccion` VARCHAR(255) NULL,
    `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cliente_nrc` (`nrc`)
);

-- =====================================================================
-- 5. LIBRO DIARIO (partidas)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `partidas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero` INT NOT NULL UNIQUE,
    `fecha` DATE NOT NULL,
    `folio` INT NULL,
    `tipo` ENUM('apertura','compra','venta','costo_venta','planilla','provision',
                'depreciacion','liquidacion_iva','cierre','ajuste','otro') NOT NULL DEFAULT 'otro',
    `concepto` VARCHAR(255) NOT NULL,
    `estado` ENUM('borrador','contabilizada','anulada') NOT NULL DEFAULT 'borrador',
    `usuario_id` INT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_partidas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`),
    KEY `idx_partidas_fecha` (`fecha`)
);

CREATE TABLE IF NOT EXISTS `partidas_detalle` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partida_id` INT NOT NULL,
    `orden` TINYINT NOT NULL DEFAULT 1,
    `cuenta_id` INT NOT NULL,
    `debe` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `haber` DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT `fk_pd_partida` FOREIGN KEY (`partida_id`) REFERENCES `partidas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pd_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas`(`id`),
    CONSTRAINT `chk_pd_montos` CHECK (`debe` >= 0 AND `haber` >= 0 AND (`debe` = 0 OR `haber` = 0)),
    KEY `idx_pd_cuenta` (`cuenta_id`)
);

-- =====================================================================
-- 6. PRODUCTOS E INVENTARIO PERPETUO
-- =====================================================================
CREATE TABLE IF NOT EXISTS `categorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(80) NOT NULL,
    `padre_id` INT NULL,
    CONSTRAINT `fk_categorias_padre` FOREIGN KEY (`padre_id`) REFERENCES `categorias`(`id`),
    UNIQUE KEY `uq_categoria` (`padre_id`, `nombre`)
);

-- existencias, valor y costo promedio los mantiene SOLO el trigger del kardex
CREATE TABLE IF NOT EXISTS `productos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL UNIQUE,
    `categoria_id` INT NOT NULL,
    `marca` VARCHAR(60) NOT NULL,
    `modelo` VARCHAR(150) NOT NULL,
    `stock_actual` INT NOT NULL DEFAULT 0,
    `valor_inventario` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `costo_promedio` DECIMAL(14,4) NOT NULL DEFAULT 0,
    `ultimo_costo` DECIMAL(14,4) NOT NULL DEFAULT 0,
    `precio_venta` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Precio unitario CON IVA',
    `stock_minimo` INT NOT NULL DEFAULT 0,
    `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`),
    CONSTRAINT `chk_productos_stock` CHECK (`stock_actual` >= 0),
    UNIQUE KEY `uq_producto` (`marca`, `modelo`)
);

-- =====================================================================
-- 7. COMPRAS (Libro de Compras + detalle)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `compras` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL,
    `tipo_documento` ENUM('CCF','FACTURA','SUJETO_EXCLUIDO','NOTA_CREDITO') NOT NULL DEFAULT 'CCF',
    `numero_documento` VARCHAR(30) NOT NULL,
    `proveedor_id` INT NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `compras_exentas_internas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `compras_exentas_importaciones` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `compras_gravadas_internas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `compras_gravadas_importaciones` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `credito_fiscal` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `anticipo_iva_retenido` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `anticipo_iva_percibido` DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Misma fórmula que el Excel: =SUM(G:M)
    `total` DECIMAL(14,2) GENERATED ALWAYS AS (
        `compras_exentas_internas` + `compras_exentas_importaciones` +
        `compras_gravadas_internas` + `compras_gravadas_importaciones` +
        `credito_fiscal` + `anticipo_iva_retenido` + `anticipo_iva_percibido`
    ) STORED,
    `forma_pago` ENUM('banco','caja','credito') NOT NULL DEFAULT 'banco',
    `estado` ENUM('vigente','anulado') NOT NULL DEFAULT 'vigente',
    `partida_id` INT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_compras_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores`(`id`),
    CONSTRAINT `fk_compras_partida` FOREIGN KEY (`partida_id`) REFERENCES `partidas`(`id`),
    UNIQUE KEY `uq_compra_doc` (`proveedor_id`, `tipo_documento`, `numero_documento`),
    KEY `idx_compras_fecha` (`fecha`)
);

-- Cada línea dice a dónde va el dinero: inventario, activo fijo, gasto o depósito.
-- Si es de inventario (producto_id) el trigger genera la ENTRADA al kardex.
CREATE TABLE IF NOT EXISTS `compras_detalle` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `compra_id` INT NOT NULL,
    `tipo_item` ENUM('inventario','activo_fijo','gasto','deposito') NOT NULL,
    `producto_id` INT NULL,
    `cuenta_id` INT NOT NULL COMMENT 'Cuenta contable de destino',
    `grupo` VARCHAR(60) NULL,
    `descripcion` VARCHAR(150) NOT NULL,
    `detalle` VARCHAR(150) NULL,
    `cantidad` DECIMAL(12,2) NOT NULL DEFAULT 1,
    `costo_unitario` DECIMAL(14,4) NOT NULL,
    `subtotal` DECIMAL(14,2) NOT NULL,
    CONSTRAINT `fk_cd_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cd_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`),
    CONSTRAINT `fk_cd_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas`(`id`),
    CONSTRAINT `chk_cd_inventario` CHECK ((`tipo_item` = 'inventario') = (`producto_id` IS NOT NULL))
);

-- =====================================================================
-- 8. VENTAS (Libro de Ventas a Contribuyentes + Consumidor Final)
--    Un solo documento: CCF (contribuyente) o TICKET (consumidor final).
--    ventas_gravadas = BASE NETA; el IVA va en iva_debito.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ventas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL,
    `tipo_documento` ENUM('TICKET','CCF') NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `maquina_registradora` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Solo tickets (ej. MR-001)',
    `cliente_id` INT NULL COMMENT 'Obligatorio en CCF',
    `condicion_pago` ENUM('contado','credito','credito_pagare') NOT NULL DEFAULT 'contado'
        COMMENT 'contado=Caja, credito=Cuentas por Cobrar, credito_pagare=Documentos por Cobrar',
    `ventas_exentas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `ventas_no_sujetas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `ventas_gravadas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `ventas_exportaciones` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `iva_debito` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `terceros_exentas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `terceros_gravadas` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `terceros_debito` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `iva_percibido` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `iva_retenido` DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Igual que el Excel (=SUM(F:M) en contribuyentes, =SUM(F:I) más IVA en consumidor)
    `total` DECIMAL(14,2) GENERATED ALWAYS AS (
        `ventas_exentas` + `ventas_no_sujetas` + `ventas_gravadas` + `ventas_exportaciones` +
        `iva_debito` + `terceros_exentas` + `terceros_gravadas` + `terceros_debito` +
        `iva_percibido` + `iva_retenido`
    ) STORED,
    `estado` ENUM('vigente','anulado') NOT NULL DEFAULT 'vigente',
    `partida_id` INT NULL COMMENT 'Partida del ingreso',
    `partida_costo_id` INT NULL COMMENT 'Partida del costo de ventas',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
    CONSTRAINT `fk_ventas_partida` FOREIGN KEY (`partida_id`) REFERENCES `partidas`(`id`),
    CONSTRAINT `fk_ventas_partida_costo` FOREIGN KEY (`partida_costo_id`) REFERENCES `partidas`(`id`),
    CONSTRAINT `chk_ventas_ccf_cliente` CHECK (`tipo_documento` <> 'CCF' OR `cliente_id` IS NOT NULL),
    UNIQUE KEY `uq_venta_doc` (`tipo_documento`, `maquina_registradora`, `numero_documento`),
    KEY `idx_ventas_fecha` (`fecha`)
);

-- costo_unitario / costo_total los fija el trigger desde el kardex (costo promedio)
CREATE TABLE IF NOT EXISTS `ventas_detalle` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `venta_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `cantidad` INT NOT NULL,
    `precio_unitario` DECIMAL(14,4) NOT NULL COMMENT 'Sin IVA',
    `subtotal` DECIMAL(14,2) NOT NULL COMMENT 'Sin IVA',
    `costo_unitario` DECIMAL(14,4) NOT NULL DEFAULT 0,
    `costo_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT `fk_vd_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vd_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`),
    CONSTRAINT `chk_vd_cantidad` CHECK (`cantidad` > 0)
);

-- =====================================================================
-- 9. KARDEX (corazón del sistema perpetuo, costo promedio ponderado)
--    No se inserta a mano: lo generan los triggers de compras_detalle
--    y ventas_detalle. Para ajustes manuales, insertar tipo/origen
--    'ajuste' (el trigger calcula saldos). Registrar en orden cronológico.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `producto_id` INT NOT NULL,
    `fecha` DATE NOT NULL,
    `tipo` ENUM('entrada','salida') NOT NULL,
    `origen` ENUM('compra','venta','ajuste','devolucion_venta','devolucion_compra') NOT NULL,
    `cantidad` INT NOT NULL,
    `costo_unitario` DECIMAL(14,4) NOT NULL DEFAULT 0,
    `costo_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `saldo_cantidad` INT NOT NULL DEFAULT 0,
    `saldo_valor` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `saldo_costo_promedio` DECIMAL(14,4) NOT NULL DEFAULT 0,
    `compra_id` INT NULL,
    `venta_id` INT NULL,
    `observacion` VARCHAR(255) NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mi_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`),
    CONSTRAINT `fk_mi_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras`(`id`),
    CONSTRAINT `fk_mi_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`),
    CONSTRAINT `chk_mi_cantidad` CHECK (`cantidad` > 0),
    KEY `idx_mi_producto_fecha` (`producto_id`, `fecha`)
);

-- =====================================================================
-- 10. ACTIVOS FIJOS (parámetros de la depreciación mensual)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `activos_fijos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL UNIQUE,
    `descripcion` VARCHAR(150) NOT NULL,
    `cuenta_id` INT NOT NULL COMMENT 'Subcuenta de PPE (Mobiliario y Equipo / Equipo de Transporte)',
    `cuenta_gasto_id` INT NOT NULL COMMENT 'Gasto donde se carga la depreciación',
    `compra_detalle_id` INT NULL,
    `fecha_adquisicion` DATE NOT NULL,
    `costo` DECIMAL(14,2) NOT NULL,
    `tasa_depreciacion_anual` DECIMAL(6,4) NOT NULL,
    `valor_residual` DECIMAL(14,2) NOT NULL DEFAULT 0,
    `estado` ENUM('activo','baja') NOT NULL DEFAULT 'activo',
    CONSTRAINT `fk_af_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas`(`id`),
    CONSTRAINT `fk_af_gasto` FOREIGN KEY (`cuenta_gasto_id`) REFERENCES `cuentas`(`id`),
    CONSTRAINT `fk_af_cd` FOREIGN KEY (`compra_detalle_id`) REFERENCES `compras_detalle`(`id`)
);

-- =====================================================================
-- 11. PLANILLA (hoja Presupuesto: planilla de salarios y retenciones)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `empleados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(120) NULL,
    `cargo` VARCHAR(80) NOT NULL,
    `area` ENUM('Administración','Ventas') NOT NULL,
    `dui` VARCHAR(15) NULL,
    `salario_mensual` DECIMAL(12,2) NOT NULL,
    `fecha_ingreso` DATE NULL,
    `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
);

CREATE TABLE IF NOT EXISTS `planillas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anio` SMALLINT NOT NULL,
    `mes` TINYINT NOT NULL,
    `fecha_pago` DATE NULL,
    `partida_pago_id` INT NULL COMMENT 'Salarios y retenciones',
    `partida_patronal_id` INT NULL COMMENT 'Provisión cuota patronal',
    `estado` ENUM('borrador','pagada') NOT NULL DEFAULT 'borrador',
    CONSTRAINT `fk_planillas_pago` FOREIGN KEY (`partida_pago_id`) REFERENCES `partidas`(`id`),
    CONSTRAINT `fk_planillas_patronal` FOREIGN KEY (`partida_patronal_id`) REFERENCES `partidas`(`id`),
    UNIQUE KEY `uq_planilla_periodo` (`anio`, `mes`)
);

CREATE TABLE IF NOT EXISTS `planilla_detalle` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `planilla_id` INT NOT NULL,
    `empleado_id` INT NOT NULL,
    `salario_devengado` DECIMAL(12,2) NOT NULL,
    `isss_laboral` DECIMAL(12,2) NOT NULL,
    `afp_laboral` DECIMAL(12,2) NOT NULL,
    `total_retenciones` DECIMAL(12,2) GENERATED ALWAYS AS (`isss_laboral` + `afp_laboral`) STORED,
    `liquido_pagar` DECIMAL(12,2) GENERATED ALWAYS AS (`salario_devengado` - `isss_laboral` - `afp_laboral`) STORED,
    `isss_patronal` DECIMAL(12,2) NOT NULL,
    `afp_patronal` DECIMAL(12,2) NOT NULL,
    `cuota_patronal` DECIMAL(12,2) GENERATED ALWAYS AS (`isss_patronal` + `afp_patronal`) STORED,
    CONSTRAINT `fk_pl_planilla` FOREIGN KEY (`planilla_id`) REFERENCES `planillas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pl_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados`(`id`),
    UNIQUE KEY `uq_planilla_empleado` (`planilla_id`, `empleado_id`)
);

-- =====================================================================
-- 12. PRESUPUESTO (hoja Presupuesto: inversión inicial y costos fijos)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `presupuesto_inversion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seccion` VARCHAR(60) NOT NULL,
    `grupo` VARCHAR(60) NULL,
    `concepto` VARCHAR(150) NOT NULL,
    `detalle` VARCHAR(150) NULL,
    `monto` DECIMAL(14,2) NOT NULL,
    `gravado_iva` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = depósito de garantía',
    `orden` INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS `costos_fijos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `concepto` VARCHAR(100) NOT NULL,
    `detalle` VARCHAR(150) NULL,
    `monto_mensual` DECIMAL(12,2) NOT NULL,
    `aplica_iva` TINYINT(1) NOT NULL DEFAULT 1,
    `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
);

-- =====================================================================
-- 13. TRIGGERS
-- =====================================================================
DELIMITER $$

-- ---- Kardex: calcula saldos y costo promedio ponderado --------------
DROP TRIGGER IF EXISTS `trg_mov_inv_bi`$$
CREATE TRIGGER `trg_mov_inv_bi` BEFORE INSERT ON `movimientos_inventario`
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;
    DECLARE v_valor DECIMAL(14,2);
    DECLARE v_cp    DECIMAL(14,4);

    SELECT `stock_actual`, `valor_inventario`, `costo_promedio`
      INTO v_stock, v_valor, v_cp
      FROM `productos` WHERE `id` = NEW.`producto_id` FOR UPDATE;

    IF NEW.`tipo` = 'entrada' THEN
        SET NEW.`costo_total`     = ROUND(NEW.`cantidad` * NEW.`costo_unitario`, 2);
        SET NEW.`saldo_cantidad`  = v_stock + NEW.`cantidad`;
        SET NEW.`saldo_valor`     = v_valor + NEW.`costo_total`;
    ELSE
        IF NEW.`cantidad` > v_stock THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Existencias insuficientes para registrar la salida';
        END IF;
        -- La salida se valúa al costo promedio vigente; si sale todo, se vacía el valor (sin residuos de centavos)
        IF NEW.`cantidad` = v_stock THEN
            SET NEW.`costo_total` = v_valor;
        ELSE
            SET NEW.`costo_total` = ROUND(v_valor * NEW.`cantidad` / v_stock, 2);
        END IF;
        SET NEW.`costo_unitario`  = ROUND(NEW.`costo_total` / NEW.`cantidad`, 4);
        SET NEW.`saldo_cantidad`  = v_stock - NEW.`cantidad`;
        SET NEW.`saldo_valor`     = v_valor - NEW.`costo_total`;
    END IF;

    SET NEW.`saldo_costo_promedio` = IF(NEW.`saldo_cantidad` > 0,
                                        ROUND(NEW.`saldo_valor` / NEW.`saldo_cantidad`, 4), v_cp);
END$$

DROP TRIGGER IF EXISTS `trg_mov_inv_ai`$$
CREATE TRIGGER `trg_mov_inv_ai` AFTER INSERT ON `movimientos_inventario`
FOR EACH ROW
BEGIN
    UPDATE `productos`
       SET `stock_actual`     = NEW.`saldo_cantidad`,
           `valor_inventario` = NEW.`saldo_valor`,
           `costo_promedio`   = NEW.`saldo_costo_promedio`,
           `ultimo_costo`     = IF(NEW.`tipo` = 'entrada' AND NEW.`origen` = 'compra',
                                   NEW.`costo_unitario`, `ultimo_costo`)
     WHERE `id` = NEW.`producto_id`;
END$$

-- ---- Compra de mercadería => ENTRADA al kardex ----------------------
DROP TRIGGER IF EXISTS `trg_compras_detalle_ai`$$
CREATE TRIGGER `trg_compras_detalle_ai` AFTER INSERT ON `compras_detalle`
FOR EACH ROW
BEGIN
    IF NEW.`producto_id` IS NOT NULL THEN
        INSERT INTO `movimientos_inventario`
            (`producto_id`, `fecha`, `tipo`, `origen`, `cantidad`, `costo_unitario`, `compra_id`)
        SELECT NEW.`producto_id`, c.`fecha`, 'entrada', 'compra', NEW.`cantidad`, NEW.`costo_unitario`, c.`id`
          FROM `compras` c WHERE c.`id` = NEW.`compra_id`;
    END IF;
END$$

-- ---- Venta => SALIDA al kardex y costo de venta en la línea ---------
DROP TRIGGER IF EXISTS `trg_ventas_detalle_bi`$$
CREATE TRIGGER `trg_ventas_detalle_bi` BEFORE INSERT ON `ventas_detalle`
FOR EACH ROW
BEGIN
    DECLARE v_fecha DATE;
    DECLARE v_mov   INT;
    DECLARE v_cu    DECIMAL(14,4);
    DECLARE v_ct    DECIMAL(14,2);

    SELECT `fecha` INTO v_fecha FROM `ventas` WHERE `id` = NEW.`venta_id`;

    INSERT INTO `movimientos_inventario`
        (`producto_id`, `fecha`, `tipo`, `origen`, `cantidad`, `costo_unitario`, `venta_id`)
    VALUES (NEW.`producto_id`, v_fecha, 'salida', 'venta', NEW.`cantidad`, 0, NEW.`venta_id`);
    SET v_mov = LAST_INSERT_ID();

    SELECT `costo_unitario`, `costo_total` INTO v_cu, v_ct
      FROM `movimientos_inventario` WHERE `id` = v_mov;
    SET NEW.`costo_unitario` = v_cu;
    SET NEW.`costo_total`    = v_ct;
END$$

-- ---- Partidas: solo se editan en borrador y deben cuadrar -----------
DROP TRIGGER IF EXISTS `trg_partidas_bu`$$
CREATE TRIGGER `trg_partidas_bu` BEFORE UPDATE ON `partidas`
FOR EACH ROW
BEGIN
    DECLARE v_debe  DECIMAL(14,2);
    DECLARE v_haber DECIMAL(14,2);
    DECLARE v_lineas INT;

    IF OLD.`estado` <> 'borrador' AND NOT (OLD.`estado` = 'contabilizada' AND NEW.`estado` = 'anulada') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una partida contabilizada solo puede anularse';
    END IF;

    IF OLD.`estado` = 'borrador' AND NEW.`estado` = 'contabilizada' THEN
        SELECT COALESCE(SUM(`debe`), 0), COALESCE(SUM(`haber`), 0), COUNT(*)
          INTO v_debe, v_haber, v_lineas
          FROM `partidas_detalle` WHERE `partida_id` = OLD.`id`;
        IF v_lineas = 0 OR v_debe <> v_haber THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La partida no cuadra (debe <> haber) o no tiene líneas';
        END IF;
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_partidas_bd`$$
CREATE TRIGGER `trg_partidas_bd` BEFORE DELETE ON `partidas`
FOR EACH ROW
BEGIN
    IF OLD.`estado` <> 'borrador' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede eliminar una partida contabilizada; anúlela';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_pd_bi`$$
CREATE TRIGGER `trg_pd_bi` BEFORE INSERT ON `partidas_detalle`
FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(20);
    DECLARE v_acepta TINYINT;

    SELECT `estado` INTO v_estado FROM `partidas` WHERE `id` = NEW.`partida_id`;
    IF v_estado <> 'borrador' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La partida no está en borrador';
    END IF;

    SELECT `acepta_movimientos` INTO v_acepta FROM `cuentas` WHERE `id` = NEW.`cuenta_id`;
    IF v_acepta = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cuenta no acepta movimientos; use una subcuenta';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_pd_bu`$$
CREATE TRIGGER `trg_pd_bu` BEFORE UPDATE ON `partidas_detalle`
FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(20);
    SELECT `estado` INTO v_estado FROM `partidas` WHERE `id` = OLD.`partida_id`;
    IF v_estado <> 'borrador' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La partida no está en borrador';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_pd_bd`$$
CREATE TRIGGER `trg_pd_bd` BEFORE DELETE ON `partidas_detalle`
FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(20);
    SELECT `estado` INTO v_estado FROM `partidas` WHERE `id` = OLD.`partida_id`;
    IF v_estado <> 'borrador' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La partida no está en borrador';
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- 14. VISTAS (reemplazan las hojas calculadas del Excel)
--     Todas leen solo partidas 'contabilizada' y documentos 'vigente'.
--     Filtre por fecha/mes desde PHP (WHERE fecha BETWEEN ...).
-- =====================================================================

-- Base: una fila por línea de partida, con su cuenta de mayor
CREATE OR REPLACE VIEW `v_asientos` AS
SELECT pd.`id` AS detalle_id, p.`id` AS partida_id, p.`numero` AS partida_numero, p.`fecha`,
       p.`tipo` AS partida_tipo, p.`concepto`,
       c.`id` AS cuenta_id, c.`codigo` AS cuenta_codigo, c.`nombre` AS cuenta_nombre,
       m.`id` AS mayor_id, m.`codigo` AS mayor_codigo, m.`nombre` AS mayor_nombre,
       m.`tipo` AS mayor_tipo, m.`naturaleza` AS mayor_naturaleza, m.`padre_id` AS grupo_id,
       pd.`debe`, pd.`haber`
FROM `partidas_detalle` pd
JOIN `partidas` p ON p.`id` = pd.`partida_id` AND p.`estado` = 'contabilizada'
JOIN `cuentas` c  ON c.`id` = pd.`cuenta_id`
JOIN `cuentas` m  ON m.`id` = IF(c.`nivel` = 4, c.`padre_id`, c.`id`);

-- LIBRO DIARIO
CREATE OR REPLACE VIEW `v_libro_diario` AS
SELECT p.`numero` AS partida, p.`fecha`, p.`folio`, p.`tipo`, p.`concepto`,
       pd.`orden`, c.`codigo` AS cuenta_codigo, c.`nombre` AS cuenta,
       m.`nombre` AS cuenta_mayor, (c.`nivel` = 4) AS es_subcuenta,
       pd.`debe`, pd.`haber`
FROM `partidas` p
JOIN `partidas_detalle` pd ON pd.`partida_id` = p.`id`
JOIN `cuentas` c ON c.`id` = pd.`cuenta_id`
JOIN `cuentas` m ON m.`id` = IF(c.`nivel` = 4, c.`padre_id`, c.`id`)
WHERE p.`estado` = 'contabilizada';

-- LIBRO MAYOR (esquemas en T): una fila por partida y cuenta de mayor
CREATE OR REPLACE VIEW `v_libro_mayor` AS
SELECT mayor_id, mayor_codigo, mayor_nombre, partida_numero, fecha,
       SUM(debe) AS debe, SUM(haber) AS haber
FROM `v_asientos`
GROUP BY mayor_id, mayor_codigo, mayor_nombre, partida_id, partida_numero, fecha;

-- BALANZA DE COMPROBACIÓN (movimientos y saldos de cada cuenta de mayor)
CREATE OR REPLACE VIEW `v_balanza_comprobacion` AS
SELECT m.`id` AS cuenta_id, m.`codigo`, m.`nombre`, m.`tipo`,
       COALESCE(SUM(a.`debe`), 0)  AS mov_deudor,
       COALESCE(SUM(a.`haber`), 0) AS mov_acreedor,
       GREATEST(COALESCE(SUM(a.`debe`), 0) - COALESCE(SUM(a.`haber`), 0), 0) AS saldo_deudor,
       GREATEST(COALESCE(SUM(a.`haber`), 0) - COALESCE(SUM(a.`debe`), 0), 0) AS saldo_acreedor
FROM `cuentas` m
LEFT JOIN `v_asientos` a ON a.`mayor_id` = m.`id`
WHERE m.`nivel` = 3
GROUP BY m.`id`, m.`codigo`, m.`nombre`, m.`tipo`;

-- BALANCE GENERAL (saldos con signo: depreciación y pérdida salen negativas)
CREATE OR REPLACE VIEW `v_balance_general` AS
SELECT m.`tipo` AS rubro, g.`nombre` AS grupo, m.`codigo`, m.`nombre` AS cuenta,
       CASE WHEN m.`tipo` = 'activo'
            THEN SUM(a.`debe`) - SUM(a.`haber`)
            ELSE SUM(a.`haber`) - SUM(a.`debe`) END AS saldo
FROM `cuentas` m
JOIN `cuentas` g ON g.`id` = m.`padre_id`
JOIN `v_asientos` a ON a.`mayor_id` = m.`id`
WHERE m.`nivel` = 3 AND m.`tipo` IN ('activo','pasivo','patrimonio')
GROUP BY m.`id`, m.`tipo`, g.`nombre`, m.`codigo`, m.`nombre`
HAVING saldo <> 0;

-- ESTADO DE RESULTADOS (excluye las partidas de cierre)
CREATE OR REPLACE VIEW `v_estado_resultados` AS
SELECT a.`fecha`, a.`mayor_codigo` AS codigo, a.`mayor_nombre` AS cuenta, a.`mayor_tipo` AS tipo,
       CASE WHEN a.`mayor_tipo` = 'ingreso' THEN a.`haber` - a.`debe`
            ELSE a.`debe` - a.`haber` END AS monto
FROM `v_asientos` a
WHERE a.`mayor_tipo` IN ('ingreso','costo','gasto') AND a.`partida_tipo` <> 'cierre';

-- LIBRO DE COMPRAS (N° se reinicia cada mes)
CREATE OR REPLACE VIEW `v_libro_compras` AS
SELECT ROW_NUMBER() OVER (PARTITION BY YEAR(c.`fecha`), MONTH(c.`fecha`) ORDER BY c.`fecha`, c.`id`) AS n,
       c.`id`, c.`fecha`, CONCAT(c.`tipo_documento`, ' ', c.`numero_documento`) AS documento,
       pr.`nrc`, pr.`nit_dui`, pr.`nombre` AS proveedor,
       c.`compras_exentas_internas`, c.`compras_exentas_importaciones`,
       c.`compras_gravadas_internas`, c.`compras_gravadas_importaciones`,
       c.`credito_fiscal`, c.`anticipo_iva_retenido`, c.`anticipo_iva_percibido`, c.`total`
FROM `compras` c
JOIN `proveedores` pr ON pr.`id` = c.`proveedor_id`
WHERE c.`estado` = 'vigente';

-- LIBRO DE VENTAS A CONTRIBUYENTES
CREATE OR REPLACE VIEW `v_libro_ventas_contribuyentes` AS
SELECT ROW_NUMBER() OVER (PARTITION BY YEAR(v.`fecha`), MONTH(v.`fecha`) ORDER BY v.`fecha`, v.`id`) AS n,
       v.`id`, v.`fecha`, CONCAT(v.`tipo_documento`, ' ', v.`numero_documento`) AS documento,
       cl.`nrc`, cl.`nombre` AS contribuyente,
       v.`ventas_exentas`, v.`ventas_gravadas`, v.`iva_debito`,
       v.`terceros_exentas`, v.`terceros_gravadas`, v.`terceros_debito`,
       v.`iva_percibido`, v.`iva_retenido`, v.`total`
FROM `ventas` v
JOIN `clientes` cl ON cl.`id` = v.`cliente_id`
WHERE v.`tipo_documento` = 'CCF' AND v.`estado` = 'vigente';

-- LIBRO DE VENTAS A CONSUMIDOR FINAL (resumen diario por máquina: DEL N° / AL N°)
CREATE OR REPLACE VIEW `v_libro_ventas_consumidor` AS
SELECT ROW_NUMBER() OVER (PARTITION BY YEAR(`fecha`), MONTH(`fecha`) ORDER BY `fecha`, `maquina_registradora`) AS n,
       `fecha`,
       MIN(CAST(`numero_documento` AS UNSIGNED)) AS del_no,
       MAX(CAST(`numero_documento` AS UNSIGNED)) AS al_no,
       `maquina_registradora`,
       SUM(`ventas_exentas`)      AS ventas_exentas,
       SUM(`ventas_no_sujetas`)   AS ventas_no_sujetas,
       SUM(`ventas_gravadas` + `iva_debito`) AS ventas_gravadas_locales,
       SUM(`ventas_exportaciones`) AS ventas_exportaciones,
       SUM(`total`)               AS total_ventas,
       SUM(`terceros_gravadas` + `terceros_exentas` + `terceros_debito`) AS venta_cuenta_terceros,
       SUM(`ventas_gravadas`)     AS base_gravada,
       SUM(`iva_debito`)          AS debito_fiscal
FROM `ventas`
WHERE `tipo_documento` = 'TICKET' AND `estado` = 'vigente'
GROUP BY `fecha`, `maquina_registradora`;

-- KARDEX
CREATE OR REPLACE VIEW `v_kardex` AS
SELECT mi.`id`, mi.`fecha`, p.`codigo`, p.`marca`, p.`modelo`, mi.`tipo`, mi.`origen`,
       CASE WHEN c.`id` IS NOT NULL THEN CONCAT(c.`tipo_documento`, ' ', c.`numero_documento`)
            WHEN v.`id` IS NOT NULL THEN CONCAT(v.`tipo_documento`, ' ', v.`numero_documento`) END AS documento,
       IF(mi.`tipo` = 'entrada', mi.`cantidad`, 0)    AS entrada_cantidad,
       IF(mi.`tipo` = 'salida',  mi.`cantidad`, 0)    AS salida_cantidad,
       mi.`costo_unitario`, mi.`costo_total`,
       mi.`saldo_cantidad`, mi.`saldo_costo_promedio`, mi.`saldo_valor`, mi.`producto_id`
FROM `movimientos_inventario` mi
JOIN `productos` p ON p.`id` = mi.`producto_id`
LEFT JOIN `compras` c ON c.`id` = mi.`compra_id`
LEFT JOIN `ventas`  v ON v.`id` = mi.`venta_id`;

-- INVENTARIO (hoja "Inventario": inicial, vendida, final, costo y precio)
CREATE OR REPLACE VIEW `v_inventario` AS
SELECT p.`id`, p.`codigo`, cat.`nombre` AS categoria, p.`marca`, p.`modelo`,
       COALESCE(SUM(CASE WHEN mi.`origen` = 'compra' THEN mi.`cantidad` END), 0) AS cant_comprada,
       COALESCE(SUM(CASE WHEN mi.`origen` = 'venta'  THEN mi.`cantidad` END), 0) AS cant_vendida,
       p.`stock_actual` AS cant_final,
       p.`costo_promedio` AS costo_unit,
       p.`valor_inventario` AS costo_final_total,
       ROUND(p.`costo_promedio` * (1 + iva.`valor`), 2) AS costo_iva_unit,
       p.`stock_actual` * ROUND(p.`costo_promedio` * (1 + iva.`valor`), 2) AS costo_iva_final,
       p.`precio_venta` AS precio_venta_iva,
       p.`stock_actual` * p.`precio_venta` AS valor_venta_final
FROM `productos` p
JOIN `categorias` cat ON cat.`id` = p.`categoria_id`
JOIN `parametros` iva ON iva.`clave` = 'iva'
LEFT JOIN `movimientos_inventario` mi ON mi.`producto_id` = p.`id`
GROUP BY p.`id`, p.`codigo`, cat.`nombre`, p.`marca`, p.`modelo`, p.`stock_actual`,
         p.`costo_promedio`, p.`valor_inventario`, p.`precio_venta`, iva.`valor`;

-- DEPRECIACIÓN MENSUAL por activo (sume por cuenta_gasto_id y redondee al final)
CREATE OR REPLACE VIEW `v_depreciacion_mensual` AS
SELECT af.`id`, af.`codigo`, af.`descripcion`, af.`cuenta_gasto_id`, af.`costo`, af.`tasa_depreciacion_anual`,
       (af.`costo` - af.`valor_residual`) * af.`tasa_depreciacion_anual` / 12 AS depreciacion_mensual
FROM `activos_fijos` af
WHERE af.`estado` = 'activo';

-- CONTROL: partidas que no cuadran (debe quedar vacía)
CREATE OR REPLACE VIEW `v_partidas_descuadradas` AS
SELECT p.`id`, p.`numero`, p.`fecha`, SUM(pd.`debe`) AS debe, SUM(pd.`haber`) AS haber
FROM `partidas` p
JOIN `partidas_detalle` pd ON pd.`partida_id` = p.`id`
GROUP BY p.`id`, p.`numero`, p.`fecha`
HAVING SUM(pd.`debe`) <> SUM(pd.`haber`);

-- CONTROL: el inventario del mayor debe ser igual al del kardex (diferencia = 0)
CREATE OR REPLACE VIEW `v_control_inventario` AS
SELECT (SELECT COALESCE(SUM(`debe` - `haber`), 0) FROM `v_asientos` WHERE `mayor_codigo` = '1104') AS saldo_contable,
       (SELECT COALESCE(SUM(`valor_inventario`), 0) FROM `productos`) AS saldo_kardex,
       (SELECT COALESCE(SUM(`debe` - `haber`), 0) FROM `v_asientos` WHERE `mayor_codigo` = '1104')
     - (SELECT COALESCE(SUM(`valor_inventario`), 0) FROM `productos`) AS diferencia;