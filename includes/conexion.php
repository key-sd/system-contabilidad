<?php
$db_host = 'localhost';
$db_name = 'db_contabilidad';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $conexion = $pdo;
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}
