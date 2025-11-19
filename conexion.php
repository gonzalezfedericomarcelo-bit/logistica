<?php
// Configuración de la conexión a la base de datos
$servidor = "localhost"; // Servidor de la base de datos (XAMPP)
$usuario = "u415354546_logistica2";       // Usuario de la base de datos
$password = "l0g15t1C@!";          // Contraseña del usuario (por defecto vacía en XAMPP)
$base_de_datos = "u415354546_logistica2"; // Nombre de la BD que creamos

// Opciones de conexión PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // CLAVE PARA CORREGIR PROBLEMAS ENUM/COLACIÓN:
    // Fuerza a MySQL a usar el conjunto de caracteres correcto para la sesión.
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4' 
];

try {
    // Se cambia 'charset=utf8' por 'charset=utf8mb4' para máxima compatibilidad
    $dsn = "mysql:host=$servidor;dbname=$base_de_datos;charset=utf8mb4";
    $pdo = new PDO($dsn, $usuario, $password, $options);
    
    // Si la tabla de adjuntos usa un nombre diferente a 'adjuntos_tarea', 
    // es posible que esta línea adicional ayude a forzar el modo de SQL.
    // $pdo->exec("SET SESSION sql_mode = ''"); 

} catch (PDOException $e) {
    // Si la conexión falla, se captura la excepción y se muestra un mensaje de error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// Esta línea obtiene la URL base de tu servidor (ej: http://localhost)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Esta línea obtiene la ruta actual del script (ej: /gestion_tareas/tarea_ver.php)
$project_root = dirname($path);
// Esta línea obtiene la carpeta raíz de tu proyecto (ej: /gestion_tareas)

// Ruta final al archivo de procesamiento:
$action_url = "{$base_url}{$project_root}/tarea_actualizar_procesar.php";
// --- INICIO: NUEVA FUNCIÓN PARA NÚMERO DE ORDEN CENTRALIZADO ---

/**
 * Genera un número de orden correlativo y único (ej: 01/25, 02/25).
 * Esta función DEBE ser llamada dentro de una transacción PDO ($pdo->beginTransaction()).
 *
 * @param PDO $pdo La conexión PDO.
 * @return string El nuevo número de orden (ej: "01/25").
 * @throws Exception Si falla la consulta.
 */
function generar_nuevo_numero_orden($pdo) {
    try {
        $year_short = date('y');
        
        // 1. Bloquear la tabla (o filas relevantes) para evitar que dos usuarios obtengan el mismo número
        // Usamos FOR UPDATE para asegurar que esta lectura sea exclusiva hasta que la transacción termine.
        $sql_max_orden = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_orden, '/', 1) AS UNSIGNED)) as max_num
                          FROM pedidos_trabajo
                          WHERE SUBSTRING_INDEX(numero_orden, '/', -1) = :year_short
                          FOR UPDATE"; 
                          
        $stmt_max = $pdo->prepare($sql_max_orden);
        $stmt_max->execute([':year_short' => $year_short]);
        $max_num = $stmt_max->fetchColumn();
        
        // 2. Calcular nuevo número
        $nuevo_numero = ($max_num ?: 0) + 1;
        $numero_orden_generado = str_pad($nuevo_numero, 2, '0', STR_PAD_LEFT) . '/' . $year_short;
        
        return $numero_orden_generado;

    } catch (PDOException $e) {
        // Si falla, la transacción (que se inició fuera) hará un rollBack.
        error_log("Error CRÍTICO al generar numero_orden: " . $e->getMessage());
        throw new Exception("Error al generar el número de orden: " . $e->getMessage());
    }
}
// --- FIN: NUEVA FUNCIÓN ---
?>