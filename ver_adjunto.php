<?php
// Archivo: ver_adjunto.php (SCRIPT PARA VISUALIZAR EN LÍNEA)
session_start();
include 'conexion.php';

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403); // Prohibido
    die("Acceso denegado. Debe iniciar sesión para ver archivos.");
}

// 2. Obtener el ID del adjunto
$id_adjunto = $_GET['id'] ?? 0;
if (!is_numeric($id_adjunto) || $id_adjunto <= 0) {
    http_response_code(400); // Bad Request
    die("ID de adjunto no válido.");
}

// 3. Consultar la base de datos para obtener la ruta, el nombre y el TIPO de archivo
try {
    $sql = "SELECT id_tarea, nombre_archivo, ruta_archivo, tipo_adjunto FROM adjuntos_tarea WHERE id_adjunto = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id_adjunto, PDO::PARAM_INT);
    $stmt->execute();
    $adjunto = $stmt->fetch();

    if (!$adjunto) {
        http_response_code(404); // Not Found
        die("El archivo solicitado no existe en el sistema.");
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    die("Error de base de datos al buscar el adjunto.");
}

// 4. Determinar la carpeta base correcta
$tipo_adjunto = $adjunto['tipo_adjunto'];
$ruta_base_directorio = '';

if (in_array($tipo_adjunto, ['inicial', 'final'])) {
    $ruta_base_directorio = 'uploads/tareas/';
} elseif (in_array($tipo_adjunto, ['actualizacion', 'remito'])) {
    $ruta_base_directorio = 'uploads/actualizaciones/';
} else {
    $ruta_base_directorio = 'uploads/adjuntos/';
}

// 5. Construir la ruta completa y verificar existencia
$ruta_completa = $ruta_base_directorio . $adjunto['ruta_archivo'];

if (!file_exists($ruta_completa)) {
    http_response_code(404);
    error_log("Archivo no encontrado en la ruta: " . $ruta_completa);
    die("El archivo físico no se encuentra en el servidor.");
}

// 6. Entregar el archivo para VISUALIZACIÓN EN LÍNEA
header('Content-Description: File Transfer');
// Intentamos adivinar el tipo MIME para visualización, o usamos octet-stream
$mime = mime_content_type($ruta_completa);
header('Content-Type: ' . ($mime ?: 'application/octet-stream')); 

// **** CLAVE: Cambiar 'attachment' a 'inline' ****
header('Content-Disposition: inline; filename="' . $adjunto['nombre_archivo'] . '"'); 

header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta_completa));
ob_clean(); // Limpia cualquier output buffer anterior
flush(); // Envía los headers
readfile($ruta_completa); // Lee y envía el archivo al cliente
exit;
?>