<?php
// Archivo: tarea_cancelar_procesar.php (COMPLETO)
// *** MODIFICADO (v2) POR GEMINI PARA PERMITIR CANCELAR AL 'encargado' ***
session_start();
include 'conexion.php';

// --- INICIO DE LA MODIFICACIÓN DE GEMINI (v2) ---
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'], ['admin', 'encargado'])) {
// --- FIN DE LA MODIFICACIÓN DE GEMINI (v2) ---
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$id_tarea = (int)($_POST['id_tarea'] ?? 0);
$motivo_cancelacion = trim($_POST['motivo_cancelacion'] ?? '');
$id_usuario_admin = $_SESSION['usuario_id']; // Es el admin O el encargado

if ($id_tarea <= 0) {
    header("Location: tareas_lista.php?error=" . urlencode("ID de tarea inválido."));
    exit();
}

$pdo->beginTransaction();

try {
    // 1. Obtener datos de la tarea antes de cancelar
    $sql_tarea_data = "SELECT titulo, id_asignado, id_creador, estado FROM tareas WHERE id_tarea = :id_tarea";
    $stmt_tarea_data = $pdo->prepare($sql_tarea_data);
    $stmt_tarea_data->execute([':id_tarea' => $id_tarea]);
    $tarea_data = $stmt_tarea_data->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_data) {
        throw new Exception("Tarea no encontrada.");
    }

    if (in_array($tarea_data['estado'], ['verificada', 'cancelada'])) {
        throw new Exception("La tarea ya está " . $tarea_data['estado'] . " y no puede ser cancelada.");
    }

    // 2. Actualizar el estado de la tarea a 'cancelada'
    $sql_update_tarea = "UPDATE tareas SET estado = 'cancelada', fecha_cierre = NOW() WHERE id_tarea = :id_tarea";
    $stmt_update_tarea = $pdo->prepare($sql_update_tarea);
    $stmt_update_tarea->execute([':id_tarea' => $id_tarea]);

    // 3. Registrar una actualización en el historial
    $contenido_actualizacion = "TAREA CANCELADA POR GESTOR. "; // Cambiado de Administrador a Gestor
    if (!empty($motivo_cancelacion)) {
        $contenido_actualizacion .= "Motivo: " . $motivo_cancelacion;
    } else {
        $contenido_actualizacion .= "Sin motivo especificado.";
    }

    $sql_insert_update = "INSERT INTO actualizaciones_tarea (id_tarea, id_usuario, contenido, fecha_actualizacion)
                          VALUES (:id_tarea, :id_usuario, :contenido, NOW())";
    $stmt_insert_update = $pdo->prepare($sql_insert_update);
    $stmt_insert_update->execute([
        ':id_tarea' => $id_tarea,
        ':id_usuario' => $id_usuario_admin, // El admin/encargado es quien cancela
        ':contenido' => $contenido_actualizacion
    ]);

    // 4. Enviar notificación al usuario asignado (si no es el mismo admin/encargado)
    if (!empty($tarea_data['id_asignado']) && $tarea_data['id_asignado'] != $id_usuario_admin) {
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];
        $ruta_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $url_base_absoluta = $protocolo . '://' . $host . $ruta_base;

        $mensaje_notificacion = "La tarea #{$id_tarea}: \"{$tarea_data['titulo']}\" ha sido CANCELADA por el gestor.";
        if (!empty($motivo_cancelacion)) {
            $mensaje_notificacion .= " Motivo: {$motivo_cancelacion}";
        }
        $url_notificacion = $url_base_absoluta . "/tarea_ver.php?id={$id_tarea}&show_modal=cancelada_admin"; // Redirige y muestra modal

        $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion)
                      VALUES (:id_destino, :mensaje, :url, :tipo, 0, NOW())";
        $stmt_notif = $pdo->prepare($sql_notif);
        $stmt_notif->execute([
            ':id_destino' => $tarea_data['id_asignado'],
            ':mensaje' => $mensaje_notificacion,
            ':url' => $url_notificacion,
            ':tipo' => 'tarea_cancelada'
        ]);
    }

    $pdo->commit();
    
    // --- INICIO MODIFICACIÓN GEMINI (v3) - Preparando para el modal ---
    // En lugar de redirigir con un simple "?success=", guardamos en sesión
    // para que 'tarea_ver.php' pueda mostrar el modal.
    $_SESSION['action_success_message'] = "Tarea #{$id_tarea} ha sido cancelada correctamente.";
    $_SESSION['action_success_type'] = 'cancelada'; // Un tipo para que tarea_ver sepa qué modal mostrar
    header("Location: tarea_ver.php?id={$id_tarea}");
    // --- FIN MODIFICACIÓN GEMINI (v3) ---
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al cancelar tarea #{$id_tarea}: " . $e->getMessage());
    
    // --- INICIO MODIFICACIÓN GEMINI (v3) - Preparando para el modal de error ---
    $_SESSION['action_error_message'] = "Error al cancelar la tarea: " . $e->getMessage();
    header("Location: tarea_ver.php?id={$id_tarea}");
    // --- FIN MODIFICACIÓN GEMINI (v3) ---
    exit();
}
?>