<?php
// Archivo: ajax_confirmar_cumple.php
session_start();
require_once 'conexion.php'; 

// Forzar zona horaria Argentina en este script
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No logueado']);
    exit;
}

// Leer datos
$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? $_POST['accion'] ?? '';
$id_usuario = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario'; 

// --- FUNCIÓN DE NOTIFICACIÓN ---
function notificarAdmins($pdo, $mensaje, $id_usuario_origen) {
    try {
        // 1. Buscar IDs de administradores (Busca 'admin', 'Admin', 'ADMIN')
        $stmt_admins = $pdo->query("SELECT id_usuario FROM usuarios WHERE LOWER(rol) = 'admin'");
        $admins = $stmt_admins->fetchAll(PDO::FETCH_COLUMN);

        // 2. Generar fecha local (Argentina)
        $fecha_arg = date("Y-m-d H:i:s");
        
        // 3. Crear enlace útil (lleva a la gestión de usuarios)
        // Agregamos un parametro aleatorio para evitar caché si es necesario
        $url_destino = "admin_usuarios.php?id_buscar=" . $id_usuario_origen;

        $sql_insert = "INSERT INTO notificaciones (id_usuario_destino, mensaje, tipo, leida, fecha_creacion, url) 
                       VALUES (:id_dest, :mensaje, 'info_sistema', 0, :fecha, :url)";
        
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($admins as $id_admin) {
            $stmt_insert->execute([
                ':id_dest' => $id_admin,
                ':mensaje' => $mensaje,
                ':fecha'   => $fecha_arg,
                ':url'     => $url_destino
            ]);
        }
    } catch (Exception $e) {
        // Fallo silencioso en notificaciones para no romper el flujo principal
    }
}

// --- ACCIONES ---

if ($accion === 'confirmar') {
    $anio_a_guardar = $input['anio'] ?? date('Y');

    try {
        $sql = "UPDATE usuarios SET ultimo_saludo_cumple = :anio WHERE id_usuario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':anio' => $anio_a_guardar, ':id' => $id_usuario]);
        
        // Notificar al Admin
        $msg = "🎉 $usuario_nombre agradeció el saludo de cumpleaños.";
        notificarAdmins($pdo, $msg, $id_usuario);

        echo json_encode(['status' => 'success', 'message' => 'Confirmado']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

} elseif ($accion === 'posponer') {
    $_SESSION['cumple_pospuesto'] = true;
    echo json_encode(['status' => 'success', 'message' => 'Pospuesto']);

} elseif ($accion === 'notificar_rosa') {
    $decision = $input['decision'] ?? 'desconocida';
    
    $mensaje_notif = "";
    if ($decision === 'aceptar') {
        $mensaje_notif = "🌹 $usuario_nombre ACEPTÓ la rosa de regalo.";
    } else {
        $mensaje_notif = "🥀 $usuario_nombre NO aceptó la rosa de regalo.";
    }

    notificarAdmins($pdo, $mensaje_notif, $id_usuario);
    
    echo json_encode(['status' => 'success', 'message' => 'Notificación enviada']);

} else {
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>