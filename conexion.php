<?php
// Archivo: conexion.php (VERSIÓN CON ZONA HORARIA MYSQL FORZADA)
date_default_timezone_set('America/Argentina/Buenos_Aires');

$servidor = "localhost";
$usuario = "u415354546_logistica";
$password = "l0g15t1C@!";
$base_de_datos = "u415354546_logistica";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $dsn = "mysql:host=$servidor;dbname=$base_de_datos;charset=utf8mb4";
    $pdo = new PDO($dsn, $usuario, $password, $options);
    
    // --- SOLUCIÓN DE HORA: Forzar a MySQL a usar GMT-3 (Argentina) ---
    $pdo->exec("SET time_zone = '-03:00';");
    // -----------------------------------------------------------------

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$project_root = dirname($path);
$action_url = "{$base_url}{$project_root}/tarea_actualizar_procesar.php";

function generar_nuevo_numero_orden($pdo) {
    try {
        $year_short = date('y');
        $sql_max_orden = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_orden, '/', 1) AS UNSIGNED)) as max_num
                          FROM pedidos_trabajo
                          WHERE SUBSTRING_INDEX(numero_orden, '/', -1) = :year_short
                          FOR UPDATE"; 
        $stmt_max = $pdo->prepare($sql_max_orden);
        $stmt_max->execute([':year_short' => $year_short]);
        $max_num = $stmt_max->fetchColumn();
        $nuevo_numero = ($max_num ?: 0) + 1;
        return str_pad($nuevo_numero, 2, '0', STR_PAD_LEFT) . '/' . $year_short;
    } catch (PDOException $e) {
        error_log("Error CRÍTICO al generar numero_orden: " . $e->getMessage());
        throw new Exception("Error al generar el número de orden.");
    }
}
?>