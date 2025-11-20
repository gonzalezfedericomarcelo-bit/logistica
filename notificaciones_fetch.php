<?php
// Archivo: notificaciones_fetch.php (ACTUALIZADO para manejar Avisos Globales)
session_start();
header('Content-Type: application/json');

include 'conexion.php'; 

$currentUserId = $_SESSION['usuario_id'] ?? 0;
$lastNotificationId = (int)($_GET['last_id'] ?? 0);

$response = [
    'unread_count' => 0,
    'new_notifications' => [],
    'notifications' => [], // Solo se usa en loadFullList
    'max_id' => $lastNotificationId
];

if ($currentUserId <= 0 || !isset($pdo)) {
    echo json_encode($response);
    exit;
}

try {
    // --- 1. Contar no leídas (Solo personales) ---
    // Excluímos el tipo 'aviso_global' del contador de la campana, ya que es global
    $sql_count = "SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :id_user AND leida = 0 AND tipo <> 'aviso_global'";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':id_user' => $currentUserId]);
    $response['unread_count'] = $stmt_count->fetchColumn();

    // --- 2. Obtener NUEVAS notificaciones (Polling) ---
    
    // a) Consultar notificaciones personales más nuevas que last_id
    $sql_personal = "
        SELECT id_notificacion, mensaje, tipo, url, leida, fecha_creacion
        FROM notificaciones 
        WHERE id_usuario_destino = :id_user 
        AND id_notificacion > :last_id 
        ORDER BY id_notificacion DESC
        LIMIT 10
    ";
    $stmt_personal = $pdo->prepare($sql_personal);
    $stmt_personal->execute([
        ':id_user' => $currentUserId,
        ':last_id' => $lastNotificationId
    ]);
    $new_personal_notifications = $stmt_personal->fetchAll(PDO::FETCH_ASSOC);

    // b) Consultar la ÚLTIMA notificación global (id_usuario_destino = 0)
    // Usamos id_usuario_destino = 0 como marcador para global
    $sql_global = "
        SELECT id_notificacion, mensaje, tipo, url, leida, fecha_creacion
        FROM notificaciones 
        WHERE tipo = 'aviso_global' 
        AND id_notificacion > :last_id
        ORDER BY id_notificacion DESC
        LIMIT 1
    ";
    $stmt_global = $pdo->prepare($sql_global);
    $stmt_global->execute([':last_id' => $lastNotificationId]);
    $new_global_notification = $stmt_global->fetch(PDO::FETCH_ASSOC);
    
    // c) Combinar resultados
    $new_notifications = $new_personal_notifications;
    if ($new_global_notification) {
        // Agregamos el aviso global al inicio para que se vea primero
        array_unshift($new_notifications, $new_global_notification);
    }

    $response['new_notifications'] = $new_notifications;

    // Actualizar max_id
    if (!empty($new_notifications)) {
        $max_id_local = 0;
        foreach ($new_notifications as $notif) {
            if ($notif['id_notificacion'] > $max_id_local) {
                $max_id_local = $notif['id_notificacion'];
            }
        }
        if ($max_id_local > $response['max_id']) {
            $response['max_id'] = $max_id_local;
        }
    }
    

    // --- 3. Obtener LISTA COMPLETA (Solo si last_id es 0, usado por loadFullList) ---
    if ($lastNotificationId === 0) {
        // Consultar notificaciones personales MÁS notificaciones globales (tipo=aviso_global)
        $sql_full = "
            SELECT id_notificacion, mensaje, tipo, url, leida, fecha_creacion
            FROM notificaciones 
            WHERE id_usuario_destino = :id_user 
            OR tipo = 'aviso_global'
            ORDER BY id_notificacion DESC
            LIMIT 50
        ";
        $stmt_full = $pdo->prepare($sql_full);
        $stmt_full->execute([':id_user' => $currentUserId]);
        $response['notifications'] = $stmt_full->fetchAll(PDO::FETCH_ASSOC);

        // Si hay resultados, asegurar que el max_id refleje el más alto de la lista completa
        if (!empty($response['notifications'])) {
             $response['max_id'] = max(array_column($response['notifications'], 'id_notificacion'));
        }
    }

} catch (PDOException $e) {
    // Manejo de error silencioso o logueo
    // error_log("Error en notificaciones_fetch: " . $e->getMessage());
}

echo json_encode($response);
?>
