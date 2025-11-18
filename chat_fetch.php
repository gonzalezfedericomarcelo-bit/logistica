<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$id_conversacion = $_GET['id_conversacion'] ?? 0;
$last_message_id = $_GET['last_id'] ?? 0; // Para el polling (solo mensajes nuevos)

if (!is_numeric($id_conversacion) || $id_conversacion <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de conversación no válido.']);
    exit();
}

try {
    // 1. Verificar si el usuario participa en la conversación
    $sql_check = "SELECT id_conversacion FROM participantes_chat 
                  WHERE id_conversacion = :id_conv AND id_usuario = :id_usuario";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id_conv' => $id_conversacion, ':id_usuario' => $id_usuario]);

    if ($stmt_check->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado para ver esta conversación.']);
        exit();
    }

    // 2. Consulta para obtener los mensajes
    $sql = "SELECT m.id_mensaje, m.id_emisor, m.contenido, m.fecha_envio, u.nombre_completo AS nombre_emisor
            FROM mensajes m
            JOIN usuarios u ON m.id_emisor = u.id_usuario
            WHERE m.id_conversacion = :id_conv";
            
    $params = [':id_conv' => $id_conversacion];
    
    // Bandera para determinar el modo de carga
    $is_initial_load = ($last_message_id == 0); 
    
    // 3. Ajustar la consulta según si es carga inicial o polling
    if (!$is_initial_load) {
        // Modo Polling (mensajes nuevos)
        $sql .= " AND m.id_mensaje > :last_id";
        $params[':last_id'] = $last_message_id;
        $sql .= " ORDER BY m.fecha_envio ASC"; 
        
    } else {
        // Modo Carga Inicial (últimos 50 mensajes)
        // Se ordena DESC para obtener los más nuevos, y se limita a 50
        $sql .= " ORDER BY m.fecha_envio DESC LIMIT 50";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CORRECCIÓN CLAVE: Si fue la carga inicial, revertimos el array.
    // Esto es necesario porque pedimos los mensajes en orden DESC (para obtener los últimos 50), 
    // pero deben mostrarse en orden ASC (antiguo -> nuevo) en el chat.
    if ($is_initial_load) {
        $mensajes = array_reverse($mensajes);
    }

    // 4. Formatear la fecha para la visualización
    foreach ($mensajes as &$mensaje) {
        // El formato de fecha se deja como 'Y-m-d H:i:s' para que JavaScript lo procese correctamente.
        // No es necesario modificarlo aquí.
    }
    unset($mensaje); // Eliminar la referencia

    // 5. Devolver los mensajes
    echo json_encode(['mensajes' => $mensajes]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos al cargar mensajes: ' . $e->getMessage()]);
    exit();
}
?>