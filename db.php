<?php
// Configuración optimizada para evitar problemas de memoria
$host = 'localhost';
$dbname = 'agenda_medica';
$username = 'root';
$password = '';

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, $options);
    
    // Configuración adicional para optimización
    $pdo->exec("SET SESSION wait_timeout=600");
    $pdo->exec("SET GLOBAL max_allowed_packet=16777216");
    
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    die(json_encode(['error' => 'Error al conectar con la base de datos']));
}
?>