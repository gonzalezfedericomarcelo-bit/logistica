<?php
// Archivo: notificaciones_fetch.php (LÓGICA REAL-TIME POR ID)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['unread_count' => 0, 'notifications' => [], 'new_notifications' => [], 'max_id' => 0]);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
// Recibimos el ÚLTIMO ID de notificación que tiene el navegador
$last_id_seen = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$response = [
    'unread_count' => 0,
    'notifications' => [], 
    'new_notifications' => [],
    'max_id' => 0 // Devolvemos el ID más alto actual
];

try {
    // 1. Obtener el ID máximo actual (para sincronizar la primera vez)
    $stmt_max = $pdo->prepare("SELECT MAX(id_notificacion) FROM notificaciones WHERE id_usuario_destino = :id_user");
    $stmt_max->execute([':id_user' => $id_usuario]);
    $current_max_id = $stmt_max->fetchColumn() ?: 0;
    $response['max_id'] = $current_max_id;

    // 2. Conteo de NO LEÍDAS (Badge Rojo)
    $sql_count = "SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :id_user AND leida = 0";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':id_user' => $id_usuario]); 
    $response['unread_count'] = $stmt_count->fetchColumn();

    // 3. Notificaciones NUEVAS (Polling Real-Time)
    // Solo si el cliente nos envía un last_id válido y es menor al actual
    if ($last_id_seen > 0 && $last_id_seen < $current_max_id) {
        $sql_new = "
            SELECT * FROM notificaciones 
            WHERE id_usuario_destino = :id_user 
            AND id_notificacion > :last_id
            ORDER BY id_notificacion ASC";
        
        $stmt_new = $pdo->prepare($sql_new);
        $stmt_new->execute([':id_user' => $id_usuario, ':last_id' => $last_id_seen]);
        $response['new_notifications'] = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Listado para el Dropdown (Solo si se pide carga inicial o reset)
    if ($last_id_seen === 0) {
         $sql_list = "SELECT * FROM notificaciones 
                      WHERE id_usuario_destino = :id_user 
                      ORDER BY fecha_creacion DESC LIMIT 10"; 
         $stmt_list = $pdo->prepare($sql_list);
         $stmt_list->execute([':id_user' => $id_usuario]); 
         $response['notifications'] = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
         
         // Formato fecha
         foreach ($response['notifications'] as &$notif) {
             if (!empty($notif['fecha_creacion'])) {
                 $notif['fecha_creacion'] = date('d/m/Y H:i', strtotime($notif['fecha_creacion']));
             }
         }
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error fetch notif: " . $e->getMessage());
    echo json_encode($response);
}
?>