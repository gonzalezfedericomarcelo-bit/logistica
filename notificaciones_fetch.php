<?php
// Archivo: notificaciones_fetch.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$last_check_timestamp = $_GET['last'] ?? null; 

$response = [
    'unread_count' => 0,
    'notifications' => [], 
    'new_notifications' => [], 
];

try {
    // 1. Obtener el conteo total de notificaciones no leídas (Badge)
    $sql_count = "SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :id_user AND leida = 0";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':id_user' => $id_usuario]); 
    $response['unread_count'] = $stmt_count->fetchColumn();


    // 2. Obtener notificaciones NUEVAS y NO LEÍDAS (Polling)
    // Se usa el timestamp solo para activar la consulta.
    if (is_numeric($last_check_timestamp) && $last_check_timestamp > 0) { 
        $last_time_seconds = floor($last_check_timestamp / 1000); 
        
        $sql_new = "
            SELECT 
                id_notificacion, 
                mensaje, 
                url, 
                tipo, 
                fecha_creacion 
            FROM notificaciones 
            WHERE id_usuario_destino = :id_user 
            AND leida = 0 
            AND UNIX_TIMESTAMP(fecha_creacion) > :last_time_seconds
            ORDER BY fecha_creacion ASC";

        $stmt_new = $pdo->prepare($sql_new);
        $stmt_new->bindParam(':id_user', $id_usuario, PDO::PARAM_INT);
        $stmt_new->bindParam(':last_time_seconds', $last_time_seconds, PDO::PARAM_INT); // <-- BINDING DESCOMENTADO
        $stmt_new->execute();
        
        $response['new_notifications'] = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 3. Obtener la lista completa (Dropdown)
    if ($last_check_timestamp === null || $last_check_timestamp === 'null') {
         $sql_list = "SELECT id_notificacion, mensaje, url, leida, tipo, fecha_creacion FROM notificaciones 
                      WHERE id_usuario_destino = :id_user 
                      ORDER BY fecha_creacion DESC LIMIT 10"; 
         $stmt_list = $pdo->prepare($sql_list);
         $stmt_list->execute([':id_user' => $id_usuario]); 
         $response['notifications'] = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
         
         foreach ($response['notifications'] as &$notif) {
             if (!empty($notif['fecha_creacion'])) {
                 $notif['fecha_creacion'] = (new DateTime($notif['fecha_creacion']))->format('d/m/Y H:i');
             }
         }
         unset($notif); 
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error en notificaciones_fetch.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error de base de datos']);
}
?>