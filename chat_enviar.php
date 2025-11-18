<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
// Usamos $_SESSION['usuario_nombre'] directamente, ya está disponible.
$nombre_emisor = $_SESSION['usuario_nombre'];

$id_conversacion = $_POST['id_conversacion'] ?? 0;
$contenido = trim($_POST['contenido'] ?? '');

// 1. Validaciones
if (!is_numeric($id_conversacion) || $id_conversacion <= 0 || empty($contenido)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos de mensaje o conversación incompletos/inválidos.']);
    exit();
}

try {
    // 2. Verificar que el usuario participa en la conversación antes de insertar
    $sql_check = "SELECT id_conversacion FROM participantes_chat 
                  WHERE id_conversacion = :id_conv AND id_usuario = :id_usuario";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id_conv' => $id_conversacion, ':id_usuario' => $id_usuario]);

    if ($stmt_check->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado para enviar mensajes en esta conversación.']);
        exit();
    }
    
    // --- NUEVO: Obtener info de la conversación y participantes para la notificación ---
    $sql_conv = "SELECT c.nombre_grupo, pc.id_usuario 
                 FROM conversaciones c
                 JOIN participantes_chat pc ON c.id_conversacion = pc.id_conversacion
                 WHERE c.id_conversacion = :id_conv";
    $stmt_conv = $pdo->prepare($sql_conv);
    $stmt_conv->execute([':id_conv' => $id_conversacion]);
    $participantes = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

    $conversacion_info = ['nombre_grupo' => ''];
    $participantes_destino = [];
    
    if (!empty($participantes)) {
        $conversacion_info = $participantes[0]; // Solo necesitamos la info de la conversación una vez
        foreach ($participantes as $p) {
            // Excluir al emisor actual de la lista de destino de la notificación
            if ($p['id_usuario'] != $id_usuario) {
                $participantes_destino[] = $p['id_usuario'];
            }
        }
    }
    // -----------------------------------------------------------------------------------


    // 3. Insertar el mensaje
    $sql_insert = "INSERT INTO mensajes (id_conversacion, id_emisor, contenido) 
                   VALUES (:id_conv, :id_emisor, :contenido)";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    if ($stmt_insert->execute([
        ':id_conv' => $id_conversacion,
        ':id_emisor' => $id_usuario,
        ':contenido' => $contenido
    ])) {
        $id_mensaje = $pdo->lastInsertId();

        // =======================================================
        // INICIO: Lógica de Notificación
        // =======================================================
        if (!empty($participantes_destino)) {
            // Determinar el mensaje y la URL
            $url_notif = "chat.php?id_conversacion=" . $id_conversacion;
            
            if (!empty($conversacion_info['nombre_grupo'])) {
                // Chat Grupal
                $nombre_conv = htmlspecialchars($conversacion_info['nombre_grupo']);
                $mensaje_notif = "Nuevo mensaje de {$nombre_emisor} en el grupo '{$nombre_conv}'.";
            } else {
                // Chat Individual (con el nombre del otro participante)
                $mensaje_notif = "{$nombre_emisor} te ha enviado un nuevo mensaje.";
            }

            // Inserción masiva de la notificación con el `tipo` (tipo='chat')
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo) 
                          VALUES (:id_destino, :mensaje, :url, 'chat')";
            $stmt_notif = $pdo->prepare($sql_notif);

            foreach ($participantes_destino as $id_destino) {
                $stmt_notif->execute([
                    ':id_destino' => $id_destino,
                    ':mensaje' => $mensaje_notif,
                    ':url' => $url_notif
                ]);
            }
        }
        // =======================================================
        // FIN: Lógica de Notificación
        // =======================================================

        // 4. Devolver los datos del mensaje (para que se muestre en el chat inmediatamente)
        $message_data = [
            'id_mensaje' => $id_mensaje,
            'id_emisor' => $id_usuario,
            'nombre_emisor' => $nombre_emisor,
            'contenido' => $contenido,
            'fecha_envio' => date('Y-m-d H:i:s') 
        ];

        echo json_encode(['success' => true, 'message_data' => $message_data]);
        exit();

    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar el mensaje en la base de datos.']);
        exit();
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error de BD al enviar mensaje: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
    exit();
}
?>