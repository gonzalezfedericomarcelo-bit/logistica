<?php
session_start();
include 'conexion.php';

// 1. Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Acceso denegado.");
}

// 2. Obtener ID de la notificación
$id_notificacion = $_GET['id'] ?? 0;
if (!is_numeric($id_notificacion) || $id_notificacion <= 0) {
    http_response_code(400);
    die("ID de notificación no válido.");
}

$id_usuario = $_SESSION['usuario_id'];

// 3. Marcar la notificación como leída
try {
    $sql = "UPDATE notificaciones SET leida = 1 
            WHERE id_notificacion = :id_notificacion 
            AND id_usuario_destino = :id_usuario AND leida = 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_notificacion', $id_notificacion, PDO::PARAM_INT);
    $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    
    $stmt->execute();
    
    // Solo se envía una respuesta simple, ya que es una llamada asíncrona
    http_response_code(200);
    echo "OK"; 

} catch (PDOException $e) {
    http_response_code(500);
    die("Error de base de datos: " . $e->getMessage());
}
?>