<?php
// Archivo: tarea_recall_request.php (NUEVO - Gestiona la SOLICITUD de corrección del técnico)
// *** MODIFICADO (v2) POR GEMINI PARA PERMITIR SOLICITUD A ROLES 'auxiliar' y 'encargado' ***
session_start();
include 'conexion.php';

// 1. Verificar sesión, rol y método POST
// --- INICIO MODIFICACIÓN GEMINI (v2) ---
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['empleado', 'auxiliar', 'encargado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
// --- FIN MODIFICACIÓN GEMINI (v2) ---
    // Redirigir silenciosamente o mostrar error genérico si falla la validación básica
    header("Location: dashboard.php");
    exit();
}

$id_usuario_tecnico = $_SESSION['usuario_id'];
$id_tarea = (int)($_POST['id_tarea'] ?? 0);
$motivo_recall_tecnico = trim($_POST['motivo_recall_tecnico'] ?? ''); // Nombre del campo en el modal

// URL de redirección base
$redirect_url = "tarea_ver.php?id=" . $id_tarea;

// 2. Validar datos
if ($id_tarea <= 0) {
    // Usaremos modal de error en tarea_ver.php en lugar de GET param
    $_SESSION['action_error_message'] = "ID de tarea inválido.";
    header("Location: tareas_lista.php"); // Mejor ir a la lista si el ID es malo
    exit();
}
if (empty($motivo_recall_tecnico)) {
    $_SESSION['action_error_message'] = "Debe indicar un motivo para solicitar la corrección.";
    header("Location: {$redirect_url}");
    exit();
}

$pdo->beginTransaction();
try {
    // 3. Verificar estado actual ('finalizada_tecnico') y asignación
    $sql_check = "SELECT estado, id_creador, titulo FROM tareas WHERE id_tarea = :id_tarea AND id_asignado = :id_tecnico";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':id_tarea' => $id_tarea, ':id_tecnico' => $id_usuario_tecnico]);
    $tarea_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_info) {
        throw new Exception("Tarea no encontrada o no asignada a este usuario.");
    }
    // Solo permitir si está exactamente en este estado
    if ($tarea_info['estado'] !== 'finalizada_tecnico') {
        throw new Exception("Solo se puede solicitar corrección para tareas pendientes de revisión.");
    }

    // 4. Registrar la solicitud en el historial (NO CAMBIAR ESTADO DE LA TAREA)
    $contenido_actualizacion = "[SOLICITUD TÉCNICO] Corrección solicitada (post-entrega). Motivo: " . $motivo_recall_tecnico;
    $sql_insert_update = "INSERT INTO actualizaciones_tarea (id_tarea, id_usuario, contenido, fecha_actualizacion)
                          VALUES (:id_tarea, :id_usuario, :contenido, NOW())";
    $stmt_insert_update = $pdo->prepare($sql_insert_update);
    $stmt_insert_update->execute([
        ':id_tarea' => $id_tarea,
        ':id_usuario' => $id_usuario_tecnico, // El técnico hace la solicitud
        ':contenido' => $contenido_actualizacion
    ]);
    $id_actualizacion = $pdo->lastInsertId(); // ID para resaltar

    // 5. Notificar al administrador (creador de la tarea)
    $id_administrador = $tarea_info['id_creador'];
    if ($id_administrador && $id_administrador != $id_usuario_tecnico) {
        $nombre_tecnico = $_SESSION['usuario_nombre'] ?? 'El técnico';
        $titulo_tarea = $tarea_info['titulo'];
        $mensaje_notif = "{$nombre_tecnico} solicita corregir la tarea #{$id_tarea}: {$titulo_tarea} (ya enviada).";

        // Calcular URL Absoluta con highlight
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST']; $ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $url_base_absoluta = $protocolo . '://' . $host . $ruta_base;
        // Apuntar a la pestaña de actualizaciones y resaltar la solicitud
        $url_notif = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}&highlight_update={$id_actualizacion}#actualizaciones";

        $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion)
                      VALUES (:id_destino, :mensaje, :url, 'tarea_recall_request', 0, NOW())"; // Nuevo tipo
        $stmt_notif = $pdo->prepare($sql_notif);
        $stmt_notif->execute([
            ':id_destino' => $id_administrador,
            ':mensaje' => $mensaje_notif,
            ':url' => $url_notif
        ]);
    }

    $pdo->commit();
    // Usar variable de sesión para mensaje de éxito en modal
    $_SESSION['action_success_message'] = "Solicitud de corrección enviada al administrador. La tarea sigue pendiente de revisión.";
    header("Location: {$redirect_url}"); // Redirigir de vuelta
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al solicitar corrección Tarea #" . $id_tarea . ": " . $e->getMessage());
    // Usar variable de sesión para mensaje de error en modal
    $_SESSION['action_error_message'] = "Error al procesar la solicitud: " . $e->getMessage();
    header("Location: {$redirect_url}"); // Redirigir de vuelta
    exit();
}
?>